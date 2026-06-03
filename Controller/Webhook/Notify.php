<?php
namespace Coinify\Payment\Controller\Webhook;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\DB\Transaction;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Service\InvoiceService;
use Psr\Log\LoggerInterface;
use Coinify\Payment\Model\Config as CoinifyConfig;
use Coinify\Payment\Model\ResourceModel\PaymentIntent\CollectionFactory as IntentCollectionFactory;
use Magento\Sales\Model\OrderFactory;
use Coinify\Payment\Model\WebhookLogFactory;
use Coinify\Payment\Model\ResourceModel\Refund\CollectionFactory as RefundCollectionFactory;
use Coinify\Payment\Model\Service\CreditMemoProcessor;

class Notify extends Action implements CsrfAwareActionInterface
{
    private LoggerInterface $logger;
    private CoinifyConfig $config;
    private IntentCollectionFactory $intentCollectionFactory;
    private OrderFactory $orderFactory;
    private WebhookLogFactory $webhookLogFactory;
    private InvoiceService $invoiceService;
    private Transaction $transaction;
    private InvoiceSender $invoiceSender;
    private RefundCollectionFactory $refundCollectionFactory;
    private CreditMemoProcessor $creditMemoProcessor;

    public function __construct(
        Context $context,
        LoggerInterface $logger,
        CoinifyConfig $config,
        IntentCollectionFactory $intentCollectionFactory,
        OrderFactory $orderFactory,
        WebhookLogFactory $webhookLogFactory,
        InvoiceService $invoiceService,
        Transaction $transaction,
        InvoiceSender $invoiceSender,
        RefundCollectionFactory $refundCollectionFactory,
        CreditMemoProcessor $creditMemoProcessor
    ) {
        parent::__construct($context);
        $this->logger = $logger;
        $this->config = $config;
        $this->intentCollectionFactory = $intentCollectionFactory;
        $this->orderFactory = $orderFactory;
        $this->webhookLogFactory = $webhookLogFactory;
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;
        $this->invoiceSender = $invoiceSender;
        $this->refundCollectionFactory = $refundCollectionFactory;
        $this->creditMemoProcessor = $creditMemoProcessor;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);

        $body = $this->getRequest()->getContent();
        if (empty($body)) {
            $body = file_get_contents('php://input');
        }

        $sigHeader = $this->getRequest()->getHeader('X-Coinify-Webhook-Signature');
        $secret = $this->config->getWebhookSecret();

        $computed = $secret ? hash_hmac('sha256', $body, $secret) : null;
        $this->logger->debug('Coinify webhook received', [
            'body_length' => strlen($body),
            'computed_hmac' => $computed,
            'received_sig' => $sigHeader,
            'match' => $computed && $sigHeader && hash_equals($computed, strtolower($sigHeader)) ? 'YES' : 'NO',
        ]);

        if ($secret) {
            if (!$sigHeader || !hash_equals($computed, strtolower($sigHeader))) {
                $this->logger->warning('Coinify webhook rejected: invalid or missing signature', [
                    'computed' => $computed,
                    'header' => $sigHeader,
                ]);
                $result->setHttpResponseCode(400);
                $result->setContents('invalid signature');
                return $result;
            }
        }

        $payload = json_decode($body, true);
        if (!$payload || empty($payload['event'])) {
            $this->logger->warning('Coinify webhook invalid payload', ['body' => $body]);
            $result->setHttpResponseCode(400);
            $result->setContents('invalid payload');
            return $result;
        }

        $event = $payload['event'];
        $context = $payload['context'] ?? [];

        try {
            try {
                $log = $this->webhookLogFactory->create();
                $log->setData([
                    'event' => $payload['event'] ?? null,
                    'payload' => $body,
                    'headers' => json_encode($this->getRequest()->getHeaders()->toArray()),
                    'payment_intent_id' => $context['id'] ?? null,
                    'order_id' => $context['orderId'] ?? null,
                ]);
                $log->save();
            } catch (\Exception $e) {
                $this->logger->warning('Coinify: failed saving webhook log: ' . $e->getMessage());
            }

            $collection = $this->intentCollectionFactory->create();
            if (!empty($context['id'])) {
                $collection->addFieldToFilter('payment_intent_id', $context['id']);
            } elseif (!empty($context['orderId'])) {
                $collection->addFieldToFilter('order_id', $context['orderId']);
            }

            $intent = $collection->getFirstItem();
            if ($intent && $intent->getId()) {
                $intent->setData('state', $context['state'] ?? null);
                $intent->setData('state_reason', $context['stateReason'] ?? null);
                $intent->setData('response_raw', json_encode($payload));
                $intent->save();
            }

            if ($event === 'payment-intent.refund.completed') {
                $refundId = $context['refund']['id'] ?? null;
                if ($refundId) {
                    $refundCollection = $this->refundCollectionFactory->create();
                    $refundCollection->addFieldToFilter('refund_id', $refundId);
                    $refundRecord = $refundCollection->getFirstItem();
                    if ($refundRecord && $refundRecord->getId()) {
                        $refundRecord->setData('state', 'completed');
                        $refundRecord->save();
                    }
                }
                $orderIdForComment = ($intent && $intent->getId()) ? $intent->getData('order_id') : null;
                if ($orderIdForComment) {
                    $order = $this->orderFactory->create()->loadByIncrementId($orderIdForComment);
                    if ($order && $order->getId()) {
                        $order->addStatusHistoryComment('Coinify refund completed.' . ($refundId ? ' Refund ID: ' . $refundId : ''));
                        $order->save();

                        if ($this->config->isAutoCreateCreditMemo() && $refundRecord && $refundRecord->getId()) {
                            $freshOrder = $this->orderFactory->create()->loadByIncrementId($orderIdForComment);
                            $this->creditMemoProcessor->createForRefund($freshOrder, (float)$refundRecord->getData('amount'));
                        }
                    }
                }
            }

            $orderId = $context['orderId'] ?? null;
            if ($orderId) {
                $order = $this->orderFactory->create()->loadByIncrementId($orderId);
                if ($order && $order->getId()) {
                    if ($event === 'payment-intent.completed') {
                        $this->handleCompleted($order, $context);
                    } elseif ($event === 'payment-intent.failed') {
                        $order->addStatusHistoryComment(
                            'Coinify payment failed: ' . ($context['stateReason'] ?? '')
                        );
                        $order->setState(\Magento\Sales\Model\Order::STATE_CANCELED)
                            ->setStatus(\Magento\Sales\Model\Order::STATE_CANCELED);
                        $order->save();
                    }
                }
            }

            $result->setContents('ok');
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Coinify webhook processing error: ' . $e->getMessage());
            $result->setHttpResponseCode(500);
            $result->setContents('error');
            return $result;
        }
    }

    private function handleCompleted(\Magento\Sales\Model\Order $order, array $context): void
    {
        $stateReason = $context['stateReason'] ?? '';
        $payments = $context['payments'] ?? [];
        $firstPayment = $payments[0] ?? [];

        $cryptoAmount = $firstPayment['amount'] ?? null;
        $cryptoCurrency = $firstPayment['currency'] ?? null;
        $txId = $firstPayment['transactionId'] ?? null;

        $commentParts = ['Coinify payment completed (' . $stateReason . ').'];
        if ($cryptoCurrency && $cryptoAmount) {
            $commentParts[] = 'Paid: ' . $cryptoAmount . ' ' . $cryptoCurrency . '.';
        }
        if ($txId) {
            $commentParts[] = 'Transaction ID: ' . $txId . '.';
        }
        $comment = implode(' ', $commentParts);

        $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING)
            ->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
        $order->addStatusHistoryComment($comment);

        if ($cryptoAmount && $cryptoCurrency) {
            $customerNote = 'Paid with: ' . $cryptoAmount . ' ' . $cryptoCurrency;
            if ($txId) {
                $customerNote .= ' | Transaction ID: ' . $txId;
            }
            $order->setCustomerNote($customerNote);
            $order->setCustomerNoteNotify(true);
        }

        if ($order->canInvoice()) {
            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
            $invoice->register();
            $invoice->setState(\Magento\Sales\Model\Order\Invoice::STATE_PAID);
            if ($txId) {
                $invoice->setTransactionId($txId);
            }
            if ($cryptoAmount && $cryptoCurrency) {
                $invoiceNote = 'Cryptocurrency payment: ' . $cryptoAmount . ' ' . $cryptoCurrency;
                if ($txId) {
                    $invoiceNote .= ' | Transaction ID: ' . $txId;
                }
                $invoice->addComment($invoiceNote, false, true);
            }

            $transactionSave = $this->transaction
                ->addObject($invoice)
                ->addObject($invoice->getOrder());
            $transactionSave->save();

            try {
                $this->invoiceSender->send($invoice);
            } catch (\Exception $e) {
                $this->logger->warning('Coinify: failed sending invoice email: ' . $e->getMessage());
            }
        } else {
            $order->save();
        }
    }
}

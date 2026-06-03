<?php
namespace Coinify\Payment\Model\Service;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\CreditmemoService;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\DB\TransactionFactory;
use Psr\Log\LoggerInterface;

class CreditMemoProcessor
{
    private CreditmemoFactory $creditmemoFactory;
    private CreditmemoService $creditmemoService;
    private InvoiceService $invoiceService;
    private TransactionFactory $transactionFactory;
    private OrderFactory $orderFactory;
    private LoggerInterface $logger;

    public function __construct(
        CreditmemoFactory $creditmemoFactory,
        CreditmemoService $creditmemoService,
        InvoiceService $invoiceService,
        TransactionFactory $transactionFactory,
        OrderFactory $orderFactory,
        LoggerInterface $logger
    ) {
        $this->creditmemoFactory = $creditmemoFactory;
        $this->creditmemoService = $creditmemoService;
        $this->invoiceService = $invoiceService;
        $this->transactionFactory = $transactionFactory;
        $this->orderFactory = $orderFactory;
        $this->logger = $logger;
    }

    public function createForRefund(Order $order, float $refundAmount): void
    {
        // Recovery path: if the order was paid via Coinify but invoice creation was
        // missed (e.g. old code or transient failure), create the invoice now so that
        // total_paid is set and canCreditmemo() can pass.
        if (!$order->getBaseTotalInvoiced() && $order->canInvoice()) {
            $this->logger->warning('Coinify: no invoice found for paid order ' . $order->getIncrementId() . ' — creating now before credit memo');
            $this->createMissingInvoice($order);
            $order = $this->orderFactory->create()->loadByIncrementId($order->getIncrementId());
        }

        if (!$order->canCreditmemo()) {
            $this->logger->warning('Coinify: cannot create credit memo for order ' . $order->getIncrementId() . ' — canCreditmemo() is false (total_paid=' . $order->getTotalPaid() . ')');
            return;
        }

        $invoice = $order->getInvoiceCollection()->getFirstItem();
        if (!$invoice || !$invoice->getId()) {
            $this->logger->warning('Coinify: no invoice found for order ' . $order->getIncrementId() . ', cannot create credit memo');
            return;
        }

        try {
            $grandTotal = (float)$order->getGrandTotal();
            $alreadyRefunded = (float)($order->getTotalRefunded() ?? 0);
            $isFullRefund = abs(($alreadyRefunded + $refundAmount) - $grandTotal) < 0.01;

            if ($isFullRefund) {
                $creditmemo = $this->creditmemoFactory->createByInvoice($invoice, [
                    'shipping_amount' => $invoice->getShippingAmount(),
                ]);
            } else {
                $qtys = [];
                foreach ($invoice->getAllItems() as $item) {
                    $qtys[$item->getOrderItemId()] = 0;
                }
                $creditmemo = $this->creditmemoFactory->createByInvoice($invoice, [
                    'qtys' => $qtys,
                    'shipping_amount' => 0,
                    'adjustment_positive' => $refundAmount,
                ]);
            }

            $creditmemo->setOfflineRequested(true);
            $this->creditmemoService->refund($creditmemo, true);

            $this->logger->info(sprintf(
                'Coinify: credit memo created for order %s — amount: %s, type: %s',
                $order->getIncrementId(),
                $refundAmount,
                $isFullRefund ? 'full' : 'partial'
            ));
        } catch (\Exception $e) {
            $this->logger->error('Coinify: failed creating credit memo for order ' . $order->getIncrementId() . ': ' . $e->getMessage());
        }
    }

    private function createMissingInvoice(Order $order): void
    {
        try {
            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);
            $invoice->register();
            $invoice->setState(Invoice::STATE_PAID);
            $transaction = $this->transactionFactory->create();
            $transaction->addObject($invoice)->addObject($invoice->getOrder());
            $transaction->save();
            $this->logger->info('Coinify: created missing invoice for order ' . $order->getIncrementId());
        } catch (\Exception $e) {
            $this->logger->error('Coinify: failed creating missing invoice for order ' . $order->getIncrementId() . ': ' . $e->getMessage());
        }
    }
}

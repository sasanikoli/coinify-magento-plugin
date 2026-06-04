<?php
namespace Coinify\Payment\Controller\Adminhtml\Order;

use Magento\Backend\App\Action\Context;
use Magento\Backend\App\Action;
use Magento\Sales\Model\OrderFactory;
use Psr\Log\LoggerInterface;
use Coinify\Payment\Model\ResourceModel\PaymentIntent\CollectionFactory as IntentCollectionFactory;
use Coinify\Payment\Model\Service\RefundProcessor;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;

/**
 * Handles the admin refund form submission from the order detail page.
 *
 * Security checks applied before any API call:
 *  1. Magento form key (CSRF token) validation.
 *  2. IDOR guard — verifies the submitted payment intent belongs to the
 *     submitted order ID, preventing one order's intent from being used
 *     to trigger a refund against a different order.
 *
 * On success, delegates to RefundProcessor which calls the Coinify API
 * and saves the refund record. The actual fund transfer is asynchronous:
 * Coinify emails the customer for their wallet address and fires a
 * payment-intent.refund.completed webhook when the transfer is done.
 */
class Refund extends Action
{
    const ADMIN_RESOURCE = 'Coinify_Payment::refunds';

    private OrderFactory $orderFactory;
    private IntentCollectionFactory $intentCollectionFactory;
    private RefundProcessor $refundProcessor;
    private LoggerInterface $logger;
    private FormKeyValidator $formKeyValidator;

    public function __construct(
        Context $context,
        OrderFactory $orderFactory,
        IntentCollectionFactory $intentCollectionFactory,
        RefundProcessor $refundProcessor,
        LoggerInterface $logger,
        FormKeyValidator $formKeyValidator
    ) {
        parent::__construct($context);
        $this->orderFactory = $orderFactory;
        $this->intentCollectionFactory = $intentCollectionFactory;
        $this->refundProcessor = $refundProcessor;
        $this->logger = $logger;
        $this->formKeyValidator = $formKeyValidator;
    }

    public function execute()
    {
        $orderId = $this->getRequest()->getParam('order_id');
        $intentId = $this->getRequest()->getParam('intent_id');
        $amount = (float)$this->getRequest()->getParam('amount');

        if (!$this->formKeyValidator->validate($this->getRequest())) {
            $this->messageManager->addErrorMessage(__('Invalid form key.'));
            return $this->_redirect($this->_redirect->getRefererUrl());
        }

        if (!$orderId || !$intentId || $amount <= 0) {
            $this->messageManager->addErrorMessage(__('Missing required refund parameters.'));
            return $this->_redirect($this->_redirect->getRefererUrl());
        }

        try {
            $order = $this->orderFactory->create()->loadByIncrementId($orderId);
            if (!$order || !$order->getId()) {
                $this->messageManager->addErrorMessage(__('Order not found.'));
                return $this->_redirect($this->_redirect->getRefererUrl());
            }

            $intentCollection = $this->intentCollectionFactory->create();
            $intentCollection->addFieldToFilter('payment_intent_id', $intentId);
            $intentCollection->setPageSize(1);
            $intent = $intentCollection->getFirstItem();

            if (!$intent || !$intent->getId()) {
                $this->messageManager->addErrorMessage(__('Payment intent not found.'));
                return $this->_redirect($this->_redirect->getRefererUrl());
            }

            // IDOR guard: ensure the intent record's order_id matches what was
            // submitted. Without this check, a tampered form could refund any
            // intent regardless of which order is being viewed.
            if ($intent->getData('order_id') !== $orderId) {
                $this->logger->warning('Coinify refund rejected: intent does not belong to order', [
                    'intent_id'        => $intentId,
                    'intent_order_id'  => $intent->getData('order_id'),
                    'submitted_order_id' => $orderId,
                ]);
                $this->messageManager->addErrorMessage(__('Payment intent does not belong to this order.'));
                return $this->_redirect($this->_redirect->getRefererUrl());
            }

            $response = $this->refundProcessor->createRefund($intentId, $orderId, $amount, $order->getOrderCurrencyCode());

            if (isset($response['errorCode'])) {
                $this->messageManager->addErrorMessage(
                    __('Coinify refund error: %1', $response['errorMessage'] ?? $response['errorCode'])
                );
            } elseif (!empty($response)) {
                $this->messageManager->addSuccessMessage(__('Refund initiated with Coinify. The customer will receive an email to provide their refund address.'));
            } else {
                $this->messageManager->addErrorMessage(__('Refund request failed. Please check the logs.'));
            }
        } catch (\Exception $e) {
            $this->logger->error('Coinify refund controller error: ' . $e->getMessage());
            $this->messageManager->addErrorMessage(__('Refund processing error: %1', $e->getMessage()));
        }

        return $this->_redirect($this->_redirect->getRefererUrl());
    }
}

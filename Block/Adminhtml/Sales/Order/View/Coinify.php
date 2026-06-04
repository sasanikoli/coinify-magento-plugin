<?php
namespace Coinify\Payment\Block\Adminhtml\Sales\Order\View;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Registry;
use Coinify\Payment\Model\ResourceModel\PaymentIntent\CollectionFactory as IntentCollectionFactory;
use Coinify\Payment\Model\ResourceModel\WebhookLog\CollectionFactory as WebhookCollectionFactory;
use Coinify\Payment\Model\ResourceModel\Refund\CollectionFactory as RefundCollectionFactory;

/**
 * Provides data for the "Coinify Payments" section injected into the admin order view.
 *
 * Renders three sub-sections via order/coinify.phtml:
 *  - Payment intents (with per-intent refund form)
 *  - Webhook log entries for the order
 *  - Refund records
 */
class Coinify extends Template
{
    private IntentCollectionFactory $intentCollectionFactory;
    private WebhookCollectionFactory $webhookCollectionFactory;
    private RefundCollectionFactory $refundCollectionFactory;
    private Registry $registry;

    protected $formKey;

    public function __construct(
        Context $context,
        IntentCollectionFactory $intentCollectionFactory,
        WebhookCollectionFactory $webhookCollectionFactory,
        RefundCollectionFactory $refundCollectionFactory,
        FormKey $formKey,
        Registry $registry,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->intentCollectionFactory = $intentCollectionFactory;
        $this->webhookCollectionFactory = $webhookCollectionFactory;
        $this->refundCollectionFactory = $refundCollectionFactory;
        $this->formKey = $formKey;
        $this->registry = $registry;
    }

    /** Returns the order currently being viewed, sourced from Magento's registry. */
    public function getOrder()
    {
        return $this->registry->registry('current_order');
    }

    /** Returns all Coinify payment intents associated with this order. */
    public function getIntents()
    {
        $order = $this->getOrder();
        if (!$order) {
            return [];
        }
        $collection = $this->intentCollectionFactory->create();
        $collection->addFieldToFilter('order_id', $order->getIncrementId());
        return $collection->getItems();
    }

    /** Returns webhook log entries for this order, newest first. */
    public function getWebhookLogs()
    {
        $order = $this->getOrder();
        if (!$order) {
            return [];
        }
        $collection = $this->webhookCollectionFactory->create();
        $collection->addFieldToFilter('order_id', $order->getIncrementId());
        $collection->setOrder('created_at', 'DESC');
        return $collection->getItems();
    }

    /** Returns refund records for this order, newest first. */
    public function getRefunds()
    {
        $order = $this->getOrder();
        if (!$order) {
            return [];
        }
        $collection = $this->refundCollectionFactory->create();
        $collection->addFieldToFilter('order_id', $order->getIncrementId());
        $collection->setOrder('created_at', 'DESC');
        return $collection->getItems();
    }

    public function getFormKey()
    {
        return $this->formKey->getFormKey();
    }
}

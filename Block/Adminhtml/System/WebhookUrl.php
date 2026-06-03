<?php
namespace Coinify\Payment\Block\Adminhtml\System;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Backend\Block\Template\Context;

class WebhookUrl extends Field
{
    private StoreManagerInterface $storeManager;

    public function __construct(Context $context, StoreManagerInterface $storeManager, array $data = [])
    {
        $this->storeManager = $storeManager;
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        $baseUrl = rtrim($this->storeManager->getStore()->getBaseUrl(), '/');
        $webhookUrl = $baseUrl . '/coinify/webhook/notify';

        return '<div style="padding: 6px 0;">'
            . '<input type="text" readonly value="' . $this->escapeHtmlAttr($webhookUrl) . '"'
            . ' style="width:100%;background:#f8f8f8;cursor:text;" onclick="this.select()" />'
            . '<p class="note"><span>' . __('Enter this URL in your Coinify dashboard under Webhook settings.') . '</span></p>'
            . '</div>';
    }
}

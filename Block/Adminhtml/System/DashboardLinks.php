<?php
namespace Coinify\Payment\Block\Adminhtml\System;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class DashboardLinks extends Field
{
    protected function _getElementHtml(AbstractElement $element)
    {
        $paymentConfigUrl = 'https://app.sandbox.coinify.com/payment/configuration/payments-configuration';
        $feesConfigUrl = 'https://app.sandbox.coinify.com/payment/configuration/fees-configuration';

        return '<div style="padding: 8px 0;">'
            . '<p>' . __('Configure payment acceptance and fee settings in the Coinify dashboard.') . '</p>'
            . '<p><a href="' . $paymentConfigUrl . '" target="_blank">' . __('Payment Configuration') . '</a></p>'
            . '<p><a href="' . $feesConfigUrl . '" target="_blank">' . __('Fees Configuration') . '</a></p>'
            . '</div>';
    }
}

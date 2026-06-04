<?php
namespace Coinify\Payment\Block\Adminhtml\System;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Coinify\Payment\Model\Config as CoinifyConfig;

class DashboardLinks extends Field
{
    /** @var CoinifyConfig */
    private $config;

    public function __construct(Context $context, CoinifyConfig $config, array $data = [])
    {
        $this->config = $config;
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        $isSandbox = $this->config->getEnvironment() !== 'production';
        $base = $isSandbox ? 'https://app.sandbox.coinify.com' : 'https://app.coinify.com';

        $paymentConfigUrl = $base . '/payment/configuration/payments-configuration';
        $feesConfigUrl    = $base . '/payment/configuration/fees-configuration';

        return '<div style="padding: 8px 0;">'
            . '<p>' . __('Configure payment acceptance and fee settings in the Coinify dashboard.') . '</p>'
            . '<p><a href="' . $this->escapeUrl($paymentConfigUrl) . '" target="_blank">' . __('Payment Configuration') . '</a></p>'
            . '<p><a href="' . $this->escapeUrl($feesConfigUrl) . '" target="_blank">' . __('Fees Configuration') . '</a></p>'
            . '</div>';
    }
}

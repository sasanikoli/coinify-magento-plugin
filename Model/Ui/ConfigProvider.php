<?php
namespace Coinify\Payment\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Coinify\Payment\Model\Config as CoinifyConfig;

/**
 * Injects Coinify configuration into the frontend checkout JS.
 *
 * The data returned here is merged into window.checkoutConfig.payment.coinify
 * and consumed by the payment renderer (coinify.js), which reads redirectUrl
 * to forward the customer after order placement.
 */
class ConfigProvider implements ConfigProviderInterface
{
    public const CODE = 'coinify';

    private CoinifyConfig $config;

    public function __construct(CoinifyConfig $config)
    {
        $this->config = $config;
    }

    public function getConfig()
    {
        $component = 'Coinify_Payment/js/view/payment/method-renderer/coinify';

        return [
            'payment' => [
                self::CODE => [
                    'isActive' => $this->config->isActive(),
                    'title' => $this->config->getTitle(),
                    'redirectUrl' => '/coinify/checkout/redirect',
                    'component' => $component
                ]
            ]
        ];
    }
}

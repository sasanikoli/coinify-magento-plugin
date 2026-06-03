<?php
namespace Coinify\Payment\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Coinify\Payment\Model\Config as CoinifyConfig;

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

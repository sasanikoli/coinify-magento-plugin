<?php
namespace Coinify\Payment\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const XML_PATH_PAYMENT = 'payment/coinify/';

    private ScopeConfigInterface $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function isActive($storeId = null): bool
    {
        return (bool)$this->getValue('active', $storeId);
    }

    public function getTitle($storeId = null): string
    {
        return (string)$this->getValue('title', $storeId);
    }

    public function getEnvironment($storeId = null): string
    {
        return (string)$this->getValue('environment', $storeId);
    }

    public function getApiKey($storeId = null): string
    {
        return (string)$this->getValue('api_key', $storeId);
    }

    public function getWebhookSecret($storeId = null): string
    {
        return (string)$this->getValue('webhook_secret', $storeId);
    }

    public function isAutoCreateCreditMemo($storeId = null): bool
    {
        return (bool)$this->getValue('auto_credit_memo', $storeId);
    }

    private function getValue(string $field, $storeId = null)
    {
        return $this->scopeConfig->getValue(self::XML_PATH_PAYMENT . $field, ScopeInterface::SCOPE_STORE, $storeId);
    }
}

<?php
namespace Coinify\Payment\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const XML_PATH_PAYMENT = 'payment/coinify/';

    private ScopeConfigInterface $scopeConfig;
    private EncryptorInterface $encryptor;

    public function __construct(ScopeConfigInterface $scopeConfig, EncryptorInterface $encryptor)
    {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
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
        return $this->decryptValue((string)$this->getValue('api_key', $storeId));
    }

    public function getWebhookSecret($storeId = null): string
    {
        return $this->decryptValue((string)$this->getValue('webhook_secret', $storeId));
    }

    private function decryptValue(string $value): string
    {
        if (!$value) {
            return '';
        }
        // Magento encrypted values follow the pattern "version:keyId:base64ciphertext".
        // Plain-text values stored before this backend_model was added are returned as-is
        // until the admin re-saves the configuration.
        if (preg_match('/^\d+:\d+:.+$/', $value)) {
            return (string)$this->encryptor->decrypt($value);
        }
        return $value;
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

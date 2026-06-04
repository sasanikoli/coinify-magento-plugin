<?php
namespace Coinify\Payment\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Central accessor for Coinify module configuration.
 *
 * All config values live under payment/coinify/ in core_config_data.
 * Sensitive fields (api_key, webhook_secret) are stored encrypted via
 * Magento's Encrypted backend model and decrypted on read here.
 */
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

    /** Returns 'sandbox' or 'production'. */
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

    /**
     * Decrypts a config value if it is in Magento's encrypted format ("version:keyId:ciphertext").
     * Plain-text values stored before encryption was introduced are returned as-is
     * until the admin re-saves the configuration field.
     */
    private function decryptValue(string $value): string
    {
        if (!$value) {
            return '';
        }
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

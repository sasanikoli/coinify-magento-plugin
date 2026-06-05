<?php
namespace Coinify\Payment\Test\Unit\Model;

use Coinify\Payment\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Model/Config.php
 *
 * Covers:
 *  - decryptValue() returns empty string for empty input
 *  - decryptValue() passes plaintext through unchanged
 *  - decryptValue() calls EncryptorInterface::decrypt() for Magento-encrypted values
 *  - getApiKey() and getWebhookSecret() delegate to decryptValue()
 *  - getEnvironment() returns the raw config value
 *  - isActive() casts the config value to bool
 */
class ConfigTest extends TestCase
{
    private ScopeConfigInterface $scopeConfig;
    private EncryptorInterface $encryptor;
    private Config $config;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->encryptor   = $this->createMock(EncryptorInterface::class);
        $this->config      = new Config($this->scopeConfig, $this->encryptor);
    }

    /** An empty stored value should come back as an empty string, never passed to decrypt(). */
    public function testGetApiKeyReturnsEmptyStringWhenNotSet(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('');
        $this->encryptor->expects($this->never())->method('decrypt');

        $this->assertSame('', $this->config->getApiKey());
    }

    /** A plaintext value stored before encryption was introduced should pass through unchanged. */
    public function testGetApiKeyReturnsPlaintextUnchanged(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('my-plain-api-key');
        $this->encryptor->expects($this->never())->method('decrypt');

        $this->assertSame('my-plain-api-key', $this->config->getApiKey());
    }

    /** A value in Magento's encrypted format ("version:keyId:ciphertext") must be decrypted. */
    public function testGetApiKeyDecryptsEncryptedValue(): void
    {
        $encrypted = '0:3:AbCdEfGhIjKlMnOpQrStUvWxYz==';
        $this->scopeConfig->method('getValue')->willReturn($encrypted);
        $this->encryptor->expects($this->once())
            ->method('decrypt')
            ->with($encrypted)
            ->willReturn('decrypted-api-key');

        $this->assertSame('decrypted-api-key', $this->config->getApiKey());
    }

    /** webhook_secret follows the same decryption path as api_key. */
    public function testGetWebhookSecretDecryptsEncryptedValue(): void
    {
        $encrypted = '1:2:SomeBase64Cipher==';
        $this->scopeConfig->method('getValue')->willReturn($encrypted);
        $this->encryptor->method('decrypt')->willReturn('my-secret');

        $this->assertSame('my-secret', $this->config->getWebhookSecret());
    }

    /** getEnvironment() returns the raw string from config (no decryption). */
    public function testGetEnvironmentReturnsConfigValue(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('sandbox');
        $this->assertSame('sandbox', $this->config->getEnvironment());
    }

    /** isActive() must cast the stored '1'/'0' string to a boolean. */
    public function testIsActiveReturnsTrueWhenEnabled(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('1');
        $this->assertTrue($this->config->isActive());
    }

    public function testIsActiveReturnsFalseWhenDisabled(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('0');
        $this->assertFalse($this->config->isActive());
    }
}

<?php
namespace Coinify\Payment\Test\Unit\Observer;

use Coinify\Payment\Model\Config;
use Coinify\Payment\Observer\CheckWebhookSecret;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Observer/CheckWebhookSecret.php
 *
 * Covers:
 *  - Warning message added when plugin is active and webhook_secret is empty
 *  - No warning when plugin is active and webhook_secret is set
 *  - No warning when plugin is inactive (even if secret is empty)
 *  - AJAX requests are skipped entirely — no message added
 */
class CheckWebhookSecretTest extends TestCase
{
    private Config $config;
    private ManagerInterface $messageManager;
    private RequestInterface $request;
    private CheckWebhookSecret $observer;

    protected function setUp(): void
    {
        $this->config         = $this->createMock(Config::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->request        = $this->createMock(RequestInterface::class);

        $this->observer = new CheckWebhookSecret(
            $this->config,
            $this->messageManager,
            $this->request
        );
    }

    /** A warning must appear on every non-AJAX admin page when the secret is missing. */
    public function testWarningAddedWhenActiveAndSecretMissing(): void
    {
        $this->request->method('isAjax')->willReturn(false);
        $this->config->method('isActive')->willReturn(true);
        $this->config->method('getWebhookSecret')->willReturn('');

        $this->messageManager->expects($this->once())->method('addWarningMessage');

        $this->observer->execute(new Observer());
    }

    /** No warning when the secret is properly configured. */
    public function testNoWarningWhenSecretIsSet(): void
    {
        $this->request->method('isAjax')->willReturn(false);
        $this->config->method('isActive')->willReturn(true);
        $this->config->method('getWebhookSecret')->willReturn('my-secret');

        $this->messageManager->expects($this->never())->method('addWarningMessage');

        $this->observer->execute(new Observer());
    }

    /** No warning when the plugin is disabled, even if the secret is empty. */
    public function testNoWarningWhenPluginInactive(): void
    {
        $this->request->method('isAjax')->willReturn(false);
        $this->config->method('isActive')->willReturn(false);
        $this->config->method('getWebhookSecret')->willReturn('');

        $this->messageManager->expects($this->never())->method('addWarningMessage');

        $this->observer->execute(new Observer());
    }

    /** AJAX requests must be skipped to prevent session message accumulation. */
    public function testAjaxRequestsAreSkipped(): void
    {
        $this->request->method('isAjax')->willReturn(true);

        // isActive and getWebhookSecret should never even be called on AJAX.
        $this->config->expects($this->never())->method('isActive');
        $this->messageManager->expects($this->never())->method('addWarningMessage');

        $this->observer->execute(new Observer());
    }
}

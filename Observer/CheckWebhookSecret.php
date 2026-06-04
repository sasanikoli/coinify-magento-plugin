<?php
namespace Coinify\Payment\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\App\RequestInterface;
use Coinify\Payment\Model\Config as CoinifyConfig;

/**
 * Displays a persistent admin warning banner when the webhook secret is not set.
 *
 * Fires on every non-AJAX admin page load via controller_action_predispatch
 * (registered in etc/adminhtml/events.xml). Using an observer rather than a
 * system message notification ensures the warning appears on every page until
 * the secret is configured, rather than only once as a one-time popup.
 *
 * AJAX requests are skipped to prevent the message accumulating in session
 * storage on every background poll.
 */
class CheckWebhookSecret implements ObserverInterface
{
    /** @var CoinifyConfig */
    private $config;

    /** @var ManagerInterface */
    private $messageManager;

    /** @var RequestInterface */
    private $request;

    public function __construct(
        CoinifyConfig $config,
        ManagerInterface $messageManager,
        RequestInterface $request
    ) {
        $this->config = $config;
        $this->messageManager = $messageManager;
        $this->request = $request;
    }

    public function execute(Observer $observer)
    {
        if ($this->request->isAjax()) {
            return;
        }
        if ($this->config->isActive() && !$this->config->getWebhookSecret()) {
            $this->messageManager->addWarningMessage(
                __('Coinify Payment: Webhook secret is not configured — webhooks will be rejected and order statuses will not update. Go to Stores → Configuration → Payment Methods → Coinify to set it.')
            );
        }
    }
}

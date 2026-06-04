<?php
namespace Coinify\Payment\Controller\Checkout;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Coinify\Payment\Model\Api\Client as CoinifyClient;
use Psr\Log\LoggerInterface;
use Magento\Framework\UrlInterface;
use Coinify\Payment\Model\PaymentIntentFactory;

/**
 * Handles the checkout redirect to the Coinify payment window.
 *
 * After the customer places an order, this controller is called to:
 * 1. Create a payment intent via the Coinify API.
 * 2. Persist the intent record to the database.
 * 3. Fetch the initial intent state (a second GET call, because the create
 *    response does not always include a state field).
 * 4. Validate the returned payment window URL against an allowlist, then
 *    redirect the customer to it.
 *
 * On any failure the customer is sent back to the cart.
 */
class Redirect extends Action
{
    private CheckoutSession $checkoutSession;
    private CoinifyClient $client;
    private LoggerInterface $logger;
    private UrlInterface $urlBuilder;
    private PaymentIntentFactory $intentFactory;

    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        CoinifyClient $client,
        LoggerInterface $logger,
        UrlInterface $urlBuilder,
        PaymentIntentFactory $intentFactory
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->client = $client;
        $this->logger = $logger;
        $this->urlBuilder = $urlBuilder;
        $this->intentFactory = $intentFactory;
    }

    public function execute()
    {
        $order = $this->checkoutSession->getLastRealOrder();

        if (!$order || !$order->getId()) {
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setPath('checkout/cart');
            return $resultRedirect;
        }

        $payload = [
            'amount' => (float)$order->getGrandTotal(),
            'currency' => $order->getOrderCurrencyCode() ?: $order->getOrderCurrencyCode(),
            'orderId' => $order->getIncrementId(),
            'customerId' => $order->getCustomerId() ?: $order->getCustomerEmail(),
            'customerEmail' => $order->getCustomerEmail(),
            'pluginIdentifier' => 'Magento - Coinify Integration',
            'successUrl' => $this->urlBuilder->getUrl('checkout/onepage/success', ['_secure' => true]),
            'failureUrl' => $this->urlBuilder->getUrl('checkout/cart', ['_secure' => true])
        ];

        try {
            $response = $this->client->createPaymentIntent($payload);

            // Persist the intent immediately so webhook handlers can look it up
            // by payment_intent_id or order_id as soon as they arrive.
            $intent = $this->intentFactory->create();
            $intent->setData([
                'order_id' => $order->getIncrementId(),
                'payment_intent_id' => $response['id'] ?? null,
                'state' => $response['state'] ?? null,
                'state_reason' => $response['stateReason'] ?? null,
                'payment_window_url' => $response['paymentWindowUrl'] ?? null,
                'response_raw' => json_encode($response),
            ]);

            try {
                $intent->save();
            } catch (\Exception $e) {
                $this->logger->error('Coinify: failed saving payment intent: ' . $e->getMessage());
            }

            // The create response does not always include a state. Fetch it now
            // so the order detail page shows the correct state from the start.
            $intentId = $response['id'] ?? null;
            if ($intentId && empty($response['state'])) {
                try {
                    $fetched = $this->client->getPaymentIntent($intentId);
                    if (!empty($fetched['state'])) {
                        $intent->setData('state', $fetched['state']);
                        $intent->setData('state_reason', $fetched['stateReason'] ?? null);
                        $intent->setData('response_raw', json_encode($fetched));
                        $intent->save();
                    }
                } catch (\Exception $e) {
                    $this->logger->warning('Coinify: failed fetching payment intent state: ' . $e->getMessage());
                }
            }

            if (!empty($response['paymentWindowUrl'])) {
                $url = $response['paymentWindowUrl'];
                if (!$this->isAllowedPaymentWindowUrl($url)) {
                    $this->logger->error('Coinify: paymentWindowUrl rejected — domain not in allowlist', ['url' => $url]);
                } else {
                    $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
                    $resultRedirect->setUrl($url);
                    return $resultRedirect;
                }
            }

            $this->logger->error('Coinify createPaymentIntent missing paymentWindowUrl', ['response' => $response]);
        } catch (\Exception $e) {
            $this->logger->error('Coinify createPaymentIntent error: ' . $e->getMessage());
        }

        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setPath('checkout/cart');
        return $resultRedirect;
    }

    /**
     * Ensures the payment window URL belongs to a known Coinify domain.
     * Prevents an API compromise from redirecting customers to a phishing page.
     */
    private function isAllowedPaymentWindowUrl(string $url): bool
    {
        $allowedPrefixes = [
            'https://checkout.coinify.com/',
            'https://checkout.sandbox.coinify.com/',
        ];
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($url, $prefix)) {
                return true;
            }
        }
        return false;
    }
}

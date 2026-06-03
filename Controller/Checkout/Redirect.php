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

            // persist intent
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

            if (!empty($response['paymentWindowUrl'])) {
                $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
                $resultRedirect->setUrl($response['paymentWindowUrl']);
                return $resultRedirect;
            }

            $this->logger->error('Coinify createPaymentIntent missing paymentWindowUrl', ['response' => $response]);
        } catch (\Exception $e) {
            $this->logger->error('Coinify createPaymentIntent error: ' . $e->getMessage());
        }

        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setPath('checkout/cart');
        return $resultRedirect;
    }
}

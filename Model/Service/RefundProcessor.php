<?php
namespace Coinify\Payment\Model\Service;

use Coinify\Payment\Model\Api\Client as CoinifyClient;
use Coinify\Payment\Model\RefundFactory;
use Psr\Log\LoggerInterface;

class RefundProcessor
{
    private CoinifyClient $client;
    private RefundFactory $refundFactory;
    private LoggerInterface $logger;

    public function __construct(CoinifyClient $client, RefundFactory $refundFactory, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->refundFactory = $refundFactory;
        $this->logger = $logger;
    }

    /**
     * Create a merchant refund for a payment intent.
     * @param string $paymentIntentId
     * @param string $orderIncrementId
     * @param float $amount
     * @param string|null $currency
     * @return array refund response
     */
    public function createRefund(string $paymentIntentId, string $orderIncrementId, float $amount, ?string $currency = null): array
    {
        $payload = [
            'amount' => $amount,
        ];

        try {
            $response = $this->client->createRefund($paymentIntentId, $payload);

            // The API returns the full payment intent object; the new refund is the last item in merchantRefunds
            $merchantRefunds = $response['merchantRefunds'] ?? [];
            $lastRefund = !empty($merchantRefunds) ? end($merchantRefunds) : null;
            $refundId = $lastRefund['id'] ?? null;

            if ($refundId) {
                $refund = $this->refundFactory->create();
                $refund->setData([
                    'refund_id' => $refundId,
                    'payment_intent_id' => $paymentIntentId,
                    'order_id' => $orderIncrementId,
                    'amount' => $amount,
                    'currency' => $currency,
                    'state' => 'initiated',
                    'response_raw' => json_encode($response),
                ]);
                try {
                    $refund->save();
                } catch (\Exception $e) {
                    $this->logger->warning('Coinify: failed saving refund record: ' . $e->getMessage());
                }
            }

            return $response;
        } catch (\Exception $e) {
            $this->logger->error('Coinify refund error: ' . $e->getMessage());
            return [];
        }
    }
}

<?php
namespace Coinify\Payment\Model\Service;

use Coinify\Payment\Model\Api\Client as CoinifyClient;
use Coinify\Payment\Model\RefundFactory;
use Psr\Log\LoggerInterface;

/**
 * Creates merchant refunds via the Coinify API and persists the refund record.
 *
 * Note: the Coinify refund flow is asynchronous. This service initiates the
 * refund and saves it with state 'initiated'. The actual fund transfer happens
 * after the customer provides their wallet address via email. A
 * payment-intent.refund.completed webhook updates the state to 'completed'.
 */
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
     * Calls POST /payment-intents/{id}/refunds, then extracts the new refund
     * entry from the merchantRefunds array in the response and saves it locally.
     * Returns the full API response so the caller can inspect error codes.
     */
    public function createRefund(string $paymentIntentId, string $orderIncrementId, float $amount, ?string $currency = null): array
    {
        $payload = [
            'amount' => $amount,
        ];

        try {
            $response = $this->client->createRefund($paymentIntentId, $payload);

            // The API returns the updated payment intent object. The newly created
            // refund is appended as the last item in merchantRefunds.
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

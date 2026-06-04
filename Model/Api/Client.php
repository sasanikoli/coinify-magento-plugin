<?php
namespace Coinify\Payment\Model\Api;

use Magento\Framework\HTTP\Client\Curl;
use Coinify\Payment\Model\Config as CoinifyConfig;

/**
 * HTTP client for the Coinify REST API.
 *
 * Selects the sandbox or production base URL based on the module's environment
 * setting and injects the API key on every request via the X-API-KEY header.
 */
class Client
{
    private const SANDBOX_BASE_URL = 'https://api.payment.sandbox.coinify.com/v1';
    private const PRODUCTION_BASE_URL = 'https://api.payment.coinify.com/v1';

    private Curl $curl;
    private CoinifyConfig $config;

    public function __construct(Curl $curl, CoinifyConfig $config)
    {
        $this->curl = $curl;
        $this->config = $config;
    }

    /** Creates a new payment intent and returns the full API response array. */
    public function createPaymentIntent(array $payload): array
    {
        return $this->request('POST', '/payment-intents', $payload);
    }

    /** Fetches the current state of an existing payment intent by its ID. */
    public function getPaymentIntent(string $paymentIntentId): array
    {
        $uri = '/payment-intents/' . urlencode($paymentIntentId);
        return $this->request('GET', $uri);
    }

    /** Initiates a merchant refund for a completed payment intent. */
    public function createRefund(string $paymentIntentId, array $payload): array
    {
        $uri = '/payment-intents/' . urlencode($paymentIntentId) . '/refunds';
        return $this->request('POST', $uri, $payload);
    }

    /**
     * Executes an API request and returns the decoded JSON response.
     * GET requests send no body; all other methods POST a JSON-encoded payload.
     */
    private function request(string $method, string $uri, array $payload = []): array
    {
        $environment = $this->config->getEnvironment();
        $baseUrl = $environment === 'production' ? self::PRODUCTION_BASE_URL : self::SANDBOX_BASE_URL;
        $url = rtrim($baseUrl, '/') . $uri;
        $apiKey = $this->config->getApiKey();
        $this->curl->addHeader('Content-Type', 'application/json');
        $this->curl->addHeader('Accept', 'application/json');
        if ($apiKey) {
            $this->curl->addHeader('X-API-KEY', $apiKey);
        }

        if ($method === 'GET') {
            $this->curl->get($url);
        } else {
            $this->curl->post($url, json_encode($payload));
        }

        $response = $this->curl->getBody();
        return $response ? json_decode($response, true) : [];
    }
}

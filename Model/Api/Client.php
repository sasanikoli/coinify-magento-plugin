<?php
namespace Coinify\Payment\Model\Api;

use Magento\Framework\HTTP\Client\Curl;
use Coinify\Payment\Model\Config as CoinifyConfig;

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

    public function createPaymentIntent(array $payload): array
    {
        return $this->request('POST', '/payment-intents', $payload);
    }

    public function getPaymentIntent(string $paymentIntentId): array
    {
        $uri = '/payment-intents/' . urlencode($paymentIntentId);
        return $this->request('GET', $uri);
    }

    public function createRefund(string $paymentIntentId, array $payload): array
    {
        $uri = '/payment-intents/' . urlencode($paymentIntentId) . '/refunds';
        return $this->request('POST', $uri, $payload);
    }

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

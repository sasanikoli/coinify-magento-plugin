<?php
namespace Coinify\Payment\Test\Unit\Model\Api;

use Coinify\Payment\Model\Api\Client;
use Coinify\Payment\Model\Config;
use Magento\Framework\HTTP\Client\Curl;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Model/Api/Client.php
 *
 * Covers:
 *  - Sandbox base URL used when environment is 'sandbox'
 *  - Production base URL used when environment is 'production'
 *  - X-API-KEY header injected when api_key is non-empty
 *  - X-API-KEY header omitted when api_key is empty
 *  - GET requests call curl->get(), not curl->post()
 *  - POST requests call curl->post() with JSON-encoded body
 */
class ClientTest extends TestCase
{
    private Curl $curl;
    private Config $config;
    private Client $client;

    protected function setUp(): void
    {
        $this->curl   = $this->createMock(Curl::class);
        $this->config = $this->createMock(Config::class);
        $this->client = new Client($this->curl, $this->config);
    }

    /** createPaymentIntent() must POST to the sandbox endpoint when environment = sandbox. */
    public function testUsesSandboxUrlInSandboxEnvironment(): void
    {
        $this->config->method('getEnvironment')->willReturn('sandbox');
        $this->config->method('getApiKey')->willReturn('key');

        $this->curl->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('api.payment.sandbox.coinify.com'),
                $this->anything()
            );
        $this->curl->method('getBody')->willReturn('{}');

        $this->client->createPaymentIntent(['amount' => 10]);
    }

    /** createPaymentIntent() must POST to the production endpoint when environment = production. */
    public function testUsesProductionUrlInProductionEnvironment(): void
    {
        $this->config->method('getEnvironment')->willReturn('production');
        $this->config->method('getApiKey')->willReturn('key');

        $this->curl->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('api.payment.coinify.com'),
                $this->anything()
            );
        $this->curl->method('getBody')->willReturn('{}');

        $this->client->createPaymentIntent(['amount' => 10]);
    }

    /** X-API-KEY header must be set when the api_key config value is non-empty. */
    public function testApiKeyHeaderInjectedWhenPresent(): void
    {
        $this->config->method('getEnvironment')->willReturn('sandbox');
        $this->config->method('getApiKey')->willReturn('test-key-123');
        $this->curl->method('getBody')->willReturn('{}');
        $this->curl->method('post');

        $headers = [];
        $this->curl->method('addHeader')->willReturnCallback(
            function ($name, $value) use (&$headers) { $headers[$name] = $value; }
        );

        $this->client->createPaymentIntent([]);

        $this->assertArrayHasKey('X-API-KEY', $headers);
        $this->assertSame('test-key-123', $headers['X-API-KEY']);
    }

    /** X-API-KEY header must NOT be added when the api_key config value is empty. */
    public function testApiKeyHeaderOmittedWhenEmpty(): void
    {
        $this->config->method('getEnvironment')->willReturn('sandbox');
        $this->config->method('getApiKey')->willReturn('');
        $this->curl->method('getBody')->willReturn('{}');
        $this->curl->method('post');

        // Capture all addHeader calls and assert X-API-KEY is never among them.
        $headers = [];
        $this->curl->method('addHeader')->willReturnCallback(
            function ($name, $value) use (&$headers) { $headers[$name] = $value; }
        );

        $this->client->createPaymentIntent([]);
        $this->assertArrayNotHasKey('X-API-KEY', $headers);
    }

    /** getPaymentIntent() must use curl->get(), not curl->post(). */
    public function testGetPaymentIntentUsesGetMethod(): void
    {
        $this->config->method('getEnvironment')->willReturn('sandbox');
        $this->config->method('getApiKey')->willReturn('key');
        $this->curl->method('getBody')->willReturn('{"id":"abc","state":"awaiting_payment"}');

        $this->curl->expects($this->once())->method('get')
            ->with($this->stringContains('/payment-intents/abc'));
        $this->curl->expects($this->never())->method('post');

        $result = $this->client->getPaymentIntent('abc');
        $this->assertSame('awaiting_payment', $result['state']);
    }

    /** createPaymentIntent() must send a JSON-encoded body via curl->post(). */
    public function testCreatePaymentIntentPostsJsonBody(): void
    {
        $this->config->method('getEnvironment')->willReturn('sandbox');
        $this->config->method('getApiKey')->willReturn('key');
        $this->curl->method('getBody')->willReturn('{}');

        $payload = ['amount' => 99.5, 'currency' => 'EUR'];

        $this->curl->expects($this->once())
            ->method('post')
            ->with($this->anything(), json_encode($payload));

        $this->client->createPaymentIntent($payload);
    }
}

<?php
namespace Coinify\Payment\Test\Unit\Controller\Webhook;

use Coinify\Payment\Controller\Webhook\Notify;
use Coinify\Payment\Model\Config;
use Coinify\Payment\Model\ResourceModel\PaymentIntent\CollectionFactory as IntentCollectionFactory;
use Coinify\Payment\Model\ResourceModel\PaymentIntent\Collection as IntentCollection;
use Coinify\Payment\Model\ResourceModel\Refund\CollectionFactory as RefundCollectionFactory;
use Coinify\Payment\Model\Service\CreditMemoProcessor;
use Coinify\Payment\Model\WebhookLogFactory;
use Coinify\Payment\Model\WebhookLog;
use Coinify\Payment\Model\PaymentIntent;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\DB\Transaction;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Controller/Webhook/Notify.php
 *
 * Strategy: Notify extends Magento's Action base class which has a complex
 * constructor. We bypass it via disableOriginalConstructor() and inject all
 * required dependencies (including the protected resultFactory and _request
 * inherited from Action) using setProperty().
 *
 * Covers:
 *  - HTTP 400 returned when webhook_secret is not configured
 *  - HTTP 400 returned when X-Coinify-Webhook-Signature is missing or wrong
 *  - HTTP 400 returned when intent ID and order ID in payload don't match DB record (IDOR)
 *  - HTTP 200 returned for a valid, correctly signed webhook
 */
class NotifyTest extends TestCase
{
    private Notify $controller;
    private Config $config;
    private RequestInterface $request;
    private Raw $rawResult;
    private IntentCollectionFactory $intentCollectionFactory;
    private WebhookLogFactory $webhookLogFactory;

    protected function setUp(): void
    {
        $this->config   = $this->createMock(Config::class);
        $this->request  = $this->createMock(RequestInterface::class);

        // getHeaders() is called when logging each webhook — return a minimal stub.
        $headers = new class { public function toArray() { return []; } };
        $this->request->method('getHeaders')->willReturn($headers);

        $this->rawResult = $this->createMock(Raw::class);
        $this->rawResult->method('setHttpResponseCode')->willReturnSelf();
        $this->rawResult->method('setContents')->willReturnSelf();

        $resultFactory = $this->createMock(ResultFactory::class);
        $resultFactory->method('create')->willReturn($this->rawResult);

        $this->intentCollectionFactory = $this->createMock(IntentCollectionFactory::class);
        $this->webhookLogFactory       = $this->createMock(WebhookLogFactory::class);

        $webhookLog = $this->createMock(WebhookLog::class);
        $this->webhookLogFactory->method('create')->willReturn($webhookLog);

        $this->controller = $this->getMockBuilder(Notify::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])  // test the real execute() — mock nothing on Notify itself
            ->getMock();

        // Inject dependencies that would normally come through Magento's DI.
        $this->setProperty('config', $this->config);
        $this->setProperty('logger', $this->createMock(LoggerInterface::class));
        $this->setProperty('intentCollectionFactory', $this->intentCollectionFactory);
        // Configure orderFactory so create()->loadByIncrementId() returns an Order
        // with getId() = null, causing handleCompleted/cancel to be skipped safely.
        $mockOrder = $this->createMock(\Magento\Sales\Model\Order::class);
        $mockOrder->method('loadByIncrementId')->willReturnSelf();
        $mockOrder->method('getId')->willReturn(null);
        $orderFactory = $this->createMock(OrderFactory::class);
        $orderFactory->method('create')->willReturn($mockOrder);
        $this->setProperty('orderFactory', $orderFactory);
        $this->setProperty('webhookLogFactory', $this->webhookLogFactory);
        $this->setProperty('invoiceService', $this->createMock(InvoiceService::class));
        $this->setProperty('transaction', $this->createMock(Transaction::class));
        $this->setProperty('invoiceSender', $this->createMock(InvoiceSender::class));
        $this->setProperty('refundCollectionFactory', $this->createMock(RefundCollectionFactory::class));
        $this->setProperty('creditMemoProcessor', $this->createMock(CreditMemoProcessor::class));

        // Inject the Action-level protected properties.
        $this->setProperty('resultFactory', $resultFactory);
        $this->setProperty('_request', $this->request);
    }

    /** Webhook must be rejected with 400 when webhook_secret is not configured. */
    public function testReturns400WhenWebhookSecretNotConfigured(): void
    {
        $this->request->method('getContent')->willReturn('{"event":"test"}');
        $this->request->method('getHeader')->willReturn('');
        $this->config->method('getWebhookSecret')->willReturn('');

        $this->rawResult->expects($this->once())
            ->method('setHttpResponseCode')
            ->with(400);

        $this->controller->execute();
    }

    /** Webhook must be rejected with 400 when the HMAC signature does not match. */
    public function testReturns400WhenSignatureInvalid(): void
    {
        $body   = '{"event":"payment-intent.completed","context":{"id":"pi_1","orderId":"100000001"}}';
        $secret = 'correct-secret';

        $this->request->method('getContent')->willReturn($body);
        $this->request->method('getHeader')->willReturnCallback(function ($name) {
            if ($name === 'X-Coinify-Webhook-Signature') {
                return 'wrongsignature';
            }
            return '';
        });
        $this->config->method('getWebhookSecret')->willReturn($secret);

        $this->rawResult->expects($this->once())
            ->method('setHttpResponseCode')
            ->with(400);

        $this->controller->execute();
    }

    /**
     * Webhook must be rejected with 400 when the intent ID in the payload maps to a
     * different order ID in the database (IDOR cross-validation guard).
     */
    public function testReturns400WhenIntentOrderIdMismatch(): void
    {
        $secret = 'test-secret';
        $body   = json_encode([
            'event'   => 'payment-intent.completed',
            'context' => ['id' => 'pi_abc', 'orderId' => '100000002'],
        ]);
        $sig = hash_hmac('sha256', $body, $secret);

        $this->request->method('getContent')->willReturn($body);
        $this->request->method('getHeader')->willReturnCallback(function ($name) use ($sig) {
            return $name === 'X-Coinify-Webhook-Signature' ? $sig : '';
        });
        $this->config->method('getWebhookSecret')->willReturn($secret);

        // Intent pi_abc belongs to order 100000001, but payload claims 100000002.
        $intent = $this->createMock(PaymentIntent::class);
        $intent->method('getId')->willReturn(1);
        $intent->method('getData')->with('order_id')->willReturn('100000001');

        $collection = $this->createMock(IntentCollection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($intent);
        $this->intentCollectionFactory->method('create')->willReturn($collection);

        $this->rawResult->expects($this->once())
            ->method('setHttpResponseCode')
            ->with(400);

        $this->controller->execute();
    }

    /** A correctly signed webhook with matching intent/order IDs must return 200 ok. */
    public function testReturns200ForValidWebhook(): void
    {
        $secret = 'test-secret';
        $body   = json_encode([
            'event'   => 'payment-intent.completed',
            'context' => ['id' => 'pi_abc', 'orderId' => '100000001', 'payments' => []],
        ]);
        $sig = hash_hmac('sha256', $body, $secret);

        $this->request->method('getContent')->willReturn($body);
        $this->request->method('getHeader')->willReturnCallback(function ($name) use ($sig) {
            return $name === 'X-Coinify-Webhook-Signature' ? $sig : '';
        });
        $this->config->method('getWebhookSecret')->willReturn($secret);

        // Intent belongs to the same order as the payload claims.
        $intent = $this->createMock(PaymentIntent::class);
        $intent->method('getId')->willReturn(1);
        $intent->method('getData')->willReturnCallback(function ($key) {
            return $key === 'order_id' ? '100000001' : null;
        });

        $collection = $this->createMock(IntentCollection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($intent);
        $this->intentCollectionFactory->method('create')->willReturn($collection);

        $this->rawResult->expects($this->never())->method('setHttpResponseCode');
        $this->rawResult->expects($this->once())->method('setContents')->with('ok');

        $this->controller->execute();
    }

    // -------------------------------------------------------------------------

    /** Sets a protected/private property on the controller via reflection. */
    private function setProperty(string $name, mixed $value): void
    {
        $ref = new \ReflectionClass($this->controller);
        // Walk up the class hierarchy to find the property (some are on parent Action).
        while ($ref) {
            if ($ref->hasProperty($name)) {
                $prop = $ref->getProperty($name);
                $prop->setAccessible(true);
                $prop->setValue($this->controller, $value);
                return;
            }
            $ref = $ref->getParentClass();
        }
        throw new \RuntimeException("Property '$name' not found on " . get_class($this->controller));
    }
}

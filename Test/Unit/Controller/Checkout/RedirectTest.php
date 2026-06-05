<?php
namespace Coinify\Payment\Test\Unit\Controller\Checkout;

use Coinify\Payment\Controller\Checkout\Redirect;
use Coinify\Payment\Model\Api\Client as CoinifyClient;
use Coinify\Payment\Model\PaymentIntentFactory;
use Coinify\Payment\Model\PaymentIntent;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect as RedirectResult;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Controller/Checkout/Redirect.php
 *
 * Covers:
 *  - isAllowedPaymentWindowUrl() accepts a valid production URL
 *  - isAllowedPaymentWindowUrl() accepts a valid sandbox URL
 *  - isAllowedPaymentWindowUrl() rejects a URL with an evil subdomain
 *    (e.g. checkout.coinify.com.evil.com) to prevent open-redirect attacks
 *  - isAllowedPaymentWindowUrl() rejects a plain HTTP URL
 *  - Customer is sent to the cart when no order exists in the session
 */
class RedirectTest extends TestCase
{
    private Redirect $controller;
    private CheckoutSession $checkoutSession;
    private CoinifyClient $client;
    private PaymentIntentFactory $intentFactory;
    private ResultFactory $resultFactory;

    protected function setUp(): void
    {
        $this->checkoutSession = $this->createMock(CheckoutSession::class);
        $this->client          = $this->createMock(CoinifyClient::class);
        $this->intentFactory   = $this->createMock(PaymentIntentFactory::class);

        $intent = $this->createMock(PaymentIntent::class);
        $intent->method('setData')->willReturnSelf();
        $this->intentFactory->method('create')->willReturn($intent);

        $this->resultFactory = $this->createMock(ResultFactory::class);

        $this->controller = $this->getMockBuilder(Redirect::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $this->setProperty('checkoutSession', $this->checkoutSession);
        $this->setProperty('client', $this->client);
        $this->setProperty('logger', $this->createMock(LoggerInterface::class));
        $this->setProperty('urlBuilder', $this->createMock(UrlInterface::class));
        $this->setProperty('intentFactory', $this->intentFactory);
        $this->setProperty('resultFactory', $this->resultFactory);
    }

    // -------------------------------------------------------------------------
    // URL allowlist tests — exercise the private isAllowedPaymentWindowUrl() method
    // -------------------------------------------------------------------------

    /** A valid production checkout URL must be accepted and result in a redirect. */
    public function testValidProductionUrlIsAccepted(): void
    {
        $url = 'https://checkout.coinify.com/payment-intent/pi_abc123';
        $this->assertUrlAccepted($url);
    }

    /** A valid sandbox checkout URL must be accepted and result in a redirect. */
    public function testValidSandboxUrlIsAccepted(): void
    {
        $url = 'https://checkout.sandbox.coinify.com/payment-intent/pi_abc123';
        $this->assertUrlAccepted($url);
    }

    /**
     * A URL that uses the Coinify domain as a subdomain prefix of a different domain
     * must be rejected, preventing an attacker who controls the API response from
     * redirecting customers to a phishing site.
     */
    public function testEvilSubdomainIsRejected(): void
    {
        $url = 'https://checkout.coinify.com.evil.com/steal-credentials';
        $this->assertUrlRejected($url);
    }

    /** Plain HTTP URLs must be rejected regardless of the domain. */
    public function testHttpUrlIsRejected(): void
    {
        $url = 'http://checkout.coinify.com/payment-intent/pi_abc123';
        $this->assertUrlRejected($url);
    }

    // -------------------------------------------------------------------------
    // Session / order guard test
    // -------------------------------------------------------------------------

    /** When no order is in the checkout session, the customer must go back to the cart. */
    public function testRedirectsToCartWhenNoOrder(): void
    {
        $this->checkoutSession->method('getLastRealOrder')->willReturn(null);

        $cartRedirect = $this->createMock(RedirectResult::class);
        $cartRedirect->method('setPath')->willReturnSelf();
        $this->resultFactory->method('create')->willReturn($cartRedirect);

        $cartRedirect->expects($this->once())->method('setPath')->with('checkout/cart');

        $this->controller->execute();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Asserts that the given URL is accepted by the allowlist:
     * the controller must create a redirect result pointing at that URL.
     */
    private function assertUrlAccepted(string $url): void
    {
        $order = $this->buildOrder();
        $this->checkoutSession->method('getLastRealOrder')->willReturn($order);
        $this->client->method('createPaymentIntent')->willReturn([
            'id' => 'pi_1', 'state' => 'awaiting_payment', 'paymentWindowUrl' => $url,
        ]);

        $redirectResult = $this->createMock(RedirectResult::class);
        $redirectResult->method('setUrl')->willReturnSelf();
        $this->resultFactory->method('create')->willReturn($redirectResult);

        $redirectResult->expects($this->once())->method('setUrl')->with($url);

        $this->controller->execute();
    }

    /**
     * Asserts that the given URL is rejected by the allowlist:
     * the controller must fall back to the cart redirect, never calling setUrl().
     */
    private function assertUrlRejected(string $url): void
    {
        $order = $this->buildOrder();
        $this->checkoutSession->method('getLastRealOrder')->willReturn($order);
        $this->client->method('createPaymentIntent')->willReturn([
            'id' => 'pi_1', 'state' => 'awaiting_payment', 'paymentWindowUrl' => $url,
        ]);

        $redirectResult = $this->createMock(RedirectResult::class);
        $redirectResult->method('setPath')->willReturnSelf();
        $this->resultFactory->method('create')->willReturn($redirectResult);

        $redirectResult->expects($this->never())->method('setUrl');
        $redirectResult->expects($this->once())->method('setPath')->with('checkout/cart');

        $this->controller->execute();
    }

    private function buildOrder(): \Magento\Sales\Model\Order
    {
        $order = $this->createMock(\Magento\Sales\Model\Order::class);
        $order->method('getId')->willReturn(1);
        $order->method('getIncrementId')->willReturn('100000001');
        $order->method('getGrandTotal')->willReturn(50.0);
        $order->method('getOrderCurrencyCode')->willReturn('EUR');
        $order->method('getCustomerId')->willReturn(null);
        $order->method('getCustomerEmail')->willReturn('test@example.com');
        return $order;
    }

    private function setProperty(string $name, mixed $value): void
    {
        $ref = new \ReflectionClass($this->controller);
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

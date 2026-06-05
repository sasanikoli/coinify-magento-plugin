<?php
namespace Coinify\Payment\Test\Unit\Model\Service;

use Coinify\Payment\Model\Service\CreditMemoProcessor;
use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Service\CreditmemoService;
use Magento\Sales\Model\Service\InvoiceService;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Model/Service/CreditMemoProcessor.php
 *
 * Covers:
 *  - A refund equal to the full order total triggers a full credit memo (with shipping)
 *  - A partial refund triggers a partial credit memo (adjustment_positive, zero quantities)
 *  - Processing stops early and logs a warning when canCreditmemo() returns false
 */
class CreditMemoProcessorTest extends TestCase
{
    private CreditmemoFactory $creditmemoFactory;
    private CreditmemoService $creditmemoService;
    private InvoiceService $invoiceService;
    private TransactionFactory $transactionFactory;
    private OrderFactory $orderFactory;
    private CreditMemoProcessor $processor;

    protected function setUp(): void
    {
        $this->creditmemoFactory  = $this->createMock(CreditmemoFactory::class);
        $this->creditmemoService  = $this->createMock(CreditmemoService::class);
        $this->invoiceService     = $this->createMock(InvoiceService::class);
        $this->transactionFactory = $this->createMock(TransactionFactory::class);
        $this->orderFactory       = $this->createMock(OrderFactory::class);

        $this->processor = new CreditMemoProcessor(
            $this->creditmemoFactory,
            $this->creditmemoService,
            $this->invoiceService,
            $this->transactionFactory,
            $this->orderFactory,
            $this->createMock(LoggerInterface::class)
        );
    }

    /**
     * When the refund amount equals the order total, createByInvoice() must receive
     * the invoice's shipping_amount (full credit memo path).
     */
    public function testFullRefundCreatesCreditMemoWithShipping(): void
    {
        $invoice = $this->buildInvoice(10.0);
        $order   = $this->buildOrder(100.0, 0.0, $invoice);

        $creditmemo = $this->createMock(Creditmemo::class);
        $creditmemo->method('setOfflineRequested')->willReturnSelf();

        // Expect createByInvoice with shipping_amount = 10.0 (the full-refund path).
        $this->creditmemoFactory->expects($this->once())
            ->method('createByInvoice')
            ->with($invoice, $this->callback(function ($data) {
                return isset($data['shipping_amount']) && $data['shipping_amount'] === 10.0
                    && !isset($data['adjustment_positive']);
            }))
            ->willReturn($creditmemo);

        $this->creditmemoService->expects($this->once())->method('refund');

        $this->processor->createForRefund($order, 100.0);
    }

    /**
     * When the refund amount is less than the order total, createByInvoice() must use
     * adjustment_positive with zero item quantities (partial credit memo path).
     */
    public function testPartialRefundCreatesCreditMemoWithAdjustment(): void
    {
        $invoice = $this->buildInvoice(10.0);
        $order   = $this->buildOrder(100.0, 0.0, $invoice);

        $creditmemo = $this->createMock(Creditmemo::class);
        $creditmemo->method('setOfflineRequested')->willReturnSelf();

        // Expect createByInvoice with adjustment_positive = 30.0 (the partial-refund path).
        $this->creditmemoFactory->expects($this->once())
            ->method('createByInvoice')
            ->with($invoice, $this->callback(function ($data) {
                return isset($data['adjustment_positive'])
                    && $data['adjustment_positive'] === 30.0
                    && $data['shipping_amount'] === 0;
            }))
            ->willReturn($creditmemo);

        $this->creditmemoService->expects($this->once())->method('refund');

        $this->processor->createForRefund($order, 30.0);
    }

    /** When canCreditmemo() returns false, processing must stop and no memo be created. */
    public function testEarlyReturnWhenCannotCreateCreditMemo(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getBaseTotalInvoiced')->willReturn(100.0);
        $order->method('getIncrementId')->willReturn('100000001');
        $order->method('canCreditmemo')->willReturn(false);

        $this->creditmemoFactory->expects($this->never())->method('createByInvoice');
        $this->creditmemoService->expects($this->never())->method('refund');

        $this->processor->createForRefund($order, 50.0);
    }

    // -------------------------------------------------------------------------

    private function buildInvoice(float $shippingAmount): Invoice
    {
        $invoice = $this->createMock(Invoice::class);
        $invoice->method('getId')->willReturn(1);
        $invoice->method('getShippingAmount')->willReturn($shippingAmount);
        $invoice->method('getAllItems')->willReturn([]);
        return $invoice;
    }

    private function buildOrder(float $grandTotal, float $alreadyRefunded, Invoice $invoice): Order
    {
        $invoiceCollection = new class($invoice) {
            public function __construct(private $invoice) {}
            public function getFirstItem() { return $this->invoice; }
        };

        $order = $this->createMock(Order::class);
        $order->method('getBaseTotalInvoiced')->willReturn($grandTotal);
        $order->method('getGrandTotal')->willReturn($grandTotal);
        $order->method('getTotalRefunded')->willReturn($alreadyRefunded);
        $order->method('getIncrementId')->willReturn('100000001');
        $order->method('canCreditmemo')->willReturn(true);
        $order->method('getInvoiceCollection')->willReturn($invoiceCollection);
        return $order;
    }
}

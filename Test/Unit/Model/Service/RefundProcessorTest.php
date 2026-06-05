<?php
namespace Coinify\Payment\Test\Unit\Model\Service;

use Coinify\Payment\Model\Api\Client as CoinifyClient;
use Coinify\Payment\Model\Refund;
use Coinify\Payment\Model\RefundFactory;
use Coinify\Payment\Model\Service\RefundProcessor;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Model/Service/RefundProcessor.php
 *
 * Covers:
 *  - A refund record is saved to the database after a successful API call
 *  - The refund ID is correctly extracted from the last entry in merchantRefunds
 *  - No refund record is saved when the API response contains no merchantRefunds
 */
class RefundProcessorTest extends TestCase
{
    private CoinifyClient $client;
    private RefundFactory $refundFactory;
    private Refund $refund;
    private RefundProcessor $processor;

    protected function setUp(): void
    {
        $this->client        = $this->createMock(CoinifyClient::class);
        $this->refund        = $this->createMock(Refund::class);
        $this->refundFactory = $this->createMock(RefundFactory::class);
        $this->refundFactory->method('create')->willReturn($this->refund);

        $this->processor = new RefundProcessor(
            $this->client,
            $this->refundFactory,
            $this->createMock(LoggerInterface::class)
        );
    }

    /**
     * When the API returns a merchantRefunds array, the last entry's ID must be
     * used to create a local refund record with state 'initiated'.
     */
    public function testRefundRecordSavedWithCorrectData(): void
    {
        $this->client->method('createRefund')->willReturn([
            'id' => 'pi_abc',
            'merchantRefunds' => [
                ['id' => 'ref_001', 'amount' => 50.0],
            ],
        ]);

        $savedData = [];
        $this->refund->method('setData')->willReturnCallback(
            function ($data) use (&$savedData) { $savedData = $data; return $this->refund; }
        );
        $this->refund->expects($this->once())->method('save');

        $this->processor->createRefund('pi_abc', '100000001', 50.0, 'EUR');

        $this->assertSame('ref_001', $savedData['refund_id']);
        $this->assertSame('pi_abc', $savedData['payment_intent_id']);
        $this->assertSame('100000001', $savedData['order_id']);
        $this->assertSame(50.0, $savedData['amount']);
        $this->assertSame('initiated', $savedData['state']);
    }

    /**
     * When the API response contains multiple refunds, the last one added must
     * be used — Coinify appends the new refund to the end of merchantRefunds.
     */
    public function testLastMerchantRefundEntryIsUsed(): void
    {
        $this->client->method('createRefund')->willReturn([
            'merchantRefunds' => [
                ['id' => 'ref_001'],
                ['id' => 'ref_002'],  // ← most recent, should be used
            ],
        ]);

        $savedData = [];
        $this->refund->method('setData')->willReturnCallback(
            function ($data) use (&$savedData) { $savedData = $data; return $this->refund; }
        );

        $this->processor->createRefund('pi_abc', '100000001', 25.0, 'EUR');

        $this->assertSame('ref_002', $savedData['refund_id']);
    }

    /** When the API returns no merchantRefunds, no refund record should be saved. */
    public function testNoRecordSavedWhenNoMerchantRefunds(): void
    {
        $this->client->method('createRefund')->willReturn(['id' => 'pi_abc']);

        $this->refund->expects($this->never())->method('save');

        $this->processor->createRefund('pi_abc', '100000001', 10.0, 'EUR');
    }
}

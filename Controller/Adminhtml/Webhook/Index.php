<?php
namespace Coinify\Payment\Controller\Adminhtml\Webhook;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\RawFactory;
use Coinify\Payment\Model\ResourceModel\WebhookLog\CollectionFactory;

class Index extends Action
{
    const ADMIN_RESOURCE = 'Coinify_Payment::webhook_logs';

    /** @var RawFactory */
    private $resultRawFactory;

    /** @var CollectionFactory */
    private $collectionFactory;

    public function __construct(
        Context $context,
        RawFactory $resultRawFactory,
        CollectionFactory $collectionFactory
    ) {
        parent::__construct($context);
        $this->resultRawFactory = $resultRawFactory;
        $this->collectionFactory = $collectionFactory;
    }

    public function execute()
    {
        $collection = $this->collectionFactory->create();
        $collection->setOrder('created_at', 'DESC');
        $collection->setPageSize(100);

        $logs = [];
        foreach ($collection->getItems() as $item) {
            $logs[] = $item->getData();
        }

        $rows = '';
        foreach ($logs as $log) {
            $rows .= '<tr>'
                . '<td>' . (int) ($log['entity_id'] ?? 0) . '</td>'
                . '<td>' . htmlspecialchars($log['event'] ?? '') . '</td>'
                . '<td>' . htmlspecialchars($log['order_id'] ?? '') . '</td>'
                . '<td>' . htmlspecialchars($log['payment_intent_id'] ?? '') . '</td>'
                . '<td>' . htmlspecialchars($log['created_at'] ?? '') . '</td>'
                . '<td style="word-break:break-all;font-size:11px;max-width:400px">' . htmlspecialchars($log['payload'] ?? '') . '</td>'
                . '</tr>';
        }

        $count = count($logs);
        $body = $count > 0
            ? '<p>' . $count . ' log(s) found.</p>'
              . '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%">'
              . '<thead><tr><th>ID</th><th>Event</th><th>Order ID</th><th>Payment Intent ID</th><th>Received</th><th>Payload</th></tr></thead>'
              . '<tbody>' . $rows . '</tbody></table>'
            : '<p>No webhook logs found in the database.</p>';

        $html = '<!DOCTYPE html><html><head><title>Coinify Webhook Logs</title>'
            . '<style>body{font-family:sans-serif;padding:20px}th{background:#eee}</style>'
            . '</head><body><h2>Coinify Webhook Logs (raw)</h2>' . $body . '</body></html>';

        $result = $this->resultRawFactory->create();
        $result->setContents($html);
        return $result;
    }
}

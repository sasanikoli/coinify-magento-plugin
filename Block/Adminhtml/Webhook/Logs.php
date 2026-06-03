<?php
namespace Coinify\Payment\Block\Adminhtml\Webhook;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Coinify\Payment\Model\ResourceModel\WebhookLog\CollectionFactory;

class Logs extends Template
{
    /** @var CollectionFactory */
    private $collectionFactory;

    public function __construct(Context $context, CollectionFactory $collectionFactory, array $data = [])
    {
        parent::__construct($context, $data);
        $this->collectionFactory = $collectionFactory;
    }

    protected function _toHtml(): string
    {
        $collection = $this->collectionFactory->create();
        $collection->setOrder('created_at', 'DESC');
        $collection->setPageSize(100);

        $logs = [];
        foreach ($collection->getItems() as $item) {
            $logs[] = $item->getData();
        }

        $e = function ($s) { return $this->escapeHtml((string) $s); };

        $html = '<div style="padding:20px">';

        if (empty($logs)) {
            $html .= '<p>' . __('No webhook logs found.') . '</p>';
        } else {
            $count = count($logs);
            $html .= '<p style="margin-bottom:12px">Showing ' . $count . ' most recent webhook log' . ($count !== 1 ? 's' : '') . '.</p>';
            $html .= '<table class="data-grid" style="width:100%;table-layout:fixed">';
            $html .= '<thead><tr>';
            $html .= '<th style="width:50px">ID</th>';
            $html .= '<th style="width:160px">Event</th>';
            $html .= '<th style="width:120px">Order ID</th>';
            $html .= '<th style="width:280px">Payment Intent ID</th>';
            $html .= '<th style="width:160px">Received</th>';
            $html .= '<th>Payload</th>';
            $html .= '</tr></thead><tbody>';

            foreach ($logs as $i => $log) {
                $row = ($i % 2 === 0) ? '' : ' style="background:#f9f9f9"';
                $html .= '<tr' . $row . '>';
                $html .= '<td>' . (int) ($log['entity_id'] ?? 0) . '</td>';
                $html .= '<td>' . $e($log['event'] ?? '') . '</td>';
                $html .= '<td>' . $e($log['order_id'] ?? '') . '</td>';
                $html .= '<td style="font-size:11px">' . $e($log['payment_intent_id'] ?? '') . '</td>';
                $html .= '<td>' . $e($log['created_at'] ?? '') . '</td>';
                $html .= '<td style="word-break:break-all;font-size:11px">' . $e($log['payload'] ?? '') . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
        }

        $html .= '</div>';
        return $html;
    }
}

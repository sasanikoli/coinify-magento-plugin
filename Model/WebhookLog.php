<?php
namespace Coinify\Payment\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * ORM model for the coinify_webhook_log table.
 * Every inbound webhook is persisted here — event type, full payload JSON,
 * request headers, and the associated order/intent IDs — for auditability.
 */
class WebhookLog extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(ResourceModel\WebhookLog::class);
    }
}

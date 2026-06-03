<?php
namespace Coinify\Payment\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class WebhookLog extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('coinify_webhook_log', 'entity_id');
    }
}

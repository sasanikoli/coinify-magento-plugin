<?php
namespace Coinify\Payment\Model\ResourceModel\WebhookLog;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(\Coinify\Payment\Model\WebhookLog::class, \Coinify\Payment\Model\ResourceModel\WebhookLog::class);
    }
}

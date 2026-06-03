<?php
namespace Coinify\Payment\Model;

use Magento\Framework\Model\AbstractModel;

class WebhookLog extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(ResourceModel\WebhookLog::class);
    }
}

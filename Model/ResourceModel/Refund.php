<?php
namespace Coinify\Payment\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Refund extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('coinify_payment_refund', 'entity_id');
    }
}

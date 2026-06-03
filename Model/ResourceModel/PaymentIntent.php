<?php
namespace Coinify\Payment\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class PaymentIntent extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('coinify_payment_intent', 'entity_id');
    }
}

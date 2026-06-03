<?php
namespace Coinify\Payment\Model\ResourceModel\PaymentIntent;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(\Coinify\Payment\Model\PaymentIntent::class, \Coinify\Payment\Model\ResourceModel\PaymentIntent::class);
    }
}

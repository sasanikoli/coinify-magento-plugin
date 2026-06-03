<?php
namespace Coinify\Payment\Model\ResourceModel\Refund;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(\Coinify\Payment\Model\Refund::class, \Coinify\Payment\Model\ResourceModel\Refund::class);
    }
}

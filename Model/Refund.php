<?php
namespace Coinify\Payment\Model;

use Magento\Framework\Model\AbstractModel;

class Refund extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(ResourceModel\Refund::class);
    }
}

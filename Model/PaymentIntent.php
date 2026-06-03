<?php
namespace Coinify\Payment\Model;

use Magento\Framework\Model\AbstractModel;

class PaymentIntent extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(ResourceModel\PaymentIntent::class);
    }
}

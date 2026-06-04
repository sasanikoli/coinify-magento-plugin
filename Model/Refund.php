<?php
namespace Coinify\Payment\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * ORM model for the coinify_payment_refund table.
 * Tracks each merchant-initiated refund: amount, currency, Coinify refund ID,
 * and state (initiated → completed via webhook).
 */
class Refund extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(ResourceModel\Refund::class);
    }
}

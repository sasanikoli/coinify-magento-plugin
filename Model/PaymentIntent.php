<?php
namespace Coinify\Payment\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * ORM model for the coinify_payment_intent table.
 * Stores one record per payment attempt: intent ID, state, payment window URL,
 * and the raw API response for debugging.
 */
class PaymentIntent extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(ResourceModel\PaymentIntent::class);
    }
}

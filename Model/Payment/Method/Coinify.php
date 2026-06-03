<?php
namespace Coinify\Payment\Model\Payment\Method;

use Magento\Payment\Model\Method\AbstractMethod;

class Coinify extends AbstractMethod
{
    protected $_code = 'coinify';
    protected $_isInitializeNeeded = true;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canRefund = true;

    public function isAvailable($quote = null)
    {
        if (!$this->getConfigData('active')) {
            return false;
        }

        return parent::isAvailable($quote);
    }
}

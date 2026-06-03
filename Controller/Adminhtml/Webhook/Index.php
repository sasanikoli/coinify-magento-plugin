<?php
namespace Coinify\Payment\Controller\Adminhtml\Webhook;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\PageFactory;

class Index extends Action
{
    const ADMIN_RESOURCE = 'Coinify_Payment::webhook_logs';

    private PageFactory $resultPageFactory;

    public function __construct(Context $context, PageFactory $resultPageFactory)
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Coinify_Payment::webhook_logs');
        $resultPage->getConfig()->getTitle()->prepend(__('Coinify Webhook Logs'));
        return $resultPage;
    }
}

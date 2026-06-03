<?php
namespace Coinify\Payment\Block\Adminhtml\Webhook;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Coinify\Payment\Model\ResourceModel\WebhookLog\CollectionFactory;

class Logs extends Template
{
    private CollectionFactory $collectionFactory;

    public function __construct(Context $context, CollectionFactory $collectionFactory, array $data = [])
    {
        parent::__construct($context, $data);
        $this->collectionFactory = $collectionFactory;
    }

    public function getLogs(): array
    {
        $collection = $this->collectionFactory->create();
        $collection->setOrder('created_at', 'DESC');
        $collection->setPageSize(100);
        $items = [];
        foreach ($collection->getItems() as $item) {
            $items[] = $item->getData();
        }
        return $items;
    }
}

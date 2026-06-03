<?php
namespace Coinify\Payment\Ui\Component\DataProvider;

use Magento\Ui\DataProvider\AbstractDataProvider;
use Coinify\Payment\Model\ResourceModel\WebhookLog\CollectionFactory;

class WebhookLog extends AbstractDataProvider
{
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
        $this->collection->setOrder('created_at', 'DESC');
    }

    public function getData()
    {
        $collection = $this->getCollection();
        return [
            'totalRecords' => $collection->getSize(),
            'items' => array_values($collection->toArray()['items'] ?? []),
        ];
    }
}

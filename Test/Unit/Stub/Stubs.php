<?php
/**
 * Minimal stubs for Magento framework classes and interfaces.
 *
 * These exist solely so PHPUnit can resolve type declarations and create mocks
 * without a full Magento installation. None of these stubs contain real logic —
 * every method returns null/void. Tests replace all behaviour with PHPUnit mocks.
 *
 * Organised in namespace blocks, one per namespace.
 */

// ---------------------------------------------------------------------------
// Magento\Framework\App\Config
// ---------------------------------------------------------------------------
namespace Magento\Framework\App\Config {
    interface ScopeConfigInterface
    {
        public function getValue($path, $scopeType = null, $scopeCode = null);
        public function isSetFlag($path, $scopeType = null, $scopeCode = null);
    }
}

// ---------------------------------------------------------------------------
// Magento\Framework\Encryption
// ---------------------------------------------------------------------------
namespace Magento\Framework\Encryption {
    interface EncryptorInterface
    {
        public function encrypt($data);
        public function decrypt($data);
        public function hash($data, $version = false);
        public function isValidHash($password, $hash);
        public function validateKey($key);
        public function exportKeys();
    }
}

// ---------------------------------------------------------------------------
// Psr\Log
// ---------------------------------------------------------------------------
namespace Psr\Log {
    interface LoggerInterface
    {
        public function emergency($message, array $context = []);
        public function alert($message, array $context = []);
        public function critical($message, array $context = []);
        public function error($message, array $context = []);
        public function warning($message, array $context = []);
        public function notice($message, array $context = []);
        public function info($message, array $context = []);
        public function debug($message, array $context = []);
        public function log($level, $message, array $context = []);
    }
}

// ---------------------------------------------------------------------------
// Magento\Framework\Model
// ---------------------------------------------------------------------------
namespace Magento\Framework\Model {
    abstract class AbstractModel
    {
        protected $_data = [];

        public function getId() { return $this->_data['entity_id'] ?? null; }
        public function setData($key, $value = null) {
            if (is_array($key)) { $this->_data = array_merge($this->_data, $key); }
            else { $this->_data[$key] = $value; }
            return $this;
        }
        public function getData($key = '', $index = null) {
            if ($key === '') { return $this->_data; }
            return $this->_data[$key] ?? null;
        }
        public function save() { return $this; }
        public function load($id) { return $this; }
        public function delete() { return $this; }
        protected function _construct() {}
    }
}

// ---------------------------------------------------------------------------
// Magento\Store\Model
// ---------------------------------------------------------------------------
namespace Magento\Store\Model {
    class ScopeInterface
    {
        const SCOPE_STORE    = 'store';
        const SCOPE_STORES   = 'stores';
        const SCOPE_WEBSITE  = 'website';
        const SCOPE_WEBSITES = 'websites';
    }
}

// ---------------------------------------------------------------------------
// Magento\Framework\HTTP\Client
// ---------------------------------------------------------------------------
namespace Magento\Framework\HTTP\Client {
    class Curl
    {
        public function addHeader($name, $value) {}
        public function get($url) {}
        public function post($url, $body) {}
        public function getBody() { return ''; }
        public function setOption($name, $value) {}
        public function getStatus() { return 200; }
    }
}

// ---------------------------------------------------------------------------
// Magento\Framework\Event
// ---------------------------------------------------------------------------
namespace Magento\Framework\Event {
    interface ObserverInterface
    {
        public function execute(Observer $observer);
    }

    class Observer {}
}

// ---------------------------------------------------------------------------
// Magento\Framework\Message
// ---------------------------------------------------------------------------
namespace Magento\Framework\Message {
    interface ManagerInterface
    {
        public function addWarningMessage($message);
        public function addErrorMessage($message);
        public function addSuccessMessage($message);
        public function addNoticeMessage($message);
        public function addUniqueMessages(array $messages);
        public function getMessages($clear = false, $group = null);
        public function addMessage(\Magento\Framework\Message\MessageInterface $message);
        public function addMessages(array $messages);
        public function addComplexWarningMessage($identifier, array $data = []);
        public function addComplexErrorMessage($identifier, array $data = []);
        public function addComplexSuccessMessage($identifier, array $data = []);
        public function addComplexNoticeMessage($identifier, array $data = []);
        public function getDefaultGroup();
        public function createMessage($type, $identifier = null);
    }

    interface MessageInterface {}
}

// ---------------------------------------------------------------------------
// Magento\Framework\App
// ---------------------------------------------------------------------------
namespace Magento\Framework\App {
    interface RequestInterface
    {
        public function getModuleName();
        public function setModuleName($name);
        public function getActionName();
        public function setActionName($name);
        public function getParam($key, $defaultValue = null);
        public function getParams();
        public function setParams(array $params);
        public function getCookie($name, $default);
        public function isSecure();
        public function isAjax();
        public function getContent();
        public function getHeader($name);
        public function getHeaders();
    }

    interface CsrfAwareActionInterface
    {
        public function createCsrfValidationException(RequestInterface $request): ?\Magento\Framework\App\Request\InvalidRequestException;
        public function validateForCsrf(RequestInterface $request): ?bool;
    }
}

// ---------------------------------------------------------------------------
// Magento\Framework\App\Request
// ---------------------------------------------------------------------------
namespace Magento\Framework\App\Request {
    class InvalidRequestException extends \Exception {}
}

// ---------------------------------------------------------------------------
// Magento\Framework\App\Action
// ---------------------------------------------------------------------------
namespace Magento\Framework\App\Action {
    abstract class Action
    {
        /** @var \Magento\Framework\Controller\ResultFactory */
        protected $resultFactory;

        /** @var \Magento\Framework\App\RequestInterface */
        protected $_request;

        public function __construct(Context $context) {}

        public function getRequest(): \Magento\Framework\App\RequestInterface
        {
            return $this->_request;
        }

        abstract public function execute();

        public function dispatch(\Magento\Framework\App\RequestInterface $request) {}
    }

    class Context
    {
        public function getResultFactory(): \Magento\Framework\Controller\ResultFactory { return new \Magento\Framework\Controller\ResultFactory(); }
        public function getRequest(): \Magento\Framework\App\RequestInterface { return new class implements \Magento\Framework\App\RequestInterface {
            public function getModuleName() {}
            public function setModuleName($name) {}
            public function getActionName() {}
            public function setActionName($name) {}
            public function getParam($key, $defaultValue = null) {}
            public function getParams() { return []; }
            public function setParams(array $params) {}
            public function getCookie($name, $default) {}
            public function isSecure() { return false; }
            public function isAjax() { return false; }
            public function getContent() { return ''; }
            public function getHeader($name) { return ''; }
        }; }
    }
}

// ---------------------------------------------------------------------------
// Magento\Backend\App\Action
// ---------------------------------------------------------------------------
namespace Magento\Backend\App {
    abstract class Action
    {
        protected $resultFactory;
        protected $_request;
        protected $messageManager;
        protected $_redirect;

        public function __construct(\Magento\Backend\App\Action\Context $context) {}

        public function getRequest(): \Magento\Framework\App\RequestInterface
        {
            return $this->_request;
        }

        abstract public function execute();

        protected function _redirect($path, array $arguments = []) {}
    }
}

namespace Magento\Backend\App\Action {
    class Context
    {
        public function getResultFactory() { return null; }
        public function getRequest() { return null; }
        public function getMessageManager() { return null; }
        public function getRedirect() { return null; }
        public function getAuth() { return null; }
        public function getBackendUrl() { return null; }
    }
}

// ---------------------------------------------------------------------------
// Magento\Framework\Controller
// ---------------------------------------------------------------------------
namespace Magento\Framework\Controller {
    class ResultFactory
    {
        const TYPE_RAW      = 'raw';
        const TYPE_REDIRECT = 'redirect';
        const TYPE_JSON     = 'json';

        public function create($type) { return null; }
    }
}

namespace Magento\Framework\Controller\Result {
    class Raw
    {
        public function setHttpResponseCode($code) { return $this; }
        public function setContents($contents) { return $this; }
        public function setHeader($name, $value, $replace = false) { return $this; }
        public function renderResult(\Magento\Framework\App\Response\HttpInterface $response) { return $this; }
    }

    class Redirect
    {
        public function setUrl($url) { return $this; }
        public function setPath($path, array $params = []) { return $this; }
        public function setRefererUrl() { return $this; }
        public function renderResult(\Magento\Framework\App\Response\HttpInterface $response) { return $this; }
    }
}

// ---------------------------------------------------------------------------
// Magento\Framework\UrlInterface
// ---------------------------------------------------------------------------
namespace Magento\Framework {
    interface UrlInterface
    {
        public function getUrl($routePath = null, $routeParams = null);
        public function getBaseUrl($params = []);
        public function getCurrentUrl();
        public function getRedirectUrl($url);
        public function addSessionParam();
        public function addQueryParams(array $data);
        public function setQueryParam($key, $data);
        public function getDirectUrl($url, $params = []);
        public function escape($string);
        public function getRouteUrl($routePath = null, $routeParams = null);
        public function isOwnOriginUrl();
        public function getRouteToUrl($request);
    }
}

// ---------------------------------------------------------------------------
// Magento\Framework\DB
// ---------------------------------------------------------------------------
namespace Magento\Framework\DB {
    class Transaction
    {
        public function addObject($object) { return $this; }
        public function save() {}
        public function delete() {}
    }

    class TransactionFactory
    {
        public function create() { return new Transaction(); }
    }
}

// ---------------------------------------------------------------------------
// Magento\Sales\Model
// ---------------------------------------------------------------------------
namespace Magento\Sales\Model {
    class Order
    {
        const STATE_PROCESSING = 'processing';
        const STATE_CANCELED   = 'canceled';
        const STATE_COMPLETE   = 'complete';
        const STATE_CLOSED     = 'closed';
        const STATE_NEW        = 'new';

        public function getId() { return null; }
        public function getIncrementId() { return null; }
        public function getGrandTotal() { return 0.0; }
        public function getOrderCurrencyCode() { return 'EUR'; }
        public function getBaseCurrencyCode() { return 'EUR'; }
        public function getCustomerId() { return null; }
        public function getCustomerEmail() { return ''; }
        public function getBaseTotalInvoiced() { return null; }
        public function getTotalPaid() { return null; }
        public function getTotalRefunded() { return null; }
        public function getState() { return null; }
        public function getStatus() { return null; }
        public function canInvoice() { return false; }
        public function canCreditmemo() { return false; }
        public function setState($state) { return $this; }
        public function setStatus($status) { return $this; }
        public function addStatusHistoryComment($comment, $status = false) { return $this; }
        public function setCustomerNote($note) { return $this; }
        public function setCustomerNoteNotify($notify) { return $this; }
        public function getInvoiceCollection() { return new class { public function getFirstItem() { return null; } }; }
        public function save() {}
        public function load($id) { return $this; }
        public function loadByIncrementId($id) { return $this; }
    }

    class OrderFactory
    {
        public function create() { return new Order(); }
    }
}

namespace Magento\Sales\Model\Order {
    class Invoice
    {
        const CAPTURE_OFFLINE = 'offline';
        const STATE_PAID      = 6;

        public function getId() { return null; }
        public function getShippingAmount() { return 0.0; }
        public function getAllItems() { return []; }
        public function getOrderItemId() { return null; }
        public function setRequestedCaptureCase($case) { return $this; }
        public function register() { return $this; }
        public function setState($state) { return $this; }
        public function setTransactionId($id) { return $this; }
        public function addComment($comment, $notify = false, $visibleOnFront = false) { return $this; }
        public function getOrder() { return new \Magento\Sales\Model\Order(); }
        public function save() {}
    }

    class Creditmemo
    {
        public function setOfflineRequested($flag) { return $this; }
    }

    class CreditmemoFactory
    {
        public function createByInvoice($invoice, array $data = []) { return new Creditmemo(); }
    }
}

// ---------------------------------------------------------------------------
// Magento\Sales\Model\Service
// ---------------------------------------------------------------------------
namespace Magento\Sales\Model\Service {
    class InvoiceService
    {
        public function prepareInvoice($order, $qty = []) { return new \Magento\Sales\Model\Order\Invoice(); }
    }

    class CreditmemoService
    {
        public function refund($creditmemo, $offline = false) {}
    }
}

// ---------------------------------------------------------------------------
// Magento\Sales\Model\Order\Email\Sender
// ---------------------------------------------------------------------------
namespace Magento\Sales\Model\Order\Email\Sender {
    class InvoiceSender
    {
        public function send($invoice, $forceSyncMode = false) { return false; }
    }
}

// ---------------------------------------------------------------------------
// Magento\Checkout\Model
// ---------------------------------------------------------------------------
namespace Magento\Checkout\Model {
    class Session
    {
        public function getLastRealOrder() { return null; }
        public function getLastRealOrderId() { return null; }
    }
}

// ---------------------------------------------------------------------------
// Magento\Framework\Data\Form\FormKey
// ---------------------------------------------------------------------------
namespace Magento\Framework\Data\Form\FormKey {
    class Validator
    {
        public function validate($request) { return true; }
    }
}

// ---------------------------------------------------------------------------
// Coinify-generated factory/collection stubs
// (These classes are auto-generated by Magento's DI system and do not exist
//  as source files in the plugin repository.)
// ---------------------------------------------------------------------------
namespace Coinify\Payment\Model {
    class PaymentIntentFactory
    {
        public function create(array $data = []) { return new \Coinify\Payment\Model\PaymentIntent(); }
    }

    class RefundFactory
    {
        public function create(array $data = []) { return new \Coinify\Payment\Model\Refund(); }
    }

    class WebhookLogFactory
    {
        public function create(array $data = []) { return new \Coinify\Payment\Model\WebhookLog(); }
    }
}

namespace Coinify\Payment\Model\ResourceModel\PaymentIntent {
    class CollectionFactory
    {
        public function create() { return new Collection(); }
    }

    class Collection
    {
        public function addFieldToFilter($field, $condition) { return $this; }
        public function setPageSize($size) { return $this; }
        public function setOrder($field, $dir = 'DESC') { return $this; }
        public function getFirstItem() { return new \Coinify\Payment\Model\PaymentIntent(); }
        public function getItems() { return []; }
    }
}

namespace Coinify\Payment\Model\ResourceModel\Refund {
    class CollectionFactory
    {
        public function create() { return new Collection(); }
    }

    class Collection
    {
        public function addFieldToFilter($field, $condition) { return $this; }
        public function setPageSize($size) { return $this; }
        public function setOrder($field, $dir = 'DESC') { return $this; }
        public function getFirstItem() { return new \Coinify\Payment\Model\Refund(); }
        public function getItems() { return []; }
    }
}

namespace Coinify\Payment\Model\ResourceModel\WebhookLog {
    class CollectionFactory
    {
        public function create() { return new Collection(); }
    }

    class Collection
    {
        public function addFieldToFilter($field, $condition) { return $this; }
        public function setPageSize($size) { return $this; }
        public function setOrder($field, $dir = 'DESC') { return $this; }
        public function getFirstItem() { return new \Coinify\Payment\Model\WebhookLog(); }
        public function getItems() { return []; }
    }
}

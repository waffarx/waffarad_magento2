<?php


namespace WaffarAD\Magento2\Observer;

use Exception;
use WaffarAD\Magento2\Service\CurlService;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\Cookie\FailureToSendException;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Catalog\Model\CategoryFactory;
use \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use \Magento\Framework\App\Helper\Context;
use \Magento\Sales\Model\ResourceModel\Order\Tax\Item;

class SendOrder implements ObserverInterface
{

    /**
     * @var RemoteAddress
     */
    private $remoteAddress;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var CurlService
     */
    private $curlService;
    /**
     * @var CookieManagerInterface
     */
    private $cookieManager;
    /**
     * @var UrlInterface
     */
    private $url;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var SessionManagerInterface
     */
    private $sessionManager;
    /**
     * @var CookieMetadataFactory
     */
    private $cookieMetadataFactory;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var CategoryInterface
     */
    private $CategoryManager;

    /**
     * @var CategoryFactory
     */
    private $CategoryFactory;

    /**
     * @var CollectionFactory
     */
    private $CollectionFactory;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Tax\Item
     */
    protected $taxItem;
    protected $_orderCollectionFactory;
    protected $orders;

    const XML_MODULE_ENABLED = 'waffarad/general/enabled';
    const API_URL = 'https://conversion.waffarx.com/magento/addOrder';
    const CONTENT_TYPE = 'application/json';

    /**
     * SendOrder constructor.
     * @param RemoteAddress $remoteAddress
     * @param StoreManagerInterface $storeManager
     * @param CurlService $curlService
     * @param CookieManagerInterface $cookieManager
     * @param UrlInterface $url
     * @param LoggerInterface $logger
     * @param SessionManagerInterface $sessionManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param Curl $curl
     * @param CategoryInterface $CategoryManager
     * @param CategoryFactory $CategoryFactory
     * @param CollectionFactory $CollectionFactory
     */
    public function __construct(
        RemoteAddress $remoteAddress,
        StoreManagerInterface $storeManager,
        CurlService $curlService,
        CookieManagerInterface $cookieManager,
        UrlInterface $url,
        LoggerInterface $logger,
        SessionManagerInterface $sessionManager,
        CookieMetadataFactory $cookieMetadataFactory,
        ScopeConfigInterface $scopeConfig,
        Curl $curl,
        CategoryInterface $CategoryManager,
        CategoryFactory $CategoryFactory,
        CollectionFactory $CollectionFactory,
        Item $taxItem,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Magento\Customer\Model\Session $session
    ) {
        $this->remoteAddress = $remoteAddress;
        $this->storeManager = $storeManager;
        $this->curlService = $curlService;
        $this->cookieManager = $cookieManager;
        $this->url = $url;
        $this->logger = $logger;
        $this->sessionManager = $sessionManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->scopeConfig = $scopeConfig;
        $this->curl = $curl;
        $this->CategoryManager = $CategoryManager;
        $this->CategoryFactory = $CategoryFactory;
        $this->CollectionFactory = $CollectionFactory;
        $this->taxItem = $taxItem;
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->_session = $session;
    }
    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        if (!$this->scopeConfig->getValue(self::XML_MODULE_ENABLED)) {
            return $this;
        }
        /**
         * @var $order OrderInterface
         */
        $afId = $this->cookieManager->getCookie('af_id');
        $subId = $this->cookieManager->getCookie('subid');
        $subId1 = $this->cookieManager->getCookie('subid1');
        $subId2 = $this->cookieManager->getCookie('subid2');
        $subId3 = $this->cookieManager->getCookie('subid3');
        $subId4 = $this->cookieManager->getCookie('subid4');
        $subId5 = $this->cookieManager->getCookie('subid5');
        $mrid = $this->cookieManager->getCookie('mrid');
        $pid = $this->cookieManager->getCookie('pid');
        $afsrc = $this->cookieManager->getCookie('afsrc');
        $afftoken = $this->cookieManager->getCookie('afftoken');

        $customerId = $this->_session->getCustomer()->getId();
        $customer_email = $this->_session->getCustomer()->getEmail();
        $orders = $this->_orderCollectionFactory->create()->addFieldToSelect('*')->addFieldToFilter('customer_email', $customer_email);

        if ($afId && $afsrc && $afsrc === "waffarad") {
            try {
                $order = $observer->getOrder();
                $baseUrl = $this->storeManager->getStore()->getBaseUrl();
                $current_url = $this->url->getUrl('checkout/onepage/success');
                /**
                 * 
                 * DEBUGGING CODE 
                 */
                $this->getCurlClient()->addHeader('Content-Type', self::CONTENT_TYPE);


                //GETTING ORDER PROPS
                $affiliateData = array(
                    "order_id" => $order->getIncrementId(),
                    "order_CouponeCode" => $order->getCouponCode(),
                    "ip" => $this->remoteAddress->getRemoteAddress(),
                    //User's Data
                    "OrdersCount" => count($orders),
                    //Shipping Data
                    "shippingAddress" => $order->getShippingAddress()->getData(),
                    //Billing Data
                    "billingAddress" => $order->getBillingAddress()->getData(),
                    //TaxInfo
                    "Taxes" => $this->taxItem->getTaxItemsByOrderId($order->getId()),
                    //ORDER DATA
                    "CDate" => $order->getCreatedAt(),
                    "order_currency" => $order->getOrderCurrencyCode(),
                    "order_total" => $order->getGrandTotal(),
                    "Subtotal" => $order->getSubtotal(),
                    "SubtotalCanceled" => $order->getSubtotalCanceled(),
                    "SubtotalInclTax" => $order->getSubtotalInclTax(),
                    "SubtotalInvoiced" => $order->getSubtotalInvoiced(),
                    "SubtotalRefunded" => $order->getSubtotalRefunded(),
                    "paymentMethodCode" => $order->getPayment()->getMethodInstance()->getCode(),
                    "paymentMethodTitle" => $order->getPayment()->getMethodInstance()->getTitle(),
                    //DISCOUNT SECTION 
                    "DiscountAmount" => $order->getDiscountAmount(),
                    "DiscountCanceled" => $order->getDiscountCanceled(),
                    "DiscountDescription" => $order->getDiscountDescription(),
                    "DiscountInvoiced" => $order->getDiscountInvoiced(),
                    "CouponCode" => $order->getCouponCode(),
                    "DiscountTaxCompensationInvoiced" => $order->getDiscountTaxCompensationInvoiced(),
                    "DiscountTaxCompensationRefunded" => $order->getDiscountTaxCompensationRefunded(),
                    //TAXES SECTION 
                    "TaxAmount" => $order->getTaxAmount(),
                    "BaseTaxAmount" => $order->getBaseTaxAmount(),
                    "BaseTaxCanceled" => $order->getBaseTaxCanceled(),
                    "BaseTaxInvoiced" => $order->getBaseTaxInvoiced(),
                    "BaseTaxRefunded" => $order->getBaseTaxRefunded(),
                    "DiscountTaxCompensationAmount" => $order->getDiscountTaxCompensationAmount(),
                    //SHIPPING SECTION 
                    "ShippingAmount" => (float)$order->getShippingAmount(),
                    "BaseShippingCanceled" => (float)$order->getBaseShippingCanceled(),
                    "BaseShippingDiscountAmount" => (float)$order->getBaseShippingDiscountAmount(),
                    "BaseShippingDiscountTaxCompensationAmnt" => (float)$order->getBaseShippingDiscountTaxCompensationAmnt(),
                    "BaseShippingInclTax" => (float)$order->getBaseShippingInclTax(),
                    "BaseShippingRefunded" => (float)$order->getBaseShippingRefunded(),
                    "ShippingDiscountAmount" => (float)$order->getShippingDiscountAmount(),
                    "ShippingDiscountTaxCompensationAmount" => (float)$order->getShippingDiscountTaxCompensationAmount(),
                    //Aff Data
                    "af_id" => $afId,
                    "subid" => $subId,
                    "subid1" => $subId1,
                    "subid2" => $subId2,
                    "subid3" => $subId3,
                    "subid4" => $subId4,
                    "subid5" => $subId5,
                    "mrid" => $mrid,
                    "pid" => $pid,
                    "afsrc" => $afsrc,
                    "afftoken" => $afftoken,
                    "base_url" => base64_encode($baseUrl),
                    "current_page_url" => base64_encode($current_url),
                    "script_name" => "magento",
                );
                $itemCategories = [];
                $Products = array();

                foreach ($order->getAllVisibleItems() as $item) {
                    $cats = array();
                    $categories = $this->getCategoryCollection()->addAttributeToFilter('entity_id', $item->getProduct()->getCategoryIds());
                    foreach ($categories as $cat) {
                        //$cats['categories'][] = $cat->getName();
                        array_push($cats, $cat->getName());
                    }
                    $Products[] = [
                        'name' => $item->getName(),
                        'sku' => $item->getSku(),
                        'item_id' => $item->getItemId(),
                        'getParentItemId' => $item->getParentItemId(),
                        'qty' => $item->getQtyOrdered(),
                        'product_type' => $item->getProductType(),
                        'weight' => $item->getWeight(),
                        'price' => $item->getPrice(),
                        'finalPrice' => $item->getFinalPrice(),
                        'specialPrice' => $item->getSpecialPrice(),
                        'discount' => $item->getDiscountAmount(),
                        'Categories' => $cats
                    ];

                    // $categories = $this->getCategoryCollection()->addAttributeToFilter('entity_id', $item->getProduct()->getCategoryIds());
                    // foreach( $categories as $cat)
                    // {
                    //     $Products['categories'][] = $cat->getName();
                    // }

                    $affiliateData['product_ids'][] = $item->getSku();
                    $discountValue = $item->getOriginalPrice() - $item->getPrice();
                    $affiliateData['discount'][] = $item->getSku() . ":" . $discountValue;
                }
                $affiliateData['Products'] = $Products;
                $this->getCurlClient()->post(self::API_URL, json_encode($affiliateData));

                //CAUSING ERROR ON LOCALHOST
                //$this->curlService->sendAddOrder($affiliateData);
                $metadata = $this->cookieMetadataFactory->createCookieMetadata()
                    ->setPath($this->sessionManager->getCookiePath());

                $this->cookieManager->deleteCookie('af_id', $metadata);
                $this->cookieManager->deleteCookie('subid', $metadata);
                $this->cookieManager->deleteCookie('subid1', $metadata);
                $this->cookieManager->deleteCookie('subid2', $metadata);
                $this->cookieManager->deleteCookie('subid3', $metadata);
                $this->cookieManager->deleteCookie('subid4', $metadata);
                $this->cookieManager->deleteCookie('subid5', $metadata);
                $this->cookieManager->deleteCookie('mrid', $metadata);
                $this->cookieManager->deleteCookie('pid', $metadata);
                $this->cookieManager->deleteCookie('afsrc', $metadata);
                $this->cookieManager->deleteCookie('afftoken', $metadata);
            } catch (Exception $e) {
                $this->logger->error($e->getMessage(), ['exception' => $e]);
            }
        }
    }
    /**
     * @return Curl
     */
    protected function getCurlClient(): Curl
    {
        return $this->curl;
    }

    /**
     * Get category collection
     *
     * @param bool $isActive
     * @param bool|int $level
     * @param bool|string $sortBy
     * @param bool|int $pageSize
     * @return \Magento\Catalog\Model\ResourceModel\Category\Collection or array
     */
    public function getCategoryCollection($isActive = true, $level = false, $sortBy = false, $pageSize = false)
    {
        $collection = $this->CollectionFactory->create();
        $collection->addAttributeToSelect('*');

        // select only active categories
        if ($isActive) {
            $collection->addIsActiveFilter();
        }

        // select categories of certain level
        if ($level) {
            $collection->addLevelFilter($level);
        }

        // sort categories by some value
        if ($sortBy) {
            $collection->addOrderField($sortBy);
        }

        // select certain number of categories
        if ($pageSize) {
            $collection->setPageSize($pageSize);
        }

        return $collection;
    }
}

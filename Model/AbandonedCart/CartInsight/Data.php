<?php

namespace Dotdigitalgroup\Email\Model\AbandonedCart\CartInsight;

class Data
{
    /**
     * Basket prefix for accessing stored quotes
     */
    const CONNECTOR_BASKET_PATH = 'connector/email/getbasket';

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var \Dotdigitalgroup\Email\Helper\Data
     */
    private $helper;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    private $dateTime;

    /**
     * Data constructor.
     *
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Dotdigitalgroup\Email\Helper\Data $helper
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $dateTime
     */
    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Dotdigitalgroup\Email\Helper\Data $helper,
        \Magento\Framework\Stdlib\DateTime\DateTime $dateTime
    ) {
        $this->storeManager = $storeManager;
        $this->productRepository = $productRepository;
        $this->scopeConfig = $scopeConfig;
        $this->helper = $helper;
        $this->dateTime = $dateTime;
    }

    /**
     * @param \Magento\Quote\Model\Quote $quote
     * @param int $storeId
     *
     * @return void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function send($quote, $storeId)
    {
        $store = $this->storeManager->getStore($storeId);
        $client = $this->helper->getWebsiteApiClient($store->getWebsiteId());

        $payload = $this->getPayload($quote, $store);
        $client->postAbandonedCartCartInsight($payload);
    }

    /**
     * Get payload data for API push.
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @param \Magento\Store\Api\Data\StoreInterface $store
     *
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getPayload($quote, $store)
    {
        $data = [
            'key' => $quote->getId(),
            'contactIdentifier' => $quote->getCustomerEmail(),
            'json' => [
                'cartId' => $quote->getId(),
                'cartUrl' => $this->getBasketUrl($quote->getId(), $store),
                'createdDate' => $this->dateTime->date(\Zend_Date::ISO_8601, $quote->getCreatedAt()),
                'modifiedDate' => $this->dateTime->date(\Zend_Date::ISO_8601, $quote->getUpdatedAt()),
                'currency' => $quote->getQuoteCurrencyCode(),
                'subTotal' => (float) number_format($quote->getSubtotal(), 2),
                'taxAmount' => (float) number_format($quote->getShippingAddress()->getTaxAmount(), 2),
                'grandTotal' => (float) number_format($quote->getGrandTotal(), 2)
            ]
        ];

        $discountTotal = 0;
        $lineItems = [];

        foreach ($quote->getAllVisibleItems() as $item) {

            $discountTotal += $item->getDiscountAmount();

            $lineItems[] = [
                'sku' => $item->getSku(),
                'imageUrl' => $this->getProductImageUrl($item, $store),
                'productUrl' => $item->getProduct()->getProductUrl(),
                'name' => $item->getName(),
                'unitPrice' => (float) number_format($item->getPrice(), 2),
                'quantity' => $item->getQty()
            ];
        }

        $data['json']['discountAmount'] = (float) $discountTotal;
        $data['json']['lineItems'] = $lineItems;

        return $data;
    }

    /**
     * Get basket URL for use in abandoned cart block in email templates.
     *
     * @param int $quoteId
     * @param \Magento\Store\Api\Data\StoreInterface $store
     *
     * @return string
     */
    private function getBasketUrl($quoteId, $store)
    {
        return $store->getUrl(
            self::CONNECTOR_BASKET_PATH,
            ['quote_id' => $quoteId]
        );
    }

    /**
     * Get product image URL. We respect the "Configurable Product Image" setting in determining which image to retrieve.
     *
     * @param \Magento\Quote\Model\Quote\Item $item
     * @param \Magento\Store\Api\Data\StoreInterface $store
     *
     * @return string
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getProductImageUrl($item, $store)
    {
        $url = "";
        $base = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA, true) . 'catalog/product';

        $configurableProductImage = $this->scopeConfig->getValue(
            'checkout/cart/configurable_product_image',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store->getId()
        );

        if ($configurableProductImage === "itself") {

            // Use item SKU to retrieve properties of configurable products
            $id = $item->getProduct()->getIdBySku($item->getSku());
            $product = $this->productRepository->getById($id);

            if ($product->getThumbnail() !== "no_selection") {
                return $base . $product->getThumbnail();
            }
        }

        // Parent thumbnail
        if ($item->getProduct()->getThumbnail() !== "no_selection") {
            $url = $base . $item->getProduct()->getThumbnail();
        }

        return $url;
    }
}
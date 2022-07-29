<?php
/**
 * Copyright © Landofcoder.com All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Ves\Productlist\Model\Api;

use Ves\Productlist\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductSearchResultsInterfaceFactory;
use Ves\Productlist\Model\ProductFactory as ProductlistProductFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Api\ExtensibleDataObjectConverter;
use Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Reflection\DataObjectProcessor;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\Product;

class ProductRepository implements ProductRepositoryInterface
{

    protected $dataObjectProcessor;

    protected $extensionAttributesJoinProcessor;

    protected $productFactory;

    private $collectionProcessor;

    protected $extensibleDataObjectConverter;
    private $storeManager;

    protected $dataProductFactory;

    protected $searchResultsFactory;

    protected $dataObjectHelper;

    /**
     * @var int
     */
    private $cacheLimit = 0;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    private $serializer;

    /**
     * @var Product[]
     */
    protected $instancesById = [];


    /**
     * @param ProductlistProductFactory $productFactory
     * @param ProductInterfaceFactory $dataProductFactory
     * @param ProductSearchResultsInterfaceFactory $searchResultsFactory
     * @param DataObjectHelper $dataObjectHelper
     * @param DataObjectProcessor $dataObjectProcessor
     * @param StoreManagerInterface $storeManager
     * @param CollectionProcessorInterface $collectionProcessor
     * @param JoinProcessorInterface $extensionAttributesJoinProcessor
     * @param ExtensibleDataObjectConverter $extensibleDataObjectConverter
     * @param \Magento\Framework\Serialize\Serializer\Json|null $serializer
     * @param int $cacheLimit [optional]
     */
    public function __construct(
        ProductlistProductFactory $productFactory,
        ProductInterfaceFactory $dataProductFactory,
        ProductSearchResultsInterfaceFactory $searchResultsFactory,
        DataObjectHelper $dataObjectHelper,
        DataObjectProcessor $dataObjectProcessor,
        StoreManagerInterface $storeManager,
        CollectionProcessorInterface $collectionProcessor,
        JoinProcessorInterface $extensionAttributesJoinProcessor,
        ExtensibleDataObjectConverter $extensibleDataObjectConverter,
        \Magento\Framework\Serialize\Serializer\Json $serializer = null,
        $cacheLimit = 1000
    ) {
        $this->productFactory = $productFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->dataObjectHelper = $dataObjectHelper;
        $this->dataProductFactory = $dataProductFactory;
        $this->dataObjectProcessor = $dataObjectProcessor;
        $this->storeManager = $storeManager;
        $this->collectionProcessor = $collectionProcessor;
        $this->extensionAttributesJoinProcessor = $extensionAttributesJoinProcessor;
        $this->extensibleDataObjectConverter = $extensibleDataObjectConverter;
        $this->serializer = $serializer ?: \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Magento\Framework\Serialize\Serializer\Json::class);
        $this->cacheLimit = (int)$cacheLimit;
    }
    /**
     * {@inheritdoc}
     */
    public function getNewarrivalProducts(
        \Magento\Framework\Api\SearchCriteriaInterface $criteria
    ) {
        return $this->getProductsBySource($criteria, "new_arrival");
    }

    /**
     * {@inheritdoc}
     */
    public function getLatestProducts(
        \Magento\Framework\Api\SearchCriteriaInterface $criteria
    ) {
        return $this->getProductsBySource($criteria, "latest");
    }

    /**
     * {@inheritdoc}
     */
    public function getSpecialProducts(
        \Magento\Framework\Api\SearchCriteriaInterface $criteria
    ) {
        return $this->getProductsBySource($criteria, "special");
    }

    /**
     * {@inheritdoc}
     */
    public function getMostViewedProducts(
        \Magento\Framework\Api\SearchCriteriaInterface $criteria
    ) {
        return $this->getProductsBySource($criteria, "most_popular");
    }

    /**
     * {@inheritdoc}
     */
    public function getBestsellerProducts(
        \Magento\Framework\Api\SearchCriteriaInterface $criteria
    ) {
        return $this->getProductsBySource($criteria, "best_seller");
    }

    /**
     * {@inheritdoc}
     */
    public function getTopratedProducts(
        \Magento\Framework\Api\SearchCriteriaInterface $criteria
    ) {
        return $this->getProductsBySource($criteria, "top_rated");
    }

    /**
     * {@inheritdoc}
     */
    public function getRandomProducts(
        \Magento\Framework\Api\SearchCriteriaInterface $criteria
    ) {
        return $this->getProductsBySource($criteria, "random");
    }

    /**
     * {@inheritdoc}
     */
    public function getFeaturedProducts(
        \Magento\Framework\Api\SearchCriteriaInterface $criteria
    ) {
        return $this->getProductsBySource($criteria, "featured");
    }

    /**
     * {@inheritdoc}
     */
    public function getDealsProducts(
        \Magento\Framework\Api\SearchCriteriaInterface $criteria
    ) {
        return $this->getProductsBySource($criteria, "deals");
    }

     /**
     * {@inheritdoc}
     */
    public function getProductsBySource(
        $source_key,
        \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
    ) {
        if (!isset($source_key) || empty($source_key)) {
            $source_key = "latest";
        }
        $product = $this->productFactory->create();
        $config = [];
        foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
            $filters = $filterGroup->getFilters();
            if(is_array($filters)) {
                foreach ($filters as $filter) {
                    if("categories" == $filter->getField()){
                        $config['categories'] = explode(",",$filter->getValue());
                        break;
                    }
                }
            }
        }
        $collection = null;
        switch ($source_key) {
            case 'latest':
            $collection = $product->getLatestProducts($config);
            break;
            case 'new_arrival':
            $collection = $product->getNewarrivalProducts($config);
            break;
            case 'special':
            $collection = $product->getSpecialProducts($config);
            break;
            case 'most_popular':
            $collection = $product->getMostViewedProducts($config);
            break;
            case 'best_seller':
            $collection = $product->getBestsellerProducts($config);
            break;
            case 'top_rated':
            $collection = $product->getTopratedProducts($config);
            break;
            case 'random':
            $collection = $product->getRandomProducts($config);
            break;
            case 'featured':
            $collection = $product->getFeaturedProducts($config);
            break;
            case 'deals':
            $collection = $product->getDealsProducts($config);
            break;
        }
        $this->extensionAttributesJoinProcessor->process($collection);

        $this->collectionProcessor->process($searchCriteria, $collection);

        $collection->load();

        $searchResult = $this->searchResultsFactory->create();
        $searchResult->setSearchCriteria($searchCriteria);
        $searchResult->setItems($collection->getItems());
        $searchResult->setTotalCount($collection->getSize());

        foreach ($collection->getItems() as $product) {
            $this->cacheProduct(
                $this->getCacheKey(
                    [
                        false,
                        $product->getStoreId()
                    ]
                ),
                $product
            );
        }

        return $searchResult;
    }

    /**
     * Get key for cache
     *
     * @param array $data
     * @return string
     */
    protected function getCacheKey($data)
    {
        $serializeData = [];
        foreach ($data as $key => $value) {
            if (is_object($value)) {
                $serializeData[$key] = $value->getId();
            } else {
                $serializeData[$key] = $value;
            }
        }
        $serializeData = $this->serializer->serialize($serializeData);
        return sha1($serializeData);
    }

    /**
     * Add product to internal cache and truncate cache if it has more than cacheLimit elements.
     *
     * @param string $cacheKey
     * @param ProductInterface $product
     * @return void
     */
    private function cacheProduct($cacheKey, ProductInterface $product)
    {
        $this->instancesById[$product->getId()][$cacheKey] = $product;
        $this->saveProductInLocalCache($product, $cacheKey);

        if ($this->cacheLimit && count($this->instances) > $this->cacheLimit) {
            $offset = round($this->cacheLimit / -2);
            $this->instancesById = array_slice($this->instancesById, (int)$offset, null, true);
            $this->instances = array_slice($this->instances, (int)$offset, null, true);
        }
    }

    /**
     * Saves product in the local cache by sku.
     *
     * @param Product $product
     * @param string $cacheKey
     * @return void
     */
    private function saveProductInLocalCache(Product $product, string $cacheKey): void
    {
        $preparedSku = $this->prepareSku($product->getSku());
        $this->instances[$preparedSku][$cacheKey] = $product;
    }
    /**
     * Converts SKU to lower case and trims.
     *
     * @param string $sku
     * @return string
     */
    private function prepareSku(string $sku): string
    {
        return mb_strtolower(trim($sku));
    }
}


<?php

namespace Exam\Customer\Model;

use Exam\Customer\Logger\CustomerLogger;
use Exception;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Attribute\CollectionFactory;
use Magento\CustomerImportExport\Model\Import\AbstractCustomer;
use Magento\CustomerImportExport\Model\Import\Customer as MagentoCustomer;
use Magento\CustomerImportExport\Model\ResourceModel\Import\Customer\StorageFactory;
use Magento\Eav\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\StringUtils;
use Magento\ImportExport\Model\Export\Factory;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use Magento\ImportExport\Model\ImportFactory;
use Magento\ImportExport\Model\ResourceModel\Helper;
use Magento\Store\Model\StoreManagerInterface;
use Zend_Validate_Exception;

class ImportServices extends MagentoCustomer
{

    /**
     * Default Website code
     */
    const DEFAULT_WEBSITE = 'base';

    //in real project, should create configuration so that admin can easy to update url
    const API_URL = 'https://reqres.in/api/users';

    //in real project, should create configuration so that admin can select true/false
    const WRITE_LOG = true;

    /**
     * @var Curl
     */
    protected $curl;

    /**
     * @var Json
     */
    protected $serializer;

    /**
     * @var array
     */
    protected $bunch = [];

    /**
     * @var CustomerLogger
     */
    protected $customerImportLogger;

    /**
     * @var array
     */
    protected $defaultData = [
        AbstractCustomer::COLUMN_WEBSITE => self::DEFAULT_WEBSITE,
        CustomerInterface::GROUP_ID => MagentoCustomer::DEFAULT_GROUP_ID
    ];

    /**
     * @var array
     */
    protected $fieldsMapping = [
        CustomerInterface::FIRSTNAME => 'first_name',
        CustomerInterface::LASTNAME => 'last_name'
    ];

    /**
     * ImportServices constructor.
     * @param StringUtils $string
     * @param ScopeConfigInterface $scopeConfig
     * @param ImportFactory $importFactory
     * @param Helper $resourceHelper
     * @param ResourceConnection $resource
     * @param ProcessingErrorAggregatorInterface $errorAggregator
     * @param StoreManagerInterface $storeManager
     * @param Factory $collectionFactory
     * @param Config $eavConfig
     * @param StorageFactory $storageFactory
     * @param CollectionFactory $attrCollectionFactory
     * @param CustomerFactory $customerFactory
     * @param Curl $curl
     * @param Json|null $serializer
     * @param CustomerLogger $customerImportLogger
     * @param array $data
     */
    public function __construct(
        StringUtils $string,
        ScopeConfigInterface $scopeConfig,
        ImportFactory $importFactory,
        Helper $resourceHelper,
        ResourceConnection $resource,
        ProcessingErrorAggregatorInterface $errorAggregator,
        StoreManagerInterface $storeManager,
        Factory $collectionFactory,
        Config $eavConfig,
        StorageFactory $storageFactory,
        CollectionFactory $attrCollectionFactory,
        CustomerFactory $customerFactory,
        Curl $curl,
        Json $serializer = null,
        CustomerLogger $customerImportLogger,
        array $data = []
    ) {
        parent::__construct(
            $string,
            $scopeConfig,
            $importFactory,
            $resourceHelper,
            $resource,
            $errorAggregator,
            $storeManager,
            $collectionFactory,
            $eavConfig,
            $storageFactory,
            $attrCollectionFactory,
            $customerFactory,
            $data
        );
        $this->curl = $curl;
        $this->serializer = $serializer ?: ObjectManager::getInstance()
            ->get(Json::class);
        $this->customerImportLogger = $customerImportLogger;
    }

    /**
     * Import Customer
     *
     * @throws Exception
     */
    public function import()
    {
        try {
            $stop = false;
            $page = 1;
            //START
            if (self::WRITE_LOG) {
                $this->customerImportLogger->info('================ Start Importing Process ================');
            }
            while (!$stop) {
                $url = $this->getCustomerApiUrl($page);

                $this->curl->get($url);

                if (!$this->isSuccessResponseStatus()) {
                    throw new Exception('API response status: ' . $this->curl->getStatus());
                }
                $response = $this->serializer->unserialize($this->curl->getBody());

                //Process Import Customer
                if (isset($response['data']) && count($response['data']) > 0) {
                    $this->bunch = $response['data'];
                    if (self::WRITE_LOG) {
                        $this->customerImportLogger->info('Start importing Page: ' . $page);
                    }
                    $this->_importData();
                    //write error log
                    if (self::WRITE_LOG) {
                        if ($this->getErrorAggregator()->getErrorsCount() > 0) {
                            foreach ($this->getErrorAggregator()->getAllErrors() as $error) {
                                $errorMsg = 'Error Message: ' . $error->getErrorMessage() . ' Row: ' .
                                    $error->getRowNumber();
                                $this->customerImportLogger->error($errorMsg);
                            }
                            // Stop import next page if error happen
                            $stop = true;
                        }

                        $this->customerImportLogger->info('End importing Page: ' . $page);
                    }
                } else {
                    $stop = true;
                }
                $page++;
            }
            //END
            if (self::WRITE_LOG) {
                $this->customerImportLogger->info('================ End Importing Process ================');
            }
        } catch (Exception $exception) {
            if (self::WRITE_LOG) {
                $this->customerImportLogger->info(
                    '================ End Importing Process ================' . $exception->getMessage()
                );
            }
            throw $exception;
        }
    }

    /**
     * Get API url for each page
     *
     * @param int $page
     * @return string
     */
    public function getCustomerApiUrl($page)
    {
        return self::API_URL . '?page=' . $page;
    }

    /**
     * Is success response status.
     *
     * @return bool
     */
    public function isSuccessResponseStatus()
    {
        if ($this->curl->getStatus() >= 200 & $this->curl->getStatus() < 300) {
            return true;
        }

        return false;
    }

    /**
     * Add/Update Customer
     *
     * @return bool
     * @throws Zend_Validate_Exception
     */
    protected function _importData()
    {
        $this->prepareCustomerData($this->bunch);
        $entitiesToCreate = [];
        $entitiesToUpdate = [];
        $entitiesToDelete = [];
        $attributesToSave = [];

        foreach ($this->bunch as $rowNumber => $rowData) {
            $this->updateRowData($rowData);
            if (!$this->validateRow($rowData, $rowNumber)) {
                continue;
            }

            $processedData = $this->_prepareDataForUpdate($rowData);
            $entitiesToCreate = array_merge($entitiesToCreate, $processedData[self::ENTITIES_TO_CREATE_KEY]);
            $entitiesToUpdate = array_merge($entitiesToUpdate, $processedData[self::ENTITIES_TO_UPDATE_KEY]);
            foreach ($processedData[self::ATTRIBUTES_TO_SAVE_KEY] as $tableName => $customerAttributes) {
                if (!isset($attributesToSave[$tableName])) {
                    $attributesToSave[$tableName] = [];
                }
                $attributesToSave[$tableName] = array_diff_key(
                    $attributesToSave[$tableName],
                    $customerAttributes
                ) + $customerAttributes;
            }
        }
        $this->updateItemsCounterStats($entitiesToCreate, $entitiesToUpdate, $entitiesToDelete);
        /**
         * Save prepared data
         */
        if ($entitiesToCreate || $entitiesToUpdate) {
            $this->_saveCustomerEntities($entitiesToCreate, $entitiesToUpdate);

            if (self::WRITE_LOG) {
                if ($entitiesToUpdate) {
                    foreach ($entitiesToUpdate as $update) {
                        $this->customerImportLogger->info('Update Success :' . $update['email']);
                    }
                }
                if ($entitiesToCreate) {
                    foreach ($entitiesToCreate as $create) {
                        $this->customerImportLogger->info('Create Success :' . $create['email']);
                    }
                }
            }
        }

        if ($attributesToSave) {
            $this->_saveCustomerAttributes($attributesToSave);
        }

        return true;
    }

    /**
     * Update rowdata with mapping
     *
     * @param array $rowData
     */
    public function updateRowData(&$rowData)
    {
        foreach ($this->defaultData as $fieldName => $fieldValue) {
            if (!array_key_exists($fieldName, $rowData)) {
                $rowData[$fieldName] = $fieldValue;
            }
        }
        foreach ($this->fieldsMapping as $systemFieldName => $fileFieldName) {
            if (array_key_exists($fileFieldName, $rowData)) {
                $rowData[$systemFieldName] = $rowData[$fileFieldName];
            }
        }
    }
}

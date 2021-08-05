<?php

namespace Exam\Customer\Setup;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

/**
 * @codeCoverageIgnore
 */
class InstallData implements InstallDataInterface
{

    /**
     * @var CustomerSetupFactory
     */
    protected $customerSetupFactory;

    /**
     * InstallData constructor.
     * @param CustomerSetupFactory $customerSetupFactory
     */
    public function __construct(
        CustomerSetupFactory $customerSetupFactory
    ) {
        $this->customerSetupFactory = $customerSetupFactory;
    }

    /**
     * Main function to install new eav
     *
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     * @throws LocalizedException
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        $customerSetup = $this->customerSetupFactory->create([
            'setup' => $setup
        ]);

        $customerSetup->addAttribute(
            Customer::ENTITY,
            'avatar',
            [
                'label' => 'Avatar',
                'type' => 'varchar',
                'input' => 'text',
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'visible' => true,
                'required' => false,
                'system' => false,
                'position' => 60
            ]
        );

        $attributeUpdate = $customerSetup->getEavConfig()->getAttribute(
            Customer::ENTITY,
            'avatar'
        );

        //only use for adminhtml_customer forn now, may add 'customer_account_create', 'customer_account_edit' later
        $attributeUpdate->setData(
            'used_in_forms',
            ['adminhtml_customer']
        );
        $attributeUpdate->save();

        $setup->endSetup();
    }
}

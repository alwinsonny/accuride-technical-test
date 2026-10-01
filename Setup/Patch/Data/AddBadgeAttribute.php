<?php
declare(strict_types=1);

namespace Alwin\ProductBadge\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Model\Entity\Attribute\Source\Table;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;
use Magento\Store\Api\StoreRepositoryInterface;

class AddBadgeAttribute implements DataPatchInterface, PatchRevertableInterface
{
    public const ATTRIBUTE_CODE = 'badge';

    /**
     * Admin (default) label plus store-view translations, keyed by store code.
     
     */
    private const BADGES = [
        'new'         => ['admin' => 'New',         'fr' => 'Nouveau',         'de' => 'Neu'],
        'best_seller' => ['admin' => 'Best Seller', 'fr' => 'Meilleure vente', 'de' => 'Bestseller'],
        'sale'        => ['admin' => 'Sale',        'fr' => 'Promo',           'de' => 'Angebot'],
    ];

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory,
        private readonly StoreRepositoryInterface $storeRepository
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $eavSetup->addAttribute(Product::ENTITY, self::ATTRIBUTE_CODE, [
            'type' => 'int',
            'label' => 'Badge',
            'input' => 'select',
            'source' => Table::class,
            'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
            'required' => false,
            'user_defined' => true,
            'visible' => true,
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'visible_on_front' => false,
            'used_in_product_listing' => true,
            'is_used_in_grid' => true,
            'is_visible_in_grid' => false,
            'is_filterable_in_grid' => true,
            'group' => 'General',
            'sort_order' => 25,
            'option' => $this->buildOptions(),
        ]);

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    public function revert(): void
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $eavSetup->removeAttribute(Product::ENTITY, self::ATTRIBUTE_CODE);
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }

    /**
     * Builds option values in the format EavSetup::addAttributeOption() expects:
     * ['value' => ['option_x' => [storeId => label]], 'order' => ['option_x' => sortOrder]]
     */
    private function buildOptions(): array
    {
        $storeIds = [
            'fr' => $this->getStoreId('fr'),
            'de' => $this->getStoreId('de'),
        ];

        $options = ['value' => [], 'order' => []];
        $sortOrder = 10;

        foreach (self::BADGES as $key => $labels) {
            $optionKey = 'option_' . $key;
            $values = [0 => $labels['admin']];

            foreach ($storeIds as $code => $storeId) {
                if ($storeId !== null) {
                    $values[$storeId] = $labels[$code];
                }
            }

            $options['value'][$optionKey] = $values;
            $options['order'][$optionKey] = $sortOrder;
            $sortOrder += 10;
        }

        return $options;
    }

    private function getStoreId(string $code): ?int
    {
        try {
            return (int) $this->storeRepository->get($code)->getId();
        } catch (NoSuchEntityException) {
            return null;
        }
    }
}
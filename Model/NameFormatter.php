<?php
declare(strict_types=1);

namespace Alwin\ProductBadge\Model;

use Alwin\ProductBadge\Setup\Patch\Data\AddBadgeAttribute;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory as OptionCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Resolves a product's badge label for a store view and appends it to the product name.
 */
class NameFormatter
{
    /** @var array<int, array<int, string>> [storeId => [optionId => label]] */
    private array $labelsByStore = [];

    public function __construct(
        private readonly EavConfig $eavConfig,
        private readonly OptionCollectionFactory $optionCollectionFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function format(string $name, Product $product, ?int $storeId = null): string
    {
        $label = $this->getBadgeLabel($product, $storeId);
        if ($label === null) {
            return $name;
        }

        $suffix = ' [' . $label . ']';

        // Guard against appending twice if getName() is called on an already formatted value
        return str_ends_with($name, $suffix) ? $name : $name . $suffix;
    }

    public function getBadgeLabel(Product $product, ?int $storeId = null): ?string
    {
        $optionId = (int) $product->getData(AddBadgeAttribute::ATTRIBUTE_CODE);
        if ($optionId === 0) {
            return null;
        }

        $storeId ??= (int) ($product->getStoreId() ?: $this->storeManager->getStore()->getId());

        return $this->getLabels($storeId)[$optionId] ?? null;
    }

    /**
     * Loads all badge option labels for a store once per request.
     * setStoreFilter() returns the store-view label, falling back to the admin label.
     */
    private function getLabels(int $storeId): array
    {
        if (!isset($this->labelsByStore[$storeId])) {
            $attributeId = (int) $this->eavConfig
                ->getAttribute(Product::ENTITY, AddBadgeAttribute::ATTRIBUTE_CODE)
                ->getId();

            $collection = $this->optionCollectionFactory->create()
                ->setAttributeFilter($attributeId)
                ->setStoreFilter($storeId);

            $this->labelsByStore[$storeId] = [];
            foreach ($collection as $option) {
                $this->labelsByStore[$storeId][(int) $option->getId()] = (string) $option->getValue();
            }
        }

        return $this->labelsByStore[$storeId];
    }
}
<?php
declare(strict_types=1);

namespace Alwin\ProductBadge\Model\Resolver;

use Alwin\ProductBadge\Model\NameFormatter;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\IsProductSalableInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Model\Store;

/**
 * Resolves the productDetails query for the store view given in the Store header.
 */
class ProductDetails implements ResolverInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly NameFormatter $nameFormatter,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly StockResolverInterface $stockResolver,
        private readonly IsProductSalableInterface $isProductSalable
    ) {
    }

    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): array
    {
        $sku = trim((string) ($args['sku'] ?? ''));
        if ($sku === '') {
            throw new GraphQlInputException(__('A product SKU is required.'));
        }

        /** @var Store $store */
        $store = $context->getExtensionAttributes()->getStore();
        $storeId = (int) $store->getId();

        $product = $this->getProduct($sku, $store);

        $basePrice = (float) $product->getPriceInfo()
            ->getPrice(FinalPrice::PRICE_CODE)
            ->getAmount()
            ->getValue();

        return [
            'entity_id' => (int) $product->getId(), // used by the cache identity
            'sku' => $product->getSku(),
            'name' => $this->nameFormatter->format((string) $product->getData('name'), $product, $storeId),
            'price' => $this->priceCurrency->convertAndRound($basePrice, $store),
            'currency' => $store->getCurrentCurrencyCode(),
            'stock_status' => $this->isInStock($product->getSku(), $store)
                ? __('In stock')->render()
                : __('Out of stock')->render(),
            'badge' => $this->nameFormatter->getBadgeLabel($product, $storeId),
        ];
    }

    /**
     * Loads the product for the store, treating disabled or unassigned products the same as missing ones.
     */
    private function getProduct(string $sku, Store $store): Product
    {
        try {
            /** @var Product $product */
            $product = $this->productRepository->get($sku, false, (int) $store->getId());
        } catch (NoSuchEntityException) {
            throw new GraphQlNoSuchEntityException(__('Product with SKU "%1" does not exist.', $sku));
        }

        $websiteIds = array_map('intval', $product->getWebsiteIds());
        if ((int) $product->getStatus() !== Status::STATUS_ENABLED
            || !in_array((int) $store->getWebsiteId(), $websiteIds, true)
        ) {
            throw new GraphQlNoSuchEntityException(__('Product with SKU "%1" is not available in this store.', $sku));
        }

        return $product;
    }

    /**
     * Uses MSI to check salable status against the stock assigned to the store's website.
     */
    private function isInStock(string $sku, Store $store): bool
    {
        $stockId = (int) $this->stockResolver
            ->execute(SalesChannelInterface::TYPE_WEBSITE, $store->getWebsite()->getCode())
            ->getStockId();

        return $this->isProductSalable->execute($sku, $stockId);
    }
}
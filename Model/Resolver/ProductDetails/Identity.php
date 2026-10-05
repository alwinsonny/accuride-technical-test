<?php
declare(strict_types=1);

namespace Alwin\ProductBadge\Model\Resolver\ProductDetails;

use Magento\Catalog\Model\Product;
use Magento\Framework\GraphQl\Query\Resolver\IdentityInterface;

class Identity implements IdentityInterface
{
    public function getIdentities(array $resolvedData): array
    {
        return empty($resolvedData['entity_id'])
            ? []
            : [Product::CACHE_TAG . '_' . $resolvedData['entity_id']];
    }
}
<?php
declare(strict_types=1);

namespace Alwin\ProductBadge\Plugin\Catalog\Model;

use Alwin\ProductBadge\Model\NameFormatter;
use Magento\Catalog\Model\Product;

/**
 * Appends the translated badge to the product name, e.g. "Joust Duffle Bag [New]".
 * Registered for the frontend and graphql areas only, so the admin and saved data are never affected.
 */
class ProductNamePlugin
{
    public function __construct(
        private readonly NameFormatter $nameFormatter
    ) {
    }

    public function afterGetName(Product $subject, mixed $result): mixed
    {
        if (!is_string($result) || $result === '') {
            return $result;
        }

        return $this->nameFormatter->format($result, $subject);
    }
}
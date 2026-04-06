<?php
/**
 * Copyright © Dazoot Software S.R.L. All rights reserved.
 *
 * @author Newsman by Dazoot <support@newsman.com>
 * @copyright Copyright © Dazoot Software S.R.L. All rights reserved.
 * @license https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 *
 * @website https://www.newsman.ro/
 */

namespace PrestaShop\Module\Newsmanv8\Export\Retriever;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Products extends ProductsFeed
{
    /**
     * @param array<string, mixed> $product
     *
     * @return array<string, mixed>
     */
    /**
     * @param array<int> $shopIds
     */
    public function processProduct(array $product, int $langId, array $shopIds = []): array
    {
        $row = parent::processProduct($product, $langId, $shopIds);

        if (isset($row['price_discount']) || isset($row['price_full'])) {
            $row['price'] = $row['price_discount'] ?? $row['price_full'];
            $row['price_old'] = $row['price_full'] ?? '';
            unset($row['price_discount'], $row['price_full']);
        } else {
            $row['price_old'] = '';
        }

        return $row;
    }
}

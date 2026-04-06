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

namespace PrestaShop\Module\Newsmanv8\Remarketing;

if (!defined('_PS_VERSION_')) {
    exit;
}

class CategoryView
{
    /**
     * Generate category view JS with ec:addImpression for each product.
     *
     * @param array<array<string, mixed>> $products
     */
    public function getHtml(int $categoryId, array $products, int $langId): string
    {
        if (empty($categoryId) || empty($products)) {
            return '';
        }

        $categoryName = $this->getCategoryName($categoryId, $langId);
        $run = JsHelper::getRunFunc();

        $js = '';
        $position = 1;
        foreach ($products as $product) {
            $productId = (int) ($product['id_product'] ?? 0);
            $name = (string) ($product['name'] ?? '');
            $price = isset($product['price_amount'])
                ? (float) $product['price_amount']
                : (float) ($product['price'] ?? 0);

            $js .= $run . "('ec:addImpression', {"
                . 'id: ' . $productId . ','
                . "name: '" . JsHelper::escapeJs($name) . "',"
                . "category: '" . JsHelper::escapeJs($categoryName) . "',"
                . 'price: ' . number_format($price, 2, '.', '') . ','
                . "list: 'Category Page',"
                . "position: '" . ($position++) . "'"
                . '}); ';
        }

        return '<script>' . $js . '</script>' . "\n";
    }

    protected function getCategoryName(int $categoryId, int $langId): string
    {
        $sql = 'SELECT name FROM ' . _DB_PREFIX_ . 'category_lang'
            . ' WHERE id_category = ' . $categoryId
            . ' AND id_lang = ' . $langId
            . ' AND id_shop = ' . (int) \Shop::getContextShopID();

        $result = \Db::getInstance()->getValue($sql);

        return $result ? (string) $result : '';
    }
}

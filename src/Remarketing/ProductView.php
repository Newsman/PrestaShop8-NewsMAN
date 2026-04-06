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

class ProductView
{
    /**
     * Generate product detail view JS.
     */
    public function getHtml(int $productId, int $langId): string
    {
        if (empty($productId)) {
            return '';
        }

        $product = new \Product($productId, false, $langId);
        if (!\Validate::isLoadedObject($product)) {
            return '';
        }

        $categoryName = '';
        $defaultCategoryId = (int) $product->id_category_default;
        if ($defaultCategoryId > 0) {
            $categoryName = $this->getCategoryName($defaultCategoryId, $langId);
        }

        $price = (float) $product->getPrice(true);
        $run = JsHelper::getRunFunc();

        $js = $run . "('ec:addProduct', {"
            . "'id': '" . JsHelper::escapeHtml((string) $productId) . "',"
            . "'name': '" . JsHelper::escapeJs($product->name) . "',"
            . "'category': '" . JsHelper::escapeJs($categoryName) . "',"
            . "'price': '" . number_format($price, 2, '.', '') . "',"
            . "'list': 'Product Page'"
            . '}); ';
        $js .= $run . "('ec:setAction', 'detail');";

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

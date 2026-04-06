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

class ProductsFeed extends AbstractRetriever implements RetrieverInterface
{
    public const DEFAULT_PAGE_SIZE = 1000;

    /** @var array<int, array<int, array<string, mixed>>> */
    protected array $categories = [];

    /**
     * @param array<string, mixed> $data
     *
     * @return array<mixed>
     */
    /**
     * @param array<int> $shopIds
     */
    public function process(array $data = [], array $shopIds = []): array
    {
        $data['default_page_size'] = self::DEFAULT_PAGE_SIZE;

        $parameters = $this->processListParameters($data, $shopIds);

        $shopId = $shopIds[0] ?? null;
        $this->logger->info(sprintf('Export products, shop IDs [%s]', implode(',', $shopIds) ?: 'default'));

        $langId = $this->getDefaultLanguageId($shopId);
        $this->loadCategories($langId, $shopId);

        $products = $this->getProducts($parameters, $langId, $shopIds);

        \Hook::exec('actionNewsmanExportRetrieverProductsFeedProcessFetchAfter', [
            'products' => $products,
            'parameters' => $parameters,
            'shop_id' => $shopId,
            'shop_ids' => $shopIds,
        ]);

        if (empty($products)) {
            return [];
        }

        $result = [];
        foreach ($products as $product) {
            try {
                $result[] = $this->processProduct($product, $langId, $shopIds);
            } catch (\Exception $e) {
                $this->logger->logException($e);
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<array<string, mixed>>
     */
    /**
     * @param array<int> $shopIds
     */
    public function getProducts(array $params, int $langId, array $shopIds = []): array
    {
        $shopIdsSql = !empty($shopIds) ? implode(',', array_map('intval', $shopIds)) : '';

        // Build specific_price shop filter
        $spShopFilter = 'sp.id_shop IN (0' . (!empty($shopIdsSql) ? ',' . $shopIdsSql : '') . ')';
        $sp2ShopFilter = 'sp2.id_shop IN (0' . (!empty($shopIdsSql) ? ',' . $shopIdsSql : '') . ')';

        // Build stock_available join condition (handles shared stock)
        $stockJoinCondition = 'p.id_product = sa.id_product AND sa.id_product_attribute = 0';
        if (!empty($shopIds)) {
            $groupedShops = $this->config->groupShopIdsByGroup($shopIds);
            $stockConditions = [];
            foreach ($groupedShops as $groupId => $gShopIds) {
                $flags = $this->config->getShopGroupSharingFlags($gShopIds[0]);
                if ($flags['share_stock']) {
                    $stockConditions[] = 'sa.id_shop_group = ' . (int) $groupId;
                } else {
                    $stockConditions[] = 'sa.id_shop IN (' . implode(',', array_map('intval', $gShopIds)) . ')';
                }
            }
            $stockJoinCondition .= ' AND (' . implode(' OR ', $stockConditions) . ')';
        }

        $sql = 'SELECT
            p.id_product AS product_id,
            p.active AS status,
            p.date_add AS date_added,
            p.date_upd AS date_modified,
            pl.name AS name,
            pl.link_rewrite AS link_rewrite,
            p.price AS price,
            sa.quantity AS quantity,
            IFNULL(
                (SELECT sp.reduction
                 FROM ' . _DB_PREFIX_ . 'specific_price sp
                 WHERE sp.id_product = p.id_product
                   AND ' . $spShopFilter . "
                   AND (sp.from = '0000-00-00 00:00:00' OR sp.from <= NOW())
                   AND (sp.to = '0000-00-00 00:00:00' OR sp.to >= NOW())
                   AND sp.id_customer = 0
                   AND sp.id_group = 0
                 ORDER BY sp.id_specific_price DESC
                 LIMIT 1
                ), 0
            ) AS reduction,
            IFNULL(
                (SELECT sp2.reduction_type
                 FROM " . _DB_PREFIX_ . 'specific_price sp2
                 WHERE sp2.id_product = p.id_product
                   AND ' . $sp2ShopFilter . "
                   AND (sp2.from = '0000-00-00 00:00:00' OR sp2.from <= NOW())
                   AND (sp2.to = '0000-00-00 00:00:00' OR sp2.to >= NOW())
                   AND sp2.id_customer = 0
                   AND sp2.id_group = 0
                 ORDER BY sp2.id_specific_price DESC
                 LIMIT 1
                ), ''
            ) AS reduction_type,
            i.id_image AS id_image
        FROM " . _DB_PREFIX_ . 'product p
        LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl
            ON (p.id_product = pl.id_product AND pl.id_lang = ' . (int) $langId . ')
        LEFT JOIN ' . _DB_PREFIX_ . 'stock_available sa
            ON (' . $stockJoinCondition . ')
        LEFT JOIN ' . _DB_PREFIX_ . 'image i
            ON (p.id_product = i.id_product AND i.cover = 1)';

        if (!empty($shopIds)) {
            $sql .= ' LEFT JOIN ' . _DB_PREFIX_ . 'product_shop ps
                ON (p.id_product = ps.id_product AND ps.id_shop IN (' . $shopIdsSql . '))';
            $sql .= ' WHERE ps.id_shop IS NOT NULL';
        } else {
            $sql .= ' WHERE 1';
        }

        $sql .= ' AND p.active = 1';

        $where = [];
        if (!empty($params['filters'])) {
            foreach ($params['filters'] as $filter) {
                if (is_array($filter)) {
                    $where[] = implode(' AND ', $filter);
                } else {
                    $where[] = $filter;
                }
            }
        }

        if (!empty($where)) {
            $sql .= ' AND ' . implode(' AND ', $where);
        }

        $sql .= ' GROUP BY p.id_product';

        if (isset($params['sort']) && isset($params['order'])) {
            $sql .= ' ORDER BY ' . $params['sort'] . ' ' . $params['order'];
        }

        $start = 0;
        if (isset($params['start']) && $params['start'] >= 0) {
            $start = (int) $params['start'];
        }
        $limit = $params['default_page_size'];
        if (isset($params['limit']) && $params['limit'] >= 1) {
            $limit = (int) $params['limit'];
        }
        $sql .= ' LIMIT ' . $start . ',' . $limit;

        $rows = \Db::getInstance()->executeS($sql);
        if (!is_array($rows) || empty($rows)) {
            return [];
        }

        $productIds = array_column($rows, 'product_id');
        $productCategories = $this->getProductsCategories($productIds);
        foreach ($rows as &$row) {
            $row['categories'] = [];
            if (isset($productCategories[$row['product_id']])) {
                $row['categories'] = $productCategories[$row['product_id']];
            }
        }
        unset($row);

        return $rows;
    }

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
        $shopId = $shopIds[0] ?? null;
        $price = (float) $product['price'];
        $link = new \Link();

        $row = [
            'id' => $product['product_id'],
            'url' => $link->getProductLink(
                (int) $product['product_id'],
                $product['link_rewrite'],
                null,
                null,
                $langId,
                $shopId
            ),
            'name' => $product['name'],
        ];

        $reduction = (float) $product['reduction'];
        $reductionType = (string) $product['reduction_type'];

        if ($reduction > 0) {
            if ($reductionType === 'percentage') {
                $discountPrice = $price * (1 - $reduction);
            } else {
                $discountPrice = $price - $reduction;
            }

            if ($discountPrice > 0 && $discountPrice < $price) {
                $row['price_full'] = round($price, 2);
                $row['price_discount'] = round($discountPrice, 2);
            } else {
                $row['price'] = round($price, 2);
            }
        } else {
            $row['price'] = round($price, 2);
        }

        if (!empty($product['id_image'])) {
            $row['image_url'] = $link->getImageLink(
                $product['link_rewrite'],
                (string) $product['id_image'],
                'large_default'
            );
        } else {
            $row['image_url'] = '';
        }

        $row['category'] = '';
        $row['subcategories'] = [];
        $categories = [];
        $levels = [];
        $maxLevel = 0;
        $maxCategoryLevel = false;
        foreach ($product['categories'] as $categoryId) {
            $path = $this->getCategoryPath($categoryId, $shopId);
            $categories[$categoryId] = $path;
            $levels[$categoryId] = count($path);
            if ($levels[$categoryId] > $maxLevel) {
                $maxLevel = $levels[$categoryId];
                $maxCategoryLevel = $categoryId;
            }
        }

        if ($maxCategoryLevel !== false) {
            $row['category'] = html_entity_decode($categories[$maxCategoryLevel][0]['name']);
            $subcategories = [];
            foreach ($categories as $category) {
                $subcategories[] = array_map('html_entity_decode', array_column(array_reverse($category), 'name'));
            }
            $row['subcategories'] = $subcategories;
        }

        $row['in_stock'] = ($product['status'] && (int) $product['quantity'] > 0) ? 1 : 0;
        $row['stock_quantity'] = (int) $product['quantity'];
        $row['variants'] = '';

        $hookResult = \Hook::exec(
            'actionNewsmanExportRetrieverProductsFeedProcessProductAfter',
            ['row' => $row, 'product' => $product, 'shop_id' => $shopId, 'shop_ids' => $shopIds],
            null,
            false,
            true,
            false,
            null,
            true
        );
        if (is_array($hookResult) && isset($hookResult['row'])) {
            $row = $hookResult['row'];
        }

        return $row;
    }

    /**
     * @param array<int> $productIds
     *
     * @return array<int, array<int>>
     */
    public function getProductsCategories(array $productIds): array
    {
        $productCategories = [];
        if (empty($productIds)) {
            return $productCategories;
        }

        $batches = array_chunk($productIds, 300);
        foreach ($batches as $batch) {
            $sql = 'SELECT id_product, id_category FROM ' . _DB_PREFIX_ . 'category_product'
                . ' WHERE id_product IN (' . implode(',', array_map('intval', $batch)) . ')';
            $rows = \Db::getInstance()->executeS($sql);
            if (!is_array($rows)) {
                continue;
            }
            foreach ($rows as $row) {
                $productCategories[$row['id_product']][] = $row['id_category'];
            }
        }

        return $productCategories;
    }

    protected function loadCategories(int $langId, ?int $shopId = null): void
    {
        $key = $shopId ?? 0;
        if (isset($this->categories[$key])) {
            return;
        }

        $sql = 'SELECT c.id_category AS category_id, c.id_parent AS parent_id, cl.name'
            . ' FROM ' . _DB_PREFIX_ . 'category c'
            . ' LEFT JOIN ' . _DB_PREFIX_ . 'category_lang cl ON (c.id_category = cl.id_category AND cl.id_lang = ' . (int) $langId . ')'
            . ' WHERE c.active = 1';

        if ($shopId !== null) {
            $sql .= ' AND cl.id_shop = ' . (int) $shopId;
        }

        $rows = \Db::getInstance()->executeS($sql);
        $this->categories[$key] = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $this->categories[$key][$row['category_id']] = $row;
            }
        }
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function getCategoryPath(int $categoryId, ?int $shopId = null): array
    {
        $key = $shopId ?? 0;
        $path = [];
        $currentId = $categoryId;
        $failSafe = 0;

        while (isset($this->categories[$key][$currentId]) && $failSafe < 30) {
            $category = $this->categories[$key][$currentId];

            if ($category['parent_id'] == 0 || $category['parent_id'] == $currentId) {
                break;
            }

            $path[] = $category;
            $currentId = $category['parent_id'];
            ++$failSafe;
        }

        return $path;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getWhereParametersMapping(): array
    {
        return array_merge(parent::getWhereParametersMapping(), [
            'created_at' => [
                'field' => 'p.date_add',
                'quote' => true,
                'type' => 'string',
            ],
            'modified_at' => [
                'field' => 'p.date_upd',
                'quote' => true,
                'type' => 'string',
            ],
            'product_id' => [
                'field' => 'p.id_product',
                'quote' => false,
                'type' => 'int',
            ],
            'product_ids' => [
                'field' => 'p.id_product',
                'quote' => false,
                'multiple' => true,
                'force_array' => true,
                'type' => 'int',
            ],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function getAllowedSortFields(): array
    {
        return array_merge(parent::getAllowedSortFields(), [
            'created_at' => 'p.date_add',
            'modified_at' => 'p.date_upd',
            'product_id' => 'p.id_product',
            'name' => 'pl.name',
            'price' => 'p.price',
        ]);
    }
}

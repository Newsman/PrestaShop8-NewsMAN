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

use PrestaShop\Module\Newsmanv8\Config;
use PrestaShop\Module\Newsmanv8\Export\Order\StatusMapper;
use PrestaShop\Module\Newsmanv8\Logger;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Orders extends BaseOrders implements RetrieverInterface
{
    public const DEFAULT_PAGE_SIZE = 200;

    protected StatusMapper $statusMapper;

    public function __construct(Config $config, Logger $logger, StatusMapper $statusMapper)
    {
        parent::__construct($config, $logger);
        $this->statusMapper = $statusMapper;
    }

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

        $this->logger->info(sprintf('Export orders, shop IDs [%s]', implode(',', $shopIds) ?: 'default'));

        $orders = $this->getOrders($parameters, $shopIds);

        \Hook::exec('actionNewsmanExportRetrieverOrdersProcessFetchAfter', [
            'orders' => $orders,
            'parameters' => $parameters,
            'shop_id' => $shopIds[0] ?? null,
            'shop_ids' => $shopIds,
        ]);

        if (empty($orders)) {
            return [];
        }

        $orderIds = array_column($orders, 'id_order');
        $allOrderProducts = $this->getOrdersProducts($orderIds);

        $result = [];
        foreach ($orders as $order) {
            try {
                $orderId = $order['id_order'];
                $orderProducts = isset($allOrderProducts[$orderId]) ? $allOrderProducts[$orderId] : [];
                $result[] = $this->processOrder($order, $orderProducts, $shopIds);
            } catch (\Exception $e) {
                $this->logger->logException($e);
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $order
     * @param array<array<string, mixed>> $products
     *
     * @return array<string, mixed>
     */
    /**
     * @param array<int> $shopIds
     */
    public function processOrder(array $order, array $products, array $shopIds = []): array
    {
        $productsData = [];
        $subtotalAmount = 0;

        foreach ($products as $product) {
            $unitPrice = (float) $product['unit_price_tax_incl'];
            $productsData[] = [
                'id' => $product['product_id'],
                'quantity' => (int) $product['product_quantity'],
                'unit_price' => round($unitPrice, 2),
                'name' => $product['product_name'],
            ];
            $subtotalAmount += ($unitPrice * (int) $product['product_quantity']);
        }

        $shippingAmount = (float) $order['total_shipping_tax_incl'];
        $discount = abs((float) $order['total_discounts_tax_incl']);
        $taxAmount = (float) $order['total_paid_tax_incl'] - (float) $order['total_paid_tax_excl'];

        $discountCode = '';
        $cartRules = $this->getOrderCartRules((int) $order['id_order']);
        if (!empty($cartRules)) {
            $codes = [];
            foreach ($cartRules as $rule) {
                if (!empty($rule['code'])) {
                    $codes[] = $rule['code'];
                }
            }
            $discountCode = implode(',', $codes);
        }

        $orderStatus = $this->statusMapper->map(
            (int) $order['current_state'],
            $order['order_status_name'] ?? ''
        );

        $row = [
            'id' => $order['id_order'],
            'billing_name' => trim(($order['firstname'] ?? '') . ' ' . ($order['lastname'] ?? '')),
            'billing_company_name' => $order['billing_company'] ?? '',
            'billing_phone' => !empty($order['phone']) ? $order['phone'] : ($order['phone_mobile'] ?? ''),
            'customer_email' => $order['email'] ?? '',
            'customer_id' => !empty($order['id_customer']) ? (string) $order['id_customer'] : '',
            'shipping_amount' => round($shippingAmount, 2),
            'tax_amount' => round($taxAmount, 2),
            'total_amount' => round((float) $order['total_paid_tax_incl'], 2),
            'currency' => $this->getCurrencyIsoById((int) $order['id_currency']),
            'subtotal_amount' => round($subtotalAmount, 2),
            'discount' => round($discount, 2),
            'discount_code' => $discountCode,
            'status' => $orderStatus,
            'date_created' => $order['date_add'],
            'date_modified' => $order['date_upd'],
            'products' => $productsData,
        ];

        $hookResult = \Hook::exec(
            'actionNewsmanExportRetrieverOrdersProcessOrderAfter',
            ['row' => $row, 'order' => $order, 'shop_id' => $shopIds[0] ?? null, 'shop_ids' => $shopIds],
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
     * @param array<int> $orderIds
     *
     * @return array<int, array<array<string, mixed>>>
     */
    protected function getOrdersProducts(array $orderIds): array
    {
        if (empty($orderIds)) {
            return [];
        }

        $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'order_detail'
            . ' WHERE id_order IN (' . implode(',', array_map('intval', $orderIds)) . ')';

        $rows = \Db::getInstance()->executeS($sql);
        if (!is_array($rows)) {
            return [];
        }

        $orderProducts = [];
        foreach ($rows as $row) {
            $orderProducts[$row['id_order']][] = $row;
        }

        return $orderProducts;
    }

    /**
     * @return array<array<string, mixed>>
     */
    protected function getOrderCartRules(int $orderId): array
    {
        $sql = 'SELECT ocr.*, cr.code FROM ' . _DB_PREFIX_ . 'order_cart_rule ocr'
            . ' LEFT JOIN ' . _DB_PREFIX_ . 'cart_rule cr ON (ocr.id_cart_rule = cr.id_cart_rule)'
            . ' WHERE ocr.id_order = ' . $orderId;

        $rows = \Db::getInstance()->executeS($sql);

        return is_array($rows) ? $rows : [];
    }

    protected function getCurrencyIsoById(int $currencyId): string
    {
        $sql = 'SELECT iso_code FROM ' . _DB_PREFIX_ . 'currency WHERE id_currency = ' . $currencyId;
        $result = \Db::getInstance()->getValue($sql);

        return $result ? (string) $result : '';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getWhereParametersMapping(): array
    {
        return array_merge(parent::getWhereParametersMapping(), [
            'created_at' => [
                'field' => 'o.date_add',
                'quote' => true,
                'type' => 'string',
            ],
            'modified_at' => [
                'field' => 'o.date_upd',
                'quote' => true,
                'type' => 'string',
            ],
            'order_id' => [
                'field' => 'o.id_order',
                'quote' => false,
                'type' => 'int',
            ],
            'order_ids' => [
                'field' => 'o.id_order',
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
            'created_at' => 'o.date_add',
            'modified_at' => 'o.date_upd',
            'order_id' => 'o.id_order',
        ]);
    }
}

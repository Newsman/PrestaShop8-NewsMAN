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
use PrestaShop\Module\Newsmanv8\Service\Context\Remarketing\SaveOrders as SaveOrdersContext;
use PrestaShop\Module\Newsmanv8\Service\Remarketing\SaveOrders;

if (!defined('_PS_VERSION_')) {
    exit;
}

class SendOrders extends BaseOrders implements RetrieverInterface
{
    public const DEFAULT_PAGE_SIZE = 200;
    public const BATCH_SIZE = 500;

    protected StatusMapper $statusMapper;
    protected SaveOrders $saveOrdersService;

    public function __construct(Config $config, Logger $logger, StatusMapper $statusMapper, SaveOrders $saveOrdersService)
    {
        parent::__construct($config, $logger);
        $this->statusMapper = $statusMapper;
        $this->saveOrdersService = $saveOrdersService;
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

        $this->logger->info(sprintf('Send orders, shop IDs [%s]', implode(',', $shopIds) ?: 'default'));

        $orders = $this->getOrders($parameters, $shopIds);
        if (empty($orders)) {
            return [];
        }

        $shopConstraint = Config::shopConstraint($shopIds[0] ?? null);

        $orderIds = array_column($orders, 'id_order');
        $allOrderProducts = $this->getOrdersProducts($orderIds);

        $result = [];
        $countOrders = 0;
        foreach ($orders as $order) {
            try {
                $orderId = $order['id_order'];
                $orderProducts = isset($allOrderProducts[$orderId]) ? $allOrderProducts[$orderId] : [];
                $result[] = $this->processOrder($order, $orderProducts, $shopIds);
                ++$countOrders;
            } catch (\Exception $e) {
                $this->logger->logException($e);
            }
        }

        $batches = array_chunk($result, self::BATCH_SIZE);
        unset($result);

        $count = 0;
        $apiResults = [];
        foreach ($batches as $batch) {
            try {
                $context = new SaveOrdersContext();
                $context->setListId($this->config->getListId($shopConstraint))
                    ->setOrders($batch);

                $apiResults[] = $this->saveOrdersService->execute($context);

                $count += count($batch);
            } catch (\Exception $e) {
                $this->logger->logException($e);
            }
        }

        return [
            'status' => sprintf('Sent to NewsMAN %d orders out of a total of %d.', $count, $countOrders),
            'results' => $apiResults,
        ];
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
    protected function processOrder(array $order, array $products, array $shopIds = []): array
    {
        $productsData = [];
        foreach ($products as $product) {
            $productsData[] = [
                'id' => (string) $product['product_id'],
                'quantity' => (int) $product['product_quantity'],
                'price' => round((float) $product['unit_price_tax_incl'], 2),
                'variation_code' => '',
            ];
        }

        $discount = abs((float) $order['total_discounts_tax_incl']);
        $shippingAmount = (float) $order['total_shipping_tax_incl'];

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

        $details = [
            'order_no' => $order['id_order'],
            'lastname' => $order['lastname'] ?? '',
            'firstname' => $order['firstname'] ?? '',
            'email' => $order['email'] ?? '',
            'phone' => !empty($order['phone']) ? $order['phone'] : ($order['phone_mobile'] ?? ''),
            'status' => $orderStatus,
            'created_at' => $order['date_add'],
            'discount_code' => $discountCode,
            'discount' => round($discount, 2),
            'shipping' => round($shippingAmount, 2),
            'rebates' => 0,
            'fees' => 0,
            'total' => round((float) $order['total_paid_tax_incl'], 2),
            'currency' => $this->getCurrencyIsoById((int) $order['id_currency']),
        ];

        $details['products'] = $productsData;

        return $details;
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

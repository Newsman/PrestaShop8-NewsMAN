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

namespace PrestaShop\Module\Newsmanv8\Action\Order;

use PrestaShop\Module\Newsmanv8\Config;
use PrestaShop\Module\Newsmanv8\Export\Order\StatusMapper;
use PrestaShop\Module\Newsmanv8\Logger;
use PrestaShop\Module\Newsmanv8\Service\Context\Remarketing\SaveOrders as SaveOrdersContext;
use PrestaShop\Module\Newsmanv8\Service\Remarketing\SaveOrders as SaveOrdersService;
use PrestaShop\PrestaShop\Adapter\Configuration;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Save
{
    protected Config $config;
    protected Logger $logger;
    protected StatusMapper $statusMapper;
    protected SaveOrdersService $saveOrdersService;
    protected Configuration $configuration;

    public function __construct(
        Config $config,
        Logger $logger,
        StatusMapper $statusMapper,
        SaveOrdersService $saveOrdersService,
        Configuration $configuration,
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->statusMapper = $statusMapper;
        $this->saveOrdersService = $saveOrdersService;
        $this->configuration = $configuration;
    }

    /**
     * Save order to Newsman remarketing API.
     */
    public function execute(int $orderId, bool $isNew = false, ?ShopConstraint $shopConstraint = null): void
    {
        if (!$this->config->isEnabled($shopConstraint)) {
            return;
        }

        try {
            $order = new \Order($orderId);
            if (!\Validate::isLoadedObject($order)) {
                return;
            }

            $customer = new \Customer($order->id_customer);
            $address = new \Address($order->id_address_invoice);
            $currency = new \Currency($order->id_currency);

            $email = $customer->email;
            if (empty($email) || $customer->deleted) {
                $email = 'deleteduser@' . $this->getStoreDomain($shopConstraint);
            }

            $orderStateLang = $this->getOrderStateName((int) $order->current_state);
            $orderStatus = $this->statusMapper->map((int) $order->current_state, $orderStateLang);

            if ($isNew && (int) $order->current_state === 0) {
                $orderStatus = 'pending';
            }

            $products = $order->getProducts();
            $productsData = [];
            foreach ($products as $product) {
                $productsData[] = [
                    'id' => (string) $product['product_id'],
                    'quantity' => (int) $product['product_quantity'],
                    'price' => round((float) $product['unit_price_tax_incl'], 2),
                    'variation_code' => '',
                ];
            }

            $discount = abs((float) $order->total_discounts_tax_incl);
            $discountCode = '';
            $cartRules = $this->getOrderCartRules($orderId);
            if (!empty($cartRules)) {
                $codes = [];
                foreach ($cartRules as $rule) {
                    if (!empty($rule['code'])) {
                        $codes[] = $rule['code'];
                    }
                }
                $discountCode = implode(',', $codes);
            }

            $firstname = !empty($address->firstname) ? $address->firstname : $customer->firstname;
            $lastname = !empty($address->lastname) ? $address->lastname : $customer->lastname;
            $phone = !empty($address->phone) ? $address->phone : $address->phone_mobile;

            $details = [
                'order_no' => $orderId,
                'lastname' => $lastname,
                'firstname' => $firstname,
                'email' => $email,
                'phone' => $phone,
                'status' => $orderStatus,
                'created_at' => $order->date_add,
                'discount_code' => $discountCode,
                'discount' => round($discount, 2),
                'shipping' => round((float) $order->total_shipping_tax_incl, 2),
                'rebates' => 0,
                'fees' => 0,
                'total' => round((float) $order->total_paid_tax_incl, 2),
                'currency' => $currency->iso_code,
            ];

            $hookParams = [
                'order_id' => $orderId,
                'order' => $order,
                'details' => $details,
                'products' => $productsData,
                'is_new' => $isNew,
            ];
            $hookResponses = \Hook::exec('actionNewsmanBeforeOrderSave', $hookParams, null, true);
            if (is_array($hookResponses)) {
                foreach ($hookResponses as $response) {
                    if (is_array($response) && !empty($response['cancel'])) {
                        return;
                    }
                }
            }

            $orderRow = $details;
            $orderRow['products'] = $productsData;

            $context = new SaveOrdersContext();
            $context->setListId($this->config->getListId($shopConstraint))
                ->setOrders([$orderRow]);

            $this->saveOrdersService->execute($context);

            \Hook::exec('actionNewsmanAfterOrderSave', [
                'order_id' => $orderId,
                'order' => $order,
                'details' => $details,
                'products' => $productsData,
                'is_new' => $isNew,
            ]);
        } catch (\Exception $e) {
            $this->logger->logException($e);
        }
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

    protected function getStoreDomain(?ShopConstraint $shopConstraint = null): string
    {
        $domain = \Tools::getShopDomainSsl(false);
        if (!empty($domain)) {
            return $domain;
        }

        return 'example.com';
    }

    protected function getOrderStateName(int $orderStateId): string
    {
        $langId = (int) $this->configuration->get('PS_LANG_DEFAULT');
        $sql = 'SELECT name FROM ' . _DB_PREFIX_ . 'order_state_lang'
            . ' WHERE id_order_state = ' . $orderStateId
            . ' AND id_lang = ' . $langId;

        $result = \Db::getInstance()->getValue($sql);

        return $result ? (string) $result : '';
    }
}

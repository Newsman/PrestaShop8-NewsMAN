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

use PrestaShop\Module\Newsmanv8\Config;
use PrestaShop\PrestaShop\Adapter\Configuration;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Purchase
{
    protected Config $config;
    protected PageView $pageView;
    protected Configuration $configuration;

    public function __construct(Config $config, PageView $pageView, Configuration $configuration)
    {
        $this->config = $config;
        $this->pageView = $pageView;
        $this->configuration = $configuration;
    }

    /**
     * Generate purchase tracking JS.
     */
    public function getHtml(\Order $order, ?ShopConstraint $shopConstraint = null): string
    {
        if (!\Validate::isLoadedObject($order)) {
            return $this->pageView->getHtml();
        }

        $customer = new \Customer($order->id_customer);
        $currency = new \Currency($order->id_currency);
        $run = JsHelper::getRunFunc();

        $email = $customer->email;
        $firstName = $customer->firstname;
        $lastName = $customer->lastname;
        $currencyCode = $currency->iso_code;

        $js = '_nzm.identify({email: "' . JsHelper::escapeJs($email) . '", ';

        if ($this->config->isRemarketingSendTelephone($shopConstraint)) {
            $address = $this->getDeliveryAddress($order);
            $phone = $address ? ($address->phone_mobile ?: $address->phone) : '';
            if (!empty($phone)) {
                $js .= 'phone: "' . JsHelper::escapeJs($phone) . '", ';
            }
        }

        $js .= 'first_name: "' . JsHelper::escapeJs($firstName) . '", '
            . 'last_name: "' . JsHelper::escapeJs($lastName) . '"});';

        $js .= ' ' . $run . "('set', 'currencyCode', '" . JsHelper::escapeHtml($currencyCode) . "'); ";

        $products = $order->getProducts();
        $productsJs = '';
        foreach ($products as $product) {
            $productsJs .= $run . "('ec:addProduct', {"
                . "'id': '" . JsHelper::escapeHtml((string) $product['product_id']) . "',"
                . "'name': '" . JsHelper::escapeJs($product['product_name']) . "',"
                . "'price': '" . round((float) $product['unit_price_tax_incl'], 2) . "',"
                . "'quantity': '" . (int) $product['product_quantity'] . "'"
                . '}); ';
        }

        $shopName = $this->configuration->get('PS_SHOP_NAME') ?: '';

        $orderData = [
            'id' => JsHelper::escapeHtml((string) $order->id),
            'affiliation' => JsHelper::escapeHtml($shopName),
            'revenue' => JsHelper::escapeHtml((string) round((float) $order->total_paid_tax_incl, 2)),
            'tax' => 0,
            'shipping' => 0,
            'currency' => JsHelper::escapeHtml($currencyCode),
        ];

        $js .= 'setTimeout(function() { '
            . $productsJs . ' '
            . $run . "('ec:setAction', 'purchase', " . json_encode($orderData) . '); '
            . $this->pageView->getJs()
            . ' }, 1000);';

        return '<script>' . $js . '</script>' . "\n";
    }

    protected function getDeliveryAddress(\Order $order): ?\Address
    {
        if (empty($order->id_address_delivery)) {
            return null;
        }

        $address = new \Address($order->id_address_delivery);

        return \Validate::isLoadedObject($address) ? $address : null;
    }
}

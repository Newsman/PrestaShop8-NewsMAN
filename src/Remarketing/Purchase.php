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
     * Generate purchase tracking JS for the order and the other orders made from the same cart.
     */
    public function getHtml(\Order $order, ?ShopConstraint $shopConstraint = null): string
    {
        if (!\Validate::isLoadedObject($order)) {
            return $this->pageView->getHtml();
        }

        $customer = new \Customer($order->id_customer);

        $orderIds = [];
        $purchasesJs = '';
        foreach ($this->getCartOrders($order) as $cartOrder) {
            $orderId = (int) $cartOrder->id;
            $orderIds[] = (string) $orderId;
            $purchasesJs .= "if (!nzmSent['" . $orderId . "']) {\n"
                . $this->getOrderPurchaseJs($cartOrder)
                . "}\n";
        }

        $js = $this->getDeliveryGuardJs($orderIds);

        $email = (string) $customer->email;
        if ($email !== '') {
            $js .= 'var nzmIdentity = {email: "' . JsHelper::escapeJs($email) . '", ';

            if ($this->config->isRemarketingSendTelephone($shopConstraint)) {
                $address = $this->getDeliveryAddress($order);
                $phone = $address ? ($address->phone_mobile ?: $address->phone) : '';
                if (!empty($phone)) {
                    $js .= 'phone: "' . JsHelper::escapeJs($phone) . '", ';
                }
            }

            $js .= 'first_name: "' . JsHelper::escapeJs($customer->firstname) . '", '
                . 'last_name: "' . JsHelper::escapeJs($customer->lastname) . "\"};\n";
        } else {
            $js .= "var nzmIdentity = null;\n";
        }

        $js .= "var nzmPurchaseQueued = false;\n"
            . "function nzmSendPurchase() {\n"
            . "if (nzmPurchaseQueued) {\n"
            . "return;\n"
            . "}\n"
            . "nzmPurchaseQueued = true;\n"
            . "try { nzmWatchDelivery(0); } catch (e) {}\n"
            . $purchasesJs
            . "}\n";

        // Reload of a confirmation page whose purchases Newsman already received
        $js .= "if (nzmAlreadySent) {\n"
            . "if (nzmIdentity) {\n"
            . "_nzm.identify(nzmIdentity);\n"
            . "}\n"
            . $this->pageView->getJs() . "\n"
            . "return;\n"
            . "}\n";

        // The tracker drops a purchase from a visitor without a tracking id, so a
        // visitor not identified yet sends it once identify has stored the id.
        $js .= "if (!nzmIdentity) {\n"
            . "nzmSendPurchase();\n"
            . "} else if (/(?:^|;\\s*)_nzm_id_[^=]*=[^;]/.test(document.cookie)) {\n"
            . "_nzm.identify(nzmIdentity);\n"
            . "nzmSendPurchase();\n"
            . "} else {\n"
            . "_nzm.identify(nzmIdentity, undefined, nzmSendPurchase);\n"
            . "setTimeout(nzmSendPurchase, 5000);\n"
            . "}\n";

        return '<script>setTimeout(function () {' . "\n" . $js . '}, 1000);</script>' . "\n";
    }

    /**
     * Purchase hit of one order: its products, the purchase action and a page view.
     */
    protected function getOrderPurchaseJs(\Order $order): string
    {
        $currency = new \Currency($order->id_currency);
        $currencyCode = $currency->iso_code;
        $run = JsHelper::getRunFunc();

        $js = $run . "('set', 'currencyCode', '" . JsHelper::escapeHtml($currencyCode) . "');\n";

        $products = $order->getProducts();
        foreach ($products as $product) {
            $js .= $run . "('ec:addProduct', {"
                . "'id': '" . JsHelper::escapeHtml((string) $product['product_id']) . "',"
                . "'name': '" . JsHelper::escapeJs($product['product_name']) . "',"
                . "'price': '" . round((float) $product['unit_price_tax_incl'], 2) . "',"
                . "'quantity': '" . (int) $product['product_quantity'] . "'"
                . "});\n";
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

        $js .= $run . "('ec:setAction', 'purchase', " . json_encode($orderData) . ");\n"
            . $this->pageView->getJs() . "\n";

        return $js;
    }

    /**
     * The order and the other orders created from the same cart (a cart split by carrier
     * makes several orders with the same reference; the confirmation page gets the first).
     *
     * @return \Order[]
     */
    protected function getCartOrders(\Order $order): array
    {
        $orders = [(int) $order->id => $order];

        try {
            foreach ($order->getBrother() as $brother) {
                if (\Validate::isLoadedObject($brother)
                    && (int) $brother->id_customer === (int) $order->id_customer
                ) {
                    $orders[(int) $brother->id] = $brother;
                }
            }
        } catch (\Throwable $e) {
            // Only the order of the confirmation page is tracked.
        }

        ksort($orders);

        return array_values($orders);
    }

    /**
     * Remembers in localStorage the orders whose purchase Newsman received, so a
     * reload of the confirmation page does not send the purchase again.
     *
     * An order counts as received only after the tracker confirmed a purchase
     * (_nzm.onPurchase, called on an OK response) while the request for this
     * order, issued on this page, is no longer waiting in the tracker's
     * nzm_retry queue. Anything less (tracker blocked, request failed, consent
     * denied, storage unavailable) leaves the order unmarked and the purchase is
     * sent again on the next view.
     *
     * @param string[] $orderIds
     */
    protected function getDeliveryGuardJs(array $orderIds): string
    {
        $js = <<<'JS'
var nzmOrderIds = __ORDER_IDS__;
var nzmSentKey = 'newsman_purchases_sent';
function nzmSentOrders() {
    try {
        var sent = JSON.parse(window.localStorage.getItem(nzmSentKey) || '{}');
        return sent && typeof sent === 'object' ? sent : {};
    } catch (e) {
        return {};
    }
}
function nzmIsOrderPurchase(url, orderId) {
    var u = String(url);
    for (var i = 0; i < 3; i++) {
        try {
            var decoded = decodeURIComponent(u);
            if (decoded === u) {
                break;
            }
            u = decoded;
        } catch (e) {
            break;
        }
    }
    return u.indexOf('p[pa]=purchase') !== -1 && new RegExp('[?&]p\\[ti\\]=' + orderId + '(&|$)').test(u);
}
function nzmPurchaseDelivered(orderId) {
    var requests = window._nzm && window._nzm.stats && window._nzm.stats.requests;
    var issued = false;
    for (var i = 0; requests && i < requests.length; i++) {
        if (nzmIsOrderPurchase(requests[i], orderId)) {
            issued = true;
        }
    }
    if (!issued) {
        return false;
    }
    var pending = JSON.parse(window.localStorage.getItem('nzm_retry') || '[]');
    if (!Array.isArray(pending)) {
        return false;
    }
    for (var j = 0; j < pending.length; j++) {
        if (!pending[j] || typeof pending[j].url !== 'string' || nzmIsOrderPurchase(pending[j].url, orderId)) {
            return false;
        }
    }
    return true;
}
function nzmMarkSent() {
    try {
        var sent = nzmSentOrders();
        var changed = false;
        for (var n = 0; n < nzmOrderIds.length; n++) {
            if (!sent[nzmOrderIds[n]] && nzmPurchaseDelivered(nzmOrderIds[n])) {
                sent[nzmOrderIds[n]] = Date.now();
                changed = true;
            }
        }
        if (!changed) {
            return;
        }
        var ids = [];
        for (var id in sent) {
            if (Object.prototype.hasOwnProperty.call(sent, id)) {
                ids.push(id);
            }
        }
        ids.sort(function (a, b) { return sent[b] - sent[a]; });
        for (var k = 50; k < ids.length; k++) {
            delete sent[ids[k]];
        }
        window.localStorage.setItem(nzmSentKey, JSON.stringify(sent));
    } catch (e) {}
}
function nzmWatchDelivery(attempt) {
    var nzm = window._nzm;
    // Until t.js loads _nzm is the stub array, which the tracker then replaces.
    if (!nzm || Array.isArray(nzm)) {
        if (attempt < 600) {
            setTimeout(function () { nzmWatchDelivery(attempt + 1); }, 100);
        }
        return;
    }
    var previous = nzm.onPurchase;
    nzm.onPurchase = function () {
        nzmMarkSent();
        if (typeof previous === 'function') {
            return previous.apply(this, arguments);
        }
    };
}
var nzmSent = nzmSentOrders();
var nzmAlreadySent = true;
for (var m = 0; m < nzmOrderIds.length; m++) {
    if (!nzmSent[nzmOrderIds[m]]) {
        nzmAlreadySent = false;
    }
}

JS;

        return str_replace('__ORDER_IDS__', (string) json_encode(array_values($orderIds)), $js);
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

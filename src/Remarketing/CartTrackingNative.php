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

class CartTrackingNative
{
    /**
     * Generate the lightweight cart tracking JS HTML.
     *
     * Listens to native PrestaShop /cart AJAX responses (fetch + XMLHttpRequest)
     * and reads cart.products from the returned JSON to drive the remarketing
     * clear_cart / add events. No background polling.
     *
     * Additionally, once per browser session (gated by the nzm_cart_sync
     * session cookie), fetches the module cart endpoint to clear the Newsman
     * remarketing cart and replay the current PrestaShop cart into it. This
     * covers the "user closed browser, came back" case that the cart-mutation
     * hooks alone can't observe.
     */
    public function getHtml(string $cartAjaxUrl, string $cookiePath = '/'): string
    {
        $js = <<<'JS'
<script>
    _nzm.run('require', 'ec');

    (function () {
        var isProd = true;

        function aggregateProducts(items) {
            var byId = {};
            var order = [];
            for (var i = 0; i < items.length; i++) {
                var it = items[i];
                if (!it || !it.id) {
                    continue;
                }
                if (!byId.hasOwnProperty(it.id)) {
                    byId[it.id] = {
                        id: it.id,
                        name: it.name || '',
                        price: it.price,
                        quantity: it.quantity
                    };
                    order.push(it.id);
                    continue;
                }
                var acc = byId[it.id];
                acc.quantity += it.quantity;
                if ((!acc.name || acc.name === '') && it.name) {
                    acc.name = it.name;
                }
                if ((acc.price === 0 || isNaN(acc.price)) && !isNaN(it.price)) {
                    acc.price = it.price;
                }
            }
            var out = [];
            for (var k = 0; k < order.length; k++) {
                out.push(byId[order[k]]);
            }
            return out;
        }

        function readLastCart() {
            try {
                var raw = sessionStorage.getItem('lastCart');
                if (raw === null || raw === '') {
                    return [];
                }
                var parsed = JSON.parse(raw);
                return Array.isArray(parsed) ? parsed : [];
            } catch (e) {
                return [];
            }
        }

        function writeLastCart(products) {
            try {
                sessionStorage.setItem('lastCart', JSON.stringify(products));
            } catch (e) {}
        }

        function nzmClearCart() {
            _nzm.run('ec:setAction', 'clear_cart');
            _nzm.run('send', 'event', 'detail view', 'click', 'clearCart');
            writeLastCart([]);
            if (!isProd) {
                console.log('newsman remarketing: clear cart sent');
            }
        }

        function nzmAddToCart(products) {
            // console.log('newsman remarketing: nzmAddToCart', JSON.stringify(products));
            _nzm.run('ec:setAction', 'clear_cart');
            _nzm.run('send', 'event', 'detail view', 'click', 'clearCart', null, function () {
                var sent = [];
                for (var i = 0; i < products.length; i++) {
                    var item = products[i];
                    if (item && item.hasOwnProperty('id')) {
                        // Pass a fresh copy to _nzm — Newsman's send pipeline mutates
                        // queued product objects, which would otherwise strip "name"
                        // from the entry we persist to sessionStorage.
                        _nzm.run('ec:addProduct', {
                            id: item.id,
                            name: item.name,
                            price: item.price,
                            quantity: item.quantity
                        });
                        sent.push({
                            id: item.id,
                            name: item.name,
                            price: item.price,
                            quantity: item.quantity
                        });
                    }
                }
                _nzm.run('ec:setAction', 'add');
                _nzm.run('send', 'event', 'UX', 'click', 'add to cart');
                writeLastCart(sent);
                if (!isProd) {
                    console.log('newsman remarketing: cart sent');
                }
            });
        }

        function extractProducts(payload) {
            if (!payload || typeof payload !== 'object') {
                return null;
            }
            if (!payload.cart || !Array.isArray(payload.cart.products)) {
                return null;
            }
            var mapped = [];
            for (var i = 0; i < payload.cart.products.length; i++) {
                var p = payload.cart.products[i];
                if (!p) {
                    continue;
                }
                var id = (p.id_product !== undefined && p.id_product !== null) ? String(p.id_product) : '';
                if (id === '') {
                    continue;
                }
                var name = (p.name !== undefined && p.name !== null) ? String(p.name) : '';
                var rawQty;
                if (p.cart_quantity !== undefined && p.cart_quantity !== null) {
                    rawQty = p.cart_quantity;
                } else if (p.quantity_wanted !== undefined && p.quantity_wanted !== null) {
                    rawQty = p.quantity_wanted;
                } else {
                    rawQty = p.quantity;
                }
                var qty = parseInt(rawQty, 10);
                if (isNaN(qty) || qty < 0) {
                    qty = 0;
                }
                var priceRaw;
                if (p.price_amount !== undefined && p.price_amount !== null) {
                    priceRaw = p.price_amount;
                } else if (p.price !== undefined && p.price !== null) {
                    priceRaw = p.price;
                } else {
                    priceRaw = 0;
                }
                var priceNum;
                if (typeof priceRaw === 'number') {
                    priceNum = priceRaw;
                } else {
                    var cleaned = String(priceRaw).replace(/[^0-9.,-]/g, '').replace(/\.(?=\d{3}(\D|$))/g, '').replace(',', '.');
                    priceNum = parseFloat(cleaned);
                }
                if (isNaN(priceNum)) {
                    priceNum = 0;
                }
                mapped.push({
                    id: id,
                    name: name,
                    price: priceNum,
                    quantity: qty
                });
            }
            var aggregated = aggregateProducts(mapped);
            // console.log('newsman remarketing: extractProducts mapped=', JSON.stringify(mapped), ' aggregated=', JSON.stringify(aggregated));
            return aggregated;
        }

        function processPayload(payload) {
            var products = extractProducts(payload);
            if (products === null) {
                return;
            }
            var lastCart = readLastCart();
            if (JSON.stringify(lastCart) === JSON.stringify(products)) {
                if (!isProd) {
                    console.log('newsman remarketing: cart unchanged');
                }
                return;
            }
            if (products.length === 0) {
                if (lastCart.length > 0) {
                    nzmClearCart();
                } else {
                    writeLastCart([]);
                }
                return;
            }
            nzmAddToCart(products);
        }

        function urlTargetsCart(urlStr) {
            if (typeof urlStr !== 'string' || urlStr === '') {
                return false;
            }
            var sameOrigin = (urlStr.indexOf(window.location.origin) === 0) || urlStr.indexOf('://') === -1;
            if (!sameOrigin) {
                return false;
            }
            if (urlStr.indexOf('module/newsmanv8/cart') >= 0) {
                return false;
            }
            if (/[?&]controller=cart(?:&|$)/.test(urlStr)) {
                return true;
            }
            var path = urlStr;
            var qIndex = path.indexOf('?');
            if (qIndex >= 0) {
                path = path.substring(0, qIndex);
            }
            var hIndex = path.indexOf('#');
            if (hIndex >= 0) {
                path = path.substring(0, hIndex);
            }
            return /(?:^|\/)cart\/?$/.test(path);
        }

        function tryParseJson(text) {
            if (typeof text !== 'string' || text === '') {
                return null;
            }
            var trimmed = text.replace(/^\s+/, '');
            if (trimmed.charAt(0) !== '{' && trimmed.charAt(0) !== '[') {
                return null;
            }
            try {
                return JSON.parse(text);
            } catch (e) {
                return null;
            }
        }

        function interceptFetch() {
            if (typeof window.fetch !== 'function') {
                return;
            }
            var origFetch = window.fetch;
            window.fetch = function () {
                var reqUrl = '';
                try {
                    var a0 = arguments[0];
                    reqUrl = typeof a0 === 'string' ? a0 : (a0 && a0.url) || '';
                } catch (e) {}
                var promise = origFetch.apply(this, arguments);
                promise.then(function (response) {
                    var finalUrl = (response && response.url) || reqUrl;
                    if (!urlTargetsCart(finalUrl)) {
                        return;
                    }
                    if (!response || typeof response.clone !== 'function') {
                        return;
                    }
                    response.clone().text().then(function (text) {
                        var payload = tryParseJson(text);
                        if (payload !== null) {
                            processPayload(payload);
                        }
                    }).catch(function () {});
                }).catch(function () {});
                return promise;
            };
        }

        function interceptXhr() {
            var OrigXHR = window.XMLHttpRequest;
            if (typeof OrigXHR !== 'function') {
                return;
            }
            var origOpen = OrigXHR.prototype.open;
            var origSend = OrigXHR.prototype.send;
            OrigXHR.prototype.open = function (method, url) {
                try {
                    this.__nzmUrl = url;
                } catch (e) {}
                return origOpen.apply(this, arguments);
            };
            OrigXHR.prototype.send = function () {
                var self = this;
                try {
                    self.addEventListener('load', function () {
                        var finalUrl = (self.responseURL || self.__nzmUrl || '') + '';
                        if (!urlTargetsCart(finalUrl)) {
                            return;
                        }
                        if (self.status < 200 || self.status >= 300) {
                            return;
                        }
                        var text = '';
                        try {
                            text = self.responseText || '';
                        } catch (e) {
                            return;
                        }
                        var payload = tryParseJson(text);
                        if (payload !== null) {
                            processPayload(payload);
                        }
                    });
                } catch (e) {}
                return origSend.apply(this, arguments);
            };
        }

        function nzmGetCookie(name) {
            var needle = name + '=';
            var parts = document.cookie ? document.cookie.split(';') : [];
            for (var i = 0; i < parts.length; i++) {
                var p = parts[i].replace(/^\s+/, '');
                if (p.indexOf(needle) === 0) {
                    return p.substring(needle.length);
                }
            }
            return null;
        }

        function nzmSetSessionCookie(name, value, path) {
            // No Max-Age / Expires => browser-session lifetime. SameSite=Lax keeps
            // it on ordinary same-site navigations. Cleared when the browser closes.
            document.cookie = name + '=' + value + '; path=' + path + '; SameSite=Lax';
        }

        // Once per browser session, clear the Newsman remarketing cart and replay
        // the current PrestaShop cart into it. Runs when no nzm_cart_sync cookie
        // is present; the cookie is set unconditionally after the XHR resolves —
        // including on failure — so a broken endpoint cannot cause a re-request
        // on every navigation.
        function nzmSessionBootstrap() {
            var cookieName = 'nzm_cart_sync';
            var cookiePath = __NEWSMAN_COOKIE_PATH__;
            if (nzmGetCookie(cookieName)) {
                return;
            }

            var url = __NEWSMAN_CART_AJAX_URL__;
            var sep = url.indexOf('?') >= 0 ? '&t=' : '?t=';
            url = url + sep + Date.now();

            var xhr = new XMLHttpRequest();
            var markDone = function () {
                nzmSetSessionCookie(cookieName, '1', cookiePath);
            };

            try {
                xhr.open('GET', url, true);
            } catch (e) {
                markDone();
                return;
            }

            xhr.onload = function () {
                try {
                    if (xhr.status !== 200 && xhr.status !== 201) {
                        return;
                    }
                    var parsed;
                    try { parsed = JSON.parse(xhr.responseText); } catch (e) { parsed = null; }
                    if (Array.isArray(parsed) && parsed.length > 0) {
                        nzmAddToCart(parsed);
                    } else {
                        nzmClearCart();
                    }
                } finally {
                    markDone();
                }
            };

            xhr.onerror = markDone;
            xhr.ontimeout = markDone;

            try {
                xhr.send(null);
            } catch (e) {
                markDone();
            }
        }

        interceptFetch();
        interceptXhr();
        nzmSessionBootstrap();
    })();
</script>
JS;

        return strtr($js, [
            '__NEWSMAN_CART_AJAX_URL__' => json_encode(
                $cartAjaxUrl,
                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES
            ),
            '__NEWSMAN_COOKIE_PATH__' => json_encode(
                $cookiePath,
                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES
            ),
        ]) . "\n";
    }
}

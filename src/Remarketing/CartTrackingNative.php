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
     * clear_cart / add events. No background polling and no call to the module
     * cart endpoint.
     */
    public function getHtml(): string
    {
        $js = <<<'JS'
<script>
    _nzm.run('require', 'ec');

    (function () {
        var isProd = true;

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
            _nzm.run('ec:setAction', 'clear_cart');
            _nzm.run('send', 'event', 'detail view', 'click', 'clearCart', null, function () {
                var sent = [];
                for (var i = 0; i < products.length; i++) {
                    var item = products[i];
                    if (item && item.hasOwnProperty('id')) {
                        _nzm.run('ec:addProduct', item);
                        sent.push(item);
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
                var qty = parseInt(p.quantity, 10);
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
            return mapped;
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

        interceptFetch();
        interceptXhr();
    })();
</script>
JS;

        return $js . "\n";
    }
}

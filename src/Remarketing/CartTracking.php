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

class CartTracking
{
    /**
     * Generate the cart tracking JS HTML.
     */
    public function getHtml(string $cartAjaxUrl, bool $isCheckoutSuccess = false): string
    {
        $nzmTimeDiff = $isCheckoutSuccess ? 1000 : 5000;

        $js = <<<'JS'
<script>
    _nzm.run('require', 'ec');

    var ajaxurl = __NEWSMAN_CART_AJAX_URL__;
    var isProd = true;
    let lastCart = sessionStorage.getItem('lastCart');
    if (lastCart === null) {
        lastCart = {};
    }
    var lastCartFlag = false;
    var firstLoad = true;
    var bufferedXHR = false;
    var unlockClearCart = true;
    var isError = false;
    let secondsAllow = 5;
    let msRunAutoEvents = 5000;
    let msClick = new Date();
    var documentComparer = document.location.hostname;
    var documentUrl = document.URL;
    var sameOrigin = (documentUrl.indexOf(documentComparer) !== -1);
    let startTime, endTime;

    function startTimePassed() {
        startTime = new Date();
    }

    startTimePassed();

    function endTimePassed() {
        var flag = false;
        endTime = new Date();
        var timeDiff = endTime - startTime;
        timeDiff /= 1000;
        var seconds = Math.round(timeDiff);
        if (firstLoad) {
            flag = true;
        }
        if (seconds >= secondsAllow) {
            flag = true;
        }
        return flag;
    }

    if (sameOrigin) {
        NewsmanAutoEvents();
        setInterval(NewsmanAutoEvents, msRunAutoEvents);
        detectClicks();
        detectXHR();
        detectFetch();
    }

    function timestampGenerator(min, max) {
        min = Math.ceil(min);
        max = Math.floor(max);
        return Math.floor(Math.random() * (max - min + 1)) + min;
    }

    function NewsmanAutoEvents() {
        if (!endTimePassed()) {
            return;
        }
        if (isError && isProd === true) {
            return;
        }
        let xhr = new XMLHttpRequest();
        if (bufferedXHR || firstLoad) {
            var paramChar = '?t=';
            if (ajaxurl.indexOf('?') >= 0) {
                paramChar = '&t=';
            }
            var timestamp = paramChar + Date.now() + timestampGenerator(999, 999999999);
            try {
                xhr.open('GET', ajaxurl + timestamp, true);
            } catch (ex) {
                isError = true;
            }
            startTimePassed();
            xhr.onload = function () {
                if (xhr.status == 200 || xhr.status == 201) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                    } catch (error) {
                        isError = true;
                        return;
                    }
                    lastCart = JSON.parse(sessionStorage.getItem('lastCart'));
                    if (lastCart === null) {
                        lastCart = {};
                    }
                    if ((typeof lastCart !== 'undefined') && lastCart.length > 0 && (typeof response !== 'undefined') && response.length > 0) {
                        var objComparer = response;
                        var missingProp = false;
                        lastCart.forEach(e => {
                            if (!e.hasOwnProperty('name')) {
                                missingProp = true;
                            }
                        });
                        if (missingProp) {
                            objComparer.forEach(function (v) {
                                delete v.name;
                            });
                        }
                        if (JSON.stringify(lastCart) === JSON.stringify(objComparer)) {
                            lastCartFlag = true;
                        } else {
                            lastCartFlag = false;
                        }
                    }
                    if (response.length > 0 && lastCartFlag == false) {
                        nzmAddToCart(response);
                    } else if (response.length == 0 && lastCart.length > 0 && unlockClearCart) {
                        nzmClearCart();
                    }
                    firstLoad = false;
                    bufferedXHR = false;
                } else {
                    isError = true;
                }
            };
            try {
                xhr.send(null);
            } catch (ex) {
                isError = true;
            }
        }
    }

    function nzmClearCart() {
        _nzm.run('ec:setAction', 'clear_cart');
        _nzm.run('send', 'event', 'detail view', 'click', 'clearCart');
        sessionStorage.setItem('lastCart', JSON.stringify([]));
        unlockClearCart = false;
    }

    function nzmAddToCart(response) {
        _nzm.run('ec:setAction', 'clear_cart');
        detailviewEvent(response);
    }

    function detailviewEvent(response) {
        _nzm.run('send', 'event', 'detail view', 'click', 'clearCart', null, function () {
            var products = [];
            for (var item in response) {
                if (response[item].hasOwnProperty('id')) {
                    _nzm.run('ec:addProduct', response[item]);
                    products.push(response[item]);
                }
            }
            _nzm.run('ec:setAction', 'add');
            _nzm.run('send', 'event', 'UX', 'click', 'add to cart');
            sessionStorage.setItem('lastCart', JSON.stringify(products));
            unlockClearCart = true;
        });
    }

    function detectClicks() {
        window.addEventListener('click', function () {
            msClick = new Date();
        }, false);
    }

    function detectXHR() {
        var proxied = window.XMLHttpRequest.prototype.send;
        window.XMLHttpRequest.prototype.send = function () {
            var pointer = this;
            var validate = false;
            var timeValidate = false;
            var intervalId = window.setInterval(function () {
                if (pointer.readyState != 4) {
                    return;
                }
                var msClickPassed = new Date();
                var timeDiff = msClickPassed.getTime() - msClick.getTime();
                if (timeDiff > __NEWSMAN_NZM_TIME_DIFF__) {
                    validate = false;
                } else {
                    timeValidate = true;
                }
                var _location = pointer.responseURL;
                if (timeValidate) {
                    if (_location.indexOf('module/newsmanv8/cart') >= 0) {
                        validate = false;
                    } else {
                        if (_location.indexOf(window.location.origin) !== -1) {
                            validate = true;
                        }
                    }
                    if (validate) {
                        bufferedXHR = true;
                        NewsmanAutoEvents();
                    }
                }
                clearInterval(intervalId);
            }, 1);
            return proxied.apply(this, [].slice.call(arguments));
        };
    }

    function detectFetch() {
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
                var validate = false;
                var timeValidate = false;

                var msClickPassed = new Date();
                var timeDiff = msClickPassed.getTime() - msClick.getTime();
                if (timeDiff > __NEWSMAN_NZM_TIME_DIFF__) {
                    validate = false;
                } else {
                    timeValidate = true;
                }

                var _location = (response && response.url) || reqUrl;

                if (timeValidate) {
                    if (_location.indexOf('module/newsmanv8/cart') >= 0) {
                        validate = false;
                    } else {
                        if (_location.indexOf(window.location.origin) !== -1) {
                            validate = true;
                        }
                    }

                    if (validate) {
                        bufferedXHR = true;
                        NewsmanAutoEvents();
                    }
                }
            }).catch(function () {});

            return promise;
        };
    }
</script>
JS;

        return strtr($js, [
            '__NEWSMAN_CART_AJAX_URL__' => json_encode(
                $cartAjaxUrl,
                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES
            ),
            '__NEWSMAN_NZM_TIME_DIFF__' => (string) $nzmTimeDiff,
        ]) . "\n";
    }
}

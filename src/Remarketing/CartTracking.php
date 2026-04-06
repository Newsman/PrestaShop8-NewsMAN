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
        $run = JsHelper::getRunFunc();
        $nzmTimeDiff = $isCheckoutSuccess ? 1000 : 5000;

        $js = <<<JS
<script>
    {$run}('require', 'ec');

    var ajaxurl = '{$cartAjaxUrl}';
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
        {$run}('ec:setAction', 'clear_cart');
        {$run}('send', 'event', 'detail view', 'click', 'clearCart');
        sessionStorage.setItem('lastCart', JSON.stringify([]));
        unlockClearCart = false;
    }

    function nzmAddToCart(response) {
        {$run}('ec:setAction', 'clear_cart');
        detailviewEvent(response);
    }

    function detailviewEvent(response) {
        {$run}('send', 'event', 'detail view', 'click', 'clearCart', null, function () {
            var products = [];
            for (var item in response) {
                if (response[item].hasOwnProperty('id')) {
                    {$run}('ec:addProduct', response[item]);
                    products.push(response[item]);
                }
            }
            {$run}('ec:setAction', 'add');
            {$run}('send', 'event', 'UX', 'click', 'add to cart');
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
                if (timeDiff > {$nzmTimeDiff}) {
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
</script>
JS;

        return $js . "\n";
    }
}

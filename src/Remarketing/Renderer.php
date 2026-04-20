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
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Renderer
{
    protected Config $config;
    protected TrackingScript $trackingScript;
    protected CartTracking $cartTracking;
    protected CartTrackingNative $cartTrackingNative;
    protected CustomerIdentify $customerIdentify;
    protected ProductView $productView;
    protected PageView $pageView;
    protected CategoryView $categoryView;
    protected Purchase $purchase;

    public function __construct(
        Config $config,
        TrackingScript $trackingScript,
        CartTracking $cartTracking,
        CartTrackingNative $cartTrackingNative,
        CustomerIdentify $customerIdentify,
        ProductView $productView,
        PageView $pageView,
        CategoryView $categoryView,
        Purchase $purchase,
    ) {
        $this->config = $config;
        $this->trackingScript = $trackingScript;
        $this->cartTracking = $cartTracking;
        $this->cartTrackingNative = $cartTrackingNative;
        $this->customerIdentify = $customerIdentify;
        $this->productView = $productView;
        $this->pageView = $pageView;
        $this->categoryView = $categoryView;
        $this->purchase = $purchase;
    }

    /**
     * Render the main tracking script (nzm_config + remarketing JS + settings).
     * Called from hookDisplayAfterBodyOpeningTag.
     */
    public function renderTrackingScript(\Context $context): string
    {
        $shopId = (int) ($context->shop->id ?? \Shop::getContextShopID());
        $shopConstraint = Config::shopConstraint($shopId ?: null);

        if (!$this->config->isRemarketingActive($shopConstraint)) {
            return '';
        }

        $currencyCode = $context->currency->iso_code ?? '';

        $html = $this->trackingScript->getHtml($shopConstraint, $currencyCode);

        $hookResult = \Hook::exec(
            'actionNewsmanRemarketingRender',
            ['type' => 'tracking_script', 'html' => $html],
            null,
            false,
            true,
            false,
            null,
            true
        );
        if (is_array($hookResult) && isset($hookResult['html'])) {
            $html = $hookResult['html'];
        }

        return $html;
    }

    /**
     * Render cart tracking + customer identify + page-specific JS.
     * Called from hookDisplayBeforeBodyClosingTag.
     */
    public function renderBodyClosingTag(\Context $context): string
    {
        $shopConstraint = Config::shopConstraint((int) $context->shop->id ?: null);

        if (!$this->config->isRemarketingActive($shopConstraint)) {
            return '';
        }

        $controllerName = $this->getControllerName($context);
        $isCheckoutSuccess = ($controllerName === 'order-confirmation');

        $output = '';

        if ($this->config->isThemeCartCompatibility($shopConstraint)) {
            $cartUrl = $this->getCartAjaxUrl($context);
            $output .= $this->cartTracking->getHtml($cartUrl, $isCheckoutSuccess);
        } else {
            $output .= $this->cartTrackingNative->getHtml();
        }

        if (!$isCheckoutSuccess && $context->customer->isLogged()) {
            $output .= $this->customerIdentify->getHtml(
                $context->customer->email,
                $context->customer->firstname,
                $context->customer->lastname
            );
        }

        $langId = (int) $context->language->id;

        switch ($controllerName) {
            case 'product':
                $productId = (int) \Tools::getValue('id_product');
                if ($productId > 0) {
                    $output .= $this->productView->getHtml($productId, $langId);
                }
                $output .= $this->pageView->getHtml();
                break;

            case 'category':
                $output .= $this->renderCategoryView($context, $langId);
                $output .= $this->pageView->getHtml();
                break;

            default:
                if (!$isCheckoutSuccess) {
                    $output .= $this->pageView->getHtml();
                }
                break;
        }

        $hookResult = \Hook::exec(
            'actionNewsmanRemarketingRender',
            ['type' => 'body_closing', 'html' => $output],
            null,
            false,
            true,
            false,
            null,
            true
        );
        if (is_array($hookResult) && isset($hookResult['html'])) {
            $output = $hookResult['html'];
        }

        return $output;
    }

    /**
     * Render purchase tracking JS.
     * Called from hookDisplayOrderConfirmation.
     */
    public function renderPurchaseTracking(\Order $order, ?ShopConstraint $shopConstraint = null): string
    {
        if (!$this->config->isRemarketingActive($shopConstraint)) {
            return '';
        }

        $html = $this->purchase->getHtml($order, $shopConstraint);

        $hookResult = \Hook::exec(
            'actionNewsmanRemarketingRender',
            ['type' => 'purchase', 'html' => $html, 'order_id' => (int) $order->id],
            null,
            false,
            true,
            false,
            null,
            true
        );
        if (is_array($hookResult) && isset($hookResult['html'])) {
            $html = $hookResult['html'];
        }

        return $html;
    }

    protected function getControllerName(\Context $context): string
    {
        if (isset($context->controller) && isset($context->controller->php_self)) {
            return (string) $context->controller->php_self;
        }

        return '';
    }

    protected function getCartAjaxUrl(\Context $context): string
    {
        return rtrim($context->link->getBaseLink(), '/')
            . '/index.php?fc=module&module=newsmanv8&controller=cart';
    }

    /**
     * @return string
     */
    protected function renderCategoryView(\Context $context, int $langId): string
    {
        $categoryId = (int) \Tools::getValue('id_category');
        if ($categoryId <= 0) {
            return '';
        }

        $products = $this->getCategoryProducts($context);
        if (empty($products)) {
            return '';
        }

        return $this->categoryView->getHtml($categoryId, $products, $langId);
    }

    /**
     * @return array<array<string, mixed>>
     */
    protected function getCategoryProducts(\Context $context): array
    {
        if (!isset($context->smarty)) {
            return [];
        }

        $products = $context->smarty->getTemplateVars('listing');
        if (isset($products['products'])) {
            return is_array($products['products']) ? $products['products'] : [];
        }

        $products = $context->smarty->getTemplateVars('products');

        return is_array($products) ? $products : [];
    }
}

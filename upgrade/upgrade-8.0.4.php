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
if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\Module\Newsmanv8\Config;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;

/**
 * Seed the Theme Cart Compatibility setting to enabled (1) for any shop
 * where it is not already set, so existing installs keep the current
 * remarketing cart behaviour after upgrading to 8.0.4.
 *
 * @param Newsmanv8 $module
 *
 * @return bool
 */
function upgrade_module_8_0_4($module)
{
    /** @var PrestaShop\PrestaShop\Adapter\Configuration $configuration */
    $configuration = $module->get('prestashop.adapter.legacy.configuration');

    $allShops = ShopConstraint::allShops();
    $currentAll = $configuration->get(Config::KEY_REMARKETING_THEME_CART_COMPATIBILITY, null, $allShops);
    if ($currentAll === null || $currentAll === false || $currentAll === '') {
        $configuration->set(Config::KEY_REMARKETING_THEME_CART_COMPATIBILITY, 1, $allShops);
    }

    foreach (Shop::getShops(true, null, true) as $shopId) {
        $sc = ShopConstraint::shop((int) $shopId);
        $current = $configuration->get(Config::KEY_REMARKETING_THEME_CART_COMPATIBILITY, null, $sc);
        if ($current === null || $current === false || $current === '') {
            $configuration->set(Config::KEY_REMARKETING_THEME_CART_COMPATIBILITY, 1, $sc);
        }
    }

    return true;
}

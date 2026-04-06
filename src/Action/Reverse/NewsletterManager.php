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

namespace PrestaShop\Module\Newsmanv8\Action\Reverse;

use PrestaShop\Module\Newsmanv8\Config;
use PrestaShop\PrestaShop\Adapter\Configuration;
use PrestaShop\PrestaShop\Adapter\Shop\Context as ShopContext;

if (!defined('_PS_VERSION_')) {
    exit;
}

class NewsletterManager
{
    public function __construct(
        protected Config $config,
        protected Configuration $configuration,
        protected ShopContext $shopContext,
    ) {
    }

    public function subscribe(string $email, ?int $shopId = null): bool
    {
        $shopFilter = null !== $shopId ? ' AND id_shop = ' . (int) $shopId : '';

        $result = (bool) \Db::getInstance()->execute(
            'UPDATE ' . _DB_PREFIX_ . 'customer SET newsletter = 1, newsletter_date_add = NOW()'
            . " WHERE email = '" . pSQL($email) . "'" . $shopFilter
        );

        $affected = (int) \Db::getInstance()->Affected_Rows();
        if ($affected > 0) {
            return $result;
        }

        if (!$this->isEmailSubscriptionActive()) {
            return false;
        }

        $existing = \Db::getInstance()->getValue(
            'SELECT id FROM ' . _DB_PREFIX_ . 'emailsubscription'
            . " WHERE email = '" . pSQL($email) . "'" . $shopFilter
        );

        if ($existing) {
            return (bool) \Db::getInstance()->execute(
                'UPDATE ' . _DB_PREFIX_ . 'emailsubscription SET active = 1'
                . " WHERE email = '" . pSQL($email) . "'" . $shopFilter
            );
        }

        $idShop = $shopId ?? $this->config->getEffectiveShopId();
        $idShopGroup = (int) $this->shopContext->getGroupFromShop($idShop);
        $idLang = (int) \Configuration::get('PS_LANG_DEFAULT', null, $idShopGroup, $idShop);

        return (bool) \Db::getInstance()->execute(
            'INSERT INTO ' . _DB_PREFIX_ . 'emailsubscription'
            . ' (id_shop, id_shop_group, email, newsletter_date_add, ip_registration_newsletter, active, id_lang)'
            . ' VALUES ('
            . (int) $idShop . ', '
            . (int) $idShopGroup . ', '
            . "'" . pSQL($email) . "', "
            . 'NOW(), '
            . "'', "
            . '1, '
            . (int) $idLang
            . ')'
        );
    }

    public function unsubscribe(string $email, ?int $shopId = null): bool
    {
        $shopFilter = null !== $shopId ? ' AND id_shop = ' . (int) $shopId : '';

        \Db::getInstance()->execute(
            'UPDATE ' . _DB_PREFIX_ . 'customer SET newsletter = 0'
            . " WHERE email = '" . pSQL($email) . "'" . $shopFilter
        );

        if ($this->isEmailSubscriptionActive()) {
            \Db::getInstance()->execute(
                'UPDATE ' . _DB_PREFIX_ . 'emailsubscription SET active = 0'
                . " WHERE email = '" . pSQL($email) . "'" . $shopFilter
            );
        }

        return true;
    }

    /**
     * Subscribe an email across multiple shops.
     *
     * @param array<int> $shopIds
     */
    public function subscribeMultiShop(string $email, array $shopIds): bool
    {
        if (empty($shopIds)) {
            return $this->subscribe($email);
        }

        $result = true;
        foreach ($shopIds as $shopId) {
            $result = $this->subscribe($email, (int) $shopId) && $result;
        }

        return $result;
    }

    /**
     * Unsubscribe an email across multiple shops.
     *
     * @param array<int> $shopIds
     */
    public function unsubscribeMultiShop(string $email, array $shopIds): bool
    {
        if (empty($shopIds)) {
            return $this->unsubscribe($email);
        }

        $result = true;
        foreach ($shopIds as $shopId) {
            $result = $this->unsubscribe($email, (int) $shopId) && $result;
        }

        return $result;
    }

    protected function isEmailSubscriptionActive(): bool
    {
        $moduleId = (int) \Module::getModuleIdByName('ps_emailsubscription');
        if ($moduleId <= 0) {
            return false;
        }
        $shopId = $this->config->getEffectiveShopId();
        $sql = 'SELECT 1 FROM `' . _DB_PREFIX_ . 'module_shop` WHERE `id_module` = ' . $moduleId . ' AND `id_shop` = ' . $shopId;

        return (bool) \Db::getInstance()->getValue($sql);
    }
}

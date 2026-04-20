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

namespace PrestaShop\Module\Newsmanv8;

use PrestaShop\PrestaShop\Adapter\Configuration;
use PrestaShop\PrestaShop\Adapter\Shop\Context as ShopContext;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Config
{
    public const API_URL = 'https://ssl.newsman.app/api/';
    public const API_VERSION = '1.2';
    public const OAUTH_AUTHORIZE_URL = 'https://newsman.app/admin/oauth/authorize';
    public const OAUTH_TOKEN_URL = 'https://newsman.app/admin/oauth/token';
    public const OAUTH_CLIENT_ID = 'nzmplugin';
    public const PLATFORM_NAME = 'PrestaShop';

    /** @var array<int, bool>|null */
    protected ?array $moduleShopStatusMap = null;

    public const KEY_ACTIVE = 'NEWSMAN_ACTIVE';
    public const KEY_USER_ID = 'NEWSMAN_USER_ID';
    public const KEY_API_KEY = 'NEWSMAN_API_KEY';
    public const KEY_LIST_ID = 'NEWSMAN_LIST_ID';
    public const KEY_SEGMENT_ID = 'NEWSMAN_SEGMENT_ID';
    public const KEY_AUTHENTICATE_TOKEN = 'NEWSMAN_AUTHENTICATE_TOKEN';
    public const KEY_DOUBLE_OPTIN = 'NEWSMAN_DOUBLE_OPTIN';
    public const KEY_SEND_USER_IP = 'NEWSMAN_SEND_USER_IP';
    public const KEY_SERVER_IP = 'NEWSMAN_SERVER_IP';
    public const KEY_EXPORT_AUTH_HEADER_NAME = 'NEWSMAN_EXPORT_AUTH_HEADER_NAME';
    public const KEY_EXPORT_AUTH_HEADER_KEY = 'NEWSMAN_EXPORT_AUTH_HEADER_KEY';
    public const KEY_REMARKETING_STATUS = 'NEWSMAN_REMARKETING_STATUS';
    public const KEY_REMARKETING_ID = 'NEWSMAN_REMARKETING_ID';
    public const KEY_REMARKETING_ANONYMIZE_IP = 'NEWSMAN_REMARKETING_ANONYMIZE_IP';
    public const KEY_REMARKETING_SEND_TELEPHONE = 'NEWSMAN_REMARKETING_SEND_TELEPHONE';
    public const KEY_REMARKETING_THEME_CART_COMPATIBILITY = 'NEWSMAN_REMARKETING_THEME_CART_COMPATIBILITY';
    public const KEY_REMARKETING_SCRIPT_JS = 'NEWSMAN_REMARKETING_SCRIPT_JS';
    public const KEY_LOG_SEVERITY = 'NEWSMAN_LOG_SEVERITY';
    public const KEY_LOG_CLEAN_DAYS = 'NEWSMAN_LOG_CLEAN_DAYS';
    public const KEY_API_TIMEOUT = 'NEWSMAN_API_TIMEOUT';
    public const KEY_DEV_ACTIVE_USER_IP = 'NEWSMAN_DEV_ACTIVE_USER_IP';
    public const KEY_DEV_USER_IP = 'NEWSMAN_DEV_USER_IP';

    public const LOG_NONE = 1;
    public const LOG_ERROR = 400;
    public const LOG_WARNING = 300;
    public const LOG_NOTICE = 250;
    public const LOG_INFO = 200;
    public const LOG_DEBUG = 100;

    public const DEFAULT_API_TIMEOUT = 30;
    public const DEFAULT_LOG_CLEAN_DAYS = 30;

    public const MODULE_NAME = 'newsmanv8';
    public const CONFLICTING_MODULE_NEWSMANAPP = 'newsmanapp';
    public const CONFLICTING_MODULE_NEWSMAN = 'newsman';

    public function __construct(
        protected Configuration $configuration,
        protected ShopContext $shopContext,
    ) {
    }

    /**
     * @return array<string>
     */
    public static function getAllKeys(): array
    {
        return [
            self::KEY_ACTIVE,
            self::KEY_USER_ID,
            self::KEY_API_KEY,
            self::KEY_LIST_ID,
            self::KEY_SEGMENT_ID,
            self::KEY_AUTHENTICATE_TOKEN,
            self::KEY_DOUBLE_OPTIN,
            self::KEY_SEND_USER_IP,
            self::KEY_SERVER_IP,
            self::KEY_EXPORT_AUTH_HEADER_NAME,
            self::KEY_EXPORT_AUTH_HEADER_KEY,
            self::KEY_REMARKETING_STATUS,
            self::KEY_REMARKETING_ID,
            self::KEY_REMARKETING_ANONYMIZE_IP,
            self::KEY_REMARKETING_SEND_TELEPHONE,
            self::KEY_REMARKETING_THEME_CART_COMPATIBILITY,
            self::KEY_REMARKETING_SCRIPT_JS,
            self::KEY_LOG_SEVERITY,
            self::KEY_LOG_CLEAN_DAYS,
            self::KEY_API_TIMEOUT,
            self::KEY_DEV_ACTIVE_USER_IP,
            self::KEY_DEV_USER_IP,
        ];
    }

    public function getUserId(?ShopConstraint $shopConstraint = null): string
    {
        return (string) $this->configuration->get(self::KEY_USER_ID, '', $shopConstraint);
    }

    public function getApiKey(?ShopConstraint $shopConstraint = null): string
    {
        return (string) $this->configuration->get(self::KEY_API_KEY, '', $shopConstraint);
    }

    public function getListId(?ShopConstraint $shopConstraint = null): string
    {
        return (string) $this->configuration->get(self::KEY_LIST_ID, '', $shopConstraint);
    }

    public function getSegmentId(?ShopConstraint $shopConstraint = null): string
    {
        return (string) $this->configuration->get(self::KEY_SEGMENT_ID, '', $shopConstraint);
    }

    public function getAuthenticateToken(?ShopConstraint $shopConstraint = null): string
    {
        return (string) $this->configuration->get(self::KEY_AUTHENTICATE_TOKEN, '', $shopConstraint);
    }

    public function isDoubleOptin(?ShopConstraint $shopConstraint = null): bool
    {
        return (bool) $this->configuration->get(self::KEY_DOUBLE_OPTIN, false, $shopConstraint);
    }

    public function isSendUserIp(?ShopConstraint $shopConstraint = null): bool
    {
        return (bool) $this->configuration->get(self::KEY_SEND_USER_IP, false, $shopConstraint);
    }

    public function getServerIp(?ShopConstraint $shopConstraint = null): string
    {
        return (string) $this->configuration->get(self::KEY_SERVER_IP, '', $shopConstraint);
    }

    public function getExportAuthHeaderName(?ShopConstraint $shopConstraint = null): string
    {
        return (string) $this->configuration->get(self::KEY_EXPORT_AUTH_HEADER_NAME, '', $shopConstraint);
    }

    public function getExportAuthHeaderKey(?ShopConstraint $shopConstraint = null): string
    {
        return (string) $this->configuration->get(self::KEY_EXPORT_AUTH_HEADER_KEY, '', $shopConstraint);
    }

    public function isRemarketingActive(?ShopConstraint $shopConstraint = null): bool
    {
        if (!$this->isEnabled($shopConstraint)) {
            return false;
        }

        return (bool) $this->configuration->get(self::KEY_REMARKETING_STATUS, false, $shopConstraint)
            && !empty($this->getRemarketingId($shopConstraint));
    }

    public function getRemarketingId(?ShopConstraint $shopConstraint = null): string
    {
        return (string) $this->configuration->get(self::KEY_REMARKETING_ID, '', $shopConstraint);
    }

    public function isRemarketingAnonymizeIp(?ShopConstraint $shopConstraint = null): bool
    {
        return (bool) $this->configuration->get(self::KEY_REMARKETING_ANONYMIZE_IP, false, $shopConstraint);
    }

    public function isRemarketingSendTelephone(?ShopConstraint $shopConstraint = null): bool
    {
        return (bool) $this->configuration->get(self::KEY_REMARKETING_SEND_TELEPHONE, false, $shopConstraint);
    }

    /**
     * Theme Cart Compatibility: when enabled, remarketing JS uses background
     * polling + XHR/fetch interception against the module's own cart endpoint —
     * reliable on every theme at the cost of extra requests. When disabled, it
     * intercepts the native PrestaShop /cart JSON responses and reads
     * cart.products directly — lighter, but relies on the theme using standard
     * /cart ajax flows. The default (enabled=1) is seeded by install() and by
     * upgrade-8.0.4.php, so a missing value here means the user explicitly
     * saved it as disabled.
     */
    public function isThemeCartCompatibility(?ShopConstraint $shopConstraint = null): bool
    {
        return (bool) $this->configuration->get(self::KEY_REMARKETING_THEME_CART_COMPATIBILITY, false, $shopConstraint);
    }

    public function getRemarketingScriptJs(?ShopConstraint $shopConstraint = null): string
    {
        return (string) $this->configuration->get(self::KEY_REMARKETING_SCRIPT_JS, '', $shopConstraint);
    }

    public function getLogSeverity(?ShopConstraint $shopConstraint = null): int
    {
        $value = $this->configuration->get(self::KEY_LOG_SEVERITY, null, $shopConstraint);

        return $value !== false && $value !== null ? (int) $value : self::LOG_NONE;
    }

    public function getLogCleanDays(?ShopConstraint $shopConstraint = null): int
    {
        $value = $this->configuration->get(self::KEY_LOG_CLEAN_DAYS, null, $shopConstraint);

        return $value !== false && $value !== null ? (int) $value : self::DEFAULT_LOG_CLEAN_DAYS;
    }

    public function getApiTimeout(?ShopConstraint $shopConstraint = null): int
    {
        $value = $this->configuration->get(self::KEY_API_TIMEOUT, null, $shopConstraint);

        return $value !== false && $value !== null && (int) $value >= 5 ? (int) $value : self::DEFAULT_API_TIMEOUT;
    }

    public function isDevActiveUserIp(?ShopConstraint $shopConstraint = null): bool
    {
        return (bool) $this->configuration->get(self::KEY_DEV_ACTIVE_USER_IP, false, $shopConstraint);
    }

    public function getDevUserIp(?ShopConstraint $shopConstraint = null): string
    {
        return (string) $this->configuration->get(self::KEY_DEV_USER_IP, '', $shopConstraint);
    }

    public function isActive(?ShopConstraint $shopConstraint = null): bool
    {
        return (bool) $this->configuration->get(self::KEY_ACTIVE, true, $shopConstraint)
            && $this->isModuleEnabledForShop($shopConstraint);
    }

    /**
     * Check if the Newsman module is enabled in PrestaShop's Module Manager for a given shop.
     */
    public function isModuleEnabledForShop(?ShopConstraint $shopConstraint = null): bool
    {
        $shopId = $this->resolveShopId($shopConstraint);

        return $this->getModuleShopStatusMap()[$shopId] ?? false;
    }

    /**
     * Build a cached map of shop ID => enabled status from ps_module_shop.
     *
     * @return array<int, bool>
     */
    protected function getModuleShopStatusMap(): array
    {
        if ($this->moduleShopStatusMap !== null) {
            return $this->moduleShopStatusMap;
        }

        $this->moduleShopStatusMap = [];
        $moduleId = (int) \Module::getModuleIdByName('newsmanv8');
        if ($moduleId <= 0) {
            return $this->moduleShopStatusMap;
        }

        $rows = \Db::getInstance()->executeS(
            'SELECT `id_shop` FROM `' . _DB_PREFIX_ . 'module_shop` WHERE `id_module` = ' . $moduleId
        );

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $this->moduleShopStatusMap[(int) $row['id_shop']] = true;
            }
        }

        return $this->moduleShopStatusMap;
    }

    /**
     * Resolve a ShopConstraint to a concrete shop ID.
     */
    protected function resolveShopId(?ShopConstraint $shopConstraint): int
    {
        if ($shopConstraint !== null && $shopConstraint->getShopId()) {
            return $shopConstraint->getShopId()->getValue();
        }

        return $this->getEffectiveShopId();
    }

    /**
     * Get the current shop ID, falling back to the default shop when in "All shops" context.
     *
     * Shop::getContextShopID() returns null in "All shops" admin context.
     * This helper falls back to the default shop ID in that case.
     */
    public function getEffectiveShopId(): int
    {
        $shopId = (int) $this->shopContext->getContextShopID();
        if ($shopId > 0) {
            return $shopId;
        }

        $defaultShopId = (int) $this->configuration->get('PS_SHOP_DEFAULT');

        return $defaultShopId > 0 ? $defaultShopId : 1;
    }

    public function hasApiAccess(?ShopConstraint $shopConstraint = null): bool
    {
        return !empty($this->getUserId($shopConstraint)) && !empty($this->getApiKey($shopConstraint));
    }

    public function isEnabled(?ShopConstraint $shopConstraint = null): bool
    {
        if (!$this->isEnabledWithApiOnly($shopConstraint)) {
            return false;
        }

        if (empty($this->getListId($shopConstraint))) {
            return false;
        }

        return true;
    }

    public function isEnabledWithApiOnly(?ShopConstraint $shopConstraint = null): bool
    {
        if (!$this->isActive($shopConstraint)) {
            return false;
        }

        if (!$this->hasApiAccess($shopConstraint)) {
            return false;
        }

        return true;
    }

    public function isEnabledInAny(): bool
    {
        foreach (\Shop::getShops(true, null, true) as $shopId) {
            $sc = ShopConstraint::shop((int) $shopId);
            if ($this->isEnabled($sc)) {
                return true;
            }
        }

        return false;
    }

    public function getApiUrl(): string
    {
        return self::API_URL;
    }

    public function getApiVersion(): string
    {
        return self::API_VERSION;
    }

    /**
     * Generate a random alphanumeric token.
     */
    /**
     * Strip opening and closing script tags from JS content.
     * PrestaShop's pSQL() calls strip_tags() when html=false,
     * and purifyHTML() mangles JS when html=true.
     * Store raw JS without tags, add them back at render time.
     */
    public static function stripScriptTags(string $js): string
    {
        $js = preg_replace('#^\s*<script[^>]*>\s*#i', '', $js);
        $js = preg_replace('#\s*</script>\s*$#i', '', $js);

        return trim($js);
    }

    public static function generateToken(int $length = 32): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $len = strlen($chars);
        $token = '';
        for ($i = 0; $i < $length; ++$i) {
            $token .= $chars[random_int(0, $len - 1)];
        }

        return $token;
    }

    /**
     * Create a ShopConstraint from a shop ID integer.
     * Convenience helper for code that receives ?int $shopId.
     */
    public static function shopConstraint(?int $shopId): ?ShopConstraint
    {
        if (null === $shopId) {
            return null;
        }

        return ShopConstraint::shop($shopId);
    }

    /**
     * Get all shop IDs that share the same Newsman list ID.
     * Equivalent to Magento's getStoreIdsByListId().
     *
     * @return array<int>
     */
    public function getShopIdsByListId(string $listId): array
    {
        if (empty($listId)) {
            return [];
        }
        $shopIds = [];
        foreach (\Shop::getShops(true, null, true) as $shopId) {
            $sc = ShopConstraint::shop((int) $shopId);
            $shopListId = $this->getListId($sc);
            if ($shopListId === $listId && $this->isEnabled($sc)) {
                $shopIds[] = (int) $shopId;
            }
        }

        return $shopIds;
    }

    /**
     * Get all unique list IDs across all enabled shops.
     *
     * @return array<string>
     */
    public function getAllListIds(): array
    {
        $listIds = [];
        foreach (\Shop::getShops(true, null, true) as $shopId) {
            $sc = ShopConstraint::shop((int) $shopId);
            if (!$this->isEnabled($sc)) {
                continue;
            }
            $listId = $this->getListId($sc);
            if (!empty($listId) && !in_array($listId, $listIds, true)) {
                $listIds[] = $listId;
            }
        }

        return $listIds;
    }

    /**
     * Check if any shop in the given list has remarketing send telephone enabled.
     *
     * @param array<int> $shopIds
     */
    public function isRemarketingSendTelephoneByShopIds(array $shopIds): bool
    {
        foreach ($shopIds as $shopId) {
            if ($this->isRemarketingSendTelephone(ShopConstraint::shop((int) $shopId))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get ShopGroup sharing flags for a given shop.
     *
     * @return array{share_customer: bool, share_order: bool, share_stock: bool}
     */
    public function getShopGroupSharingFlags(int $shopId): array
    {
        $groupId = (int) $this->shopContext->getGroupFromShop($shopId);
        $group = $this->shopContext->ShopGroup($groupId);

        return [
            'share_customer' => (bool) $group->share_customer,
            'share_order' => (bool) $group->share_order,
            'share_stock' => (bool) $group->share_stock,
        ];
    }

    /**
     * Group shop IDs by their ShopGroup ID.
     *
     * @param array<int> $shopIds
     *
     * @return array<int, array<int>>
     */
    public function groupShopIdsByGroup(array $shopIds): array
    {
        $groups = [];
        foreach ($shopIds as $shopId) {
            $groupId = (int) $this->shopContext->getGroupFromShop((int) $shopId);
            $groups[$groupId][] = (int) $shopId;
        }

        return $groups;
    }

    /**
     * Check if linked shops span multiple ShopGroups.
     * Returns info for admin notice display, empty array if all in same group.
     *
     * @return array<array{group_id: int, group_name: string, shops: array<string>}>
     */
    public function getCrossGroupInfo(string $listId): array
    {
        $shopIds = $this->getShopIdsByListId($listId);
        $grouped = $this->groupShopIdsByGroup($shopIds);
        if (count($grouped) <= 1) {
            return [];
        }

        $info = [];
        foreach ($grouped as $groupId => $gShopIds) {
            $group = $this->shopContext->ShopGroup($groupId);
            $shopNames = [];
            foreach ($gShopIds as $sid) {
                $shop = new \Shop($sid);
                $shopNames[] = $shop->name;
            }
            $info[] = [
                'group_id' => $groupId,
                'group_name' => $group->name,
                'shops' => $shopNames,
            ];
        }

        return $info;
    }
}

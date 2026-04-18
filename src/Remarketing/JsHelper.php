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

class JsHelper
{
    public const JS_TRACK_RUN_FUNC = '_nzm.run';

    public static function escapeHtml(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8', false);
    }

    public static function escapeJs(?string $value): string
    {
        return addslashes((string) $value);
    }

    /**
     * @deprecated since 8.0.3 — inline the literal `_nzm.run` directly in templates.
     *             Kept for backward compatibility with third-party overrides.
     */
    public static function getRunFunc(): string
    {
        return self::escapeHtml(self::JS_TRACK_RUN_FUNC);
    }
}

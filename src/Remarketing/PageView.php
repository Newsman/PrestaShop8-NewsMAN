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

class PageView
{
    /**
     * Generate page view JS.
     */
    public function getJs(): string
    {
        return JsHelper::getRunFunc() . "('send', 'pageview');";
    }

    /**
     * Generate page view HTML.
     */
    public function getHtml(): string
    {
        return '<script>' . $this->getJs() . '</script>' . "\n";
    }
}

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

class CustomerIdentify
{
    /**
     * Generate customer identify JS.
     */
    public function getHtml(string $email, string $firstName = '', string $lastName = ''): string
    {
        if (empty($email)) {
            return '';
        }

        $js = '_nzm.identify({email: "' . JsHelper::escapeJs($email) . '"';
        if (!empty($firstName)) {
            $js .= ', first_name: "' . JsHelper::escapeJs($firstName) . '"';
        }
        if (!empty($lastName)) {
            $js .= ', last_name: "' . JsHelper::escapeJs($lastName) . '"';
        }
        $js .= '});';

        return '<script>' . $js . '</script>' . "\n";
    }
}

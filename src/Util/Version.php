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

namespace PrestaShop\Module\Newsmanv8\Util;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Version
{
    public static function getModuleVersion(): string
    {
        $module = \Module::getInstanceByName('newsmanv8');

        return $module ? $module->version : '';
    }

    public static function getIntegrationName(): string
    {
        return 'Newsman for PrestaShop';
    }
}

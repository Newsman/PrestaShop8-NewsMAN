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

namespace PrestaShop\Module\Newsmanv8\Service\Context;

if (!defined('_PS_VERSION_')) {
    exit;
}

class AbstractContext
{
    public const NULL_VALUE = 'null';

    public function getNullValue(): string
    {
        return self::NULL_VALUE;
    }
}

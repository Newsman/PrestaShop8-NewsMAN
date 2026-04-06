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

namespace PrestaShop\Module\Newsmanv8\Service;

use PrestaShop\Module\Newsmanv8\Service\Context\AbstractContext;

if (!defined('_PS_VERSION_')) {
    exit;
}

interface ServiceInterface
{
    /**
     * @return array<mixed>|string
     */
    public function execute(AbstractContext $context): array|string;
}

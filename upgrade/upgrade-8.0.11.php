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

/**
 * Register on existing installs the service hook that runs before Newsman anonymises an email
 * (customer delete or personal data erasure).
 *
 * @param Newsmanv8 $module
 *
 * @return bool
 */
function upgrade_module_8_0_11($module)
{
    return $module->registerHook('actionNewsmanServiceAnonymiseEmailBefore');
}

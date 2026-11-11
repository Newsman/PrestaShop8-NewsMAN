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
 * Register the Official GDPR compliance module (psgdpr) hooks on existing installs:
 * a personal data erasure unsubscribes the email from Newsman, and Newsman is listed
 * among the GDPR compliant modules.
 *
 * @param Newsmanv8 $module
 *
 * @return bool
 */
function upgrade_module_8_0_9($module)
{
    return $module->registerHook(['actionDeleteGDPRCustomer', 'registerGDPRConsent']);
}

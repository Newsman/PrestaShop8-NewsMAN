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

declare(strict_types=1);

namespace PrestaShop\Module\Newsmanv8\Controller\Admin;

use PrestaShop\Module\Newsmanv8\Config;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

if (!defined('_PS_VERSION_')) {
    exit;
}

class OauthLoginController extends FrameworkBundleAdminController
{
    /**
     * Detect conflicting legacy Newsman modules.
     *
     * @return list<string>
     */
    protected function detectConflictingModules(): array
    {
        $conflicting = [];
        $fs = new Filesystem();
        $modulesDir = _PS_MODULE_DIR_;

        foreach ([Config::CONFLICTING_MODULE_NEWSMANAPP, Config::CONFLICTING_MODULE_NEWSMAN] as $moduleName) {
            if ($fs->exists($modulesDir . $moduleName)) {
                $conflicting[] = $moduleName;
            }
        }

        return $conflicting;
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     *
     * @param Request $request
     *
     * @return Response
     */
    public function indexAction(Request $request): Response
    {
        /** @var Config $config */
        $config = $this->get(Config::class);
        $callbackUrl = $request->getSchemeAndHttpHost() . $this->generateUrl('newsmanv8_oauth_callback');

        return $this->render('@Modules/newsmanv8/views/templates/admin/step1_login.html.twig', [
            'oauthUrl' => Config::OAUTH_AUTHORIZE_URL
                . '?response_type=code&client_id=' . Config::OAUTH_CLIENT_ID
                . '&nzmplugin=' . Config::PLATFORM_NAME
                . '&scope=api&redirect_uri=' . urlencode($callbackUrl),
            'hasCredentials' => $config->hasApiAccess(),
            'conflictingModules' => $this->detectConflictingModules(),
            'moduleName' => Config::MODULE_NAME,
            'enableSidebar' => true,
            'help_link' => false,
        ]);
    }
}

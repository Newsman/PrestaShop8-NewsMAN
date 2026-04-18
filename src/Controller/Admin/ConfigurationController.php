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
use PrestaShop\Module\Newsmanv8\Logger;
use PrestaShop\Module\Newsmanv8\Service\Configuration\Remarketing\GetSettings as RemarketingGetSettings;
use PrestaShop\Module\Newsmanv8\Service\Context\Configuration\EmailList as EmailListContext;
use PrestaShop\Module\Newsmanv8\Util\LogFileReader;
use PrestaShop\Module\Newsmanv8\Util\Version;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ConfigurationController extends FrameworkBundleAdminController
{
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
        /** @var LogFileReader $logFileReader */
        $logFileReader = $this->get(LogFileReader::class);
        /** @var Logger $logger */
        $logger = $this->get(Logger::class);
        /** @var RemarketingGetSettings $remarketingGetSettings */
        $remarketingGetSettings = $this->get(RemarketingGetSettings::class);

        $logFileReader->cleanOldLogs();

        $formHandler = $this->get('newsmanv8.form.configuration_handler');
        $form = $formHandler->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $errors = $formHandler->save($form->getData());

            if (empty($errors)) {
                $this->addFlash('success', $this->trans('Successful update.', 'Admin.Notifications.Success'));
            } else {
                $this->flashErrors($errors);
            }

            return $this->redirectToRoute('newsmanv8_configuration');
        }

        $listId = $config->getListId();
        $crossGroupInfo = !empty($listId) ? $config->getCrossGroupInfo($listId) : [];

        $authenticateToken = $config->getAuthenticateToken();
        $maskedToken = '';
        if (!empty($authenticateToken)) {
            $len = strlen($authenticateToken);
            $maskedToken = $len > 2
                ? '*****' . substr($authenticateToken, -2)
                : str_repeat('*', $len);
        }

        return $this->render('@Modules/newsmanv8/views/templates/admin/configure.html.twig', [
            'configurationForm' => $form->createView(),
            'hasApiAccess' => $config->hasApiAccess(),
            'isConnected' => $config->isEnabledWithApiOnly() && !empty($form->get('list_id')->getConfig()->getOption('choices')),
            'isRemarketingConnected' => $this->isRemarketingConnected($config, $remarketingGetSettings, $logger),
            'moduleVersion' => Version::getModuleVersion(),
            'crossGroupInfo' => $crossGroupInfo,
            'maskedAuthenticateToken' => $maskedToken,
            'isMultistore' => \Shop::isFeatureActive(),
            'conflictingModules' => $this->detectConflictingModules(),
            'moduleName' => Config::MODULE_NAME,
            'enableSidebar' => true,
            'help_link' => false,
        ]);
    }

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

    protected function isRemarketingConnected(
        Config $config,
        RemarketingGetSettings $remarketingGetSettings,
        Logger $logger,
    ): bool {
        $storedId = $config->getRemarketingId();
        if (empty($storedId)) {
            return false;
        }

        try {
            $context = (new EmailListContext())
                ->setUserId($config->getUserId())
                ->setApiKey($config->getApiKey())
                ->setListId($config->getListId());

            $result = $remarketingGetSettings->execute($context);
            if (!is_array($result) || empty($result['site_id']) || empty($result['form_id'])) {
                return false;
            }

            $expectedId = $result['site_id'] . '-' . $config->getListId() . '-' . $result['form_id'] . '-' . ($result['control_list_hash'] ?? '');

            return $storedId === $expectedId;
        } catch (\Throwable $e) {
            $logger->logException($e);

            return false;
        }
    }
}

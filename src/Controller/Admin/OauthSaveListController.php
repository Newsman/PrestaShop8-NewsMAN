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
use PrestaShop\Module\Newsmanv8\Service\Configuration\Integration\SaveListIntegrationSetup;
use PrestaShop\Module\Newsmanv8\Service\Configuration\Remarketing\GetSettings as RemarketingGetSettings;
use PrestaShop\Module\Newsmanv8\Service\Context\Configuration\EmailList as EmailListContext;
use PrestaShop\Module\Newsmanv8\Service\Context\Configuration\SaveListIntegrationSetup as SaveListIntegrationSetupContext;
use PrestaShop\Module\Newsmanv8\Util\ServerIpResolver;
use PrestaShop\Module\Newsmanv8\Util\Version;
use PrestaShop\PrestaShop\Adapter\Configuration as ConfigurationAdapter;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

if (!defined('_PS_VERSION_')) {
    exit;
}

class OauthSaveListController extends FrameworkBundleAdminController
{
    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))", message="Access denied.", redirectRoute="newsmanv8_oauth_step1")
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function indexAction(Request $request): RedirectResponse
    {
        /** @var ConfigurationAdapter $configuration */
        $configuration = $this->get('prestashop.adapter.legacy.configuration');
        /** @var Config $newsmanConfig */
        $newsmanConfig = $this->get(Config::class);
        /** @var Logger $logger */
        $logger = $this->get(Logger::class);
        /** @var RemarketingGetSettings $remarketingGetSettings */
        $remarketingGetSettings = $this->get(RemarketingGetSettings::class);
        /** @var SaveListIntegrationSetup $saveListIntegrationSetup */
        $saveListIntegrationSetup = $this->get(SaveListIntegrationSetup::class);
        /** @var ServerIpResolver $serverIpResolver */
        $serverIpResolver = $this->get(ServerIpResolver::class);

        $userId = $request->request->get('user_id', '');
        $apiKey = $request->request->get('api_key', '');
        $listId = $request->request->get('list_id', '');

        if (empty($userId) || empty($apiKey) || empty($listId)) {
            $this->addFlash('error', 'Missing required fields.');

            return $this->redirectToRoute('newsmanv8_oauth_step1');
        }

        $configuration->set(Config::KEY_USER_ID, $userId);
        $configuration->set(Config::KEY_API_KEY, $apiKey);
        $configuration->set(Config::KEY_LIST_ID, $listId);

        $authenticateToken = Config::generateToken(32);
        $configuration->set(Config::KEY_AUTHENTICATE_TOKEN, $authenticateToken);

        $remarketingData = $this->fetchAndSaveRemarketingSettings(
            $configuration,
            $logger,
            $remarketingGetSettings,
            $userId,
            $apiKey,
            $listId
        );

        $this->callSaveListIntegrationSetup(
            $logger,
            $saveListIntegrationSetup,
            $serverIpResolver,
            $newsmanConfig,
            $userId,
            $apiKey,
            $listId,
            $authenticateToken
        );

        // Propagate authenticate token and remarketing settings to all linked shops
        $linkedShopIds = $newsmanConfig->getShopIdsByListId($listId);
        $this->propagateToLinkedShops($configuration, $linkedShopIds, $authenticateToken, $remarketingData);

        $this->addFlash('success', 'Connected to Newsman successfully.');

        return $this->redirectToRoute('newsmanv8_configuration');
    }

    /**
     * @return array{tracking_id: string, script_js: string}
     */
    public function fetchAndSaveRemarketingSettings(
        ConfigurationAdapter $configuration,
        Logger $logger,
        RemarketingGetSettings $remarketingGetSettings,
        string $userId,
        string $apiKey,
        string $listId,
    ): array {
        $remarketingData = ['tracking_id' => '', 'script_js' => ''];

        try {
            $context = (new EmailListContext())
                ->setUserId($userId)
                ->setApiKey($apiKey)
                ->setListId($listId);

            $result = $remarketingGetSettings->execute($context);
            if (is_array($result)) {
                $siteId = $result['site_id'] ?? '';
                $formId = $result['form_id'] ?? '';
                $controlListHash = $result['control_list_hash'] ?? '';

                if (!empty($siteId) && !empty($formId)) {
                    $trackingId = $siteId . '-' . $listId . '-' . $formId . '-' . $controlListHash;
                    $configuration->set(Config::KEY_REMARKETING_ID, $trackingId);
                    $configuration->set(Config::KEY_REMARKETING_STATUS, '1');
                    $remarketingData['tracking_id'] = $trackingId;
                }

                if (!empty($result['javascript'])) {
                    $scriptJs = Config::stripScriptTags($result['javascript']);
                    $configuration->set(Config::KEY_REMARKETING_SCRIPT_JS, $scriptJs);
                    $remarketingData['script_js'] = $scriptJs;
                }
            }
        } catch (\Throwable $e) {
            $logger->logException($e);
        }

        return $remarketingData;
    }

    /**
     * Propagate authenticate token and remarketing settings to all linked shops.
     *
     * @param array<int> $linkedShopIds
     * @param array{tracking_id: string, script_js: string} $remarketingData
     */
    protected function propagateToLinkedShops(
        ConfigurationAdapter $configuration,
        array $linkedShopIds,
        string $authenticateToken,
        array $remarketingData,
    ): void {
        foreach ($linkedShopIds as $linkedShopId) {
            $sc = ShopConstraint::shop((int) $linkedShopId);
            $configuration->set(Config::KEY_AUTHENTICATE_TOKEN, $authenticateToken, $sc);

            if (!empty($remarketingData['tracking_id'])) {
                $configuration->set(Config::KEY_REMARKETING_ID, $remarketingData['tracking_id'], $sc);
                $configuration->set(Config::KEY_REMARKETING_STATUS, '1', $sc);
            }
            if (!empty($remarketingData['script_js'])) {
                $configuration->set(Config::KEY_REMARKETING_SCRIPT_JS, $remarketingData['script_js'], $sc);
            }
        }
    }

    public function callSaveListIntegrationSetup(
        Logger $logger,
        SaveListIntegrationSetup $saveListIntegrationSetup,
        ServerIpResolver $serverIpResolver,
        Config $newsmanConfig,
        string $userId,
        string $apiKey,
        string $listId,
        string $authenticateToken,
    ): void {
        try {
            $shopUrl = (new \Shop($newsmanConfig->getEffectiveShopId()))->getBaseURL(true);
            $apiUrl = $shopUrl . 'index.php?fc=module&module=newsmanv8&controller=api';
            $serverIp = $serverIpResolver->resolve();

            $context = (new SaveListIntegrationSetupContext())
                ->setUserId($userId)
                ->setApiKey($apiKey)
                ->setListId($listId)
                ->setIntegration('prestashop')
                ->setPayload([
                    'api_url' => $apiUrl,
                    'api_key' => $authenticateToken,
                    'plugin_version' => Version::getModuleVersion(),
                    'platform_version' => _PS_VERSION_,
                    'platform_language' => 'PHP',
                    'platform_language_version' => phpversion(),
                    'platform_server_ip' => $serverIp,
                ]);

            $saveListIntegrationSetup->execute($context);
        } catch (\Throwable $e) {
            $logger->logException($e);
        }
    }
}

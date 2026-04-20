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

namespace PrestaShop\Module\Newsmanv8\Form;

use PrestaShop\Module\Newsmanv8\Config;
use PrestaShop\Module\Newsmanv8\Logger;
use PrestaShop\Module\Newsmanv8\Service\Configuration\Integration\SaveListIntegrationSetup;
use PrestaShop\Module\Newsmanv8\Service\Configuration\Remarketing\GetSettings as RemarketingGetSettings;
use PrestaShop\Module\Newsmanv8\Service\Context\Configuration\EmailList as EmailListContext;
use PrestaShop\Module\Newsmanv8\Service\Context\Configuration\SaveListIntegrationSetup as SaveListIntegrationSetupContext;
use PrestaShop\Module\Newsmanv8\Util\ServerIpResolver;
use PrestaShop\Module\Newsmanv8\Util\Version;
use PrestaShop\PrestaShop\Adapter\Configuration;
use PrestaShop\PrestaShop\Adapter\Shop\Context;
use PrestaShop\PrestaShop\Core\Configuration\AbstractMultistoreConfiguration;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use PrestaShop\PrestaShop\Core\Feature\FeatureInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ConfigurationDataConfiguration extends AbstractMultistoreConfiguration
{
    protected const CONFIGURATION_FIELDS = [
        'active',
        'user_id',
        'api_key',
        'list_id',
        'segment_id',
        'double_optin',
        'send_user_ip',
        'server_ip',
        'export_auth_header_name',
        'export_auth_header_key',
        'remarketing_status',
        'remarketing_id',
        'remarketing_anonymize_ip',
        'remarketing_send_telephone',
        'remarketing_theme_cart_compatibility',
        'log_severity',
        'log_clean_days',
        'api_timeout',
        'dev_active_user_ip',
        'dev_user_ip',
    ];

    protected const FIELD_MAP = [
        'active' => Config::KEY_ACTIVE,
        'user_id' => Config::KEY_USER_ID,
        'api_key' => Config::KEY_API_KEY,
        'list_id' => Config::KEY_LIST_ID,
        'segment_id' => Config::KEY_SEGMENT_ID,
        'double_optin' => Config::KEY_DOUBLE_OPTIN,
        'send_user_ip' => Config::KEY_SEND_USER_IP,
        'server_ip' => Config::KEY_SERVER_IP,
        'export_auth_header_name' => Config::KEY_EXPORT_AUTH_HEADER_NAME,
        'export_auth_header_key' => Config::KEY_EXPORT_AUTH_HEADER_KEY,
        'remarketing_status' => Config::KEY_REMARKETING_STATUS,
        'remarketing_id' => Config::KEY_REMARKETING_ID,
        'remarketing_anonymize_ip' => Config::KEY_REMARKETING_ANONYMIZE_IP,
        'remarketing_send_telephone' => Config::KEY_REMARKETING_SEND_TELEPHONE,
        'remarketing_theme_cart_compatibility' => Config::KEY_REMARKETING_THEME_CART_COMPATIBILITY,
        'log_severity' => Config::KEY_LOG_SEVERITY,
        'log_clean_days' => Config::KEY_LOG_CLEAN_DAYS,
        'api_timeout' => Config::KEY_API_TIMEOUT,
        'dev_active_user_ip' => Config::KEY_DEV_ACTIVE_USER_IP,
        'dev_user_ip' => Config::KEY_DEV_USER_IP,
    ];

    protected Logger $logger;
    protected SaveListIntegrationSetup $saveListIntegrationSetup;
    protected RemarketingGetSettings $remarketingGetSettings;
    protected ServerIpResolver $serverIpResolver;
    protected Config $newsmanConfig;

    public function __construct(
        Configuration $configuration,
        Context $shopContext,
        FeatureInterface $multistoreFeature,
        Logger $logger,
        SaveListIntegrationSetup $saveListIntegrationSetup,
        RemarketingGetSettings $remarketingGetSettings,
        ServerIpResolver $serverIpResolver,
        Config $newsmanConfig,
    ) {
        parent::__construct($configuration, $shopContext, $multistoreFeature);
        $this->logger = $logger;
        $this->saveListIntegrationSetup = $saveListIntegrationSetup;
        $this->remarketingGetSettings = $remarketingGetSettings;
        $this->serverIpResolver = $serverIpResolver;
        $this->newsmanConfig = $newsmanConfig;
    }

    public function getConfiguration(): array
    {
        $shopConstraint = $this->getShopConstraint();

        return [
            'active' => (bool) $this->configuration->get(Config::KEY_ACTIVE, true, $shopConstraint),
            'user_id' => (string) $this->configuration->get(Config::KEY_USER_ID, '', $shopConstraint),
            'api_key' => (string) $this->configuration->get(Config::KEY_API_KEY, '', $shopConstraint),
            'list_id' => (string) $this->configuration->get(Config::KEY_LIST_ID, '', $shopConstraint),
            'segment_id' => (string) $this->configuration->get(Config::KEY_SEGMENT_ID, '', $shopConstraint),
            'double_optin' => (bool) $this->configuration->get(Config::KEY_DOUBLE_OPTIN, false, $shopConstraint),
            'send_user_ip' => (bool) $this->configuration->get(Config::KEY_SEND_USER_IP, false, $shopConstraint),
            'server_ip' => (string) $this->configuration->get(Config::KEY_SERVER_IP, '', $shopConstraint),
            'export_auth_header_name' => (string) $this->configuration->get(Config::KEY_EXPORT_AUTH_HEADER_NAME, '', $shopConstraint),
            'export_auth_header_key' => (string) $this->configuration->get(Config::KEY_EXPORT_AUTH_HEADER_KEY, '', $shopConstraint),
            'remarketing_status' => (bool) $this->configuration->get(Config::KEY_REMARKETING_STATUS, false, $shopConstraint),
            'remarketing_id' => (string) $this->configuration->get(Config::KEY_REMARKETING_ID, '', $shopConstraint),
            'remarketing_anonymize_ip' => (bool) $this->configuration->get(Config::KEY_REMARKETING_ANONYMIZE_IP, false, $shopConstraint),
            'remarketing_send_telephone' => (bool) $this->configuration->get(Config::KEY_REMARKETING_SEND_TELEPHONE, false, $shopConstraint),
            'remarketing_theme_cart_compatibility' => $this->newsmanConfig->isThemeCartCompatibility($shopConstraint),
            'log_severity' => (int) $this->configuration->get(Config::KEY_LOG_SEVERITY, Config::LOG_NONE, $shopConstraint),
            'log_clean_days' => (int) $this->configuration->get(Config::KEY_LOG_CLEAN_DAYS, Config::DEFAULT_LOG_CLEAN_DAYS, $shopConstraint),
            'api_timeout' => (int) $this->configuration->get(Config::KEY_API_TIMEOUT, Config::DEFAULT_API_TIMEOUT, $shopConstraint),
            'dev_active_user_ip' => (bool) $this->configuration->get(Config::KEY_DEV_ACTIVE_USER_IP, false, $shopConstraint),
            'dev_user_ip' => (string) $this->configuration->get(Config::KEY_DEV_USER_IP, '', $shopConstraint),
        ];
    }

    public function updateConfiguration(array $configuration): array
    {
        if ($this->validateConfiguration($configuration)) {
            $shopConstraint = $this->getShopConstraint();

            $oldActive = (bool) $this->configuration->get(Config::KEY_ACTIVE, true, $shopConstraint);
            $oldUserId = (string) $this->configuration->get(Config::KEY_USER_ID, '', $shopConstraint);
            $oldApiKey = (string) $this->configuration->get(Config::KEY_API_KEY, '', $shopConstraint);
            $oldListId = (string) $this->configuration->get(Config::KEY_LIST_ID, '', $shopConstraint);

            foreach (self::FIELD_MAP as $field => $configKey) {
                $this->updateConfigurationValue($configKey, $field, $configuration, $shopConstraint);
            }

            // Re-read effective values from DB — they may now be inherited from parent
            // scope if multistore checkboxes were unchecked.
            $newActive = (bool) $this->configuration->get(Config::KEY_ACTIVE, true, $shopConstraint);
            $newUserId = (string) $this->configuration->get(Config::KEY_USER_ID, '', $shopConstraint);
            $newApiKey = (string) $this->configuration->get(Config::KEY_API_KEY, '', $shopConstraint);
            $newListId = (string) $this->configuration->get(Config::KEY_LIST_ID, '', $shopConstraint);

            $credentialsChanged = $newActive !== $oldActive || $newUserId !== $oldUserId || $newApiKey !== $oldApiKey || $newListId !== $oldListId;

            if ($credentialsChanged && !empty($newUserId) && !empty($newApiKey) && !empty($newListId)) {
                $this->syncIntegrationSetup($newUserId, $newApiKey, $newListId, $shopConstraint);
                $this->fetchAndSaveRemarketingSettings($newUserId, $newApiKey, $newListId, $shopConstraint);
                $this->propagateToLinkedShops($newListId, $shopConstraint);
            }
        }

        return [];
    }

    protected function buildResolver(): OptionsResolver
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined(self::CONFIGURATION_FIELDS);
        $resolver->setAllowedTypes('active', 'bool');
        $resolver->setAllowedTypes('user_id', ['string', 'null']);
        $resolver->setAllowedTypes('api_key', ['string', 'null']);
        $resolver->setAllowedTypes('list_id', ['string', 'null']);
        $resolver->setAllowedTypes('segment_id', ['string', 'null']);
        $resolver->setAllowedTypes('double_optin', 'bool');
        $resolver->setAllowedTypes('send_user_ip', 'bool');
        $resolver->setAllowedTypes('server_ip', ['string', 'null']);
        $resolver->setAllowedTypes('export_auth_header_name', ['string', 'null']);
        $resolver->setAllowedTypes('export_auth_header_key', ['string', 'null']);
        $resolver->setAllowedTypes('remarketing_status', 'bool');
        $resolver->setAllowedTypes('remarketing_id', ['string', 'null']);
        $resolver->setAllowedTypes('remarketing_anonymize_ip', 'bool');
        $resolver->setAllowedTypes('remarketing_send_telephone', 'bool');
        $resolver->setAllowedTypes('remarketing_theme_cart_compatibility', 'bool');
        $resolver->setAllowedTypes('log_severity', 'int');
        $resolver->setAllowedTypes('log_clean_days', 'int');
        $resolver->setAllowedTypes('api_timeout', 'int');
        $resolver->setAllowedTypes('dev_active_user_ip', 'bool');
        $resolver->setAllowedTypes('dev_user_ip', ['string', 'null']);

        return $resolver;
    }

    protected function fetchAndSaveRemarketingSettings(
        string $userId,
        string $apiKey,
        string $listId,
        ?ShopConstraint $shopConstraint = null,
    ): void {
        try {
            $context = (new EmailListContext())
                ->setUserId($userId)
                ->setApiKey($apiKey)
                ->setListId($listId);

            $result = $this->remarketingGetSettings->execute($context);
            if (is_array($result)) {
                $siteId = $result['site_id'] ?? '';
                $formId = $result['form_id'] ?? '';
                $controlListHash = $result['control_list_hash'] ?? '';

                if (!empty($siteId) && !empty($formId)) {
                    $trackingId = $siteId . '-' . $listId . '-' . $formId . '-' . $controlListHash;
                    $this->configuration->set(Config::KEY_REMARKETING_ID, $trackingId, $shopConstraint);
                    $this->configuration->set(Config::KEY_REMARKETING_STATUS, '1', $shopConstraint);
                }

                if (!empty($result['javascript'])) {
                    $this->configuration->set(Config::KEY_REMARKETING_SCRIPT_JS, Config::stripScriptTags($result['javascript']), $shopConstraint);
                }
            }
        } catch (\Throwable $e) {
            $this->logger->logException($e);
        }
    }

    /**
     * Propagate authenticate token and remarketing settings to all shops sharing the same list.
     */
    protected function propagateToLinkedShops(string $listId, ?ShopConstraint $shopConstraint = null): void
    {
        try {
            $linkedShopIds = $this->newsmanConfig->getShopIdsByListId($listId);
            if (count($linkedShopIds) <= 1) {
                return;
            }

            $authenticateToken = (string) $this->configuration->get(Config::KEY_AUTHENTICATE_TOKEN, '', $shopConstraint);
            $remarketingId = (string) $this->configuration->get(Config::KEY_REMARKETING_ID, '', $shopConstraint);
            $remarketingStatus = (string) $this->configuration->get(Config::KEY_REMARKETING_STATUS, '', $shopConstraint);
            $remarketingScriptJs = (string) $this->configuration->get(Config::KEY_REMARKETING_SCRIPT_JS, '', $shopConstraint);

            foreach ($linkedShopIds as $linkedShopId) {
                $sc = ShopConstraint::shop((int) $linkedShopId);
                if (!empty($authenticateToken)) {
                    $this->configuration->set(Config::KEY_AUTHENTICATE_TOKEN, $authenticateToken, $sc);
                }
                if (!empty($remarketingId)) {
                    $this->configuration->set(Config::KEY_REMARKETING_ID, $remarketingId, $sc);
                    $this->configuration->set(Config::KEY_REMARKETING_STATUS, $remarketingStatus, $sc);
                }
                if (!empty($remarketingScriptJs)) {
                    $this->configuration->set(Config::KEY_REMARKETING_SCRIPT_JS, $remarketingScriptJs, $sc);
                }
            }
        } catch (\Throwable $e) {
            $this->logger->logException($e);
        }
    }

    protected function syncIntegrationSetup(
        string $userId,
        string $apiKey,
        string $listId,
        ?ShopConstraint $shopConstraint = null,
    ): void {
        $authenticateToken = (string) $this->configuration->get(Config::KEY_AUTHENTICATE_TOKEN, '', $shopConstraint);
        if (empty($authenticateToken)) {
            $authenticateToken = Config::generateToken(32);
            $this->configuration->set(Config::KEY_AUTHENTICATE_TOKEN, $authenticateToken, $shopConstraint);
        }

        try {
            $shopUrl = (new \Shop($this->newsmanConfig->getEffectiveShopId()))->getBaseURL(true);
            $apiUrl = $shopUrl . 'index.php?fc=module&module=newsmanv8&controller=api';
            $serverIp = $this->serverIpResolver->resolve();

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

            $this->saveListIntegrationSetup->execute($context);
        } catch (\Throwable $e) {
            $this->logger->logException($e);
        }
    }
}

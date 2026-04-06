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

namespace PrestaShop\Module\Newsmanv8\Export\Retriever;

use PrestaShop\Module\Newsmanv8\Config;
use PrestaShop\Module\Newsmanv8\Export\V1\ApiV1Exception;
use PrestaShop\Module\Newsmanv8\Logger;
use PrestaShop\Module\Newsmanv8\Service\Configuration\Remarketing\GetSettings;
use PrestaShop\Module\Newsmanv8\Service\Context\Configuration\EmailList;
use PrestaShop\PrestaShop\Adapter\Configuration as ConfigurationAdapter;

/*
 * Handle inbound refresh.remarketing API v1 request.
 *
 * Fetches the remarketing script from the Newsman API via
 * remarketing.getSettings and stores it in configuration.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class RefreshRemarketing extends AbstractRetriever
{
    protected GetSettings $getSettingsService;
    protected ConfigurationAdapter $configuration;

    public function __construct(
        Config $config,
        Logger $logger,
        GetSettings $getSettingsService,
        ConfigurationAdapter $configuration,
    ) {
        parent::__construct($config, $logger);
        $this->getSettingsService = $getSettingsService;
        $this->configuration = $configuration;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<mixed>
     *
     * @throws ApiV1Exception
     */
    public function process(array $data = [], array $shopIds = []): array
    {
        $shopId = $shopIds[0] ?? null;
        $refresh = isset($data['refresh']) ? (int) $data['refresh'] : 0;
        if (1 !== $refresh) {
            throw new ApiV1Exception(9001, 'Missing or invalid "refresh" parameter: must be 1', 400);
        }

        $shopConstraint = Config::shopConstraint($shopId);

        $userId = $this->config->getUserId($shopConstraint);
        $apiKey = $this->config->getApiKey($shopConstraint);
        $listId = $this->config->getListId($shopConstraint);

        if (empty($userId) || empty($apiKey) || empty($listId)) {
            throw new ApiV1Exception(9002, 'Plugin is not configured: missing user ID, API key, or list ID', 400);
        }

        try {
            $context = (new EmailList())
                ->setUserId($userId)
                ->setApiKey($apiKey)
                ->setListId($listId);

            $settings = $this->getSettingsService->execute($context);
        } catch (\Exception $e) {
            $this->logger->logException($e);
            throw new ApiV1Exception(9003, 'Failed to retrieve remarketing settings from Newsman API', 500);
        }

        if (empty($settings) || !is_array($settings) || empty($settings['javascript'])) {
            throw new ApiV1Exception(9004, 'Newsman API returned empty remarketing script', 500);
        }

        $oldRemarketingJs = $this->config->getRemarketingScriptJs($shopConstraint);
        $newRemarketingJs = $settings['javascript'];

        $this->configuration->set(Config::KEY_REMARKETING_SCRIPT_JS, Config::stripScriptTags($newRemarketingJs), $shopConstraint);

        $siteId = $settings['site_id'] ?? '';
        $formId = $settings['form_id'] ?? '';
        $controlListHash = $settings['control_list_hash'] ?? '';

        if (!empty($siteId) && !empty($formId)) {
            $remarketingId = $siteId . '-' . $listId . '-' . $formId . '-' . $controlListHash;
            $this->configuration->set(Config::KEY_REMARKETING_ID, $remarketingId, $shopConstraint);
            $this->configuration->set(Config::KEY_REMARKETING_STATUS, '1', $shopConstraint);
        }

        $this->logger->info('refresh.remarketing: updated remarketing settings');

        return [
            'status' => 1,
            'old_remarketing_js' => !empty($oldRemarketingJs) ? $oldRemarketingJs : '',
            'new_remarketing_js' => $newRemarketingJs,
        ];
    }
}

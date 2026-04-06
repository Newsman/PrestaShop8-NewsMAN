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

namespace PrestaShop\Module\Newsmanv8\Service\Configuration\Integration;

use PrestaShop\Module\Newsmanv8\Service\AbstractService;
use PrestaShop\Module\Newsmanv8\Service\Context\AbstractContext;
use PrestaShop\Module\Newsmanv8\Service\Context\Configuration\SaveListIntegrationSetup as SaveListIntegrationSetupContext;

if (!defined('_PS_VERSION_')) {
    exit;
}

class SaveListIntegrationSetup extends AbstractService
{
    public const ENDPOINT = 'integration.saveListIntegrationSetup';

    /**
     * @param SaveListIntegrationSetupContext $context
     *
     * @return array<mixed>|string
     *
     * @throws \RuntimeException
     */
    public function execute(AbstractContext $context): array|string
    {
        if (empty($context->getListId())) {
            $e = new \RuntimeException('List ID is required.');
            $this->logger->logException($e);

            throw $e;
        }

        $apiContext = $this->createApiContext()
            ->setUserId($context->getUserId())
            ->setApiKey($context->getApiKey())
            ->setListId($context->getListId())
            ->setEndpoint(self::ENDPOINT);

        $this->dispatchServiceHookBefore($context);

        $client = $this->createApiClient();
        $result = $client->post(
            $apiContext,
            [],
            [
                'list_id' => $apiContext->getListId(),
                'integration' => $context->getIntegration(),
                'payload' => json_encode($context->getPayload()),
            ]
        );

        if ($client->hasError()) {
            throw new \RuntimeException($client->getErrorMessage(), $client->getErrorCode());
        }

        return $result;
    }
}

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

namespace PrestaShop\Module\Newsmanv8\Service\Configuration;

use PrestaShop\Module\Newsmanv8\Service\AbstractService;
use PrestaShop\Module\Newsmanv8\Service\Context\AbstractContext;
use PrestaShop\Module\Newsmanv8\Service\Context\Configuration\EmailList;

if (!defined('_PS_VERSION_')) {
    exit;
}

class GetSegmentAll extends AbstractService
{
    public const ENDPOINT = 'segment.all';

    /**
     * @param EmailList $context
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
            ->setEndpoint(self::ENDPOINT);

        $this->dispatchServiceHookBefore($context);

        $client = $this->createApiClient();
        $result = $client->get($apiContext, ['list_id' => $context->getListId()]);

        if ($client->hasError()) {
            throw new \RuntimeException($client->getErrorMessage(), $client->getErrorCode());
        }

        return $result;
    }
}

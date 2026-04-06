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
use PrestaShop\Module\Newsmanv8\Service\Context\GetByEmail as GetByEmailContext;

if (!defined('_PS_VERSION_')) {
    exit;
}

class GetByEmail extends AbstractService
{
    public const ENDPOINT = 'subscriber.getByEmail';

    /**
     * @param GetByEmailContext $context
     *
     * @return array<mixed>|string
     *
     * @throws \RuntimeException
     */
    public function execute(AbstractContext $context): array|string
    {
        $this->validateEmail($context->getEmail());

        $apiContext = $this->createApiContext()
            ->setListId($context->getListId())
            ->setEndpoint(self::ENDPOINT);

        $this->logger->info(sprintf('Try to get by email %s', $context->getEmail()));

        $client = $this->createApiClient();
        $result = $client->get(
            $apiContext,
            [
                'list_id' => $apiContext->getListId(),
                'email' => $context->getEmail(),
            ]
        );

        if ($client->hasError()) {
            throw new \RuntimeException($client->getErrorMessage(), $client->getErrorCode());
        }

        $this->logger->info(sprintf('Done get by email %s', $context->getEmail()));

        return $result;
    }
}

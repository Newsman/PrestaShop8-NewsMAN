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
use PrestaShop\Module\Newsmanv8\Service\Context\SubscribeEmail as SubscribeEmailContext;

if (!defined('_PS_VERSION_')) {
    exit;
}

class SubscribeEmail extends AbstractService
{
    public const ENDPOINT = 'subscriber.saveSubscribe';

    /**
     * @param SubscribeEmailContext $context
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

        $this->logger->info(sprintf('Try to subscribe email %s', $context->getEmail()));

        $this->dispatchServiceHookBefore($context);

        $client = $this->createApiClient();
        $result = $client->post(
            $apiContext,
            [],
            [
                'list_id' => $apiContext->getListId(),
                'email' => $context->getEmail(),
                'firstname' => $context->getFirstname(),
                'lastname' => $context->getLastname(),
                'ip' => $context->getIp(),
                'props' => empty($context->getProperties()) ? '' : $context->getProperties(),
            ]
        );

        if ($client->hasError()) {
            throw new \RuntimeException($client->getErrorMessage(), $client->getErrorCode());
        }

        $this->logger->info(sprintf('Subscribed email %s', $context->getEmail()));

        return $result;
    }
}

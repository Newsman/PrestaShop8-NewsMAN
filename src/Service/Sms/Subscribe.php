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

namespace PrestaShop\Module\Newsmanv8\Service\Sms;

use PrestaShop\Module\Newsmanv8\Service\AbstractService;
use PrestaShop\Module\Newsmanv8\Service\Context\AbstractContext;
use PrestaShop\Module\Newsmanv8\Service\Context\Sms\Subscribe as SubscribeContext;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Subscribe extends AbstractService
{
    public const ENDPOINT = 'sms.saveSubscribe';

    /**
     * @param SubscribeContext $context
     *
     * @return array<mixed>|string
     *
     * @throws \RuntimeException
     */
    public function execute(AbstractContext $context): array|string
    {
        $apiContext = $this->createApiContext()
            ->setListId($context->getListId())
            ->setEndpoint(self::ENDPOINT);

        $this->logger->info(sprintf('Try to subscribe telephone %s', $context->getTelephone()));

        $client = $this->createApiClient();
        $result = $client->post(
            $apiContext,
            [],
            [
                'list_id' => $apiContext->getListId(),
                'telephone' => $context->getTelephone(),
                'firstname' => $context->getFirstname(),
                'lastname' => $context->getLastname(),
                'ip' => $context->getIp(),
                'props' => empty($context->getProperties()) ? '' : $context->getProperties(),
            ]
        );

        if ($client->hasError()) {
            throw new \RuntimeException($client->getErrorMessage(), $client->getErrorCode());
        }

        $this->logger->info(sprintf('Subscribed telephone %s', $context->getTelephone()));

        return $result;
    }
}

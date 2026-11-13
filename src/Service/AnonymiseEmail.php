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
use PrestaShop\Module\Newsmanv8\Service\Context\AnonymiseEmail as AnonymiseEmailContext;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Anonymise an email in a Newsman list. Newsman answers right away and anonymises the contact in the
 * background (its email is replaced and it is unsubscribed).
 */
class AnonymiseEmail extends AbstractService
{
    public const ENDPOINT = 'subscriber.anonymiseEmail';

    /**
     * @param AnonymiseEmailContext $context
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

        $this->logger->info(sprintf('Try to anonymise email %s in list %s', $context->getEmail(), $context->getListId()));

        $this->dispatchServiceHookBefore($context);

        $client = $this->createApiClient();
        $result = $client->post(
            $apiContext,
            [],
            [
                'list_id' => $apiContext->getListId(),
                'email' => $context->getEmail(),
            ]
        );

        if ($client->hasError()) {
            throw new \RuntimeException($client->getErrorMessage(), $client->getErrorCode());
        }

        $this->logger->info(sprintf('Anonymised email %s in list %s', $context->getEmail(), $context->getListId()));

        return $result;
    }
}

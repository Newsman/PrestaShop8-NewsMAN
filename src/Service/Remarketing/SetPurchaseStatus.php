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

namespace PrestaShop\Module\Newsmanv8\Service\Remarketing;

use PrestaShop\Module\Newsmanv8\Service\AbstractService;
use PrestaShop\Module\Newsmanv8\Service\Context\AbstractContext;
use PrestaShop\Module\Newsmanv8\Service\Context\Remarketing\SetPurchaseStatus as SetPurchaseStatusContext;

if (!defined('_PS_VERSION_')) {
    exit;
}

class SetPurchaseStatus extends AbstractService
{
    public const ENDPOINT = 'remarketing.setPurchaseStatus';

    /**
     * @param SetPurchaseStatusContext $context
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

        $this->logger->info(
            sprintf('Try to send order %s status %s', $context->getOrderId(), $context->getOrderStatus())
        );

        $this->dispatchServiceHookBefore($context);

        $client = $this->createApiClient();
        $result = $client->get(
            $apiContext,
            [
                'list_id' => $apiContext->getListId(),
                'order_id' => $context->getOrderId(),
                'status' => $context->getOrderStatus(),
            ]
        );

        if ($client->hasError()) {
            throw new \RuntimeException($client->getErrorMessage(), $client->getErrorCode());
        }

        $this->logger->info(
            sprintf('Sent order %s status %s', $context->getOrderId(), $context->getOrderStatus())
        );

        return $result;
    }
}

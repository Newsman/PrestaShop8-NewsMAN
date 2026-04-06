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

namespace PrestaShop\Module\Newsmanv8\Service\Segment;

use PrestaShop\Module\Newsmanv8\Service\AbstractService;
use PrestaShop\Module\Newsmanv8\Service\Context\AbstractContext;
use PrestaShop\Module\Newsmanv8\Service\Context\Segment\AddSubscriber as AddSubscriberContext;

if (!defined('_PS_VERSION_')) {
    exit;
}

class AddSubscriber extends AbstractService
{
    public const ENDPOINT = 'segment.addSubscriber';

    /**
     * @param AddSubscriberContext $context
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
            sprintf('Try to add to segment %s subscriber ID %s', $context->getSegmentId(), $context->getSubscriberId())
        );

        $this->dispatchServiceHookBefore($context);

        $client = $this->createApiClient();
        $result = $client->post(
            $apiContext,
            [],
            [
                'list_id' => $apiContext->getListId(),
                'segment_id' => $context->getSegmentId(),
                'subscriber_id' => $context->getSubscriberId(),
            ]
        );

        if ($client->hasError()) {
            throw new \RuntimeException($client->getErrorMessage(), $client->getErrorCode());
        }

        $this->logger->info(
            sprintf('Added to segment %s subscriber ID %s', $context->getSegmentId(), $context->getSubscriberId())
        );

        return $result;
    }
}

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

use PrestaShop\Module\Newsmanv8\Action\Reverse\NewsletterManager;
use PrestaShop\Module\Newsmanv8\Config;
use PrestaShop\Module\Newsmanv8\Export\V1\ApiV1Exception;
use PrestaShop\Module\Newsmanv8\Logger;

if (!defined('_PS_VERSION_')) {
    exit;
}

class SubscriberSubscribe extends AbstractRetriever implements RetrieverInterface
{
    protected NewsletterManager $newsletterManager;

    public function __construct(Config $config, Logger $logger, NewsletterManager $newsletterManager)
    {
        parent::__construct($config, $logger);
        $this->newsletterManager = $newsletterManager;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<mixed>
     */
    /**
     * @param array<int> $shopIds
     */
    public function process(array $data = [], array $shopIds = []): array
    {
        $email = isset($data['email']) ? trim((string) $data['email']) : '';
        if (empty($email)) {
            throw new ApiV1Exception(3100, 'Missing "email" parameter', 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ApiV1Exception(3101, 'Invalid email address: ' . $email, 400);
        }

        $this->logger->info(sprintf('subscriber.subscribe: %s, shop IDs [%s]', $email, implode(',', $shopIds)));

        $this->newsletterManager->subscribeMultiShop($email, $shopIds);

        return ['success' => true, 'email' => $email];
    }
}

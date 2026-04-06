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

class SubscriberUnsubscribe extends AbstractRetriever implements RetrieverInterface
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
            throw new ApiV1Exception(3200, 'Missing "email" parameter', 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ApiV1Exception(3201, 'Invalid email address: ' . $email, 400);
        }

        $this->logger->info(sprintf('subscriber.unsubscribe: %s, shop IDs [%s]', $email, implode(',', $shopIds)));

        $this->newsletterManager->unsubscribeMultiShop($email, $shopIds);

        return ['success' => true, 'email' => $email];
    }
}

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

namespace PrestaShop\Module\Newsmanv8;

use PrestaShop\Module\Newsmanv8\Action\Reverse\NewsletterManager;
use PrestaShop\Module\Newsmanv8\Export\Renderer;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Webhooks
{
    protected Logger $logger;
    protected Renderer $renderer;
    protected NewsletterManager $newsletterManager;
    protected Config $config;

    public function __construct(Logger $logger, Renderer $renderer, NewsletterManager $newsletterManager, Config $config)
    {
        $this->logger = $logger;
        $this->renderer = $renderer;
        $this->newsletterManager = $newsletterManager;
        $this->config = $config;
    }

    /**
     * Process incoming Newsman webhook events.
     *
     * @param array<mixed>|string $events
     */
    public function execute($events): void
    {
        try {
            if (is_string($events)) {
                $events = json_decode(html_entity_decode($events), true);
            }

            if (!is_array($events)) {
                $this->logger->error('Invalid events format');
                $this->renderer->displayJson(['error' => 'Invalid events format']);

                return;
            }

            $this->logger->info('Processing newsman webhooks');

            $result = [];

            foreach ($events as $event) {
                if (!isset($event['type'])) {
                    continue;
                }

                $this->logger->info(sprintf('Processing webhook event type: %s', $event['type']));

                \Hook::exec('actionNewsmanWebhookEvent', [
                    'event' => $event,
                ]);

                switch ($event['type']) {
                    case 'unsub':
                        $result[] = $this->unsubscribe($event);
                        break;
                    case 'subscribe':
                    case 'subscribe_confirm':
                        $result[] = $this->subscribe($event);
                        break;
                    case 'import':
                        $result[] = [];
                        break;
                }
            }

            $this->renderer->displayJson($result);
        } catch (\Exception $e) {
            $this->logger->logException($e);

            $this->renderer->displayJson(['error' => $e->getMessage()]);
        }
    }

    /**
     * @param array<string, mixed> $event
     *
     * @return array<string, mixed>
     */
    protected function unsubscribe(array $event): array
    {
        if (!isset($event['data']['email'])) {
            return ['error' => 'Email not found'];
        }

        $email = $event['data']['email'];
        $shopIds = $this->resolveLinkedShopIds();
        $this->logger->debug(sprintf('Unsubscribe email: %s, shop IDs [%s]', $email, implode(',', $shopIds)));

        $this->newsletterManager->unsubscribeMultiShop($email, $shopIds);

        return ['success' => true, 'email' => $email];
    }

    /**
     * @param array<string, mixed> $event
     *
     * @return array<string, mixed>
     */
    protected function subscribe(array $event): array
    {
        if (!isset($event['data']['email'])) {
            return ['error' => 'Email not found'];
        }

        $email = $event['data']['email'];
        $shopIds = $this->resolveLinkedShopIds();
        $this->logger->debug(sprintf('Subscribe email: %s, shop IDs [%s]', $email, implode(',', $shopIds)));

        $this->newsletterManager->subscribeMultiShop($email, $shopIds);

        return ['success' => true, 'email' => $email];
    }

    /**
     * Resolve all shop IDs linked to the current shop's list.
     *
     * @return array<int>
     */
    protected function resolveLinkedShopIds(): array
    {
        $shopId = $this->config->getEffectiveShopId();
        $listId = $this->config->getListId(Config::shopConstraint($shopId));
        if (!empty($listId)) {
            $shopIds = $this->config->getShopIdsByListId($listId);
            if (!empty($shopIds)) {
                return $shopIds;
            }
        }

        return [$shopId];
    }
}

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

use PrestaShop\Module\Newsmanv8\Config;
use PrestaShop\Module\Newsmanv8\Logger;
use PrestaShop\Module\Newsmanv8\Service\Context\ExportCsvSubscribers as ExportCsvContext;
use PrestaShop\Module\Newsmanv8\Service\ExportCsvSubscribers;

if (!defined('_PS_VERSION_')) {
    exit;
}

class SendSubscribers extends SubscribersBase
{
    public const BATCH_SIZE = 9000;

    protected ExportCsvSubscribers $exportCsvSubscribers;

    public function __construct(Config $config, Logger $logger, ExportCsvSubscribers $exportCsvSubscribers)
    {
        parent::__construct($config, $logger);
        $this->exportCsvSubscribers = $exportCsvSubscribers;
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
        $subscribers = parent::process($data, $shopIds);
        if (empty($subscribers)) {
            return ['status' => 'No subscribers found.'];
        }

        $shopConstraint = Config::shopConstraint($shopIds[0] ?? null);

        $countSubscribers = count($subscribers);
        $batches = array_chunk($subscribers, self::BATCH_SIZE);
        unset($subscribers);

        $count = 0;
        $apiResults = [];
        foreach ($batches as $batch) {
            try {
                $csvBatch = [];
                foreach ($batch as $subscriber) {
                    $csvBatch[] = $this->formatForCsv($subscriber, $shopIds);
                }

                $context = new ExportCsvContext();
                $context->setListId($this->config->getListId($shopConstraint))
                    ->setSegmentId($this->config->getSegmentId($shopConstraint))
                    ->setCsvData($csvBatch)
                    ->setAdditionalFields([]);

                $this->exportCsvSubscribers->setShopConstraint($shopConstraint);
                $apiResults[] = $this->exportCsvSubscribers->execute($context);

                $count += count($csvBatch);
            } catch (\Exception $e) {
                $this->logger->logException($e);
            }
        }

        return [
            'status' => sprintf('Sent to NewsMAN %d subscribers out of a total of %d.', $count, $countSubscribers),
            'results' => $apiResults,
        ];
    }

    /**
     * @param array<string, mixed> $subscriber
     * @param array<int, string> $phones
     *
     * @return array<string, mixed>
     */
    /**
     * @param array<int> $shopIds
     */
    public function processSubscriber(array $subscriber, array $phones = [], array $shopIds = []): array
    {
        $row = [
            'email' => $subscriber['email'],
            'firstname' => $subscriber['firstname'],
            'lastname' => $subscriber['lastname'],
        ];

        if ($this->config->isRemarketingSendTelephoneByShopIds($shopIds)) {
            $subscriberId = (int) $subscriber['subscriber_id'];
            $row['phone'] = $phones[$subscriberId] ?? '';
        }

        $shopId = $shopIds[0] ?? null;
        $hookResult = \Hook::exec(
            'actionNewsmanExportRetrieverSendSubscribersProcessSubscriberAfter',
            ['row' => $row, 'subscriber' => $subscriber, 'shop_id' => $shopId, 'shop_ids' => $shopIds],
            null,
            false,
            true,
            false,
            null,
            true
        );
        if (is_array($hookResult) && isset($hookResult['row'])) {
            $row = $hookResult['row'];
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $subscriber
     * @param array<int> $shopIds
     *
     * @return array<string, mixed>
     */
    protected function formatForCsv(array $subscriber, array $shopIds = []): array
    {
        $row = [
            'email' => $subscriber['email'],
            'firstname' => $subscriber['firstname'],
            'lastname' => $subscriber['lastname'],
        ];

        if ($this->config->isRemarketingSendTelephoneByShopIds($shopIds)) {
            $row['phone'] = $subscriber['phone'] ?? '';
        }

        $row['additional'] = [];

        return $row;
    }
}

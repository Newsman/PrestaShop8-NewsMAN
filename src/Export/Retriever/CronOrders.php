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

if (!defined('_PS_VERSION_')) {
    exit;
}

class CronOrders extends SendOrders implements RetrieverInterface
{
    public const DEFAULT_PAGE_SIZE = 200;

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
        if (isset($data['limit'])) {
            return parent::process($data, $shopIds);
        }

        $lastDays = isset($data['last-days']) ? (int) $data['last-days'] : false;
        if ($lastDays !== false) {
            $data['created_at'] = [];
            $data['created_at']['from'] = date('Y-m-d', strtotime('-' . $lastDays . ' days'));
        }

        $data['limit'] = self::DEFAULT_PAGE_SIZE;
        $parameters = $this->processListParameters($data, $shopIds);

        $return = [];
        $count = (int) $this->getOrders($parameters, $shopIds, true);
        for ($start = 0; $start < $count; $start += $data['limit']) {
            $data['start'] = $start;
            $return[] = parent::process($data, $shopIds);
        }

        return $return;
    }
}

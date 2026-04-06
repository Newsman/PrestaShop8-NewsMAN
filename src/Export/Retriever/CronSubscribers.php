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

class CronSubscribers extends SendSubscribers implements RetrieverInterface
{
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

        $data['default_page_size'] = self::DEFAULT_PAGE_SIZE;
        $data['limit'] = self::DEFAULT_PAGE_SIZE;

        $parameters = $this->processListParameters($data, $shopIds);

        $return = [];
        $count = $this->getCountSubscribers($parameters, $shopIds);
        for ($start = 0; $start < $count; $start += $data['limit']) {
            $data['start'] = $start;
            $return[] = parent::process($data, $shopIds);
        }

        return $return;
    }
}

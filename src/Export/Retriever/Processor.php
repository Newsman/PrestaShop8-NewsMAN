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

if (!defined('_PS_VERSION_')) {
    exit;
}

class Processor
{
    protected Pool $pool;
    protected Authenticator $authenticator;
    protected Config $config;
    protected Logger $logger;

    public function __construct(Pool $pool, Authenticator $authenticator, Config $config, Logger $logger)
    {
        $this->pool = $pool;
        $this->authenticator = $authenticator;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<mixed>
     *
     * @throws \OutOfBoundsException
     */
    public function process(string $code, ?int $shopId = null, array $data = []): array
    {
        $tmpData = $data;
        unset($tmpData[Authenticator::API_KEY_PARAM]);
        $this->logger->info(json_encode($tmpData));

        $apiKey = $data[Authenticator::API_KEY_PARAM] ?? '';
        $shopConstraint = Config::shopConstraint($shopId);
        $this->authenticator->authenticate($apiKey, $shopConstraint);

        unset($data[Authenticator::API_KEY_PARAM]);

        $listId = $this->config->getListId($shopConstraint);
        $shopIds = [];
        if (!empty($listId)) {
            $shopIds = $this->config->getShopIdsByListId($listId);
        }
        if (empty($shopIds) && $shopId !== null) {
            $shopIds = [$shopId];
        }

        $this->logger->info(
            sprintf('Processing fetch data (%s) for shop IDs [%s] (list %s).', $code, implode(',', $shopIds), $listId)
        );

        $retriever = $this->pool->getRetrieverByCode($code);

        \Hook::exec('actionNewsmanBeforeExport', [
            'code' => $code,
            'data' => $data,
            'shop_id' => $shopIds[0] ?? $shopId,
            'shop_ids' => $shopIds,
        ]);

        $result = $retriever->process($data, $shopIds);

        $hookResult = \Hook::exec(
            'actionNewsmanAfterExport',
            ['code' => $code, 'result' => $result, 'shop_id' => $shopIds[0] ?? $shopId, 'shop_ids' => $shopIds],
            null,
            false,
            true,
            false,
            null,
            true
        );
        if (is_array($hookResult) && isset($hookResult['result'])) {
            $result = $hookResult['result'];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return string|false
     */
    public function getCodeByData(array $data)
    {
        if (!(isset($data['newsman']) && !empty($data['newsman']))) {
            return false;
        }

        return str_replace('.json', '', $data['newsman']);
    }
}

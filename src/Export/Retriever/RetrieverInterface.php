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

interface RetrieverInterface
{
    /**
     * @param array<string, mixed> $data
     * @param array<int> $shopIds
     *
     * @return array<mixed>
     */
    public function process(array $data = [], array $shopIds = []): array;
}

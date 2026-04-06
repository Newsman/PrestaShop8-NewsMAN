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

class SqlVersion extends AbstractRetriever implements RetrieverInterface
{
    public function process(array $data = [], array $shopIds = []): array
    {
        $version = (string) \Db::getInstance()->getValue('SELECT VERSION()');
        $version = preg_replace('/[^0-9.].*$/', '', $version);

        return ['version' => $version];
    }
}

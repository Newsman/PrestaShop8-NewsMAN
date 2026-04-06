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

namespace PrestaShop\Module\Newsmanv8\Export;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Renderer
{
    /**
     * @param array<mixed>|object $data
     */
    public function displayJson($data): void
    {
        header('Content-Type: application/json');
        header('Pragma: no-cache');
        header('Expires: Wed, 11 Jan 1994 05:00:00 GMT');
        header('Cache-Control: no-cache, must-revalidate, max-age=0, no-store, private');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header_remove('Last-Modified');

        echo json_encode($data);
        exit(0);
    }
}

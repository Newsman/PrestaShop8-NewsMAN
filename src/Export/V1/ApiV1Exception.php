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

namespace PrestaShop\Module\Newsmanv8\Export\V1;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ApiV1Exception extends \RuntimeException
{
    protected int $errorCode;
    protected int $httpStatus;

    public function __construct(int $errorCode, string $message, int $httpStatus = 500)
    {
        $this->errorCode = $errorCode;
        $this->httpStatus = $httpStatus;
        parent::__construct($message);
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}

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

namespace PrestaShop\Module\Newsmanv8\Service\Context\Segment;

use PrestaShop\Module\Newsmanv8\Service\Context\Store;

if (!defined('_PS_VERSION_')) {
    exit;
}

class AddSubscriber extends Store
{
    protected string $segmentId = '';
    protected string $subscriberId = '';

    public function setSegmentId(string $segmentId): static
    {
        $this->segmentId = $segmentId;

        return $this;
    }

    public function getSegmentId(): string
    {
        return $this->segmentId;
    }

    public function setSubscriberId(string $subscriberId): static
    {
        $this->subscriberId = $subscriberId;

        return $this;
    }

    public function getSubscriberId(): string
    {
        return $this->subscriberId;
    }
}

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

namespace PrestaShop\Module\Newsmanv8\Service\Context;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Store extends AbstractContext
{
    protected ?int $storeId = null;
    protected string $listId = '';
    protected string $segmentId = '';

    public function setStoreId(?int $storeId): static
    {
        $this->storeId = $storeId;

        return $this;
    }

    public function getStoreId(): ?int
    {
        return $this->storeId;
    }

    public function setListId(string $listId): static
    {
        $this->listId = $listId;

        return $this;
    }

    public function getListId(): string
    {
        return $this->listId;
    }

    public function setSegmentId(string $segmentId): static
    {
        $this->segmentId = $segmentId;

        return $this;
    }

    public function getSegmentId(): string
    {
        return $this->segmentId;
    }
}

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

namespace PrestaShop\Module\Newsmanv8\Api;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Context implements ContextInterface
{
    protected string $userId = '';
    protected string $apiKey = '';
    protected string $endpoint = '';
    protected string $listId = '';
    protected string $segmentId = '';

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function setApiKey(string $apiKey): static
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function setEndpoint(string $endpoint): static
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    public function getListId(): string
    {
        return $this->listId;
    }

    public function setListId(string $listId): static
    {
        $this->listId = $listId;

        return $this;
    }

    public function getSegmentId(): string
    {
        return $this->segmentId;
    }

    public function setSegmentId(string $segmentId): static
    {
        $this->segmentId = $segmentId;

        return $this;
    }
}

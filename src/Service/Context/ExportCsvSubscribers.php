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

class ExportCsvSubscribers extends Store
{
    /** @var array<int, array<string, mixed>> */
    protected array $csvData = [];

    /** @var array<int> */
    protected array $storeIds = [];

    /** @var array<string> */
    protected array $additionalFields = [];

    /**
     * @param array<int, array<string, mixed>> $data
     */
    public function setCsvData(array $data): static
    {
        $this->csvData = $data;

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCsvData(): array
    {
        return $this->csvData;
    }

    /**
     * @param array<int> $storeIds
     */
    public function setStoreIds(array $storeIds): static
    {
        $this->storeIds = $storeIds;

        return $this;
    }

    /**
     * @return array<int>
     */
    public function getStoreIds(): array
    {
        return $this->storeIds;
    }

    /**
     * @param array<string> $data
     */
    public function setAdditionalFields(array $data): static
    {
        $this->additionalFields = $data;

        return $this;
    }

    /**
     * @return array<string>
     */
    public function getAdditionalFields(): array
    {
        return $this->additionalFields;
    }
}

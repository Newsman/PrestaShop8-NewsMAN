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

namespace PrestaShop\Module\Newsmanv8\Service;

use PrestaShop\Module\Newsmanv8\Service\Context\AbstractContext;
use PrestaShop\Module\Newsmanv8\Service\Context\ExportCsvSubscribers as ExportCsvSubscribersContext;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ExportCsvSubscribers extends AbstractService
{
    public const ENDPOINT = 'import.csv';

    /**
     * @param ExportCsvSubscribersContext $context
     *
     * @return array<mixed>|string
     *
     * @throws \RuntimeException
     */
    public function execute(AbstractContext $context): array|string
    {
        $apiContext = $this->createApiContext()
            ->setListId($context->getListId())
            ->setEndpoint(self::ENDPOINT);

        $this->logger->info(
            sprintf('Try to export CSV with %s subscribers', count($context->getCsvData()))
        );

        $this->dispatchServiceHookBefore($context);

        $client = $this->createApiClient();
        $result = $client->post(
            $apiContext,
            [],
            [
                'list_id' => $context->getListId(),
                'segments' => !empty($context->getSegmentId())
                    ? [$context->getSegmentId()]
                    : $context->getNullValue(),
                'csv_data' => $this->serializeCsvData($context),
            ]
        );

        if ($client->hasError()) {
            throw new \RuntimeException($client->getErrorMessage(), $client->getErrorCode());
        }

        $this->logger->info(
            sprintf('Sent export CSV with %s subscribers', count($context->getCsvData()))
        );

        return $result;
    }

    public function serializeCsvData(ExportCsvSubscribersContext $context, string $source = 'PrestaShop'): string
    {
        $header = $this->getCsvHeader($context);
        $columnCount = count($header);
        $csvData = $context->getCsvData();
        $additionalFields = $context->getAdditionalFields();

        $csv = '"' . implode('","', $header) . "\"\n";
        foreach ($csvData as $key => $row) {
            $exportRow = array_combine($header, array_fill(0, $columnCount, ''));
            foreach ($row as $column => &$value) {
                if ('additional' !== $column) {
                    if (null === $value) {
                        $value = '';
                    }
                    $value = trim(str_replace('"', '', (string) $value));
                } elseif (null === $value) {
                    $value = [];
                }
            }
            unset($value);
            $row['source'] = $source;

            foreach ($additionalFields as $attribute) {
                $row[$attribute] = '';
                if (isset($row['additional'][$attribute])) {
                    $row[$attribute] = $row['additional'][$attribute];
                }
            }

            foreach ($exportRow as $exportKey => &$exportValue) {
                if (isset($row[$exportKey])) {
                    $exportValue = $row[$exportKey];
                }
            }
            unset($exportValue);

            $csv .= $this->getCsvLine($exportRow);
        }

        return $csv;
    }

    /**
     * @return array<string>
     */
    public function getCsvHeader(ExportCsvSubscribersContext $context): array
    {
        $header = ['email', 'firstname', 'lastname'];

        if ($this->config->isRemarketingSendTelephone($this->shopConstraint)) {
            $header[] = 'tel';
            $header[] = 'phone';
            $header[] = 'telephone';
            $header[] = 'billing_telephone';
            $header[] = 'shipping_telephone';
        }

        $header[] = 'source';

        foreach ($context->getAdditionalFields() as $attribute) {
            if (!in_array($attribute, $header, true)) {
                $header[] = $attribute;
            }
        }

        return $header;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function getCsvLine(array $row): string
    {
        unset($row['additional']);

        return '"' . implode('","', $row) . "\"\n";
    }
}

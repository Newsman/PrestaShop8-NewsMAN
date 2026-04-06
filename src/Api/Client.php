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

use PrestaShop\Module\Newsmanv8\Config;
use PrestaShop\Module\Newsmanv8\Logger;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Client implements ClientInterface
{
    protected Config $config;
    protected Logger $logger;
    protected ?int $status = null;
    protected int $errorCode = 0;
    protected string $errorMessage = '';

    public function __construct(Config $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<mixed>|string
     */
    public function get(ContextInterface $context, array $params = []): array|string
    {
        \Hook::exec('actionNewsmanApiClientGetParamsBefore', [
            'context' => $context,
            'params' => $params,
        ]);

        return $this->request($context, 'GET', $params);
    }

    /**
     * @param array<string, mixed> $getParams
     * @param array<string, mixed> $postParams
     *
     * @return array<mixed>|string
     */
    public function post(ContextInterface $context, array $getParams = [], array $postParams = []): array|string
    {
        \Hook::exec('actionNewsmanApiClientPostParamsBefore', [
            'context' => $context,
            'get_params' => $getParams,
            'post_params' => $postParams,
        ]);

        return $this->request($context, 'POST', $getParams, $postParams);
    }

    /**
     * @param array<string, mixed> $getParams
     * @param array<string, mixed> $postParams
     *
     * @return array<mixed>|string
     */
    protected function request(
        ContextInterface $context,
        string $method,
        array $getParams = [],
        array $postParams = [],
    ): array|string {
        $this->status = null;
        $this->errorMessage = '';
        $this->errorCode = 0;
        $result = [];

        \Hook::exec('actionNewsmanApiClientRequestParamsBefore', [
            'context' => $context,
            'method' => $method,
            'get_params' => $getParams,
            'post_params' => $postParams,
        ]);

        $url = $this->config->getApiUrl() . sprintf(
            '%s/rest/%s/%s/%s.json',
            $this->config->getApiVersion(),
            $context->getUserId(),
            $context->getApiKey(),
            $context->getEndpoint()
        );

        $logUrl = str_replace($context->getApiKey(), '****', $url);
        if (!empty($getParams)) {
            $url .= '?' . http_build_query($getParams);
            $logUrl .= '?' . http_build_query($getParams);
        }
        $logHash = uniqid();
        $this->logger->debug('[' . $logHash . '] ' . $logUrl);

        try {
            $startTime = microtime(true);
            $remoteResult = $this->execute($method, $url, $postParams);
            $elapsedMs = round((microtime(true) - $startTime) * 1000);
            $this->logger->debug(
                sprintf('[%s] Requested in %s', $logHash, $this->formatTimeDuration($elapsedMs))
            );

            if ('POST' === $method) {
                $this->logger->debug(json_encode($postParams));
            }

            if (!empty($remoteResult['error'])) {
                throw new \RuntimeException(is_array($remoteResult['error']) ? ($remoteResult['error']['message'] ?? 'cURL error') : $remoteResult['error'], (int) $remoteResult['status']);
            }

            $this->status = (int) $remoteResult['status'];
            if (200 === $this->status) {
                $result = json_decode($remoteResult['body'], true);
                $apiError = $this->parseApiError($result);
                if (false !== $apiError) {
                    $this->errorCode = (int) $apiError['code'];
                    $this->errorMessage = $apiError['message'];
                    $this->logger->warning($this->errorCode . ' | ' . $this->errorMessage);
                } else {
                    $this->logger->notice(json_encode($result));
                }
            } else {
                $this->errorCode = $this->status;
                if (stripos($remoteResult['body'], '{') !== false) {
                    $body = json_decode($remoteResult['body'], true);
                    $apiError = $this->parseApiError($body);
                    if (false !== $apiError) {
                        $this->errorCode = (int) $apiError['code'];
                        $this->errorMessage = $apiError['message'];
                    } else {
                        $this->errorMessage = 'Error: ' . $this->errorCode;
                    }
                }
                $this->logger->error($this->status . ' | ' . $remoteResult['body']);
            }
        } catch (\Throwable $e) {
            $this->errorCode = (int) $e->getCode();
            $this->errorMessage = $e->getMessage();
            $this->logger->logException($e);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $postParams
     *
     * @return array{body: string, headers: array<string, string>, error: mixed, status: int}
     */
    protected function execute(string $method, string $url, array $postParams = []): array
    {
        $timeout = $this->config->getApiTimeout();

        \Hook::exec('actionNewsmanApiClientExecuteCurlOptionsBefore', [
            'url' => $url,
            'method' => $method,
            'post_params' => $postParams,
        ]);

        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept-Charset: utf-8',
                'Accept: application/json',
                'Content-Type: application/json',
            ],
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => true,
        ];

        if ('POST' === strtoupper($method)) {
            $curlOptions[CURLOPT_POST] = true;
            $curlOptions[CURLOPT_POSTFIELDS] = json_encode($postParams);
        } else {
            $curlOptions[CURLOPT_HTTPGET] = true;
        }

        $ch = curl_init();
        curl_setopt_array($ch, $curlOptions);

        $response = curl_exec($ch);
        $error = '';

        if (curl_errno($ch)) {
            $error = [
                'name' => 'CURLE_' . curl_errno($ch),
                'message' => curl_strerror(curl_errno($ch)),
            ];
        }

        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $body = '';
        $responseHeaders = [];

        if (is_string($response)) {
            $parts = explode("\r\n\r\n", $response, 3);
            if (isset($parts[1])) {
                if ('HTTP/1.1 100 Continue' === $parts[0] && isset($parts[2])) {
                    $head = $parts[1];
                    $body = $parts[2];
                } else {
                    $head = $parts[0];
                    $body = $parts[1];
                }

                $headerLines = explode("\r\n", $head);
                array_shift($headerLines);
                foreach ($headerLines as $line) {
                    $colonPos = strpos($line, ':');
                    if (false !== $colonPos) {
                        $key = substr($line, 0, $colonPos);
                        $value = ltrim(substr($line, $colonPos + 1));
                        $responseHeaders[$key] = $value;
                    }
                }
            }
        }

        return [
            'body' => $body,
            'headers' => $responseHeaders,
            'error' => $error,
            'status' => $httpStatus,
        ];
    }

    /**
     * @param mixed $result
     *
     * @return array{code: int, message: string}|false
     */
    protected function parseApiError(mixed $result): array|false
    {
        if (!(is_array($result) && isset($result['err']))) {
            return false;
        }

        return [
            'code' => isset($result['code']) ? (int) $result['code'] : 0,
            'message' => $result['message'] ?? '',
        ];
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    public function hasError(): bool
    {
        return $this->errorCode > 0;
    }

    protected function formatTimeDuration(float $milliseconds): string
    {
        if ($milliseconds < 1000) {
            return sprintf('%d ms', $milliseconds);
        }

        $totalSeconds = $milliseconds / 1000;
        if ($totalSeconds < 60) {
            return sprintf('%.1f s', $totalSeconds);
        }

        $minutes = floor($totalSeconds / 60);
        $secondsRemainder = $totalSeconds - ($minutes * 60);

        return sprintf('%d min %.3f s', $minutes, $secondsRemainder);
    }
}

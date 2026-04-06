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

use PrestaShop\Module\Newsmanv8\Config;
use PrestaShop\Module\Newsmanv8\Export\Retriever\Authenticator;
use PrestaShop\Module\Newsmanv8\Export\Retriever\Processor;
use PrestaShop\Module\Newsmanv8\Export\V1\ApiV1Exception;
use PrestaShop\Module\Newsmanv8\Export\V1\PayloadParser;
use PrestaShop\Module\Newsmanv8\Logger;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Router
{
    protected Config $config;
    protected Logger $logger;
    protected Request $request;
    protected PayloadParser $payloadParser;
    protected Processor $processor;
    protected Renderer $renderer;

    public function __construct(
        Config $config,
        Logger $logger,
        Request $request,
        PayloadParser $payloadParser,
        Processor $processor,
        Renderer $renderer,
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->request = $request;
        $this->payloadParser = $payloadParser;
        $this->processor = $processor;
        $this->renderer = $renderer;
    }

    public function execute(): void
    {
        $rawBody = (string) file_get_contents('php://input');
        $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

        if ($this->payloadParser->isV1Payload($rawBody, $contentType)) {
            $this->executeV1($rawBody, $this->request->getShopId());

            return;
        }

        if (!$this->request->isExportRequest()) {
            return;
        }

        $shopId = $this->request->getShopId();
        $shopConstraint = Config::shopConstraint($shopId);

        if (!$this->config->isEnabled($shopConstraint)) {
            $this->renderer->displayJson([
                'status' => 403,
                'message' => 'API setting is not enabled in plugin',
            ]);
        }

        try {
            $parameters = $this->request->getRequestParameters();
            $code = $this->processor->getCodeByData($parameters);

            if ($code === false) {
                $this->renderer->displayJson([
                    'status' => 0,
                    'message' => 'Missing retriever code.',
                ]);

                return;
            }

            // Block legacy access for endpoints available in API v1.
            if (in_array($code, PayloadParser::$methodMap, true)) {
                $this->renderer->displayJson([
                    'error' => 'This endpoint is only available via API v1 (JSON POST).',
                ]);

                return;
            }

            $result = $this->processor->process($code, $shopId, $parameters);

            $this->renderer->displayJson($result);
        } catch (\OutOfBoundsException $e) {
            $this->logger->logException($e);
            $this->renderer->displayJson([
                'status' => 403,
                'message' => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            $this->logger->logException($e);
            $this->renderer->displayJson([
                'status' => 0,
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function executeV1(string $rawBody, ?int $shopId): void
    {
        $apiKey = '';
        $auth = '';
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
        }
        if (empty($auth) && function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                if (strtolower($name) === 'authorization') {
                    $auth = $value;
                    break;
                }
            }
        }
        if (!empty($auth)) {
            if (stripos($auth, 'Bearer') !== false) {
                $apiKey = trim(str_ireplace('Bearer', '', $auth));
            } else {
                $apiKey = trim($auth);
            }
        }

        try {
            $parsed = $this->payloadParser->parse($rawBody);

            $code = $parsed['code'];
            $data = $parsed['data'];

            if (!empty($data['shop_id'])) {
                $shopId = (int) $data['shop_id'];
            }

            $shopConstraint = Config::shopConstraint($shopId);
            if (!$this->config->isEnabled($shopConstraint)) {
                throw new ApiV1Exception(1011, 'API not available', 403);
            }

            if (!empty($apiKey)) {
                $data[Authenticator::API_KEY_PARAM] = $apiKey;
            }

            $result = $this->processor->process($code, $shopId, $data);

            $this->renderer->displayJson($result);
        } catch (ApiV1Exception $e) {
            $this->logger->logException($e);
            http_response_code($e->getHttpStatus());
            $this->renderer->displayJson([
                'error' => [
                    'code' => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                ],
            ]);
        } catch (\OutOfBoundsException $e) {
            $this->logger->logException($e);
            http_response_code(403);
            $this->renderer->displayJson([
                'error' => [
                    'code' => 1001,
                    'message' => 'Authentication failed',
                ],
            ]);
        } catch (\Exception $e) {
            $this->logger->logException($e);
            http_response_code(500);
            $this->renderer->displayJson([
                'error' => [
                    'code' => 1009,
                    'message' => 'Internal server error',
                ],
            ]);
        }
    }
}

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

namespace PrestaShop\Module\Newsmanv8;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger as MonologLogger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Logger implements LoggerInterface
{
    private const LEVEL_MAP = [
        LogLevel::EMERGENCY => 600,
        LogLevel::ALERT => 550,
        LogLevel::CRITICAL => 500,
        LogLevel::ERROR => 400,
        LogLevel::WARNING => 300,
        LogLevel::NOTICE => 250,
        LogLevel::INFO => 200,
        LogLevel::DEBUG => 100,
    ];

    private MonologLogger $monolog;

    public function __construct(Config $config)
    {
        $handlers = [];
        $severity = $config->getLogSeverity();

        if ($severity !== Config::LOG_NONE) {
            $handler = new RotatingFileHandler(
                _PS_ROOT_DIR_ . '/var/logs/newsman.log',
                $config->getLogCleanDays(),
                $severity,
                true,
                null,
                true
            );
            $handler->setFilenameFormat('{filename}_{date}', 'Y-m-d');
            $handler->setFormatter(new LineFormatter(
                "[%datetime%] %level_name%: %message% %context% %extra%\n",
                'Y-m-d H:i:s',
                false,
                true
            ));
            $handlers[] = $handler;
        }

        $this->monolog = new MonologLogger('newsman', $handlers, [
            new PsrLogMessageProcessor(),
        ]);
    }

    public function emergency($message, array $context = [])
    {
        $this->monolog->emergency($message, $context);
    }

    public function alert($message, array $context = [])
    {
        $this->monolog->alert($message, $context);
    }

    public function critical($message, array $context = [])
    {
        $this->monolog->critical($message, $context);
    }

    public function error($message, array $context = [])
    {
        $this->monolog->error($message, $context);
    }

    public function warning($message, array $context = [])
    {
        $this->monolog->warning($message, $context);
    }

    public function notice($message, array $context = [])
    {
        $this->monolog->notice($message, $context);
    }

    public function info($message, array $context = [])
    {
        $this->monolog->info($message, $context);
    }

    public function debug($message, array $context = [])
    {
        $this->monolog->debug($message, $context);
    }

    /**
     * PSR-3 generic log method.
     *
     * @param mixed $level A PSR-3 LogLevel constant string
     */
    public function log($level, $message, array $context = [])
    {
        $intLevel = self::LEVEL_MAP[$level] ?? null;

        if (null === $intLevel) {
            throw new \Psr\Log\InvalidArgumentException(sprintf('Unknown log level: %s', $level));
        }

        $this->monolog->log($intLevel, $message, $context);
    }

    public function logException(\Throwable $e): void
    {
        $this->error($e->getMessage(), ['exception' => $e]);
    }
}

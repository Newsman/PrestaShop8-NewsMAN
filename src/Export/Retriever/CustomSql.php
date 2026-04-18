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

namespace PrestaShop\Module\Newsmanv8\Export\Retriever;

use PHPSQLParser\PHPSQLParser;

if (!defined('_PS_VERSION_')) {
    exit;
}

class CustomSql extends AbstractRetriever implements RetrieverInterface
{
    /** @var array<string> */
    protected array $disallowedStatements = [
        'INSERT', 'UPDATE', 'DELETE', 'REPLACE',
        'CREATE', 'ALTER', 'DROP', 'TRUNCATE', 'RENAME',
        'GRANT', 'REVOKE',
        'LOCK', 'UNLOCK',
        'CALL', 'EXECUTE', 'PREPARE', 'DEALLOCATE',
        'LOAD', 'HANDLER',
        'SET', 'DO', 'FLUSH', 'RESET', 'PURGE', 'KILL', 'SHUTDOWN', 'INSTALL', 'UNINSTALL',
        'ANALYZE', 'CHECK', 'CHECKSUM', 'OPTIMIZE', 'REPAIR',
        'SHOW', 'DESCRIBE', 'EXPLAIN', 'USE',
        'BEGIN', 'COMMIT', 'ROLLBACK', 'SAVEPOINT', 'RELEASE', 'XA',
    ];

    /**
     * @param array<string, mixed> $data
     *
     * @return array<mixed>
     */
    public function process(array $data = [], array $shopIds = []): array
    {
        $shopId = $shopIds[0] ?? null;
        $sql = isset($data['sql']) ? trim((string) $data['sql']) : '';

        if (empty($sql)) {
            throw new \InvalidArgumentException('The "sql" parameter is required.');
        }

        $this->validateSelectOnly($sql);
        $sql = $this->replaceTablePlaceholders($sql);

        $this->logger->notice(sprintf('Custom SQL export, shop ID %s - Query: %s', $shopId, $sql));

        $rows = \Db::getInstance()->executeS($sql);
        if (!is_array($rows)) {
            $rows = [];
        }

        $this->logger->notice(sprintf('Custom SQL export, shop ID %s - Rows returned: %d', $shopId, count($rows)));

        return $rows;
    }

    protected function validateSelectOnly(string $sql): void
    {
        $this->validateNoMultipleStatements($sql);

        $parser = new PHPSQLParser();
        $parsed = $parser->parse($sql);

        if (empty($parsed)) {
            throw new \InvalidArgumentException('Unable to parse the SQL query.');
        }

        $statementType = key($parsed);

        if ('SELECT' !== $statementType) {
            throw new \InvalidArgumentException('Only SELECT queries are allowed. Got: ' . $statementType);
        }

        if (isset($parsed['INTO'])) {
            throw new \InvalidArgumentException('SELECT INTO is not allowed.');
        }
    }

    protected function validateNoMultipleStatements(string $sql): void
    {
        $stripped = preg_replace("/'[^'\\\\]*(?:\\\\.[^'\\\\]*)*'/s", '', $sql);
        $stripped = preg_replace('/"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"/s', '', $stripped);

        if (strpos($stripped, ';') !== false) {
            throw new \InvalidArgumentException('Multiple statements are not allowed.');
        }
    }

    protected function replaceTablePlaceholders(string $sql): string
    {
        return preg_replace_callback(
            '/\{([a-zA-Z0-9_]+)\}/',
            function ($matches) {
                return _DB_PREFIX_ . $matches[1];
            },
            $sql
        );
    }
}

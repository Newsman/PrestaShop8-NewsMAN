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

use Symfony\Component\DependencyInjection\ContainerInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Pool
{
    /** @var array<string, array{class: string, has_filters?: bool}> */
    protected array $retrieverList;

    /** @var array<string, RetrieverInterface> */
    protected array $instances = [];

    protected ContainerInterface $container;

    /**
     * @param array<string, array{class: string, has_filters?: bool}> $retrieverList
     */
    public function __construct(ContainerInterface $container, array $retrieverList)
    {
        $this->container = $container;
        $this->retrieverList = $retrieverList;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function getRetrieverByCode(string $code): RetrieverInterface
    {
        $code = strtolower($code);

        if (isset($this->instances[$code])) {
            return $this->instances[$code];
        }

        if (!isset($this->retrieverList[$code])) {
            throw new \InvalidArgumentException('Unknown retriever code: ' . $code);
        }

        $className = $this->retrieverList[$code]['class'];

        if (!$this->container->has($className)) {
            throw new \InvalidArgumentException('Retriever service not registered: ' . $className);
        }

        $instance = $this->container->get($className);

        if (!$instance instanceof RetrieverInterface) {
            throw new \InvalidArgumentException('Class ' . $className . ' does not implement ' . RetrieverInterface::class);
        }

        $this->instances[$code] = $instance;

        return $instance;
    }

    /**
     * @return array<string, array{class: string, has_filters?: bool}>
     */
    public function getRetrieverList(): array
    {
        \Hook::exec('actionNewsmanExportRetrieverPoolGetRetrieverListBefore', [
            'retriever_list' => $this->retrieverList,
        ]);

        return $this->retrieverList;
    }

    /**
     * @return array<array{class: string, has_filters?: bool}>
     */
    public function getRetrieversWithFilters(): array
    {
        $retrievers = [];
        foreach ($this->retrieverList as $code => $retriever) {
            if (!empty($retriever['has_filters'])) {
                $retrievers[$code] = $retriever;
            }
        }

        return $retrievers;
    }
}

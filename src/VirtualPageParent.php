<?php

namespace ALOMP\Airtable;

use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Exception\InvalidArgumentException;

abstract class VirtualPageParent extends Page
{
    abstract public static function getConfig();

    public string|null $apiKey;
    public string|null $baseId;
    public string $table;
    public string $child;
    public string|null $filterByFormula;
    public int|null $minutes;

    protected $airtable;
    protected $cache;

    public function __construct(array $props)
    {
        $config = $this::getConfig();

        $this->apiKey = option('alomp.airtable-virtual-pages.apiKey');
        $this->baseId = option('alomp.airtable-virtual-pages.baseId');

        if (is_null($this->apiKey)) {
            throw new InvalidArgumentException('apiKey not found.');
        }

        if (is_null($this->baseId)) {
            throw new InvalidArgumentException('baseId not found.');
        }

        $this->table = $config['table'];
        $this->child = $config['child'];
        $this->filterByFormula = $config['filterByFormula'] ?? null;
        $this->minutes = $config['minutes'];

        $this->airtable = new \Guym4c\Airtable\Airtable(
            $this->apiKey,
            $this->baseId,
        );
        $this->cache = kirby()->cache('alomp.airtable-virtual-pages');
        parent::__construct($props);
    }

    private function getResultsCacheKey()
    {
        $table = $this->baseId . '-' . $this->table;
        return $this->filterByFormula
            ? $table . '-' . md5($this->filterByFormula)
            : $table;
    }

    private function getResultCacheKey($key)
    {
        return $this->baseId . '-' . $this->table . '-' . $key;
    }

    public function fetchCachedResults()
    {
        return $this->cache->get($this->getResultsCacheKey());
    }

    public function fetchCachedResult($id)
    {
        if ($result = $this->cache->get($this->getResultCacheKey($id))) {
            return $result;
        }
        if ($results = $this->fetchCachedResults()) {
            foreach ($results as $i => $result) {
                if ($result['id'] === $id) {
                    return $result;
                }
            }
        }
        return null;
    }

    public function fetchRemoteResults()
    {
        $records = [];
        $filter = null;
        if ($this->filterByFormula) {
            $filter = new \Guym4c\Airtable\ListFilter();
            $filter->setFormula($this->filterByFormula);
        }
        $request = $this->airtable->list($this->table, $filter);

        do {
            $records = array_merge($records, $request->getRecords());
        } while ($request = $request->nextPage());

        $results = [];
        foreach ($records as $i => $record) {
            $results[] = [
                'table' => $record->getTable(),
                'id' => $record->getId(),
                'num' => $i + 1,
                'fields' => $record->getData(),
                'createdTime' => $record->getTimestamp()->format('c'),
            ];
        }
        if (!is_null($this->minutes)) {
            $this->cache->set(
                $this->getResultsCacheKey(),
                $results,
                $this->minutes,
            );
        }
        return $results;
    }

    public function fetchRemoteResult($id)
    {
        try {
            $record = $this->airtable->get($this->table, $id);
            $result = [
                'table' => $record->getTable(),
                'id' => $record->getId(),
                'fields' => $record->getData(),
                'createdTime' => $record->getTimestamp()->format('c'),
            ];
            $class = $this->getChildClass();
            $config = $class::getConfig();
            $minutes = $config['minutes'];
            if (!is_null($minutes)) {
                $this->cache->set(
                    $this->getResultCacheKey($id),
                    $result,
                    $minutes,
                );
            }
            return $result;
        } catch (\Guym4c\Airtable\AirtableApiException $e) {
            return null;
        }
    }

    public function fetchResults()
    {
        if (
            !is_null($this->minutes) &&
            ($results = $this->fetchCachedResults())
        ) {
            return $results;
        }
        return $this->fetchRemoteResults();
    }

    public function fetchResult($id)
    {
        $class = $this->getChildClass();
        $config = $class::getConfig();
        $minutes = $config['minutes'];
        if (!is_null($minutes) && ($result = $this->fetchCachedResult($id))) {
            return $result;
        }
        return $this->fetchRemoteResult($id);
    }

    public function getChildClass()
    {
        $class = Page::$models[$this->child] ?? null;
        if (is_null($class)) {
            throw new InvalidArgumentException(
                'model ' . $this->child . ' not found.',
            );
        }
        return $class;
    }

    public function flush()
    {
        $this->cache->remove($this->getResultsCacheKey());
    }

    public function resultToPageProps($result, $class)
    {
        $table = $result['table'];
        unset($result['table']);

        $num = null;
        if (isset($result['num'])) {
            $num = $result['num'];
            unset($result['num']);
        }

        $record = new \Guym4c\Airtable\Record($this->airtable, $table, $result);
        $page = [
            'slug' => $class::slugify($record),
            'table' => $this->table,
            'template' => $this->child,
            'model' => $this->child,
            'content' => $class::fromRecord($record),
        ];
        if ($num) {
            $page['num'] = $num;
        }

        return $page;
    }

    final public function children()
    {
        $results = $this->fetchResults();
        $class = $this->getChildClass();

        $pages = [];
        foreach ($results as $result) {
            $pages[] = $this->resultToPageProps($result, $class);
        }
        return Pages::factory($pages, $this);
    }

    public function get($key)
    {
        if ($result = $this->fetchResult($key)) {
            $class = $this->getChildClass();
            $page = $this->resultToPageProps($result, $class);
            return Page::factory($page, $this);
        }
        return null;
    }
}

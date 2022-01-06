<?php

namespace ALOMP\Airtable;

use Kirby\Cms\Page;
use Kirby\Exception\InvalidArgumentException;

abstract class VirtualPage extends Page
{
    abstract public static function getConfig();

    abstract public static function fromRecord($record);
    abstract public static function toRecord($content);

    public string|null $apiKey;
    public string|null $baseId;
    public string $table;
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
        $this->minutes = $config['minutes'] ?? null;

        $this->airtable = new \Guym4c\Airtable\Airtable(
            $this->apiKey,
            $this->baseId,
        );
        $this->cache = kirby()->cache('alomp.airtable-virtual-pages');
        parent::__construct($props);
    }

    public static function slugify($record)
    {
        return $record->getId();
    }

    public static function deslugify($slug)
    {
        return $slug;
    }

    public function getResultCacheKey($key)
    {
        return $this->baseId . '-' . $this->table . '-' . $key;
    }

    public function writeContent(array $data, string $languageCode = null): bool
    {
        if ($recordArray = $this->toRecord($data)) {
            try {
                $record = $this->airtable->get(
                    $this->table,
                    $this::deslugify($this->slug()),
                );
                foreach ($recordArray as $key => $value) {
                    if (!is_null($value) && $value !== $record->{$key}) {
                        $record->{$key} = $value;
                    }
                }
                $this->airtable->update($record);
            } catch (\Guym4c\Airtable\AirtableApiException $e) {
                $record = $this->airtable->create($this->table, $recordArray);
                $this->setSlug($this::slugify($record));
            }
            $this->cache->remove($this->getResultCacheKey($record->getId()));
            $parent = $this->parent();
            while ($parent) {
                if (
                    in_array(
                        'ALOMP\Airtable\VirtualPageParent',
                        class_parents($parent),
                    )
                ) {
                    $parent->flush();
                }
                $parent = $parent->parent();
            }
        }
        return true;
    }
}

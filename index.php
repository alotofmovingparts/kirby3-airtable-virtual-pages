<?php

@include_once __DIR__ . '/vendor/autoload.php';

use Kirby\Cms\Field;
use Kirby\Cms\App;

Kirby::plugin('alomp/airtable-virtual-pages', [
    'fieldMethods' => [
        'toVirtualPage' => function (Field $field) {
            return $field->toVirtualPages()->first();
        },
        'toVirtualPages' => function (
            Field $field,
            string $separator = 'yaml',
        ) {
            return $field
                ->parent()
                ->kirby()
                ->site()
                ->findVirtualPages(false, false, ...$field->toData($separator));
        },
    ],
    'pageMethods' => [
        'findVirtualPage' => function (...$keys) {
            return $this->children()->findVirtualPage(...$keys);
        },
        'findVirtualPages' => function (...$keys) {
            return $this->findVirtualPage(...$keys);
        },
    ],
    'pagesMethods' => [
        'findVirtualPageByIdRecursive' => function (
            string $id,
            string $startAt = null,
            bool $multiLang = false,
        ) {
            $path = explode('/', $id);
            $item = null;
            $query = $startAt;

            foreach ($path as $key) {
                $query = ltrim($query . '/' . $key, '/');

                if (is_a($item, 'ALOMP\Airtable\VirtualPageParent') === true) {
                    $item = $item->get($key) ?? null;
                } else {
                    $collection = $item ? $item->children() : $this;
                    $item = $collection->get($query) ?? null;
                }

                if ($item === null && $multiLang === true) {
                    $item = $collection->findBy('slug', $key);
                }

                if ($item === null) {
                    return null;
                }
            }

            return $item;
        },
        'findVirtualPageById' => function (string $id = null) {
            // remove trailing or leading slashes
            $id = trim($id, '/');

            // strip extensions from the id
            if (strpos($id, '.') !== false) {
                $info = pathinfo($id);

                if ($info['dirname'] !== '.') {
                    $id = $info['dirname'] . '/' . $info['filename'];
                } else {
                    $id = $info['filename'];
                }
            }

            // try the obvious way
            if ($page = $this->get($id)) {
                return $page;
            }

            $multiLang = App::instance()->multilang();

            if ($multiLang === true && ($page = $this->findBy('slug', $id))) {
                return $page;
            }

            $start =
                is_a($this->parent, 'Kirby\Cms\Page') === true
                    ? $this->parent->id()
                    : '';
            $page = $this->findVirtualPageByIdRecursive(
                $id,
                $start,
                $multiLang,
            );

            return $page;
        },
        'findVirtualPage' => function (...$keys) {
            if (count($keys) === 1) {
                if (is_array($keys[0]) === true) {
                    $keys = $keys[0];
                } else {
                    return $this->findVirtualPageById($keys[0]);
                }
            }

            $result = [];

            foreach ($keys as $key) {
                if ($item = $this->findVirtualPageById($key)) {
                    if (
                        is_object($item) &&
                        method_exists($item, 'id') === true
                    ) {
                        $key = $item->id();
                    }
                    $result[$key] = $item;
                }
            }

            $collection = clone $this;
            $collection->data = $result;
            return $collection;
        },
        'findVirtualPages' => function (...$keys) {
            return $this->findVirtualPage(...$keys);
        },
    ],
    'collectionMethods' => [
        'findVirtualPage' => function (...$keys) {
            $children = $this->children();
            if (method_exists($children, 'findVirtualPage')) {
                return $children->findVirtualPage(...$keys);
            }
            return $children->find(...$keys);
        },
        'findVirtualPages' => function (...$keys) {
            return $this->findVirtualPage(...$keys);
        },
    ],
    'siteMethods' => [
        'findVirtualPage' => function (...$keys) {
            return $this->children()->findVirtualPage(...$keys);
        },
        'findVirtualPages' => function (...$keys) {
            return $this->findVirtualPage(...$keys);
        },
    ],
    'options' => [
        'cache' => true,
        'apiKey' => null,
        'baseId' => null,
    ],
]);

function airtable()
{
    \Bnomei\DotEnv::load();
    $apiKey = env('AIRTABLE_API_KEY');
    $baseId = env('AIRTABLE_BASE_ID');
    return new \Guym4c\Airtable\Airtable($apiKey, $baseId);
}

function airtableFind($table, $field, $value)
{
    $record = null;
    try {
        $records = airtable()->find($table, $field, $value);
        if (count($records) > 0) {
            $record = $records[0];
        }
    } catch (Exception $e) {
    }
    return $record;
}

function airtableFindOrCreate($table, $field, $value)
{
    $record = null;
    try {
        $records = airtable()->find($table, $field, $value);
        if (count($records) > 0) {
            $record = $records[0];
        }
    } catch (Exception $e) {
    }
    if (!$record) {
        $record = airtable()->create($table, [
            $field => $value,
        ]);
    }
    return $record;
}

function airtableFindByNormalizedOrCreate(
    $table,
    $normalizedField,
    $field,
    $value,
) {
    $record = null;
    try {
        $records = airtable()->find($table, $normalizedField, $value);
        if (count($records) > 0) {
            $record = $records[0];
        }
    } catch (Exception $e) {
    }
    if (!$record) {
        $record = airtable()->create($table, [
            $field => $value,
        ]);
    }
    return $record;
}

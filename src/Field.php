<?php

namespace ALOMP\Airtable;

use Closure;
use DateTime;
use DateTimeZone;
use Kirby\Data\Yaml;

class Field
{
    static function toText(string|null $value)
    {
        return $value;
    }

    static function toDate(
        string|null $value,
        string|null $timezone = null,
    ): string|null {
        if (is_null($value)) {
            return $value;
        }

        if (empty($value)) {
            return null;
        }

        $timezone = $timezone ?? date_default_timezone_get();

        $datetime = DateTime::createFromFormat(
            'Y-m-d H:i:s',
            $value,
            new DateTimeZone($timezone),
        );
        $datetime->setTimezone(new DateTimeZone('UTC'));
        return $datetime->format('c');
    }

    static function toCheckbox(bool|null $value): bool|null
    {
        return $value;
    }

    static function toSingleSelect(string|null $value): string|null
    {
        return $value;
    }

    static function toLinkedRecords(
        string|null $value,
        Closure $deslugify = null,
    ): array|null {
        if (is_null($value)) {
            return $value;
        }

        if (is_null($deslugify)) {
            $deslugify = function ($slug) {
                return $slug;
            };
        }

        $ids = Yaml::decode($value);
        return array_map(function ($id) use ($deslugify) {
            return $deslugify(end(explode('/', $id)));
        }, $ids);
    }
}

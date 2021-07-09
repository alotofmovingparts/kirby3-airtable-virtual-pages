<?php

namespace ALOMP\Airtable;

use Closure;
use DateTime;
use DateTimeZone;

class Record
{
    static function toText(string|null $value)
    {
        if (is_null($value)) {
            return $value;
        }

        return $value;
    }

    static function toDate(
        string|null $value,
        string|null $timezone = null,
    ): string|null {
        if (is_null($value)) {
            return $value;
        }

        $timezone = $timezone ?? date_default_timezone_get();

        $datetime = DateTime::createFromFormat(
            'Y-m-d\TH:i:s+',
            $value,
            new DateTimeZone('UTC'),
        );
        $datetime->setTimezone(new DateTimeZone($timezone));
        return $datetime->format('Y-m-d H:i:s');
    }

    static function toToggle(bool|null $value): bool|null
    {
        return $value;
    }

    static function toPages(
        \Guym4c\Airtable\Loader|array|null $value,
        string|null $parent = null,
        Closure $slugify = null,
    ): array|null {
        if (is_null($value)) {
            return $value;
        }

        if (is_null($slugify)) {
            $slugify = function ($record) {
                return $record->getId();
            };
        }

        $records = is_array($value) ? $value : [$value];
        return array_map(function ($record) use ($slugify, $parent) {
            $uid = $slugify($record);
            return is_null($parent) ? $uid : $parent . '/' . $uid;
        }, $records);
    }
}

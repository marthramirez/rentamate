<?php

namespace App\Support;

final class Money
{
    public static function of(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        if (is_string($value)) {
            $value = str_replace([',', ' '], '', $value);
        }

        if (! is_numeric($value)) {
            return '0.00';
        }

        return bcadd(sprintf('%.2f', (float) $value), '0', 2);
    }

    public static function add(mixed $left, mixed $right): string
    {
        return bcadd(self::of($left), self::of($right), 2);
    }

    public static function sub(mixed $left, mixed $right): string
    {
        return bcsub(self::of($left), self::of($right), 2);
    }

    public static function comp(mixed $left, mixed $right): int
    {
        return bccomp(self::of($left), self::of($right), 2);
    }
}

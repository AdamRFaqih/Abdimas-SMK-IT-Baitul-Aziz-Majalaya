<?php

namespace App\Support;

class RaporNilaiCalculator
{
    public static function calculate(array $lingkup, ?int $test, ?int $nonTest): ?int
    {
        return self::average([
            self::average($lingkup),
            self::average([$test, $nonTest]),
        ]);
    }

    private static function average(array $values): ?int
    {
        $values = array_filter($values, static fn ($value) => $value !== null);

        return $values === [] ? null : (int) round(array_sum($values) / count($values));
    }
}

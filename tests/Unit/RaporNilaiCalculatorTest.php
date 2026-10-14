<?php

namespace Tests\Unit;

use App\Support\RaporNilaiCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RaporNilaiCalculatorTest extends TestCase
{
    #[DataProvider('nilaiCases')]
    public function test_final_score_uses_rounded_component_averages(array $lingkup, ?int $test, ?int $nonTest, ?int $expected): void
    {
        $actual = RaporNilaiCalculator::calculate($lingkup, $test, $nonTest);

        if ($expected === null) {
            $this->assertNull($actual);
        } else {
            $this->assertSame($expected, $actual);
        }
    }

    public static function nilaiCases(): array
    {
        return [
            'LM and semester' => [[80, 90], 70, 90, 83],
            'round each stage' => [[80, 81], 81, 82, 82],
            'zero is a score' => [[0], 0, null, 0],
            'ignore null LM' => [[80, null], null, null, 80],
            'test only' => [[], 73, null, 73],
            'non-test only' => [[], null, 87, 87],
            'no scores' => [[], null, null, null],
            'all null' => [[null, null], null, null, null],
        ];
    }
}

<?php

namespace PhpOffice\PhpSpreadsheet\Style\NumberFormat;

use PhpOffice\PhpSpreadsheet\Calculation\MathTrig;

class FractionFormatter extends BaseFormatter
{
    /**
     * @param mixed $value
     */
    public static function format($value, string $format): string
    {
        $format = self::stripQuotes($format);
        $value = (float) $value;
        $absValue = abs($value);

        $sign = ($value < 0.0) ? '-' : '';

        $integerPart = floor($absValue);

        $decimalPart = self::getDecimal((string) $absValue);
        if ($decimalPart === '0') {
            return "{$sign}{$integerPart}";
        }
        $decimalLength = strlen($decimalPart);
        $decimalDivisor = 10 ** $decimalLength;

        preg_match('/(#?.*\?)\/(\?+|\d+)/', $format, $matches);
        $formatIntegerPart = $matches[1];

        if (is_numeric($matches[2])) {
            $fractionDivisor = 100 / (int) $matches[2];
        } else {
            /** @var float */
            $fractionDivisor = MathTrig\Gcd::evaluate((int) $decimalPart, $decimalDivisor);
        }

        $adjustedDecimalPart = (int) round((int) $decimalPart / $fractionDivisor, 0);
        $adjustedDecimalDivisor = $decimalDivisor / $fractionDivisor;

        if ((strpos($formatIntegerPart, '0') !== false)) {
            return "{$sign}{$integerPart} {$adjustedDecimalPart}/{$adjustedDecimalDivisor}";
        } elseif ((strpos($formatIntegerPart, '#') !== false)) {
            if ($integerPart == 0) {
                return "{$sign}{$adjustedDecimalPart}/{$adjustedDecimalDivisor}";
            }

            return "{$sign}{$integerPart} {$adjustedDecimalPart}/{$adjustedDecimalDivisor}";
        } elseif ((substr($formatIntegerPart, 0, 3) == '? ?')) {
            if ($integerPart == 0) {
                $integerPart = '';
            }

            return "{$sign}{$integerPart} {$adjustedDecimalPart}/{$adjustedDecimalDivisor}";
        }

        $adjustedDecimalPart += $integerPart * $adjustedDecimalDivisor;

        return "{$sign}{$adjustedDecimalPart}/{$adjustedDecimalDivisor}";
    }

    private static function getDecimal(string $value): string
    {
        $decimalPart = '0';
        if (preg_match('/^\d*[.](\d*[1-9])0*$/', $value, $matches) === 1) {
            $decimalPart = $matches[1];
        }

        return $decimalPart;
    }
}

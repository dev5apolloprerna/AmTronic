<?php

namespace App\Support;

class AmountInWords
{
    private const ONES = [
        0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
        6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
        11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen',
        15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen',
    ];

    private const TENS = [
        2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty',
        6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety',
    ];

    public static function rupees(float|string $amount): string
    {
        $paiseTotal = (int) round(((float) $amount) * 100);
        $rupees = intdiv($paiseTotal, 100);
        $paise = $paiseTotal % 100;

        $words = 'Rupees '.self::integer($rupees);
        if ($paise > 0) {
            $words .= ' and '.self::integer($paise).' Paise';
        }

        return $words.' Only';
    }

    private static function integer(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }

        $parts = [];
        foreach ([10000000 => 'Crore', 100000 => 'Lakh', 1000 => 'Thousand'] as $value => $label) {
            if ($number >= $value) {
                $parts[] = self::integer(intdiv($number, $value)).' '.$label;
                $number %= $value;
            }
        }

        if ($number >= 100) {
            $parts[] = self::ONES[intdiv($number, 100)].' Hundred';
            $number %= 100;
        }

        if ($number > 0) {
            $parts[] = self::underHundred($number);
        }

        return implode(' ', $parts);
    }

    private static function underHundred(int $number): string
    {
        if ($number < 20) {
            return self::ONES[$number];
        }

        $words = self::TENS[intdiv($number, 10)];
        $ones = $number % 10;

        return $ones > 0 ? $words.' '.self::ONES[$ones] : $words;
    }
}

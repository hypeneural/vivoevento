<?php

namespace App\Modules\MediaProcessing\Services;

use Intervention\Image\Laravel\Facades\Image;

class PerceptualHashService
{
    private const HASH_SIZE = 8;

    /**
     * @var array<int, int>
     */
    private const HEX_BIT_COUNTS = [
        0 => 0,
        1 => 1,
        2 => 1,
        3 => 2,
        4 => 1,
        5 => 2,
        6 => 2,
        7 => 3,
        8 => 1,
        9 => 2,
        10 => 2,
        11 => 3,
        12 => 2,
        13 => 3,
        14 => 3,
        15 => 4,
    ];

    public function generateFromBinary(string $binary): string
    {
        $image = Image::decode($binary)
            ->cover(self::HASH_SIZE, self::HASH_SIZE)
            ->grayscale();

        $samples = [];
        $total = 0;

        for ($y = 0; $y < self::HASH_SIZE; $y++) {
            for ($x = 0; $x < self::HASH_SIZE; $x++) {
                $value = (int) $image->colorAt($x, $y)->red()->value();
                $samples[] = $value;
                $total += $value;
            }
        }

        $average = $total / count($samples);
        $bits = '';

        foreach ($samples as $value) {
            $bits .= $value >= $average ? '1' : '0';
        }

        return $this->binaryStringToHex($bits);
    }

    public function hammingDistance(?string $left, ?string $right): ?int
    {
        if (! is_string($left) || ! is_string($right) || $left === '' || $right === '' || strlen($left) !== strlen($right)) {
            return null;
        }

        $distance = 0;

        for ($index = 0; $index < strlen($left); $index++) {
            $distance += self::HEX_BIT_COUNTS[hexdec($left[$index]) ^ hexdec($right[$index])];
        }

        return $distance;
    }

    private function binaryStringToHex(string $bits): string
    {
        $hex = '';

        foreach (str_split($bits, 4) as $chunk) {
            $hex .= dechex(bindec(str_pad($chunk, 4, '0')));
        }

        return $hex;
    }
}

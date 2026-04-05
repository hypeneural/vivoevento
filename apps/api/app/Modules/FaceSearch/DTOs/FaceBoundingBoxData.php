<?php

namespace App\Modules\FaceSearch\DTOs;

final class FaceBoundingBoxData
{
    public function __construct(
        public readonly int $x,
        public readonly int $y,
        public readonly int $width,
        public readonly int $height,
    ) {}

    public function area(): int
    {
        return $this->width * $this->height;
    }
}

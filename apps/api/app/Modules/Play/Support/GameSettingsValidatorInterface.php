<?php

namespace App\Modules\Play\Support;

interface GameSettingsValidatorInterface
{
    public function validate(array $settings): array;
}

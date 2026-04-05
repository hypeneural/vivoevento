<?php

namespace App\Modules\MediaProcessing\Services;

use App\Shared\Exceptions\ProviderCircuitOpenException;
use App\Shared\Exceptions\ProviderMisconfiguredException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use JsonException;
use Throwable;

class PipelineFailureClassifier
{
    public function classify(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof ProviderMisconfiguredException,
            $exception instanceof ValidationException,
            $exception instanceof InvalidArgumentException,
            $exception instanceof JsonException => 'permanent',
            $exception instanceof ProviderCircuitOpenException,
            $exception instanceof ConnectionException,
            $exception instanceof RequestException => 'transient',
            default => 'transient',
        };
    }
}

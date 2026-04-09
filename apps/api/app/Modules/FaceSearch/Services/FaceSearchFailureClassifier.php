<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\MediaProcessing\Services\PipelineFailureClassifier;
use App\Shared\Exceptions\ProviderCircuitOpenException;
use Aws\Exception\AwsException;
use Illuminate\Validation\ValidationException;
use Throwable;

class FaceSearchFailureClassifier
{
    public function __construct(
        private readonly PipelineFailureClassifier $pipelineFailureClassifier,
    ) {}

    /**
     * @return array{failure_class:string, reason_code:string}
     */
    public function classify(Throwable $exception): array
    {
        if ($exception instanceof ProviderCircuitOpenException) {
            return [
                'failure_class' => 'transient',
                'reason_code' => 'provider_unavailable',
            ];
        }

        if ($exception instanceof AwsException) {
            return $this->classifyAwsException($exception);
        }

        if ($exception instanceof ValidationException) {
            return [
                'failure_class' => 'permanent',
                'reason_code' => 'functional_no_face',
            ];
        }

        $failureClass = $this->pipelineFailureClassifier->classify($exception);

        return [
            'failure_class' => $failureClass,
            'reason_code' => $failureClass === 'permanent'
                ? 'misconfigured'
                : 'retryable',
        ];
    }

    public function isRetryable(Throwable $exception): bool
    {
        return $this->classify($exception)['failure_class'] === 'transient';
    }

    /**
     * @return array{failure_class:string, reason_code:string}
     */
    private function classifyAwsException(AwsException $exception): array
    {
        $code = (string) $exception->getAwsErrorCode();
        $message = strtolower($exception->getAwsErrorMessage() ?? $exception->getMessage());

        return match (true) {
            in_array($code, ['ProvisionedThroughputExceededException', 'ThrottlingException'], true) => [
                'failure_class' => 'transient',
                'reason_code' => 'throttled',
            ],
            $code === 'InternalServerError' => [
                'failure_class' => 'transient',
                'reason_code' => 'provider_unavailable',
            ],
            $code === 'InvalidParameterException' && str_contains($message, 'no face') => [
                'failure_class' => 'permanent',
                'reason_code' => 'functional_no_face',
            ],
            in_array($code, [
                'AccessDeniedException',
                'UnrecognizedClientException',
                'InvalidSignatureException',
                'ResourceNotFoundException',
                'InvalidImageFormatException',
                'InvalidS3ObjectException',
                'ImageTooLargeException',
            ], true) => [
                'failure_class' => 'permanent',
                'reason_code' => 'misconfigured',
            ],
            default => [
                'failure_class' => $this->pipelineFailureClassifier->classify($exception),
                'reason_code' => $this->pipelineFailureClassifier->classify($exception) === 'permanent'
                    ? 'misconfigured'
                    : 'retryable',
            ],
        };
    }
}

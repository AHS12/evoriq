<?php

namespace App\Services\Pipeline;

use App\Enums\ApiErrorCode;
use App\Enums\PipelineFailureReason;
use App\Exceptions\ApiException;
use App\Exceptions\ImportCancelledException;
use Illuminate\Queue\MaxAttemptsExceededException;
use Throwable;

/**
 * Classifies why a run failed into a {@see PipelineFailureReason} (PIPE-02).
 */
class FailureReasonResolver
{
    /**
     * Classify an exception (or a bare failure with no exception).
     */
    public function fromThrowable(?Throwable $exception): PipelineFailureReason
    {
        if ($exception === null) {
            return PipelineFailureReason::UNKNOWN;
        }

        if ($exception instanceof ImportCancelledException) {
            return PipelineFailureReason::CANCELLED;
        }

        if ($exception instanceof MaxAttemptsExceededException) {
            return PipelineFailureReason::WORKER_LOST;
        }

        if ($exception instanceof ApiException) {
            return $this->fromApiException($exception);
        }

        return $this->fromMessage($exception->getMessage());
    }

    /**
     * Classify a stored error message (used when the exception is gone).
     */
    public function fromMessage(?string $message): PipelineFailureReason
    {
        if ($message === null || trim($message) === '') {
            return PipelineFailureReason::UNKNOWN;
        }

        $text = strtolower($message);

        return match (true) {
            $this->contains($text, ['rate limit', 'too many requests', '429']) => PipelineFailureReason::RATE_LIMITED,
            $this->contains($text, ['worker was lost', 'stalled']) => PipelineFailureReason::WORKER_LOST,
            $this->contains($text, ['timed out', 'timeout', 'time limit']) => PipelineFailureReason::TIMEOUT,
            $this->contains($text, ['cancel']) => PipelineFailureReason::CANCELLED,
            $this->contains($text, ['unauthor', 'unauthenticated', 'authenticat', 'forbidden', '401', '403']) => PipelineFailureReason::AUTH_FAILED,
            $this->contains($text, ['source file', 'no longer available', 'could not be stored', 'file is missing']) => PipelineFailureReason::SOURCE_MISSING,
            $this->contains($text, ['connection', 'network', 'could not resolve', 'unreachable', 'dns']) => PipelineFailureReason::NETWORK,
            $this->contains($text, ['unavailable', 'service error', '502', '503', '504']) => PipelineFailureReason::API_UNAVAILABLE,
            $this->contains($text, ['validation', 'invalid', 'not a valid', 'required']) => PipelineFailureReason::VALIDATION,
            default => PipelineFailureReason::UNKNOWN,
        };
    }

    private function fromApiException(ApiException $exception): PipelineFailureReason
    {
        $code = $exception->errorCode();
        $status = $exception->statusCode();

        if (in_array($code, [ApiErrorCode::CLOCKIFY_RATE_LIMITED->value, ApiErrorCode::RATE_LIMIT_EXCEEDED->value], true)) {
            return PipelineFailureReason::RATE_LIMITED;
        }

        if (
            in_array($code, [
                ApiErrorCode::UNAUTHORIZED->value,
                ApiErrorCode::FORBIDDEN->value,
                ApiErrorCode::CLOCKIFY_AUTHENTICATION_FAILED->value,
                ApiErrorCode::TOKEN_EXPIRED->value,
                ApiErrorCode::TOKEN_INVALID->value,
                ApiErrorCode::INVALID_CREDENTIALS->value,
            ], true)
            || in_array($status, [401, 403], true)
        ) {
            return PipelineFailureReason::AUTH_FAILED;
        }

        if (
            in_array($code, [
                ApiErrorCode::SERVICE_UNAVAILABLE->value,
                ApiErrorCode::EXTERNAL_SERVICE_ERROR->value,
                ApiErrorCode::CLOCKIFY_API_ERROR->value,
            ], true)
            || in_array($status, [502, 503, 504], true)
        ) {
            return PipelineFailureReason::API_UNAVAILABLE;
        }

        return $this->fromMessage($exception->getMessage());
    }

    /**
     * @param  array<int, string>  $needles
     */
    private function contains(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}

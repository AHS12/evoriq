<?php

namespace App\Services\Clockify;

use App\Enums\ApiErrorCode;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

/**
 * The central mapper from Clockify failures to {@see ApiErrorCode} (CONN-07).
 * It is defensive about Clockify's inconsistent error bodies and never surfaces
 * raw stack traces or credentials — only a length-capped technical message.
 */
class ClockifyErrorMapper
{
    /**
     * @return array{code: ApiErrorCode, message: string, retry_after: int|null}
     */
    public function fromResponse(Response $response): array
    {
        $status = $response->status();
        $message = $this->extractMessage($response);

        $code = match (true) {
            $status === 401 => ApiErrorCode::CLOCKIFY_AUTHENTICATION_FAILED,
            $status === 402 => ApiErrorCode::CLOCKIFY_SUBSCRIPTION_REQUIRED,
            $status === 403 => ApiErrorCode::CLOCKIFY_FORBIDDEN,
            $status === 404 => ApiErrorCode::CLOCKIFY_NOT_FOUND,
            $status === 429, str_contains(strtolower($message), 'too many requests') => ApiErrorCode::CLOCKIFY_RATE_LIMITED,
            $status >= 500 => ApiErrorCode::CLOCKIFY_API_UNAVAILABLE,
            $status >= 400 => ApiErrorCode::CLOCKIFY_API_ERROR,
            default => ApiErrorCode::CLOCKIFY_INVALID_RESPONSE,
        };

        return [
            'code' => $code,
            'message' => $message !== '' ? Str::limit($message, 500, '') : $code->description(),
            'retry_after' => $code === ApiErrorCode::CLOCKIFY_RATE_LIMITED
                ? $this->retryAfter($response)
                : null,
        ];
    }

    /**
     * Map a transport/decoding exception (no usable response).
     *
     * @return array{code: ApiErrorCode, message: string, retry_after: int|null}
     */
    public function fromThrowable(Throwable $exception): array
    {
        if ($exception instanceof RequestException && $exception->response !== null) {
            return $this->fromResponse($exception->response);
        }

        if ($exception instanceof ConnectionException) {
            return $this->error(ApiErrorCode::CLOCKIFY_NETWORK_ERROR);
        }

        if ($exception instanceof JsonException) {
            return $this->error(ApiErrorCode::CLOCKIFY_INVALID_RESPONSE);
        }

        return $this->error(ApiErrorCode::CLOCKIFY_UNKNOWN);
    }

    /**
     * @return array{code: ApiErrorCode, message: string, retry_after: int|null}
     */
    private function error(ApiErrorCode $code): array
    {
        return [
            'code' => $code,
            'message' => $code->description(),
            'retry_after' => null,
        ];
    }

    /**
     * Parse Clockify's inconsistent error body defensively.
     */
    private function extractMessage(Response $response): string
    {
        $body = (string) $response->body();
        $decoded = json_decode($body, true);

        if (is_array($decoded)) {
            foreach (['message', 'error', 'detail'] as $key) {
                if (isset($decoded[$key]) && is_string($decoded[$key])) {
                    return $decoded[$key];
                }
            }
        }

        return $body;
    }

    private function retryAfter(Response $response): ?int
    {
        $header = $response->header('Retry-After');

        return is_numeric($header) ? (int) $header : null;
    }
}

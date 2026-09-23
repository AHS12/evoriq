<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * RFC 9457 ("Problem Details for HTTP APIs") compliant exception.
 *
 * Used by JSON endpoints (sync status, API usage, exports, webhooks).
 * Inertia controllers should keep using redirects/`Inertia::render`.
 *
 * @link https://www.rfc-editor.org/rfc/rfc9457.html
 */
final class ApiException extends Exception
{
    /**
     * @param  array<string, mixed>  $additionalData
     */
    public function __construct(
        private int $responseCode,
        private string $errorCode,
        private string $errorMessage,
        private ?string $errorTitle = null,
        private array $additionalData = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($errorMessage, 0, $previous);
    }

    /**
     * Create a business logic validation error (422).
     *
     * @param  array<string, mixed>  $additionalData
     */
    public static function businessLogicError(
        string $errorCode,
        string $message,
        array $additionalData = [],
    ): static {
        return new self(
            responseCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            errorCode: $errorCode,
            errorMessage: $message,
            errorTitle: 'Business Logic Validation Failed',
            additionalData: $additionalData,
        );
    }

    /**
     * Create a resource not found error (404).
     */
    public static function resourceNotFound(string $resourceType, string|int $resourceId): static
    {
        return new self(
            responseCode: Response::HTTP_NOT_FOUND,
            errorCode: ApiErrorCode::RESOURCE_NOT_FOUND->value,
            errorMessage: "{$resourceType} with ID '{$resourceId}' was not found",
            errorTitle: 'Resource Not Found',
        );
    }

    /**
     * Create an unauthorized error (401).
     */
    public static function unauthorized(string $message = 'Authentication required'): static
    {
        return new self(
            responseCode: Response::HTTP_UNAUTHORIZED,
            errorCode: ApiErrorCode::UNAUTHORIZED->value,
            errorMessage: $message,
            errorTitle: 'Unauthorized Access',
        );
    }

    /**
     * Create a forbidden error (403).
     */
    public static function forbidden(string $message = 'You do not have permission to access this resource'): static
    {
        return new self(
            responseCode: Response::HTTP_FORBIDDEN,
            errorCode: ApiErrorCode::FORBIDDEN->value,
            errorMessage: $message,
            errorTitle: 'Access Forbidden',
        );
    }

    /**
     * Create a bad request error (400).
     */
    public static function badRequest(string $errorCode, string $message): static
    {
        return new self(
            responseCode: Response::HTTP_BAD_REQUEST,
            errorCode: $errorCode,
            errorMessage: $message,
            errorTitle: 'Bad Request',
        );
    }

    /**
     * Create an internal server error (500).
     */
    public static function serverError(string $errorCode, string $message, ?Throwable $previous = null): static
    {
        return new self(
            responseCode: Response::HTTP_INTERNAL_SERVER_ERROR,
            errorCode: $errorCode,
            errorMessage: $message,
            errorTitle: 'Internal Server Error',
            previous: $previous,
        );
    }

    /**
     * Create a service unavailable error (503).
     */
    public static function serviceUnavailable(
        string $message = 'Service temporarily unavailable',
        ?int $retryAfter = null,
    ): static {
        return new self(
            responseCode: Response::HTTP_SERVICE_UNAVAILABLE,
            errorCode: ApiErrorCode::SERVICE_UNAVAILABLE->value,
            errorMessage: $message,
            errorTitle: 'Service Unavailable',
            additionalData: $retryAfter !== null ? ['retry_after' => $retryAfter] : [],
        );
    }

    /**
     * Create a rate limit error (429).
     */
    public static function rateLimitExceeded(int $retryAfter = 60): static
    {
        return new self(
            responseCode: Response::HTTP_TOO_MANY_REQUESTS,
            errorCode: ApiErrorCode::RATE_LIMIT_EXCEEDED->value,
            errorMessage: 'Too many requests. Please try again later.',
            errorTitle: 'Rate Limit Exceeded',
            additionalData: ['retry_after' => $retryAfter],
        );
    }

    /**
     * Create a conflict error (409).
     */
    public static function conflict(string $errorCode, string $message): static
    {
        return new self(
            responseCode: Response::HTTP_CONFLICT,
            errorCode: $errorCode,
            errorMessage: $message,
            errorTitle: 'Conflict',
        );
    }

    /**
     * Create a method not allowed error (405).
     */
    public static function methodNotAllowed(string $message = 'The HTTP method is not allowed for this endpoint'): static
    {
        return new self(
            responseCode: Response::HTTP_METHOD_NOT_ALLOWED,
            errorCode: ApiErrorCode::METHOD_NOT_ALLOWED->value,
            errorMessage: $message,
            errorTitle: 'Method Not Allowed',
        );
    }

    /**
     * Log the exception with a severity based on the status code.
     */
    public function report(): void
    {
        $logContext = [
            'error_code' => $this->errorCode,
            'http_status' => $this->responseCode,
            'message' => $this->errorMessage,
            'additional_data' => $this->additionalData,
        ];

        if ($this->responseCode >= Response::HTTP_INTERNAL_SERVER_ERROR) {
            Log::error('API Exception (Server Error)', $logContext);

            if (config('app.debug')) {
                Log::error('Stack Trace', ['trace' => $this->getTraceAsString()]);
            }

            return;
        }

        Log::warning('API Exception (Client Error)', $logContext);
    }

    /**
     * Render the exception into an RFC 9457 JSON response.
     */
    public function render(Request $request): JsonResponse
    {
        $isDebugMode = (bool) config('app.debug', false);

        $response = [
            'type' => $this->buildErrorTypeUri(),
            'title' => $this->getErrorTitle(),
            'detail' => $this->getErrorDetail($isDebugMode),
            'instance' => '/'.ltrim($request->path(), '/'),
            'error_code' => $this->errorCode,
            'timestamp' => now()->toIso8601String(),
        ];

        if ($this->responseCode >= Response::HTTP_INTERNAL_SERVER_ERROR) {
            $response['status'] = $this->responseCode;
            $response['trace_id'] = $this->generateTraceId();
        }

        if ($this->additionalData !== []) {
            $response = array_merge($response, $this->additionalData);
        }

        if ($isDebugMode) {
            $response['debug'] = $this->getDebugInformation();
        }

        return new JsonResponse($response, $this->responseCode);
    }

    private function buildErrorTypeUri(): string
    {
        $baseUrl = config('app.url');
        $errorCodeSlug = strtolower(str_replace('_', '-', $this->errorCode));

        return "{$baseUrl}/errors/{$errorCodeSlug}";
    }

    private function getErrorTitle(): string
    {
        return $this->errorTitle ?? Response::$statusTexts[$this->responseCode] ?? 'Error';
    }

    private function getErrorDetail(bool $isDebugMode): string
    {
        if (! $isDebugMode && $this->responseCode >= Response::HTTP_INTERNAL_SERVER_ERROR) {
            return 'An unexpected error occurred while processing your request. Please try again later.';
        }

        return $this->errorMessage;
    }

    private function generateTraceId(): string
    {
        return 'err_'.bin2hex(random_bytes(8)).'_'.time();
    }

    /**
     * @return array<string, mixed>
     */
    private function getDebugInformation(): array
    {
        $debug = [
            'exception_class' => get_class($this),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'trace' => collect($this->getTrace())
                ->take(10)
                ->map(fn (array $trace): array => [
                    'file' => $trace['file'] ?? 'unknown',
                    'line' => $trace['line'] ?? 0,
                    'function' => $trace['function'],
                    'class' => $trace['class'] ?? null,
                ])
                ->all(),
        ];

        $previous = $this->getPrevious();

        if ($previous !== null) {
            $debug['previous_exception'] = [
                'class' => get_class($previous),
                'message' => $previous->getMessage(),
                'file' => $previous->getFile(),
                'line' => $previous->getLine(),
            ];
        }

        return $debug;
    }
}

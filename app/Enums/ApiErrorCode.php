<?php

namespace App\Enums;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cross-cutting error codes used by {@see ApiException}.
 *
 * Domain-specific error codes should be declared on the feature that owns
 * them; this enum holds framework and infrastructure level codes.
 */
enum ApiErrorCode: string
{
    // Authentication & authorization
    case UNAUTHORIZED = 'unauthorized';
    case FORBIDDEN = 'forbidden';
    case TOKEN_EXPIRED = 'token_expired';
    case TOKEN_INVALID = 'token_invalid';
    case INVALID_CREDENTIALS = 'invalid_credentials';
    case ACCOUNT_INACTIVE = 'account_inactive';
    case EMAIL_NOT_VERIFIED = 'email_not_verified';

    // Resources
    case RESOURCE_NOT_FOUND = 'resource_not_found';
    case RESOURCE_ALREADY_EXISTS = 'resource_already_exists';

    // Validation
    case VALIDATION_FAILED = 'validation_failed';
    case INVALID_INPUT = 'invalid_input';

    // Rate limiting
    case RATE_LIMIT_EXCEEDED = 'rate_limit_exceeded';
    case QUOTA_EXCEEDED = 'quota_exceeded';

    // HTTP request
    case BAD_REQUEST = 'bad_request';
    case METHOD_NOT_ALLOWED = 'method_not_allowed';
    case ROUTE_NOT_FOUND = 'route_not_found';

    // Conflict / state
    case CONFLICT = 'conflict';
    case OPERATION_NOT_ALLOWED = 'operation_not_allowed';
    case RESOURCE_LOCKED = 'resource_locked';
    case SYNC_IN_PROGRESS = 'sync_in_progress';

    // Database
    case DATABASE_ERROR = 'database_error';
    case DATABASE_CONNECTION_FAILED = 'database_connection_failed';
    case DATABASE_QUERY_FAILED = 'database_query_failed';

    // External services
    case SERVICE_UNAVAILABLE = 'service_unavailable';
    case EXTERNAL_SERVICE_ERROR = 'external_service_error';
    case CLOCKIFY_API_ERROR = 'clockify_api_error';
    case CLOCKIFY_AUTHENTICATION_FAILED = 'clockify_authentication_failed';
    case CLOCKIFY_RATE_LIMITED = 'clockify_rate_limited';

    // System
    case INTERNAL_SERVER_ERROR = 'internal_server_error';
    case NOT_IMPLEMENTED = 'not_implemented';
    case MAINTENANCE_MODE = 'maintenance_mode';

    // File & storage
    case FILE_NOT_FOUND = 'file_not_found';
    case FILE_UPLOAD_FAILED = 'file_upload_failed';
    case FILE_TOO_LARGE = 'file_too_large';
    case STORAGE_ERROR = 'storage_error';
    case EXPORT_FAILED = 'export_failed';

    // Processing
    case PROCESSING_FAILED = 'processing_failed';
    case SYNC_FAILED = 'sync_failed';

    /**
     * A human-readable description of the error code.
     */
    public function description(): string
    {
        return match ($this) {
            self::UNAUTHORIZED => 'Authentication is required to access this resource.',
            self::FORBIDDEN => 'You do not have permission to perform this action.',
            self::TOKEN_EXPIRED => 'The provided token has expired.',
            self::TOKEN_INVALID => 'The provided token is invalid.',
            self::INVALID_CREDENTIALS => 'The provided credentials are incorrect.',
            self::ACCOUNT_INACTIVE => 'This account is inactive.',
            self::EMAIL_NOT_VERIFIED => 'This account has not verified its email address.',
            self::RESOURCE_NOT_FOUND => 'The requested resource was not found.',
            self::RESOURCE_ALREADY_EXISTS => 'The resource already exists.',
            self::VALIDATION_FAILED => 'The given data failed validation.',
            self::INVALID_INPUT => 'The provided input is invalid.',
            self::RATE_LIMIT_EXCEEDED => 'Too many requests have been made.',
            self::QUOTA_EXCEEDED => 'The allowed quota has been exceeded.',
            self::BAD_REQUEST => 'The request is malformed.',
            self::METHOD_NOT_ALLOWED => 'The HTTP method is not allowed for this endpoint.',
            self::ROUTE_NOT_FOUND => 'The requested route was not found.',
            self::CONFLICT => 'The request conflicts with the current state of the resource.',
            self::OPERATION_NOT_ALLOWED => 'This operation is not allowed.',
            self::RESOURCE_LOCKED => 'The resource is currently locked.',
            self::SYNC_IN_PROGRESS => 'A synchronization is already in progress.',
            self::DATABASE_ERROR => 'A database error occurred.',
            self::DATABASE_CONNECTION_FAILED => 'Could not connect to the database.',
            self::DATABASE_QUERY_FAILED => 'The database query failed.',
            self::SERVICE_UNAVAILABLE => 'The service is temporarily unavailable.',
            self::EXTERNAL_SERVICE_ERROR => 'An external service returned an error.',
            self::CLOCKIFY_API_ERROR => 'The Clockify API returned an error.',
            self::CLOCKIFY_AUTHENTICATION_FAILED => 'Clockify authentication failed.',
            self::CLOCKIFY_RATE_LIMITED => 'The Clockify API rate limit was reached.',
            self::INTERNAL_SERVER_ERROR => 'An unexpected error occurred.',
            self::NOT_IMPLEMENTED => 'This feature is not implemented.',
            self::MAINTENANCE_MODE => 'The application is in maintenance mode.',
            self::FILE_NOT_FOUND => 'The requested file was not found.',
            self::FILE_UPLOAD_FAILED => 'The file could not be uploaded.',
            self::FILE_TOO_LARGE => 'The file is too large.',
            self::STORAGE_ERROR => 'A storage error occurred.',
            self::EXPORT_FAILED => 'The export could not be generated.',
            self::PROCESSING_FAILED => 'The processing job failed.',
            self::SYNC_FAILED => 'The synchronization failed.',
        };
    }

    /**
     * The HTTP status code associated with the error code.
     */
    public function httpStatusCode(): int
    {
        return match ($this) {
            self::UNAUTHORIZED,
            self::TOKEN_EXPIRED,
            self::TOKEN_INVALID,
            self::INVALID_CREDENTIALS => Response::HTTP_UNAUTHORIZED,

            self::FORBIDDEN,
            self::ACCOUNT_INACTIVE,
            self::EMAIL_NOT_VERIFIED,
            self::OPERATION_NOT_ALLOWED => Response::HTTP_FORBIDDEN,

            self::RESOURCE_NOT_FOUND,
            self::FILE_NOT_FOUND,
            self::ROUTE_NOT_FOUND => Response::HTTP_NOT_FOUND,

            self::RESOURCE_ALREADY_EXISTS,
            self::CONFLICT,
            self::RESOURCE_LOCKED,
            self::SYNC_IN_PROGRESS => Response::HTTP_CONFLICT,

            self::VALIDATION_FAILED,
            self::INVALID_INPUT,
            self::PROCESSING_FAILED,
            self::SYNC_FAILED,
            self::EXPORT_FAILED => Response::HTTP_UNPROCESSABLE_ENTITY,

            self::RATE_LIMIT_EXCEEDED,
            self::QUOTA_EXCEEDED,
            self::CLOCKIFY_RATE_LIMITED => Response::HTTP_TOO_MANY_REQUESTS,

            self::METHOD_NOT_ALLOWED => Response::HTTP_METHOD_NOT_ALLOWED,

            self::FILE_TOO_LARGE => Response::HTTP_REQUEST_ENTITY_TOO_LARGE,

            self::SERVICE_UNAVAILABLE => Response::HTTP_SERVICE_UNAVAILABLE,

            self::BAD_REQUEST,
            self::CLOCKIFY_AUTHENTICATION_FAILED => Response::HTTP_BAD_REQUEST,

            self::DATABASE_ERROR,
            self::DATABASE_CONNECTION_FAILED,
            self::DATABASE_QUERY_FAILED,
            self::EXTERNAL_SERVICE_ERROR,
            self::CLOCKIFY_API_ERROR,
            self::INTERNAL_SERVER_ERROR,
            self::NOT_IMPLEMENTED,
            self::MAINTENANCE_MODE,
            self::FILE_UPLOAD_FAILED,
            self::STORAGE_ERROR => Response::HTTP_INTERNAL_SERVER_ERROR,
        };
    }

    public function isServerError(): bool
    {
        return $this->httpStatusCode() >= Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    public function isClientError(): bool
    {
        return ! $this->isServerError();
    }

    /**
     * Backwards-compatible accessor for the enum value.
     */
    public function getValue(): string
    {
        return $this->value;
    }
}

<?php

use App\Enums\ApiErrorCode;
use App\Services\Clockify\ClockifyErrorMapper;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->mapper = new ClockifyErrorMapper;
});

function clockifyErrorResponse(int $status, string|array $body = '', array $headers = []): Response
{
    return new Response(new Psr7Response($status, $headers, is_array($body) ? json_encode($body) : $body));
}

it('maps every documented status to exactly one error code', function (int $status, ApiErrorCode $expected) {
    $result = $this->mapper->fromResponse(clockifyErrorResponse($status, ['message' => 'nope']));

    expect($result['code'])->toBe($expected);
})->with([
    'unauthorized' => [401, ApiErrorCode::CLOCKIFY_AUTHENTICATION_FAILED],
    'payment required' => [402, ApiErrorCode::CLOCKIFY_SUBSCRIPTION_REQUIRED],
    'forbidden' => [403, ApiErrorCode::CLOCKIFY_FORBIDDEN],
    'not found' => [404, ApiErrorCode::CLOCKIFY_NOT_FOUND],
    'bad request' => [400, ApiErrorCode::CLOCKIFY_API_ERROR],
    'unknown client error' => [418, ApiErrorCode::CLOCKIFY_API_ERROR],
    'rate limited' => [429, ApiErrorCode::CLOCKIFY_RATE_LIMITED],
    'server error' => [500, ApiErrorCode::CLOCKIFY_API_UNAVAILABLE],
    'unavailable' => [503, ApiErrorCode::CLOCKIFY_API_UNAVAILABLE],
    'unexpected success' => [200, ApiErrorCode::CLOCKIFY_INVALID_RESPONSE],
]);

it('exposes retry-after on rate limits', function () {
    $result = $this->mapper->fromResponse(
        clockifyErrorResponse(429, ['message' => 'Too many requests'], ['Retry-After' => '90']),
    );

    expect($result['code'])->toBe(ApiErrorCode::CLOCKIFY_RATE_LIMITED)
        ->and($result['retry_after'])->toBe(90);
});

it('has no retry-after when the header is absent', function () {
    $result = $this->mapper->fromResponse(clockifyErrorResponse(429, 'Too many requests'));

    expect($result['code'])->toBe(ApiErrorCode::CLOCKIFY_RATE_LIMITED)
        ->and($result['retry_after'])->toBeNull();
});

it('treats a too-many-requests message as rate limited', function () {
    expect($this->mapper->fromResponse(clockifyErrorResponse(400, 'Too many requests'))['code'])
        ->toBe(ApiErrorCode::CLOCKIFY_RATE_LIMITED);
});

it('parses the message defensively', function () {
    expect($this->mapper->fromResponse(clockifyErrorResponse(400, ['message' => 'Bad input']))['message'])->toBe('Bad input')
        ->and($this->mapper->fromResponse(clockifyErrorResponse(400, ['error' => 'Nope']))['message'])->toBe('Nope')
        ->and($this->mapper->fromResponse(clockifyErrorResponse(400, 'plain text'))['message'])->toBe('plain text')
        ->and($this->mapper->fromResponse(clockifyErrorResponse(400, ''))['message'])->toBe(ApiErrorCode::CLOCKIFY_API_ERROR->description());
});

it('caps oversized messages', function () {
    $result = $this->mapper->fromResponse(clockifyErrorResponse(400, str_repeat('x', 2000)));

    expect(mb_strlen($result['message']))->toBeLessThanOrEqual(500);
});

it('maps transport exceptions', function () {
    expect($this->mapper->fromThrowable(new ConnectionException('timeout'))['code'])
        ->toBe(ApiErrorCode::CLOCKIFY_NETWORK_ERROR)
        ->and($this->mapper->fromThrowable(new JsonException('bad'))['code'])
        ->toBe(ApiErrorCode::CLOCKIFY_INVALID_RESPONSE)
        ->and($this->mapper->fromThrowable(new RuntimeException('boom'))['code'])
        ->toBe(ApiErrorCode::CLOCKIFY_UNKNOWN);
});

it('gives every clockify code a usable action, label and hint', function () {
    $codes = [
        ApiErrorCode::CLOCKIFY_AUTHENTICATION_FAILED,
        ApiErrorCode::CLOCKIFY_FORBIDDEN,
        ApiErrorCode::CLOCKIFY_NOT_FOUND,
        ApiErrorCode::CLOCKIFY_RATE_LIMITED,
        ApiErrorCode::CLOCKIFY_API_ERROR,
        ApiErrorCode::CLOCKIFY_API_UNAVAILABLE,
        ApiErrorCode::CLOCKIFY_NETWORK_ERROR,
        ApiErrorCode::CLOCKIFY_INVALID_RESPONSE,
        ApiErrorCode::CLOCKIFY_SUBSCRIPTION_REQUIRED,
        ApiErrorCode::CLOCKIFY_UNKNOWN,
    ];

    foreach ($codes as $code) {
        expect($code->action())->toBeIn(['retry', 'reconnect', 'wait', 'contact_support'])
            ->and($code->label())->not->toBeEmpty()
            ->and($code->hint())->not->toBeEmpty();
    }
});

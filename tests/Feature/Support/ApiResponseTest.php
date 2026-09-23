<?php

use App\Enums\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

it('renders an rfc 9457 problem detail response', function () {
    $exception = ApiException::resourceNotFound('User', 42);

    $response = $exception->render(Request::create('/api/users/42'));
    $data = $response->getData(true);

    expect($response->getStatusCode())->toBe(404)
        ->and($data['error_code'])->toBe(ApiErrorCode::RESOURCE_NOT_FOUND->value)
        ->and($data['instance'])->toBe('/api/users/42')
        ->and($data['title'])->toBe('Resource Not Found');
});

it('maps api error codes to http statuses', function () {
    expect(ApiErrorCode::RESOURCE_NOT_FOUND->httpStatusCode())->toBe(404)
        ->and(ApiErrorCode::FORBIDDEN->httpStatusCode())->toBe(403)
        ->and(ApiErrorCode::INTERNAL_SERVER_ERROR->isServerError())->toBeTrue()
        ->and(ApiErrorCode::RESOURCE_NOT_FOUND->isClientError())->toBeTrue();
});

it('wraps plain data under a data key', function () {
    $controller = new class extends Controller
    {
        public function callRespond(mixed $result): JsonResponse
        {
            return $this->respond($result);
        }
    };

    $response = $controller->callRespond(['answer' => 42]);

    expect($response->getData(true))->toBe(['data' => ['answer' => 42]]);
});

it('throws an api exception from fail', function () {
    $controller = new class extends Controller
    {
        public function callFail(): never
        {
            $this->fail(ApiErrorCode::FORBIDDEN->value, 'Nope', 403);
        }
    };

    $controller->callFail();
})->throws(ApiException::class);

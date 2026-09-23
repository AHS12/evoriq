<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

abstract class Controller
{
    /**
     * Return a plain JSON message response.
     */
    public function successWithMessage(string $message, int $code = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse(['message' => $message], $code);
    }

    /**
     * Build a standardized JSON response for API data.
     *
     * Resource responses keep their own payload; everything else is wrapped
     * under `data`. Optional `meta` is merged into the response.
     *
     * @param  array<string, mixed>  $meta
     */
    protected function respond(mixed $result = null, int $code = Response::HTTP_OK, array $meta = []): JsonResponse
    {
        if ($result instanceof JsonResource) {
            $payload = (array) $result->response()->getData(true);

            if ($meta !== []) {
                $existingMeta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
                $payload['meta'] = array_merge($existingMeta, $meta);
            }

            return new JsonResponse($payload, $code);
        }

        if ($result instanceof Collection || $result instanceof Arrayable) {
            $data = $result->toArray();
        } elseif (is_array($result)) {
            $data = $result;
        } else {
            $data = $result;
        }

        $body = ['data' => $data];

        if ($meta !== []) {
            $body['meta'] = $meta;
        }

        return new JsonResponse($body, $code);
    }

    /**
     * Abort with an RFC 9457 error response. This method never returns.
     *
     * @param  array<string, mixed>  $additional
     *
     * @throws ApiException
     */
    protected function fail(
        string $errorCode,
        string $message,
        int $code = Response::HTTP_BAD_REQUEST,
        array $additional = [],
    ): never {
        throw new ApiException(
            responseCode: $code,
            errorCode: $errorCode,
            errorMessage: $message,
            additionalData: $additional,
        );
    }

    /**
     * Return a 204 No Content response.
     */
    protected function successNoContent(): JsonResponse
    {
        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }
}

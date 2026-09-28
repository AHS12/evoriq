<?php

use App\Enums\PipelineFailureReason;
use App\Exceptions\ApiException;
use App\Exceptions\ImportCancelledException;
use App\Services\Pipeline\FailureReasonResolver;
use Illuminate\Queue\MaxAttemptsExceededException;

function failureResolver(): FailureReasonResolver
{
    return new FailureReasonResolver;
}

test('maps an exhausted queue attempt to worker_lost', function () {
    expect(failureResolver()->fromThrowable(new MaxAttemptsExceededException('gone')))
        ->toBe(PipelineFailureReason::WORKER_LOST);
});

test('maps a cancellation exception to cancelled', function () {
    expect(failureResolver()->fromThrowable(new ImportCancelledException('cancelled')))
        ->toBe(PipelineFailureReason::CANCELLED);
});

test('maps API exceptions to classified reasons', function () {
    $resolver = failureResolver();

    expect($resolver->fromThrowable(ApiException::rateLimitExceeded(30)))->toBe(PipelineFailureReason::RATE_LIMITED)
        ->and($resolver->fromThrowable(ApiException::unauthorized()))->toBe(PipelineFailureReason::AUTH_FAILED)
        ->and($resolver->fromThrowable(ApiException::forbidden()))->toBe(PipelineFailureReason::AUTH_FAILED)
        ->and($resolver->fromThrowable(ApiException::serviceUnavailable()))->toBe(PipelineFailureReason::API_UNAVAILABLE);
});

test('classifies from a stored message', function () {
    $resolver = failureResolver();

    expect($resolver->fromMessage('Too many requests. Please try again later.'))->toBe(PipelineFailureReason::RATE_LIMITED)
        ->and($resolver->fromMessage('The job timed out.'))->toBe(PipelineFailureReason::TIMEOUT)
        ->and($resolver->fromMessage('The job stalled — its worker was lost or it timed out.'))->toBe(PipelineFailureReason::WORKER_LOST)
        ->and($resolver->fromMessage('The source file for this import is no longer available.'))->toBe(PipelineFailureReason::SOURCE_MISSING)
        ->and($resolver->fromMessage('SQLSTATE[08006] connection refused'))->toBe(PipelineFailureReason::NETWORK)
        ->and($resolver->fromMessage('The name field is required.'))->toBe(PipelineFailureReason::VALIDATION)
        ->and($resolver->fromMessage(null))->toBe(PipelineFailureReason::UNKNOWN);
});

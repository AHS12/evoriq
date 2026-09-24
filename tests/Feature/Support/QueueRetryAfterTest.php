<?php

test('every queue channel timeout is below the connection retry_after', function () {
    $maxTimeout = (int) collect(config('queue.channels'))->max('timeout');

    foreach (['redis', 'database'] as $connection) {
        $retryAfter = (int) config("queue.connections.{$connection}.retry_after");

        expect($retryAfter)->toBeGreaterThan($maxTimeout);
    }
});

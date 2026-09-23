<?php

use App\Enums\QueueName;
use App\Registry\QueueRegistry;

it('resolves queue channel configuration', function () {
    $heavy = QueueRegistry::get(QueueName::HEAVY);

    expect($heavy->name)->toBe('heavy')
        ->and($heavy->tries)->toBe(1)
        ->and($heavy->timeout)->toBe(1800);

    $critical = QueueRegistry::get(QueueName::CRITICAL);

    expect($critical->name)->toBe('critical')
        ->and($critical->tries)->toBe(3);
});

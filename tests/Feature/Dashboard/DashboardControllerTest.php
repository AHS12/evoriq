<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('renders the dashboard shell with typed props', function () {
    $this->actingAs(superAdmin())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('stats', 4)
            ->has('setup', 3)
            ->where('hasAnalytics', false));
});

test('guests cannot view the dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('the users stat reflects the account count', function () {
    $this->actingAs(superAdmin())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.2.key', 'users')
            ->where('stats.2.value', (string) User::query()->count()));
});

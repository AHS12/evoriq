<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_not_found_requests_render_the_branded_error_page()
    {
        $this->get('/this-route-does-not-exist')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page
                ->component('errors/error')
                ->where('status', 404),
            );
    }

    public function test_forbidden_requests_render_the_branded_error_page()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.settings.general.edit'))
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page
                ->component('errors/error')
                ->where('status', 403),
            );
    }

    public function test_json_requests_still_receive_json_errors()
    {
        $this->getJson('/this-route-does-not-exist')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }
}

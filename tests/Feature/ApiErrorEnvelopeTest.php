<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ApiErrorEnvelopeTest extends TestCase
{
    public function test_unknown_api_route_returns_404_envelope(): void
    {
        $this->get('/api/does-not-exist')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('data', null);
    }

    public function test_unhandled_exception_returns_generic_500_without_details(): void
    {
        config(['app.debug' => false]);
        Route::get('/api/test-boom', fn () => throw new RuntimeException('secret internal detail'));

        $this->getJson('/api/test-boom')
            ->assertServerError()
            ->assertExactJson(['success' => false, 'message' => 'Server error.', 'data' => null])
            ->assertDontSee('secret internal detail');
    }
}

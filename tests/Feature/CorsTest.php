<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsTest extends TestCase
{
    public function test_preflight_from_frontend_origin_is_allowed(): void
    {
        $this->withHeaders(['Origin' => 'http://localhost:3000', 'Access-Control-Request-Method' => 'POST'])
            ->options('/api/login')
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
    }

    public function test_preflight_from_other_origin_gets_no_allow_header(): void
    {
        $this->withHeaders(['Origin' => 'https://evil.example', 'Access-Control-Request-Method' => 'POST'])
            ->options('/api/login')
            ->assertNoContent()
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}

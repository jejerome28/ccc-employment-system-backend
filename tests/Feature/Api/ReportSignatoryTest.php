<?php

namespace Tests\Feature\Api;

use App\Models\ReportSignatory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportSignatoryTest extends TestCase
{
    use RefreshDatabase;

    private function body(array $extra = []): array
    {
        return array_merge(['label' => 'Prepared By:', 'name' => 'NEW NAME', 'title' => "Line 1\nLine 2"], $extra);
    }

    public function test_seeded_four_from_template(): void
    {
        $this->assertCount(4, ReportSignatory::ordered());
        $last = ReportSignatory::ordered()->last();
        $this->assertSame(4, $last->position);
        $this->assertSame('DR. RONALD A. GONZALES, LPT', $last->name);
        $this->assertSame("College President\nCity College of Calamba", $last->title);

        Sanctum::actingAs(User::factory()->viewer()->create());
        $this->getJson(route('api.report-signatories.index'))->assertOk()
            ->assertJsonCount(4, 'data.signatories')
            ->assertJsonPath('data.signatories.0.position', 1);
    }

    public function test_viewer_reads_admin_updates(): void
    {
        $s = ReportSignatory::ordered()->first();
        $url = route('api.report-signatories.update', $s);

        Sanctum::actingAs(User::factory()->viewer()->create());
        $this->putJson($url, $this->body())->assertForbidden();
        Sanctum::actingAs(User::factory()->hr()->create());
        $this->putJson($url, $this->body())->assertForbidden();

        Sanctum::actingAs(User::factory()->create());
        $this->putJson($url, $this->body())->assertOk()
            ->assertJsonPath('message', 'Signatory updated.')
            ->assertJsonPath('data.signatory.name', 'NEW NAME');
        $this->assertSame("Line 1\nLine 2", $s->fresh()->title);
    }

    public function test_update_validation(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $url = route('api.report-signatories.update', ReportSignatory::ordered()->first());

        $this->putJson($url, [])->assertUnprocessable()->assertJsonValidationErrors(['label', 'name', 'title'], 'data.errors');
        $this->putJson($url, $this->body(['label' => str_repeat('a', 61)]))->assertUnprocessable();
        $this->putJson($url, $this->body(['name' => str_repeat('a', 121)]))->assertUnprocessable();
        $this->putJson($url, $this->body(['title' => str_repeat('a', 301)]))->assertUnprocessable();
        $this->putJson($url, $this->body(['position' => 2]))->assertUnprocessable();
    }
}

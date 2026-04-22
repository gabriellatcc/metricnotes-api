<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class TaskRecordViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_last_viewed_at_for_owner(): void
    {
        Carbon::setTestNow('2026-04-21 15:00:00');

        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create([
            'last_viewed_at' => null,
        ]);

        $token = JWTAuth::fromUser($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/task/'.$task->id.'/view');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $task->refresh();
        $this->assertEquals('2026-04-21 15:00:00', $task->last_viewed_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_forbidden_for_other_users_task(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $task = Task::factory()->for($owner)->create();

        $token = JWTAuth::fromUser($other);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/task/'.$task->id.'/view');

        $response->assertForbidden();
    }
}

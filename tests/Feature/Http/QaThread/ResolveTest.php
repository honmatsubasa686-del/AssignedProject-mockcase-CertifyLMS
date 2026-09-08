<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_resolve_qa_thread(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $response = $this->actingAs($student)
            ->post(route('qa-board.resolve', $thread));

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'status' => 'resolved',
        ]);

        $this->assertDatabaseMissing('qa_threads', [
            'id' => $thread->id,
            'resolved_at' => null,
        ]);

        $response->assertRedirect(route('qa-board.show', $thread));
    }

    public function test_author_can_unresolve_qa_thread(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        $response = $this->actingAs($student)
            ->post(route('qa-board.unresolve', $thread));

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'status' => 'unresolved',
            'resolved_at' => null,
        ]);

        $response->assertRedirect(route('qa-board.show', $thread));
    }

    public function test_non_author_cannot_resolve_qa_thread(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $author->id,
            'certification_id' => $certification->id,
        ]);

        $response = $this->actingAs($otherStudent)
            ->post(route('qa-board.resolve', $thread));

        $response->assertForbidden();

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'status' => 'unresolved',
            'resolved_at' => null,
        ]);
    }

    public function test_coach_cannot_resolve_qa_thread(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $author->id,
            'certification_id' => $certification->id,
        ]);

        $response = $this->actingAs($coach)
            ->post(route('qa-board.resolve', $thread));

        $response->assertForbidden();

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'status' => 'unresolved',
            'resolved_at' => null,
        ]);
    }

    public function test_admin_cannot_resolve_qa_thread(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $admin = User::factory()->admin()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $author->id,
            'certification_id' => $certification->id,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('qa-board.resolve', $thread));

        $response->assertForbidden();

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'status' => 'unresolved',
            'resolved_at' => null,
        ]);
    }
}

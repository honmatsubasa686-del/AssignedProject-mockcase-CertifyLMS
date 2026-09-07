<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_delete_thread_without_replies(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $response = $this->actingAs($student)
            ->delete(route('qa-board.destroy', $thread));

        $response->assertRedirect(route('qa-board.index'));

        $this->assertDatabaseMissing('qa_threads', [
            'id' => $thread->id,
        ]);

        $response->assertRedirect(route('qa-board.index'));

        $this->assertDatabaseMissing('qa_threads', [
            'id' => $thread->id,
        ]);
    }

    public function test_author_cannot_delete_thread_with_replies(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $thread->replies()->create([
            'user_id' => $student->id,
            'body' => '回答があります。',
        ]);

        $response = $this->actingAs($student)
            ->delete(route('qa-board.destroy', $thread));

        $response->assertForbidden();

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
        ]);
    }

    public function test_admin_can_delete_thread_with_replies(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $admin = User::factory()->admin()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $thread->replies()->create([
            'user_id' => $student->id,
            'body' => '回答があります。',
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.qa-board.destroy', $thread));

        $response->assertRedirect(route('admin.qa-board.index'));

        $this->assertDatabaseMissing('qa_threads', [
            'id' => $thread->id,
        ]);
    }
}

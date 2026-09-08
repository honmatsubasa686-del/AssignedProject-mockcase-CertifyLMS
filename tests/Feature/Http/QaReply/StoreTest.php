<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaReply;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_create_reply(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'certification_id' => $certification->id,
        ]);

        $response = $this->actingAs($student)
            ->post(route('qa-board.replies.store', $thread), [
                'body' => 'テスト用の回答です。',
            ]);

        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $student->id,
            'body' => 'テスト用の回答です。',
        ]);

        $reply = QaReply::query()->latest()->first();

        $response->assertRedirect(
            route('qa-board.show', $thread).'#reply-'.$reply->id
        );
    }

    public function test_coach_can_create_reply(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'certification_id' => $certification->id,
        ]);

        $response = $this->actingAs($coach)
            ->post(route('qa-board.replies.store', $thread), [
                'body' => 'コーチからの回答です。',
            ]);

        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $coach->id,
            'body' => 'コーチからの回答です。',
        ]);
    }

    public function test_admin_cannot_create_reply(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'certification_id' => $certification->id,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('qa-board.replies.store', $thread), [
                'body' => '管理者からの回答です。',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_body_is_required(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'certification_id' => $certification->id,
        ]);

        $response = $this->actingAs($student)
            ->post(route('qa-board.replies.store', $thread), [
                'body' => '',
            ]);

        $response->assertSessionHasErrors('body');
    }

    public function test_body_max_length(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'certification_id' => $certification->id,
        ]);

        $response = $this->actingAs($student)
            ->post(route('qa-board.replies.store', $thread), [
                'body' => str_repeat('a', 5001),
            ]);

        $response->assertSessionHasErrors('body');
    }
}

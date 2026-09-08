<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaReply;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_delete_reply(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'certification_id' => $certification->id,
        ]);

        $reply = QaReply::factory()->create([
            'qa_thread_id' => $thread->id,
            'user_id' => $student->id,
            'body' => '削除対象の回答です。',
        ]);

        $response = $this->actingAs($student)
            ->delete(route('qa-board.replies.destroy', [$thread, $reply]));

        $this->assertDatabaseMissing('qa_replies', [
            'id' => $reply->id,
        ]);

        $response->assertRedirect(route('qa-board.show', $thread));
    }

    public function test_non_author_cannot_delete_reply(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'certification_id' => $certification->id,
        ]);

        $reply = QaReply::factory()->create([
            'qa_thread_id' => $thread->id,
            'user_id' => $author->id,
            'body' => '削除対象の回答です。',
        ]);

        $response = $this->actingAs($otherStudent)
            ->delete(route('qa-board.replies.destroy', [$thread, $reply]));

        $response->assertForbidden();

        $this->assertDatabaseHas('qa_replies', [
            'id' => $reply->id,
            'body' => '削除対象の回答です。',
        ]);
    }

    public function test_admin_can_delete_reply(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $admin = User::factory()->admin()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'certification_id' => $certification->id,
        ]);

        $reply = QaReply::factory()->create([
            'qa_thread_id' => $thread->id,
            'user_id' => $student->id,
            'body' => '管理者が削除する回答です。',
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.qa-board.replies.destroy', [$thread, $reply]));

        $this->assertDatabaseMissing('qa_replies', [
            'id' => $reply->id,
        ]);

        $response->assertRedirect(route('admin.qa-board.show', $thread));
    }
}

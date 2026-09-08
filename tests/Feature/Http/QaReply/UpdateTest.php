<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaReply;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_update_reply(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'certification_id' => $certification->id,
        ]);

        $reply = QaReply::factory()->create([
            'qa_thread_id' => $thread->id,
            'user_id' => $student->id,
            'body' => '変更前の回答です。',
        ]);

        $response = $this->actingAs($student)
            ->patch(route('qa-board.replies.update', [$thread, $reply]), [
                'body' => '変更後の回答です。',
            ]);

        $this->assertDatabaseHas('qa_replies', [
            'id' => $reply->id,
            'body' => '変更後の回答です。',
        ]);

        $response->assertRedirect(
            route('qa-board.show', $thread).'#reply-'.$reply->id
        );
    }

    public function test_non_author_cannot_update_reply(): void
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
            'body' => '変更前の回答です。',
        ]);

        $response = $this->actingAs($otherStudent)
            ->patch(route('qa-board.replies.update', [$thread, $reply]), [
                'body' => '勝手に変更した回答です。',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('qa_replies', [
            'id' => $reply->id,
            'body' => '変更前の回答です。',
        ]);
    }

    public function test_body_is_required_on_update(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'certification_id' => $certification->id,
        ]);

        $reply = QaReply::factory()->create([
            'qa_thread_id' => $thread->id,
            'user_id' => $student->id,
            'body' => '変更前の回答です。',
        ]);

        $response = $this->actingAs($student)
            ->patch(route('qa-board.replies.update', [$thread, $reply]), [
                'body' => '',
            ]);

        $response->assertSessionHasErrors('body');
    }

    public function test_body_max_length_on_update(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'certification_id' => $certification->id,
        ]);

        $reply = QaReply::factory()->create([
            'qa_thread_id' => $thread->id,
            'user_id' => $student->id,
            'body' => '変更前の回答です。',
        ]);

        $response = $this->actingAs($student)
            ->patch(route('qa-board.replies.update', [$thread, $reply]), [
                'body' => str_repeat('a', 5001),
            ]);

        $response->assertSessionHasErrors('body');
    }
}

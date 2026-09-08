<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_update_qa_thread(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => '変更前タイトル',
            'body' => '変更前本文',
        ]);

        $response = $this->actingAs($student)
            ->patch(route('qa-board.update', $thread), [
                'title' => '変更後タイトル',
                'body' => '変更後本文',
            ]);

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'title' => '変更後タイトル',
            'body' => '変更後本文',
        ]);

        $response->assertRedirect(route('qa-board.show', $thread));

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'title' => '変更後タイトル',
            'body' => '変更後本文',
        ]);

        $response->assertRedirect(route('qa-board.show', $thread));
    }

    public function test_certification_id_cannot_be_changed(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $originalCertification = Certification::factory()->published()->create();
        $anotherCertification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $originalCertification->id,
            'title' => '変更前タイトル',
            'body' => '変更前本文',
        ]);

        $this->actingAs($student)
            ->patch(route('qa-board.update', $thread), [
                'certification_id' => $anotherCertification->id,
                'title' => '変更後タイトル',
                'body' => '変更後本文',
            ]);

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'certification_id' => $originalCertification->id,
            'title' => '変更後タイトル',
            'body' => '変更後本文',
        ]);
    }

    public function test_non_author_cannot_update_qa_thread(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $author->id,
            'certification_id' => $certification->id,
            'title' => '変更前タイトル',
            'body' => '変更前本文',
        ]);

        $response = $this->actingAs($otherStudent)
            ->patch(route('qa-board.update', $thread), [
                'title' => '勝手に変更',
                'body' => '勝手に変更された本文',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'title' => '変更前タイトル',
            'body' => '変更前本文',
        ]);
    }

    public function test_title_is_required_on_update(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => '変更前タイトル',
            'body' => '変更前本文',
        ]);

        $response = $this->actingAs($student)
            ->patch(route('qa-board.update', $thread), [
                'title' => '',
                'body' => '変更後本文',
            ]);

        $response->assertSessionHasErrors('title');
    }

    public function test_body_is_required_on_update(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => '変更前タイトル',
            'body' => '変更前本文',
        ]);

        $response = $this->actingAs($student)
            ->patch(route('qa-board.update', $thread), [
                'title' => '変更後タイトル',
                'body' => '',
            ]);

        $response->assertSessionHasErrors('body');
    }

    public function test_title_max_length_on_update(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => '変更前タイトル',
            'body' => '変更前本文',
        ]);

        $response = $this->actingAs($student)
            ->patch(route('qa-board.update', $thread), [
                'title' => str_repeat('a', 201),
                'body' => '変更後本文',
            ]);

        $response->assertSessionHasErrors('title');
    }

    public function test_body_max_length_on_update(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => '変更前タイトル',
            'body' => '変更前本文',
        ]);

        $response = $this->actingAs($student)
            ->patch(route('qa-board.update', $thread), [
                'title' => '変更後タイトル',
                'body' => str_repeat('a', 5001),
            ]);

        $response->assertSessionHasErrors('body');
    }
}

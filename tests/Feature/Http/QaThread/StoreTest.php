<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_create_qa_thread(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $response = $this->actingAs($student)
            ->post(route('qa-board.store'), [
                'certification_id' => $certification->id,
                'title' => 'Laravelの質問',
                'body' => 'テスト用の質問本文です。',
            ]);

        $this->assertDatabaseHas('qa_threads', [
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => 'Laravelの質問',
            'body' => 'テスト用の質問本文です。',
            'status' => 'unresolved',
        ]);

        $thread = QaThread::query()->latest()->first();

        $response->assertRedirect(route('qa-board.show', $thread));
    }

    public function test_title_is_required(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $response = $this->actingAs($student)
            ->post(route('qa-board.store'), [
                'certification_id' => $certification->id,
                'title' => '',
                'body' => 'テスト用の質問本文です。',
            ]);

        $response->assertSessionHasErrors('title');
    }

    public function test_body_is_required(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $response = $this->actingAs($student)
            ->post(route('qa-board.store'), [
                'certification_id' => $certification->id,
                'title' => 'Laravelの質問',
                'body' => '',
            ]);

        $response->assertSessionHasErrors('body');
    }

    public function test_title_max_length(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $response = $this->actingAs($student)
            ->post(route('qa-board.store'), [
                'certification_id' => $certification->id,
                'title' => str_repeat('a', 201),
                'body' => 'テスト用の質問本文です。',
            ]);

        $response->assertSessionHasErrors('title');
    }

    public function test_body_max_length(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $response = $this->actingAs($student)
            ->post(route('qa-board.store'), [
                'certification_id' => $certification->id,
                'title' => 'Laravelの質問',
                'body' => str_repeat('a', 5001),
            ]);

        $response->assertSessionHasErrors('body');
    }

    public function test_certification_id_is_required(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($student)
            ->post(route('qa-board.store'), [
                'certification_id' => '',
                'title' => 'Laravelの質問',
                'body' => 'テスト用の質問本文です。',
            ]);

        $response->assertSessionHasErrors('certification_id');
    }

    public function test_certification_id_must_exist(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($student)
            ->post(route('qa-board.store'), [
                'certification_id' => (string) Str::ulid(),
                'title' => 'Laravelの質問',
                'body' => 'テスト用の質問本文です。',
            ]);

        $response->assertSessionHasErrors('certification_id');
    }

    public function test_coach_cannot_create_qa_thread(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $response = $this->actingAs($coach)
            ->post(route('qa-board.store'), [
                'certification_id' => $certification->id,
                'title' => 'Laravelの質問',
                'body' => 'テスト用の質問本文です。',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('qa_threads', [
            'user_id' => $coach->id,
            'title' => 'Laravelの質問',
        ]);
    }

    public function test_admin_cannot_create_qa_thread(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $response = $this->actingAs($admin)
            ->post(route('qa-board.store'), [
                'certification_id' => $certification->id,
                'title' => 'Laravelの質問',
                'body' => 'テスト用の質問本文です。',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('qa_threads', [
            'user_id' => $admin->id,
            'title' => 'Laravelの質問',
        ]);
    }
}

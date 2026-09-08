<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_replies_are_ordered_by_oldest_first(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'certification_id' => $certification->id,
        ]);

        QaReply::factory()->create([
            'qa_thread_id' => $thread->id,
            'body' => '古い回答',
            'created_at' => now()->subHour(),
        ]);

        QaReply::factory()->create([
            'qa_thread_id' => $thread->id,
            'body' => '新しい回答',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($student)
            ->get(route('qa-board.show', $thread));

        $response->assertOk();

        $response->assertSeeInOrder([
            '古い回答',
            '新しい回答',
        ]);
    }

    public function test_show_displays_all_replies(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'certification_id' => $certification->id,
        ]);

        QaReply::factory()->count(21)->create([
            'qa_thread_id' => $thread->id,
        ]);

        $lastReply = QaReply::query()
            ->where('qa_thread_id', $thread->id)
            ->latest()
            ->first();

        $response = $this->actingAs($student)
            ->get(route('qa-board.show', $thread));

        $response->assertOk();
        $response->assertSee($lastReply->body);
    }

    public function test_coach_can_view_thread(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'certification_id' => $certification->id,
            'title' => 'コーチ閲覧用の質問',
        ]);

        $response = $this->actingAs($coach)
            ->get(route('qa-board.show', $thread));

        $response->assertOk();
        $response->assertSee('コーチ閲覧用の質問');
    }

    public function test_admin_can_view_thread(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'certification_id' => $certification->id,
            'title' => '管理者閲覧用の質問',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.qa-board.show', $thread));

        $response->assertOk();
        $response->assertSee('管理者閲覧用の質問');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_sees_only_published_certifications_in_filter(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $published = Certification::factory()->published()->create([
            'name' => '公開資格',
        ]);

        $draft = Certification::factory()->draft()->create([
            'name' => '下書き資格',
        ]);

        $archived = Certification::factory()->archived()->create([
            'name' => 'アーカイブ資格',
        ]);

        $response = $this->actingAs($student)
            ->get(route('qa-board.index'));

        $response->assertOk();

        $response->assertSee('公開資格');
        $response->assertDontSee('下書き資格');
        $response->assertDontSee('アーカイブ資格');
    }

    public function test_coach_sees_only_assigned_published_certifications_in_filter(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        $admin = User::factory()->admin()->create();

        $assignedPublished = Certification::factory()->published()->create([
            'name' => '担当中の公開資格',
        ]);

        $unassignedPublished = Certification::factory()->published()->create([
            'name' => '未担当の公開資格',
        ]);

        $assignedDraft = Certification::factory()->draft()->create([
            'name' => '担当中の下書き資格',
        ]);

        $assignedPublished->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $assignedDraft->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($coach)
            ->get(route('qa-board.index'));

        $response->assertOk();

        $response->assertSee('担当中の公開資格');
        $response->assertDontSee('未担当の公開資格');
        $response->assertDontSee('担当中の下書き資格');
    }

    public function test_admin_sees_all_certifications_in_filter(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        Certification::factory()->published()->create([
            'name' => '公開資格',
        ]);

        Certification::factory()->draft()->create([
            'name' => '下書き資格',
        ]);

        Certification::factory()->archived()->create([
            'name' => 'アーカイブ資格',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.qa-board.index'));

        $response->assertOk();

        $response->assertSee('公開資格');
        $response->assertSee('下書き資格');
        $response->assertSee('アーカイブ資格');
    }

    public function test_keyword_searches_body_only(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        QaThread::factory()->create([
            'certification_id' => $certification->id,
            'title' => 'Laravelという文字はタイトルだけ',
            'body' => '本文には検索語がありません。',
        ]);

        QaThread::factory()->create([
            'certification_id' => $certification->id,
            'title' => '別のタイトル',
            'body' => 'Laravelの検索対象になる本文です。',
        ]);

        $response = $this->actingAs($student)
            ->get(route('qa-board.index', [
                'keyword' => 'Laravel',
            ]));

        $response->assertOk();

        $response->assertSee('Laravelの検索対象になる本文です。');
        $response->assertDontSee('Laravelという文字はタイトルだけ');
    }

    public function test_status_filter_returns_only_matching_threads(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        QaThread::factory()->create([
            'certification_id' => $certification->id,
            'title' => '未解決の質問',
            'status' => 'unresolved',
        ]);

        QaThread::factory()->create([
            'certification_id' => $certification->id,
            'title' => '解決済みの質問',
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        $response = $this->actingAs($student)
            ->get(route('qa-board.index', [
                'status' => 'unresolved',
            ]));

        $response->assertOk();

        $response->assertSee('未解決の質問');
        $response->assertDontSee('解決済みの質問');
    }

    public function test_certification_filter_returns_only_matching_threads(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $certificationA = Certification::factory()->published()->create([
            'name' => '資格A',
        ]);

        $certificationB = Certification::factory()->published()->create([
            'name' => '資格B',
        ]);

        QaThread::factory()->create([
            'certification_id' => $certificationA->id,
            'title' => '資格Aの質問',
        ]);

        QaThread::factory()->create([
            'certification_id' => $certificationB->id,
            'title' => '資格Bの質問',
        ]);

        $response = $this->actingAs($student)
            ->get(route('qa-board.index', [
                'certification_id' => $certificationA->id,
            ]));

        $response->assertOk();

        $response->assertSee('資格Aの質問');
        $response->assertDontSee('資格Bの質問');
    }

    public function test_threads_are_ordered_by_latest_first(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        QaThread::factory()->create([
            'certification_id' => $certification->id,
            'title' => '古い質問',
            'created_at' => now()->subDay(),
        ]);

        QaThread::factory()->create([
            'certification_id' => $certification->id,
            'title' => '新しい質問',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($student)
            ->get(route('qa-board.index'));

        $response->assertOk();

        $response->assertSeeInOrder([
            '新しい質問',
            '古い質問',
        ]);
    }

    public function test_threads_are_paginated_by_twenty(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        QaThread::factory()->count(21)->create([
            'certification_id' => $certification->id,
        ]);

        $response = $this->actingAs($student)
            ->get(route('qa-board.index'));

        $response->assertOk();

        $threads = $response->viewData('threads');

        $this->assertSame(20, $threads->perPage());
        $this->assertSame(21, $threads->total());
        $this->assertCount(20, $threads->items());
    }
}

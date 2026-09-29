<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MockExam;

use App\Models\Certification;
use App\Models\MockExam;
use App\Models\MockExamQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_displays_related_information_without_changing_content(): void
    {
        $admin = User::factory()->admin()->create();
        $updater = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create([
            'name' => '表示確認資格',
        ]);

        $mockExam = MockExam::factory()
            ->forCertification($certification)
            ->create([
                'title' => '表示確認模試',
                'passing_score' => 70,
                'updated_by_user_id' => $updater->id,
            ]);

        MockExamQuestion::factory()
            ->count(3)
            ->forMockExam($mockExam)
            ->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.mock-exams.index'));

        $response->assertOk();
        $response->assertSeeText('表示確認模試');
        $response->assertSeeText('表示確認資格');
        $response->assertSeeText('70%');
        $response->assertSeeText($updater->name);
        $response->assertSeeText('3');
    }

    public function test_index_keeps_existing_sort_order(): void
    {
        $admin = User::factory()->admin()->create();

        $certificationA = Certification::factory()->published()->create();
        $certificationB = Certification::factory()->published()->create();

        $first = MockExam::factory()
            ->forCertification($certificationA)
            ->create([
                'title' => '並び1',
                'order' => 1,
                'updated_at' => now()->subDay(),
            ]);

        $second = MockExam::factory()
            ->forCertification($certificationA)
            ->create([
                'title' => '並び2',
                'order' => 2,
                'updated_at' => now(),
            ]);

        $third = MockExam::factory()
            ->forCertification($certificationB)
            ->create([
                'title' => '並び3',
                'order' => 0,
                'updated_at' => now(),
            ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.mock-exams.index'));

        $response->assertOk();

        $titles = $response->viewData('mockExams')
            ->pluck('title')
            ->all();

        $this->assertSame([
            $first->title,
            $second->title,
            $third->title,
        ], $titles);
    }

    public function test_index_keeps_twenty_items_per_page(): void
    {
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();

        MockExam::factory()
            ->count(21)
            ->forCertification($certification)
            ->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.mock-exams.index'));

        $response->assertOk();

        $mockExams = $response->viewData('mockExams');

        $this->assertSame(20, $mockExams->perPage());
        $this->assertSame(21, $mockExams->total());
        $this->assertCount(20, $mockExams->items());
    }
}

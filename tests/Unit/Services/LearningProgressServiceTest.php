<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\ContentStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\SectionProgress;
use App\Models\User;
use App\Services\Learning\LearningProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningProgressServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summarize_calculates_section_chapter_and_part_progress(): void
    {
        $student = User::factory()->student()->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->state(['status' => EnrollmentStatus::Learning->value])
            ->create();

        $partA = Part::factory()
            ->for($certification)
            ->create(['status' => ContentStatus::Published->value]);

        $partB = Part::factory()
            ->for($certification)
            ->create(['status' => ContentStatus::Published->value]);

        $chapterA = Chapter::factory()
            ->for($partA)
            ->create(['status' => ContentStatus::Published->value]);

        $chapterB = Chapter::factory()
            ->for($partB)
            ->create(['status' => ContentStatus::Published->value]);

        $sectionA1 = Section::factory()
            ->for($chapterA)
            ->create(['status' => ContentStatus::Published->value]);

        $sectionA2 = Section::factory()
            ->for($chapterA)
            ->create(['status' => ContentStatus::Published->value]);

        $sectionB1 = Section::factory()
            ->for($chapterB)
            ->create(['status' => ContentStatus::Published->value]);

        SectionProgress::factory()
            ->forEnrollment($enrollment)
            ->forSection($sectionA1)
            ->create();

        SectionProgress::factory()
            ->forEnrollment($enrollment)
            ->forSection($sectionA2)
            ->create();

        $summary = app(LearningProgressService::class)->summarize($enrollment);

        $this->assertSame(3, $summary->sectionsTotal);
        $this->assertSame(2, $summary->sectionsCompleted);
        $this->assertSame(0.6667, $summary->sectionCompletionRatio);

        $this->assertSame(2, $summary->chaptersTotal);
        $this->assertSame(1, $summary->chaptersCompleted);
        $this->assertSame(0.5, $summary->chapterCompletionRatio);

        $this->assertSame(2, $summary->partsTotal);
        $this->assertSame(1, $summary->partsCompleted);
        $this->assertSame(0.5, $summary->partCompletionRatio);

        $this->assertSame(0.6667, $summary->overallCompletionRatio);
    }

    public function test_summarize_ignores_draft_content(): void
    {
        $student = User::factory()->student()->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->state(['status' => EnrollmentStatus::Learning->value])
            ->create();

        $publishedPart = Part::factory()
            ->for($certification)
            ->create(['status' => ContentStatus::Published->value]);

        $publishedChapter = Chapter::factory()
            ->for($publishedPart)
            ->create(['status' => ContentStatus::Published->value]);

        $publishedSection = Section::factory()
            ->for($publishedChapter)
            ->create(['status' => ContentStatus::Published->value]);

        $draftPart = Part::factory()
            ->for($certification)
            ->create(['status' => ContentStatus::Draft->value]);

        $draftChapter = Chapter::factory()
            ->for($draftPart)
            ->create(['status' => ContentStatus::Draft->value]);

        $draftSection = Section::factory()
            ->for($draftChapter)
            ->create(['status' => ContentStatus::Draft->value]);

        SectionProgress::factory()
            ->forEnrollment($enrollment)
            ->forSection($publishedSection)
            ->create();

        SectionProgress::factory()
            ->forEnrollment($enrollment)
            ->forSection($draftSection)
            ->create();

        $summary = app(LearningProgressService::class)->summarize($enrollment);

        $this->assertSame(1, $summary->sectionsTotal);
        $this->assertSame(1, $summary->sectionsCompleted);
        $this->assertSame(1.0, $summary->sectionCompletionRatio);

        $this->assertSame(1, $summary->chaptersTotal);
        $this->assertSame(1, $summary->chaptersCompleted);

        $this->assertSame(1, $summary->partsTotal);
        $this->assertSame(1, $summary->partsCompleted);

        $this->assertSame(1.0, $summary->overallCompletionRatio);
    }

    public function test_summarize_returns_zero_when_no_published_content_exists(): void
    {
        $student = User::factory()->student()->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->state(['status' => EnrollmentStatus::Learning->value])
            ->create();

        $summary = app(LearningProgressService::class)->summarize($enrollment);

        $this->assertSame(0, $summary->sectionsTotal);
        $this->assertSame(0, $summary->sectionsCompleted);
        $this->assertSame(0.0, $summary->sectionCompletionRatio);

        $this->assertSame(0, $summary->chaptersTotal);
        $this->assertSame(0, $summary->chaptersCompleted);
        $this->assertSame(0.0, $summary->chapterCompletionRatio);

        $this->assertSame(0, $summary->partsTotal);
        $this->assertSame(0, $summary->partsCompleted);
        $this->assertSame(0.0, $summary->partCompletionRatio);

        $this->assertSame(0.0, $summary->overallCompletionRatio);
    }

    public function test_batch_calculate_overall_ratios_returns_progress_for_each_enrollment(): void
    {
        $student = User::factory()->student()->create();

        $certificationA = Certification::factory()->published()->create();
        $certificationB = Certification::factory()->published()->create();

        $enrollmentA = Enrollment::factory()
            ->for($student)
            ->for($certificationA)
            ->state(['status' => EnrollmentStatus::Learning->value])
            ->create();

        $enrollmentB = Enrollment::factory()
            ->for($student)
            ->for($certificationB)
            ->state(['status' => EnrollmentStatus::Learning->value])
            ->create();

        $partA = Part::factory()
            ->for($certificationA)
            ->create(['status' => ContentStatus::Published->value]);

        $chapterA = Chapter::factory()
            ->for($partA)
            ->create(['status' => ContentStatus::Published->value]);

        $sectionA1 = Section::factory()
            ->for($chapterA)
            ->create(['status' => ContentStatus::Published->value]);

        $sectionA2 = Section::factory()
            ->for($chapterA)
            ->create(['status' => ContentStatus::Published->value]);

        $sectionA3 = Section::factory()
            ->for($chapterA)
            ->create(['status' => ContentStatus::Published->value]);

        $partB = Part::factory()
            ->for($certificationB)
            ->create(['status' => ContentStatus::Published->value]);

        $chapterB = Chapter::factory()
            ->for($partB)
            ->create(['status' => ContentStatus::Published->value]);

        Section::factory()
            ->for($chapterB)
            ->create(['status' => ContentStatus::Published->value]);

        SectionProgress::factory()
            ->forEnrollment($enrollmentA)
            ->forSection($sectionA1)
            ->create();

        SectionProgress::factory()
            ->forEnrollment($enrollmentA)
            ->forSection($sectionA2)
            ->create();

        $result = app(LearningProgressService::class)
            ->batchCalculateOverallRatios(
                Enrollment::query()
                    ->whereIn('id', [$enrollmentA->id, $enrollmentB->id])
                    ->get()
            );

        $this->assertSame(0.6667, $result[$enrollmentA->id]);
        $this->assertSame(0.0, $result[$enrollmentB->id]);
    }

    public function test_batch_calculate_overall_ratios_returns_empty_array_for_empty_collection(): void
    {
        $enrollments = Enrollment::query()
            ->whereRaw('1 = 0')
            ->get();

        $result = app(LearningProgressService::class)
            ->batchCalculateOverallRatios($enrollments);

        $this->assertSame([], $result);
    }
}

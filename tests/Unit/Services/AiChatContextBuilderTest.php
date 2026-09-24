<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\AiChatConversation;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\User;
use App\Services\AiChat\AiChatContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiChatContextBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_section_context_contains_only_hierarchy_metadata_and_not_section_body(): void
    {
        $student = User::factory()->create();

        $certification = Certification::factory()->create([
            'name' => '基本情報技術者試験',
        ]);

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->learning()
            ->create();

        $part = Part::factory()
            ->forCertification($certification)
            ->create([
                'order' => 1,
                'title' => '基礎理論',
            ]);

        $chapter = Chapter::factory()
            ->forPart($part)
            ->create([
                'order' => 2,
                'title' => 'アルゴリズム',
            ]);

        $section = Section::factory()
            ->forChapter($chapter)
            ->create([
                'order' => 3,
                'title' => '二分探索木',
                'body' => 'この本文はAIコンテキストに含まれてはいけません。',
            ]);

        $conversation = AiChatConversation::query()->create([
            'user_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'section_id' => $section->id,
            'title' => 'コンテキスト確認',
            'last_message_at' => now(),
        ]);

        $context = app(AiChatContextBuilder::class)
            ->build($conversation);

        $this->assertStringContainsString(
            '資格: 基本情報技術者試験',
            $context
        );

        $this->assertStringContainsString(
            'Part 1: 基礎理論',
            $context
        );

        $this->assertStringContainsString(
            'Chapter 2: アルゴリズム',
            $context
        );

        $this->assertStringContainsString(
            'Section 3: 二分探索木',
            $context
        );

        $this->assertStringNotContainsString(
            'この本文はAIコンテキストに含まれてはいけません。',
            $context
        );
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AiChatConversation;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_section_reuses_existing_conversation(): void
    {
        config()->set('ai-chat.enabled', true);

        $student = User::factory()->create([
            'role' => UserRole::Student->value,
            'status' => UserStatus::InProgress->value,
        ]);

        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();

        $student->update([
            'default_enrollment_id' => $enrollment->id,
        ]);

        $part = Part::factory()
            ->forCertification($enrollment->certification)
            ->create();

        $chapter = Chapter::factory()
            ->forPart($part)
            ->create();

        $section = Section::factory()
            ->forChapter($chapter)
            ->create();

        $firstResponse = $this
            ->actingAs($student)
            ->postJson(route('ai-chat.conversations.store'), [
                'source' => 'widget',
                'section_id' => $section->id,
            ]);

        $firstResponse->assertCreated();

        $secondResponse = $this
            ->actingAs($student)
            ->postJson(route('ai-chat.conversations.store'), [
                'source' => 'widget',
                'section_id' => $section->id,
            ]);

        $secondResponse->assertOk();

        $this->assertSame(
            $firstResponse->json('conversation.id'),
            $secondResponse->json('conversation.id')
        );

        $this->assertSame(
            1,
            AiChatConversation::query()
                ->where('user_id', $student->id)
                ->where('section_id', $section->id)
                ->count()
        );
    }
}

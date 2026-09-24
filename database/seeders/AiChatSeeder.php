<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Enums\EnrollmentStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Seeder;

class AiChatSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::query()
            ->where('email', 'student@certify-lms.test')
            ->first();

        if ($student === null) {
            $this->command?->warn(
                'AiChatSeeder: 固定受講生が存在しないためスキップします。'
            );

            return;
        }

        $enrollment = Enrollment::query()
            ->where('user_id', $student->id)
            ->where('status', EnrollmentStatus::Learning->value)
            ->with('certification')
            ->first();

        if ($enrollment === null) {
            $this->command?->warn(
                'AiChatSeeder: learning の受講登録が存在しないためスキップします。'
            );

            return;
        }

        $sections = Section::query()
            ->whereHas(
                'chapter.part',
                fn ($query) => $query->where(
                    'certification_id',
                    $enrollment->certification_id
                )
            )
            ->take(2)
            ->get();

        if ($sections->count() < 2) {
            $this->command?->warn(
                'AiChatSeeder: 対象Sectionが2件未満のためスキップします。'
            );

            return;
        }

        $normalSection = $sections->get(0);
        $errorSection = $sections->get(1);

        $sectionConversation = AiChatConversation::query()->firstOrCreate(
            [
                'user_id' => $student->id,
                'section_id' => $normalSection->id,
                'title' => '二分探索木の基本',
            ],
            [
                'enrollment_id' => $enrollment->id,
                'last_message_at' => now()->subMinutes(30),
            ]
        );

        AiChatMessage::query()->firstOrCreate(
            [
                'ai_chat_conversation_id' => $sectionConversation->id,
                'role' => AiChatMessageRole::User->value,
                'content' => '二分探索木の特徴を教えてください。',
            ],
            [
                'status' => AiChatMessageStatus::Completed->value,
                'created_at' => now()->subMinutes(31),
                'updated_at' => now()->subMinutes(31),
            ]
        );

        AiChatMessage::query()->firstOrCreate(
            [
                'ai_chat_conversation_id' => $sectionConversation->id,
                'role' => AiChatMessageRole::Assistant->value,
                'content' => '二分探索木は各ノードの左側に小さい値、右側に大きい値を配置する木構造です。',
            ],
            [
                'status' => AiChatMessageStatus::Completed->value,
                'model' => 'gemini-3.5-flash',
                'input_tokens' => 24,
                'output_tokens' => 36,
                'response_time_ms' => 820,
                'created_at' => now()->subMinutes(30),
                'updated_at' => now()->subMinutes(30),
            ]
        );

        $errorConversation = AiChatConversation::query()->firstOrCreate(
            [
                'user_id' => $student->id,
                'section_id' => $errorSection->id,
                'title' => 'Sectionの重要ポイント',
            ],
            [
                'enrollment_id' => $enrollment->id,
                'last_message_at' => now()->subMinutes(20),
            ]
        );

        AiChatMessage::query()->firstOrCreate(
            [
                'ai_chat_conversation_id' => $errorConversation->id,
                'role' => AiChatMessageRole::User->value,
                'content' => 'このSectionの重要ポイントを教えてください。',
            ],
            [
                'status' => AiChatMessageStatus::Completed->value,
                'created_at' => now()->subMinutes(21),
                'updated_at' => now()->subMinutes(21),
            ]
        );

        AiChatMessage::query()->firstOrCreate(
            [
                'ai_chat_conversation_id' => $errorConversation->id,
                'role' => AiChatMessageRole::Assistant->value,
                'content' => '',
            ],
            [
                'status' => AiChatMessageStatus::Error->value,
                'error_detail' => 'Gemini API request failed',
                'model' => 'gemini-3.5-flash',
                'created_at' => now()->subMinutes(20),
                'updated_at' => now()->subMinutes(20),
            ]
        );

        $generalConversation = AiChatConversation::query()->firstOrCreate(
            [
                'user_id' => $student->id,
                'section_id' => null,
                'title' => '試験勉強の進め方',
            ],
            [
                'enrollment_id' => $enrollment->id,
                'last_message_at' => now()->subMinutes(10),
            ]
        );

        AiChatMessage::query()->firstOrCreate(
            [
                'ai_chat_conversation_id' => $generalConversation->id,
                'role' => AiChatMessageRole::User->value,
                'content' => '試験勉強の進め方を教えてください。',
            ],
            [
                'status' => AiChatMessageStatus::Completed->value,
                'created_at' => now()->subMinutes(11),
                'updated_at' => now()->subMinutes(11),
            ]
        );

        AiChatMessage::query()->firstOrCreate(
            [
                'ai_chat_conversation_id' => $generalConversation->id,
                'role' => AiChatMessageRole::Assistant->value,
                'content' => '学習範囲を小さく分けて、復習と問題演習を繰り返すのがおすすめです。',
            ],
            [
                'status' => AiChatMessageStatus::Completed->value,
                'model' => 'gemini-3.5-flash',
                'input_tokens' => 18,
                'output_tokens' => 28,
                'created_at' => now()->subMinutes(10),
                'updated_at' => now()->subMinutes(10),
            ]
        );
    }
}

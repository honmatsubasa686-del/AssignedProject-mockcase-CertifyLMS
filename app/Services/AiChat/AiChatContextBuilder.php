<?php

declare(strict_types=1);

namespace App\Services\AiChat;

use App\Models\AiChatConversation;

class AiChatContextBuilder
{
    public function build(AiChatConversation $conversation): string
    {
        $conversation->loadMissing([
            'enrollment.certification',
            'section.chapter.part.certification',
        ]);

        $section = $conversation->section;

        if ($section !== null) {
            $chapter = $section->chapter;
            $part = $chapter?->part;
            $certification = $part?->certification;

            return implode("\n", array_filter([
                $certification?->name !== null
                ? '資格: '.$certification->name
                : null,

                $part !== null
                ? 'Part '.$part->order.': '.$part->title
                : null,

                $chapter !== null
                ? 'Chapter '.$chapter->order.': '.$chapter->title
                : null,

                'Section '.$section->order.': '.$section->title,
            ]));
        }

        $certification = $conversation->enrollment?->certification;

        if ($certification !== null) {
            return '資格: '.$certification->name;
        }

        return '全般相談';
    }
}

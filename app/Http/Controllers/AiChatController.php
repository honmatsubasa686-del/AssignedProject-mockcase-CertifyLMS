<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EnrollmentStatus;
use App\Exceptions\AiChatDailyLimitException;
use App\Exceptions\GeminiApiException;
use App\Http\Requests\AiChat\StoreConversationRequest;
use App\Http\Requests\AiChat\StoreMessageRequest;
use App\Http\Requests\AiChat\UpdateConversationRequest;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Enrollment;
use App\Models\Section;
use App\Services\AiChat\AiChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiChatController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $this->authorize('viewAny', AiChatConversation::class);

        $conversation = $request->user()
            ->aiChatConversations()
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->first();

        if ($conversation === null) {
            return view('ai-chat.empty-state');
        }

        return redirect()->route(
            'ai-chat.conversations.show',
            $conversation
        );
    }

    private function resolveEnrollment(
        Request $request,
        ?Section $section
    ): ?Enrollment {
        $user = $request->user();

        if ($section !== null) {
            $section->loadMissing('chapter.part');

            $certificationId = $section->chapter?->part?->certification_id;

            if ($certificationId === null) {
                abort(403);
            }

            return $user->enrollments()
                ->where('certification_id', $certificationId)
                ->whereIn('status', [
                    EnrollmentStatus::Learning->value,
                    EnrollmentStatus::Passed->value,
                ])
                ->firstOrFail();
        }

        $defaultEnrollment = $user->defaultEnrollment;

        if (
            $defaultEnrollment !== null
            && $defaultEnrollment->user_id === $user->id
            && in_array(
                $defaultEnrollment->status,
                [
                    EnrollmentStatus::Learning,
                    EnrollmentStatus::Passed,
                ],
                true
            )
        ) {
            return $defaultEnrollment;
        }

        return null;
    }

    public function store(
        StoreConversationRequest $request,
        AiChatService $aiChatService
    ): JsonResponse|RedirectResponse {

        $data = $request->validated();

        $section = isset($data['section_id'])
            ? Section::findOrFail($data['section_id'])
            : null;

        $enrollment = $this->resolveEnrollment($request, $section);

        if ($section !== null) {
            $existingConversation = $request->user()
                ->aiChatConversations()
                ->where('section_id', $section->id)
                ->first();

            if ($existingConversation !== null) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'conversation' => $existingConversation,
                    ]);
                }

                return redirect()->route(
                    'ai-chat.conversations.show',
                    $existingConversation
                );
            }
        }

        $conversation = AiChatConversation::create([
            'user_id' => $request->user()->id,
            'enrollment_id' => $enrollment?->id,
            'section_id' => $section?->id,
            'title' => '新しい相談',
            'last_message_at' => now(),
        ]);

        $message = trim((string) ($data['message'] ?? ''));

        if ($message !== '') {
            try {
                $aiChatService->send($conversation, $message);
            } catch (AiChatDailyLimitException $e) {
                return redirect()
                    ->route('ai-chat.conversations.show', $conversation)
                    ->with('error', '本日のAI相談の送信上限に達しました。');
            } catch (GeminiApiException $e) {
                return redirect()
                    ->route('ai-chat.conversations.show', $conversation)
                    ->with('error', 'AIから回答を取得できませんでした。質問は保存されています。');
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'conversation' => $conversation,
            ], 201);
        }

        return redirect()->route(
            'ai-chat.conversations.show',
            $conversation
        );
    }

    public function show(
        Request $request,
        AiChatConversation $conversation
    ): View|JsonResponse {
        $this->authorize('view', $conversation);

        if ($request->expectsJson()) {
            $messages = $conversation->messages()
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->map(fn (AiChatMessage $message) => [
                    'role' => $message->role->value,
                    'content' => $message->content,
                    'status' => $message->status->value,
                ])
                ->values();

            return response()->json([
                'messages' => $messages,
            ]);
        }

        $conversation->load([
            'messages',
            'enrollment.certification',
            'section.chapter.part.certification',
        ]);

        return view('ai-chat.show', [
            'conversation' => $conversation,
        ]);
    }

    public function update(
        AiChatConversation $conversation,
        UpdateConversationRequest $request
    ): RedirectResponse {
        $conversation->update([
            'title' => $request->validated()['title'],
        ]);

        return redirect()
            ->route('ai-chat.conversations.show', $conversation)
            ->with('success', 'タイトルを更新しました。');
    }

    public function destroy(AiChatConversation $conversation): RedirectResponse
    {
        $this->authorize('delete', $conversation);

        $conversation->delete();

        return redirect()
            ->route('ai-chat.index')
            ->with('success', '会話を削除しました。');
    }

    public function storeMessage(
        AiChatConversation $conversation,
        StoreMessageRequest $request,
        AiChatService $aiChatService
    ): JsonResponse {
        try {
            $result = $aiChatService->send(
                $conversation,
                $request->validated()['content']
            );
        } catch (AiChatDailyLimitException $e) {
            return response()->json([
                'message' => '本日のAI相談の送信上限に達しました。',
            ], 429);
        } catch (GeminiApiException $e) {
            return response()->json([
                'message' => 'AIから回答を取得できませんでした。',
            ], 502);
        }

        $conversation->refresh();

        return response()->json([
            'user_message' => [
                'id' => $result['user_message']->id,
                'role' => $result['user_message']->id,
                'content' => $result['user_message']->content,
                'status' => $result['user_message']->status->value,
                'created_at' => $result['user_message']->created_at,
            ],
            'assistant_message' => [
                'id' => $result['assistant_message']->id,
                'role' => $result['assistant_message']->role->value,
                'content' => $result['assistant_message']->content,
                'status' => $result['assistant_message']->status->value,
                'created_at' => $result['assistant_message']->created_at,
            ],
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
            ],
        ]);
    }
}

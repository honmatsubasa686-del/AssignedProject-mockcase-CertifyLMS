<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\QaReply\StoreQaReplyRequest;
use App\Http\Requests\QaReply\UpdateQaReplyRequest;
use App\Models\QaReply;
use App\Models\QaThread;
use App\UseCases\QaReply\DestroyAction;
use App\UseCases\QaReply\StoreAction;
use App\UseCases\QaReply\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class QaReplyController extends Controller
{
    public function store(
        QaThread $thread,
        StoreQaReplyRequest $request,
        StoreAction $action,
    ): RedirectResponse {
        $reply = $action(
            $thread,
            $request->user(),
            $request->validated()
        );

        return redirect()
            ->route('qa-board.show', $thread)
            ->withFragment('reply-'.$reply->id)
            ->with('success', '回答を投稿しました。');
    }

    public function edit(
        QaThread $thread,
        QaReply $reply
    ): View {
        abort_unless($reply->qa_thread_id === $thread->id, 404);

        $this->authorize('update', $reply);

        return view('qa-thread.reply-edit', [
            'thread' => $thread,
            'reply' => $reply,
        ]);
    }

    public function update(
        QaThread $thread,
        QaReply $reply,
        UpdateQaReplyRequest $request,
        UpdateAction $action
    ): RedirectResponse {
        abort_unless($reply->qa_thread_id === $thread->id, 404);

        $reply = $action(
            $reply,
            $request->validated()
        );

        return redirect()
            ->route('qa-board.show', $thread)
            ->withFragment('reply-'.$reply->id)
            ->with('success', '回答を更新しました。');
    }

    public function destroy(
        QaThread $thread,
        QaReply $reply,
        DestroyAction $action
    ): RedirectResponse {
        abort_unless($reply->qa_thread_id === $thread->id, 404);

        $this->authorize('delete', $reply);

        $action($reply);

        $route = request()->routeIs('admin.*')
            ? 'admin.qa-board.show'
            : 'qa-board.show';

        return redirect()
            ->route($route, $thread)
            ->with('success', '回答を削除しました。');
    }
}

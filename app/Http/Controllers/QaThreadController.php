<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CertificationStatus;
use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use App\Http\Requests\QaThread\IndexQaThreadRequest;
use App\Http\Requests\QaThread\StoreQaThreadRequest;
use App\Http\Requests\QaThread\UpdateQaThreadRequest;
use App\Models\Certification;
use App\Models\QaThread;
use App\UseCases\QaThread\DestroyAction;
use App\UseCases\QaThread\ResolveAction;
use App\UseCases\QaThread\StoreAction;
use App\UseCases\QaThread\UnresolveAction;
use App\UseCases\QaThread\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class QaThreadController extends Controller
{
    public function index(IndexQaThreadRequest $request): View
    {
        $this->authorize('viewAny', QaThread::class);

        $query = QaThread::query()
            ->with(['user', 'certification'])
            ->withCount('replies');

        if ($status = $request->string('status')->toString()) {
            $query->where('status', QaThreadStatus::from($status)->value);
        }

        if ($certificationId = $request->string('certification_id')->toString()) {
            $query->where('certification_id', $certificationId);
        }

        if ($keyword = trim($request->string('keyword')->toString())) {
            $query->where('body', 'like', '%'.$keyword.'%');
        }

        $threads = $query
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $certificationQuery = Certification::query();

        if ($request->user()->role !== UserRole::Admin) {
            $certificationQuery->where(
                'status',
                CertificationStatus::Published->value
            );
        }

        if ($request->user()->role === UserRole::Coach) {
            $certificationQuery->assignedTo($request->user());
        }

        $certifications = $certificationQuery
            ->orderBy('name')
            ->get();

        return view('qa-thread.index', [
            'threads' => $threads,
            'certifications' => $certifications,
            'filters' => $request->only(['status', 'certification_id', 'keyword']),
            'publishedStatus' => CertificationStatus::Published,
        ]);
    }

    public function show(QaThread $thread): View
    {
        $this->authorize('view', $thread);

        $thread->load([
            'user',
            'certification',
            'replies.user',
        ]);

        $thread->loadCount('replies');

        return view('qa-thread.show', [
            'thread' => $thread,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', QaThread::class);

        $certifications = Certification::query()
            ->where('status', CertificationStatus::Published->value)
            ->orderBy('name')
            ->get();

        return view('qa-thread.create', [
            'certifications' => $certifications,
        ]);
    }

    public function store(
        StoreQaThreadRequest $request,
        StoreAction $action
    ): RedirectResponse {
        $thread = $action(
            $request->user(),
            $request->validated()
        );

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を投稿しました。');
    }

    public function edit(QaThread $thread): View
    {
        $this->authorize('update', $thread);

        return view('qa-thread.edit', [
            'thread' => $thread,
        ]);
    }

    public function update(
        QaThread $thread,
        UpdateQaThreadRequest $request,
        UpdateAction $action
    ): RedirectResponse {
        $thread = $action(
            $thread,
            $request->validated()
        );

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を更新しました。');
    }

    public function destroy(
        QaThread $thread,
        DestroyAction $action
    ): RedirectResponse {
        $this->authorize('delete', $thread);

        $action($thread);

        $route = request()->routeIs('admin.*')
            ? 'admin.qa-board.index'
            : 'qa-board.index';

        return redirect()
            ->route($route)
            ->with('success', '質問を削除しました。');
    }

    public function resolve(
        QaThread $thread,
        ResolveAction $action
    ): RedirectResponse {
        $this->authorize('resolve', $thread);

        $thread = $action($thread);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を解決済みにしました。');
    }

    public function unresolve(
        QaThread $thread,
        UnresolveAction $action
    ): RedirectResponse {
        $this->authorize('unresolve', $thread);

        $thread = $action($thread);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を未解決に戻しました。');
    }
}

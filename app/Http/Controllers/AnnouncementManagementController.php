<?php

namespace App\Http\Controllers;

use App\Http\Requests\Announcement\StoreRequest;
use App\UseCases\Announcement\StoreAction;
use App\Models\Certification;
use App\Models\User;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Announcement;

class AnnouncementManagementController extends Controller
{
    public function index()
    {
        $announcements = Announcement::query()
            ->with([
                'targetCertification',
                'targetUser',
                'createdBy',
            ])
            ->latest()
            ->paginate(20);

        return view('announcement.management.index', [
            'announcements' => $announcements,
        ]);
    }

    public function create()
    {
        $certifications = Certification::query()
            ->orderBy('name')
            ->get();

        $students = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->orderBy('name')
            ->get();

        return view('announcement.management.create', [
            'certifications' => $certifications,
            'students' => $students,
        ]);
    }

    public function store(
        StoreRequest $request,
        StoreAction $action,
    ) {
        $announcement = $action(
            $request->user(),
            $request->validated(),
        );

        return redirect()
            ->route('admin.announcements.show', $announcement);
    }

    public function show(Announcement $announcement)
    {
        $announcement->load([
            'targetCertification',
            'targetUser',
            'createdBy',
        ]);

        return view('announcement.management.show', [
            'announcement' => $announcement,
        ]);
    }
}

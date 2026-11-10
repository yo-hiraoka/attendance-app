<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\StampCorrectionRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CorrectionRequestListController extends Controller
{
    public function index(Request $request): View
    {
        abort_if($request->user()->isAdmin(), 403);

        $user = $request->user();

        $applications = StampCorrectionRequest::query()
            ->with('attendance')
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $formattedApplications = $applications
            ->map(function (StampCorrectionRequest $application): array {
                $workDate = CarbonImmutable::parse(
                    $application->attendance->getRawOriginal('work_date'),
                    'Asia/Tokyo'
                );

                $createdAt = CarbonImmutable::parse(
                    $application->getRawOriginal('created_at'),
                    'UTC'
                )->setTimezone('Asia/Tokyo');

                return [
                    'id' => $application->id,
                    'attendance_id' => $application->attendance_id,
                    'approval_status' => $application->status === 'approved'
                        ? '承認済み'
                        : '承認待ち',
                    'date' => $workDate->format('Y/m/d'),
                    'comment' => $application->reason,
                    'application_date' => $createdAt->format('Y/m/d H:i'),
                ];
            });

        return view('user.user-application-list', [
            'user' => $user,
            'formattedApplications' => $formattedApplications,
        ]);
    }
}

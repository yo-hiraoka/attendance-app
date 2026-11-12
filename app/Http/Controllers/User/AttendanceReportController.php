<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\AttendanceReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceReportController extends Controller
{
    public function index(
        Request $request,
        AttendanceReportService $reportService
    ): View {
        abort_if($request->user()->isAdmin(), 403);

        $report = $reportService->generate($request->user());

        return view('user.attendance-report', $report);
    }
}

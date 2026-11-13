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

        $summary = $report['summary'];

        $averageMinutes = $summary['completed_days'] > 0
            ? (int) round(
                $summary['work_seconds']
                / $summary['completed_days']
                / 60
            )
            : 0;

        $report['summary'] = array_merge($summary, [
            'total_work_minutes' => (int) round(
                $summary['work_seconds'] / 60
            ),
            'total_overtime_minutes' => (int) round(
                $summary['overtime_seconds'] / 60
            ),
            'avg_work_minutes' => $averageMinutes,
        ]);

        // 提供Blade用の月次推移。
        // 新しい月から古い月の順に並べる。
        $report['monthlyTrend'] = array_reverse(
            $report['monthly_trend']
        );

        // 提供Blade用の異常検知キーを追加する。
        $report['anomalies'] = array_merge(
            $report['anomalies'],
            [
                'late_count' => $report['anomalies']['late'],
                'early_leave_count' => $report['anomalies']['early'],
                'long_work_count' => $report['anomalies']['long_hours'],
            ]
        );

        return view('reports.index', $report);
    }
}

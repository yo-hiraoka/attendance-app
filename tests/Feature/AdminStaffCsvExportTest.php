<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStaffCsvExportTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $admin = User::factory()->create();

        $admin->forceFill(['role' => 'admin'])->save();

        return $admin;
    }

    private function exportUrl(User $staff, string $month = '2026-11'): string
    {
        return route('admin.staff.export', [
            'id' => $staff->id,
            'date' => $month,
        ]);
    }

    private function createAttendance(User $staff, string $date): Attendance
    {
        return Attendance::factory()->create([
            'user_id' => $staff->id,
            'work_date' => $date,
            'clock_in_at' => $date.' 00:00:00',
            'clock_out_at' => $date.' 09:00:00',
        ]);
    }

    public function test_admin_can_download_csv(): void
    {
        $admin = $this->createAdmin();
        $staff = User::factory()->create();

        $this->createAttendance($staff, '2026-11-09');

        $this->actingAs($admin)
            ->get($this->exportUrl($staff))
            ->assertOk()
            ->assertDownload(
                "attendance_staff_{$staff->id}_2026-11.csv"
            );
    }

    public function test_csv_contains_correct_data_and_utf8_bom(): void
    {
        $admin = $this->createAdmin();
        $staff = User::factory()->create();

        $attendance = $this->createAttendance($staff, '2026-11-09');

        BreakTime::factory()->create([
            'attendance_id' => $attendance->id,
            'started_at' => '2026-11-09 03:00:00',
            'ended_at' => '2026-11-09 03:30:00',
        ]);

        BreakTime::factory()->create([
            'attendance_id' => $attendance->id,
            'started_at' => '2026-11-09 06:00:00',
            'ended_at' => '2026-11-09 06:15:00',
        ]);

        $response = $this->actingAs($admin)
            ->get($this->exportUrl($staff));

        $response->assertOk();

        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        $rows = array_map(
            'str_getcsv',
            explode("\n", trim(substr($content, 3)))
        );

        $this->assertSame(
            ['日付', '出勤', '退勤', '休憩', '合計'],
            $rows[0]
        );

        $this->assertSame(
            ['11/09(月)', '09:00', '18:00', '0:45', '8:15'],
            $rows[1]
        );

        $this->assertCount(2, $rows);
    }

    public function test_csv_contains_only_selected_staff_and_month(): void
    {
        $admin = $this->createAdmin();
        $staff = User::factory()->create();
        $otherStaff = User::factory()->create();

        $this->createAttendance($staff, '2026-11-09');
        $this->createAttendance($staff, '2026-10-10');
        $this->createAttendance($otherStaff, '2026-11-11');

        $response = $this->actingAs($admin)
            ->get($this->exportUrl($staff));

        $content = $response->streamedContent();

        $this->assertStringContainsString('11/09', $content);
        $this->assertStringNotContainsString('10/10', $content);
        $this->assertStringNotContainsString('11/11', $content);
    }

    public function test_empty_month_exports_header_only(): void
    {
        $admin = $this->createAdmin();
        $staff = User::factory()->create();

        $response = $this->actingAs($admin)
            ->get($this->exportUrl($staff));

        $response->assertOk();

        $content = $response->streamedContent();

        $this->assertSame(
            ['日付', '出勤', '退勤', '休憩', '合計'],
            str_getcsv(trim(substr($content, 3)))
        );
    }

    public function test_guest_cannot_export_csv(): void
    {
        $staff = User::factory()->create();

        $this->get($this->exportUrl($staff))
            ->assertRedirect(route('login'));
    }

    public function test_regular_user_cannot_export_csv(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($staff)
            ->get($this->exportUrl($staff))
            ->assertForbidden();
    }

    public function test_invalid_month_is_rejected(): void
    {
        $admin = $this->createAdmin();
        $staff = User::factory()->create();

        $this->actingAs($admin)
            ->get($this->exportUrl($staff, 'invalid'))
            ->assertSessionHasErrors('date');
    }

    public function test_missing_staff_returns_404(): void
    {
        $this->actingAs($this->createAdmin())
            ->get(route('admin.staff.export', [
                'id' => 999999,
                'date' => '2026-11',
            ]))
            ->assertNotFound();
    }

    public function test_admin_account_cannot_be_exported_as_staff(): void
    {
        $admin = $this->createAdmin();
        $anotherAdmin = $this->createAdmin();

        $this->actingAs($admin)
            ->get($this->exportUrl($anotherAdmin))
            ->assertNotFound();
    }

    public function test_staff_list_has_csv_export_link(): void
    {
        $admin = $this->createAdmin();
        $staff = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.staff.attendance', [
                'id' => $staff->id,
                'date' => '2026-11',
            ]))
            ->assertOk()
            ->assertSee('CSV出力')
            ->assertSee(
                $this->exportUrl($staff),
            );
    }
}

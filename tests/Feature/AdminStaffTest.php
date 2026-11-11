<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStaffTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $admin = User::factory()->create([
            'name' => '管理者テスト',
        ]);

        $admin->forceFill(['role' => 'admin'])->save();

        return $admin;
    }

    private function createAttendance(
        User $user,
        string $date = '2026-11-09'
    ): Attendance {
        return Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => $date,
            'clock_in_at' => $date.' 00:00:00',
            'clock_out_at' => $date.' 09:00:00',
        ]);
    }

    public function test_guest_cannot_view_staff_pages(): void
    {
        $this->get('/admin/staff/list')
            ->assertRedirect(route('login'));

        $this->get('/admin/attendance/staff/1')
            ->assertRedirect(route('login'));
    }

    public function test_regular_user_cannot_view_staff_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/staff/list')
            ->assertForbidden();

        $this->get('/admin/attendance/staff/'.$user->id)
            ->assertForbidden();
    }

    public function test_admin_can_view_regular_staff(): void
    {
        $admin = $this->createAdmin();

        $user = User::factory()->create([
            'name' => '一般スタッフA',
            'email' => 'staff-a@example.com',
        ]);

        $this->actingAs($admin)
            ->get('/admin/staff/list')
            ->assertOk()
            ->assertSee('一般スタッフA')
            ->assertSee('staff-a@example.com')
            ->assertSee(
                route('admin.staff.attendance', ['id' => $user->id])
            )
            ->assertDontSee('管理者テスト');
    }

    public function test_admin_can_view_selected_staff_attendance(): void
    {
        $admin = $this->createAdmin();

        $staffA = User::factory()->create([
            'name' => 'スタッフ甲',
        ]);

        $staffB = User::factory()->create([
            'name' => 'スタッフ乙',
        ]);

        $attendanceA = $this->createAttendance($staffA);
        $this->createAttendance($staffB);

        $this->actingAs($admin)
            ->get(
                route('admin.staff.attendance', ['id' => $staffA->id])
                .'?date=2026-11'
            )
            ->assertOk()
            ->assertSee('スタッフ甲さんの勤怠')
            ->assertSee('11/09')
            ->assertSee('09:00')
            ->assertSee('18:00')
            ->assertSee(
                route('admin.attendance.show', ['id' => $attendanceA->id])
            )
            ->assertDontSee('スタッフ乙');
    }

    public function test_admin_can_change_staff_attendance_month(): void
    {
        $admin = $this->createAdmin();
        $staff = User::factory()->create();

        $this->createAttendance($staff, '2026-10-09');
        $this->createAttendance($staff, '2026-11-09');

        $url = route('admin.staff.attendance', ['id' => $staff->id]);

        $this->actingAs($admin)
            ->get($url.'?date=2026-11')
            ->assertOk()
            ->assertSee('11/09')
            ->assertDontSee('10/09')
            ->assertSee('?date=2026-10')
            ->assertSee('?date=2026-12');

        $this->get($url.'?date=2026-10')
            ->assertOk()
            ->assertSee('10/09')
            ->assertDontSee('11/09');
    }

    public function test_invalid_month_is_rejected(): void
    {
        $admin = $this->createAdmin();
        $staff = User::factory()->create();

        $this->actingAs($admin)
            ->get(
                route('admin.staff.attendance', ['id' => $staff->id])
                .'?date=invalid'
            )
            ->assertSessionHasErrors('date');
    }

    public function test_missing_staff_returns_404(): void
    {
        $this->actingAs($this->createAdmin())
            ->get('/admin/attendance/staff/999999')
            ->assertNotFound();
    }

    public function test_admin_account_cannot_be_selected_as_staff(): void
    {
        $admin = $this->createAdmin();
        $anotherAdmin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(
                route('admin.staff.attendance', [
                    'id' => $anotherAdmin->id,
                ])
            )
            ->assertNotFound();
    }

    public function test_staff_monthly_list_calculates_multiple_breaks(): void
    {
        $admin = $this->createAdmin();
        $staff = User::factory()->create();

        $attendance = $this->createAttendance($staff);

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

        $this->actingAs($admin)
            ->get(
                route('admin.staff.attendance', ['id' => $staff->id])
                .'?date=2026-11'
            )
            ->assertOk()
            ->assertSee('0:45')
            ->assertSee('8:15');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\StampCorrectionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_compatible_attendance_detail(): void
    {
        $this->get('/attendance/detail/1')
            ->assertRedirect(route('login'));
    }

    public function test_user_can_view_own_compatible_attendance_detail(): void
    {
        $user = User::factory()->create();
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/attendance/detail/'.$attendance->id)
            ->assertOk()
            ->assertViewIs('user.user-detail');
    }

    public function test_user_cannot_view_other_users_compatible_detail(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $other->id,
        ]);

        $this->actingAs($user)
            ->get('/attendance/detail/'.$attendance->id)
            ->assertNotFound();
    }

    public function test_admin_cannot_view_user_compatible_detail(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $this->actingAs($admin)
            ->get('/attendance/detail/1')
            ->assertForbidden();
    }

    public function test_shared_request_list_selects_correct_views(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $this->actingAs($user)
            ->get('/stamp_correction_request/list')
            ->assertOk()
            ->assertViewIs('user.user-application-list');

        $this->actingAs($admin)
            ->get('/stamp_correction_request/list')
            ->assertOk()
            ->assertViewIs('admin.admin-application-list');
    }

    public function test_guest_cannot_access_compatible_approval_routes(): void
    {
        $this->get('/stamp_correction_request/approve/1')
            ->assertRedirect(route('login'));

        $this->post('/stamp_correction_request/approve/1')
            ->assertRedirect(route('login'));
    }

    public function test_regular_user_cannot_access_compatible_approval_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/stamp_correction_request/approve/1')
            ->assertForbidden();

        $this->post('/stamp_correction_request/approve/1')
            ->assertForbidden();
    }

    public function test_admin_can_view_compatible_approval_detail(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
        ]);

        $application = StampCorrectionRequest::factory()->create([
            'attendance_id' => $attendance->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($admin)
            ->get('/stamp_correction_request/approve/'.$application->id)
            ->assertOk()
            ->assertViewIs('admin.admin-application-detail');
    }
}

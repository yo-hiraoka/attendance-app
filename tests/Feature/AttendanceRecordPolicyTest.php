<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AttendanceRecordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_and_delete(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->actingAs($user);

        $this->assertTrue(Gate::allows('update', $attendance));
        $this->assertTrue(Gate::allows('delete', $attendance));
    }

    public function test_other_user_cannot_update_or_delete(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $owner->id,
        ]);

        $this->actingAs($other);

        $this->assertFalse(Gate::allows('update', $attendance));
        $this->assertFalse(Gate::allows('delete', $attendance));
    }

    public function test_admin_can_update_and_delete_any_attendance(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();

        $admin->forceFill(['role' => 'admin'])->save();

        $attendance = Attendance::factory()->create([
            'user_id' => $owner->id,
        ]);

        $this->actingAs($admin);

        $this->assertTrue(Gate::allows('update', $attendance));
        $this->assertTrue(Gate::allows('delete', $attendance));
    }

    public function test_guest_cannot_update_or_delete(): void
    {
        $attendance = Attendance::factory()->create();

        $this->assertFalse(Gate::allows('update', $attendance));
        $this->assertFalse(Gate::allows('delete', $attendance));
    }
}

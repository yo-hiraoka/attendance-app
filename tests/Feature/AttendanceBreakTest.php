<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceBreakTest extends TestCase
{
    use RefreshDatabase;

    private function clockIn(User $user): void
    {
        $this->actingAs($user)
            ->post('/attendance', ['action' => 'clock_in'])
            ->assertRedirect(route('attendance.index'));
    }

    public function test_user_can_start_break(): void
    {
        $this->travelTo(
            CarbonImmutable::parse('2026-11-09 09:00', 'Asia/Tokyo')
        );

        $user = User::factory()->create();
        $this->clockIn($user);

        $this->travelTo(
            CarbonImmutable::parse('2026-11-09 12:00', 'Asia/Tokyo')
        );

        $this->post('/attendance', ['action' => 'break_in'])
            ->assertRedirect(route('attendance.index'));

        $this->assertDatabaseHas('break_times', [
            'started_at' => '2026-11-09 03:00:00',
            'ended_at' => null,
        ]);

        $this->get('/attendance')
            ->assertSee('休憩中')
            ->assertSee('休憩戻');
    }

    public function test_user_can_finish_break(): void
    {
        $user = User::factory()->create();
        $this->clockIn($user);

        $this->post('/attendance', ['action' => 'break_in'])
            ->assertRedirect();

        $this->travel(30)->minutes();

        $this->post('/attendance', ['action' => 'break_out'])
            ->assertRedirect();

        $this->get('/attendance')
            ->assertSee('出勤中')
            ->assertSee('休憩入');

        $this->assertDatabaseCount('break_times', 1);
        $this->assertDatabaseMissing('break_times', [
            'ended_at' => null,
        ]);
    }

    public function test_user_can_take_multiple_breaks(): void
    {
        $user = User::factory()->create();
        $this->clockIn($user);

        $this->post('/attendance', ['action' => 'break_in'])
            ->assertRedirect();

        $this->travel(30)->minutes();

        $this->post('/attendance', ['action' => 'break_out'])
            ->assertRedirect();

        $this->travel(60)->minutes();

        $this->post('/attendance', ['action' => 'break_in'])
            ->assertRedirect();

        $this->travel(15)->minutes();

        $this->post('/attendance', ['action' => 'break_out'])
            ->assertRedirect();

        $this->assertDatabaseCount('break_times', 2);

        $this->assertDatabaseMissing('break_times', [
            'ended_at' => null,
        ]);

        $this->get('/attendance')
            ->assertSee('出勤中');
    }

    public function test_user_cannot_start_break_twice(): void
    {
        $user = User::factory()->create();
        $this->clockIn($user);

        $this->post('/attendance', ['action' => 'break_in'])
            ->assertRedirect();

        $this->post('/attendance', ['action' => 'break_in'])
            ->assertSessionHasErrors('action');

        $this->assertDatabaseCount('break_times', 1);
    }

    public function test_user_cannot_finish_break_without_starting(): void
    {
        $user = User::factory()->create();
        $this->clockIn($user);

        $this->post('/attendance', ['action' => 'break_out'])
            ->assertSessionHasErrors('action');

        $this->assertDatabaseCount('break_times', 0);
    }

    public function test_user_cannot_break_without_clocking_in(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post('/attendance', ['action' => 'break_in'])
            ->assertSessionHasErrors('action');

        $this->post('/attendance', ['action' => 'break_out'])
            ->assertSessionHasErrors('action');

        $this->assertDatabaseCount('break_times', 0);
    }

    public function test_user_cannot_clock_out_during_break(): void
    {
        $user = User::factory()->create();
        $this->clockIn($user);

        $this->post('/attendance', ['action' => 'break_in'])
            ->assertRedirect();

        $this->post('/attendance', ['action' => 'clock_out'])
            ->assertSessionHasErrors('action');

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'clock_out_at' => null,
        ]);

        $this->post('/attendance', ['action' => 'break_out'])
            ->assertRedirect();

        $this->post('/attendance', ['action' => 'clock_out'])
            ->assertRedirect();

        $this->get('/attendance')
            ->assertSee('退勤済');
    }

    public function test_user_can_finish_break_after_midnight(): void
    {
        $this->travelTo(
            CarbonImmutable::parse('2026-11-09 23:00', 'Asia/Tokyo')
        );

        $user = User::factory()->create();
        $this->clockIn($user);

        $this->travelTo(
            CarbonImmutable::parse('2026-11-09 23:50', 'Asia/Tokyo')
        );

        $this->post('/attendance', ['action' => 'break_in'])
            ->assertRedirect();

        $this->travelTo(
            CarbonImmutable::parse('2026-11-10 00:10', 'Asia/Tokyo')
        );

        $this->get('/attendance')
            ->assertSee('休憩中');

        $this->post('/attendance', ['action' => 'break_out'])
            ->assertRedirect();

        $this->assertDatabaseHas('break_times', [
            'started_at' => '2026-11-09 14:50:00',
            'ended_at' => '2026-11-09 15:10:00',
        ]);

        $this->post('/attendance', ['action' => 'clock_out'])
            ->assertRedirect();

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'work_date' => '2026-11-09',
        ]);

        $this->assertDatabaseCount('attendances', 1);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\StampCorrectionRequest;
use App\Models\User;
use App\Support\MySqlConnectionConfiguration;
use Carbon\CarbonImmutable;
use Database\Seeders\AttendanceDemoSeeder;
use Database\Seeders\SubmissionSampleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\TestDatabaseGuard;

class TimestampConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_mysql_url_cannot_override_utc_session_timezone(): void
    {
        $base = config('database.connections.mysql');
        $base['url'] = sprintf(
            'mysql://%s:%s@%s:%s/%s?timezone=%%2B09%%3A00&charset=utf8mb4',
            rawurlencode($base['username']), rawurlencode($base['password']),
            $base['host'], $base['port'], $base['database']
        );
        $resolved = MySqlConnectionConfiguration::resolve($base);
        TestDatabaseGuard::check($resolved, false, null, null, false);

        $this->assertSame('+00:00', $resolved['timezone']);
        $this->assertSame('utf8mb4', $resolved['charset']);
        $this->assertSame('+00:00', DB::connectUsing('utc_url_test', $resolved)
            ->selectOne('SELECT @@session.time_zone AS zone')->zone);
        DB::purge('utc_url_test');
        $this->assertSame('+00:00', DB::selectOne('SELECT @@session.time_zone AS zone')->zone);
    }

    public function test_mysql_read_and_write_timezones_cannot_override_utc(): void
    {
        $resolved = MySqlConnectionConfiguration::resolve([
            'driver' => 'mysql', 'host' => 'mysql', 'timezone' => '+09:00',
            'read' => ['host' => 'read-host', 'timezone' => '+09:00'],
            'write' => [['host' => 'write-host', 'timezone' => '-05:00']],
        ]);

        $this->assertSame('+00:00', $resolved['timezone']);
        $this->assertSame('+00:00', $resolved['read']['timezone']);
        $this->assertSame('+00:00', $resolved['write'][0]['timezone']);
        $this->assertSame('read-host', $resolved['read']['host']);
        $this->assertSame('write-host', $resolved['write'][0]['host']);
    }

    public function test_mysql_nested_url_is_rejected_before_another_resolution(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MySqlConnectionConfiguration::resolve([
            'url' => 'mysql://mysql/testing?url=mysql%3A%2F%2Fmysql%2Ftesting%3Ftimezone%3D%252B09%253A00',
        ]);
    }

    public function test_timestamp_accessors_preserve_array_json_and_carbon_behavior(): void
    {
        $user = new User;
        $user->setRawAttributes([
            'name' => 'Example', 'created_at' => '2026-11-20 00:00:00',
            'updated_at' => '2026-11-20 01:00:00', 'email_verified_at' => '2026-11-20 02:00:00',
        ]);
        $application = new StampCorrectionRequest;
        $application->setRawAttributes([
            'approved_at' => '2026-11-20 03:00:00', 'created_at' => null,
            'updated_at' => null, 'requested_clock_in_at' => '2026-11-20 04:00:00',
        ]);

        $userArray = $user->toArray();
        foreach (['created_at' => '00', 'updated_at' => '01', 'email_verified_at' => '02'] as $key => $hour) {
            $this->assertSame("2026-11-20T{$hour}:00:00.000000Z", $userArray[$key]);
            $this->assertSame($userArray[$key], json_decode($user->toJson(), true)[$key]);
            $this->assertSame('UTC', $user->$key->timezoneName);
        }
        $this->assertSame('2026-11-20 09:00', $user->created_at->copy()->setTimezone('Asia/Tokyo')->format('Y-m-d H:i'));
        $this->assertSame('Example', $userArray['name']);
        $user->syncOriginal();
        $user->created_at->addDay();
        $this->assertSame('2026-11-20 00:00:00', $user->getAttributes()['created_at']);
        $this->assertFalse($user->isDirty());
        $this->assertSame('2026-11-20T03:00:00.000000Z', $application->toArray()['approved_at']);
        $this->assertSame('2026-11-20T03:00:00.000000Z', json_decode($application->toJson(), true)['approved_at']);
        $this->assertSame('UTC', $application->approved_at->timezoneName);
        $this->assertNull($application->toArray()['created_at']);
        $this->assertNull($application->updated_at);
        $this->assertSame('Asia/Tokyo', $application->requested_clock_in_at->timezoneName);
        $user->setRawAttributes(['email_verified_at' => null]);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->toArray()['email_verified_at']);
        $application->setRawAttributes(['approved_at' => null]);
        $this->assertNull($application->approved_at);
        $this->assertNull($application->toArray()['approved_at']);
    }

    public function test_demo_application_is_saved_in_utc_and_displayed_in_jst(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 09:00:00', 'Asia/Tokyo'));
        $this->seed(AttendanceDemoSeeder::class);

        $application = StampCorrectionRequest::where('status', 'pending')->firstOrFail();
        $this->assertSame('2026-11-20 00:00:00', $application->getRawOriginal('created_at'));
        $this->assertSame('2026-11-20 00:00:00', $application->getRawOriginal('updated_at'));

        $this->actingAs($application->user)->get('/stamp_correction_request/list')
            ->assertOk()->assertSee('2026/11/20 09:00');
        $admin = User::where('email', 'demo-admin@example.com')->firstOrFail();
        $this->actingAs($admin)->get('/admin/stamp_correction_request/list')
            ->assertOk()->assertSee('2026/11/20 09:00');
    }

    public function test_demo_application_list_displays_the_creation_time_in_jst(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 09:00:00', 'Asia/Tokyo'));
        $this->seed(AttendanceDemoSeeder::class);
        $user = User::where('email', 'demo-user1@example.com')->firstOrFail();

        $response = $this->actingAs($user)->get('/stamp_correction_request/list')->assertOk();
        $applications = $response->viewData('formattedApplications');
        $this->assertSame('2026/11/20 09:00', $applications->first()['application_date']);
    }

    public function test_web_stamping_correction_and_approval_use_utc(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 09:00:00', 'Asia/Tokyo'));
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user)->post('/attendance', ['action' => 'clock_in'])->assertSessionHasNoErrors();
        $attendance = Attendance::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('2026-11-20 00:00:00', $attendance->getRawOriginal('created_at'));
        $this->assertSame('2026-11-20', $attendance->getRawOriginal('work_date'));

        $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00:00', 'Asia/Tokyo'));
        $this->post('/attendance', ['action' => 'break_in'])->assertSessionHasNoErrors();
        $break = BreakTime::where('attendance_id', $attendance->id)->firstOrFail();
        $this->assertSame('2026-11-20 03:00:00', $break->getRawOriginal('created_at'));
        $this->travelTo(CarbonImmutable::parse('2026-11-20 13:00:00', 'Asia/Tokyo'));
        $this->post('/attendance', ['action' => 'break_out'])->assertSessionHasNoErrors();
        $this->assertSame('2026-11-20 04:00:00', $break->fresh()->getRawOriginal('updated_at'));
        $this->travelTo(CarbonImmutable::parse('2026-11-20 18:00:00', 'Asia/Tokyo'));
        $this->post('/attendance', ['action' => 'clock_out'])->assertSessionHasNoErrors();
        $this->assertSame('2026-11-20 09:00:00', $attendance->fresh()->getRawOriginal('updated_at'));

        $this->post('/attendance/'.$attendance->id, [
            'new_clock_in' => '09:00', 'new_clock_out' => '18:00',
            'new_break_in' => ['12:00'], 'new_break_out' => ['13:00'],
            'comment' => '修正申請',
        ])->assertSessionHasNoErrors();
        $application = StampCorrectionRequest::where('attendance_id', $attendance->id)->firstOrFail();
        $this->assertSame('2026-11-20 09:00:00', $application->getRawOriginal('created_at'));
        $this->assertSame('2026-11-20 09:00:00', $application->proposalBreaks->first()->getRawOriginal('created_at'));

        $this->travelTo(CarbonImmutable::parse('2026-11-20 19:00:00', 'Asia/Tokyo'));
        $this->actingAs($admin)->post('/admin/stamp_correction_request/approve/'.$application->id)
            ->assertSessionHasNoErrors();
        $application->refresh();
        $this->assertSame('2026-11-20 10:00:00', $application->getRawOriginal('approved_at'));
        $this->assertSame('2026-11-20T10:00:00+00:00', $application->approved_at->toIso8601String());
        $this->assertSame('2026-11-20 10:00:00', $application->getRawOriginal('updated_at'));
        $this->assertSame('2026-11-20 10:00:00', $attendance->fresh()->getRawOriginal('updated_at'));
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id, 'work_date' => '2026-11-20',
            'clock_in_at' => '2026-11-20 00:00:00', 'clock_out_at' => '2026-11-20 09:00:00',
        ]);
    }

    public function test_api_create_and_update_keep_utc_timestamps_and_json(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 09:00:00', 'Asia/Tokyo'));
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->getJson('/api/user')->assertOk()
            ->assertJsonPath('created_at', '2026-11-20T00:00:00.000000Z')
            ->assertJsonPath('email_verified_at', '2026-11-20T00:00:00.000000Z');
        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-11-20', 'clock_in' => '09:00:00', 'clock_out' => '18:00:00',
        ])->assertCreated()->assertJsonPath('data.clock_in', '09:00:00');
        $attendance = Attendance::findOrFail($response->json('data.id'));
        $this->assertSame('2026-11-20 00:00:00', $attendance->getRawOriginal('created_at'));
        $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00:00', 'Asia/Tokyo'));
        $this->patchJson('/api/v1/attendance-records/'.$attendance->id, ['comment' => '更新'])
            ->assertOk()->assertJsonPath('data.comment', '更新');
        $attendance->refresh();
        $this->assertSame('2026-11-20 00:00:00', $attendance->getRawOriginal('created_at'));
        $this->assertSame('2026-11-20 01:00:00', $attendance->getRawOriginal('updated_at'));
    }

    public function test_email_verification_and_submission_seed_use_utc(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 09:00:00', 'Asia/Tokyo'));
        $user = User::factory()->unverified()->create();
        $this->assertSame('2026-11-20 00:00:00', $user->fresh()->getRawOriginal('created_at'));
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]);
        $this->actingAs($user)->get($url)->assertRedirect('/?verified=1');
        $this->assertSame('2026-11-20 00:00:00', $user->fresh()->getRawOriginal('email_verified_at'));
        $this->assertSame('2026-11-20T00:00:00+00:00', $user->fresh()->email_verified_at->toIso8601String());

        $this->seed(SubmissionSampleSeeder::class);
        $seedUser = User::where('email', 'user1@example.com')->firstOrFail();
        $this->assertSame('2026-11-20 00:00:00', $seedUser->getRawOriginal('email_verified_at'));
        $this->assertSame('2026-11-20 00:00:00', $seedUser->getRawOriginal('created_at'));
        $this->assertSame('2026-11-20 00:00:00', $seedUser->attendances()->firstOrFail()->getRawOriginal('created_at'));
        $this->assertSame('+00:00', DB::selectOne('SELECT @@session.time_zone AS zone')->zone);
    }

    public function test_api_date_change_updates_related_model_timestamps_in_utc(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 09:00:00', 'Asia/Tokyo'));
        $this->seed(AttendanceDemoSeeder::class);
        $application = StampCorrectionRequest::where('status', 'pending')->firstOrFail();
        $attendance = $application->attendance;
        $break = $attendance->breaks->first();
        $proposalBreak = $application->proposalBreaks->first();
        $this->assertSame('2026-11-20 00:00:00', $proposalBreak->getRawOriginal('created_at'));

        $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00:00', 'Asia/Tokyo'));
        Sanctum::actingAs($application->user);
        $this->patchJson('/api/v1/attendance-records/'.$attendance->id, ['date' => '2026-11-04'])
            ->assertOk()->assertJsonPath('data.date', '2026-11-04');

        foreach ([$attendance, $break, $application, $proposalBreak] as $record) {
            $record->refresh();
            $this->assertSame('2026-11-20 00:00:00', $record->getRawOriginal('created_at'));
            $this->assertSame('2026-11-20 01:00:00', $record->getRawOriginal('updated_at'));
        }
    }
}

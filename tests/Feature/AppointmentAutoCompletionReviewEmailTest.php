<?php

namespace Tests\Feature;

use App\Mail\ReviewRequestMail;
use App\Models\Appointment;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AppointmentAutoCompletionReviewEmailTest extends TestCase
{
    use RefreshDatabase;

    protected Salon $salon;
    protected Staff $staff;
    protected Service $service;
    protected User $client;
    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-05-24 12:00:00'));

        $this->owner = User::factory()->create(['role' => 'salon']);
        $this->salon = Salon::factory()->create([
            'owner_id' => $this->owner->id,
            'status' => 'approved',
        ]);
        $this->staff = Staff::factory()->create(['salon_id' => $this->salon->id]);
        $this->service = Service::factory()->create([
            'salon_id' => $this->salon->id,
            'duration' => 60,
            'price' => 50,
        ]);
        $this->client = User::factory()->create([
            'role' => 'klijent',
            'email' => 'client@example.com',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function appointment(array $overrides = []): Appointment
    {
        return Appointment::factory()->create(array_merge([
            'client_id' => $this->client->id,
            'client_name' => $this->client->name,
            'client_email' => $this->client->email,
            'salon_id' => $this->salon->id,
            'staff_id' => $this->staff->id,
            'service_id' => $this->service->id,
            'date' => '2026-05-24',
            'time' => '10:00',
            'end_time' => '11:00',
            'status' => 'confirmed',
            'total_price' => 50,
        ], $overrides));
    }

    public function test_expired_confirmed_appointment_is_completed_and_review_email_is_sent_once(): void
    {
        Mail::fake();
        $appointment = $this->appointment();

        $this->artisan('appointments:complete-expired')->assertExitCode(0);

        $appointment->refresh();
        $this->assertSame('completed', $appointment->status);
        $this->assertNotNull($appointment->review_request_sent_at);
        Mail::assertQueued(ReviewRequestMail::class, 1);

        $this->artisan('appointments:complete-expired')->assertExitCode(0);

        Mail::assertQueued(ReviewRequestMail::class, 1);
    }

    public function test_future_cancelled_and_no_show_appointments_are_not_auto_completed(): void
    {
        Mail::fake();
        $future = $this->appointment([
            'date' => '2026-05-24',
            'time' => '13:00',
            'end_time' => '14:00',
            'status' => 'confirmed',
        ]);
        $cancelled = $this->appointment(['status' => 'cancelled']);
        $noShow = $this->appointment(['status' => 'no_show']);

        $this->artisan('appointments:complete-expired')->assertExitCode(0);

        $this->assertSame('confirmed', $future->fresh()->status);
        $this->assertSame('cancelled', $cancelled->fresh()->status);
        $this->assertSame('no_show', $noShow->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_manual_completion_uses_same_review_email_idempotency_guard(): void
    {
        Mail::fake();
        $appointment = $this->appointment([
            'date' => '2026-05-25',
            'time' => '10:00',
            'end_time' => '11:00',
        ]);

        $this->actingAs($this->owner)
            ->putJson("/api/v1/appointments/{$appointment->id}/complete")
            ->assertOk();

        $appointment->refresh();
        $this->assertSame('completed', $appointment->status);
        $this->assertNotNull($appointment->review_request_sent_at);
        Mail::assertQueued(ReviewRequestMail::class, 1);

        app(\App\Services\NotificationService::class)->sendReviewRequestEmail($appointment->fresh());

        Mail::assertQueued(ReviewRequestMail::class, 1);
    }

    public function test_review_request_email_contains_direct_appointment_review_link(): void
    {
        config(['app.frontend_url' => 'https://frizerino.test']);
        $appointment = $this->appointment();

        $mail = new ReviewRequestMail($appointment->load(['salon', 'staff', 'service', 'client']));

        $this->assertSame(
            "https://frizerino.test/moji-termini?review_appointment_id={$appointment->id}",
            $mail->reviewUrl
        );
        $this->assertStringNotContainsString('writeReview=true', $mail->reviewUrl);
    }
}

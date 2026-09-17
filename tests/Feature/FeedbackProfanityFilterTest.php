<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Feedback;
use App\Models\ServiceType;
use App\Models\User;
use App\Support\ProfanityFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FeedbackProfanityFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_filter_censors_english_and_tagalog_profanity_case_insensitively(): void
    {
        $this->assertSame(
            '**** this, ****! **** at ****.',
            ProfanityFilter::censor('FUCK this, putang ina! Gago at tanga.')
        );
    }

    public function test_filter_does_not_censor_profanity_fragments_inside_safe_words(): void
    {
        $comment = 'Classic assistants discuss passion and assignments.';

        $this->assertSame($comment, ProfanityFilter::censor($comment));
    }

    public function test_filter_censors_common_standalone_tagalog_variants(): void
    {
        $this->assertSame(
            '****! ****, ****; ****.',
            ProfanityFilter::censor('Puta! Tang ina, punyeta; kingina.')
        );
    }

    public function test_admin_sees_censored_feedback_while_the_original_comment_remains_stored(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'user']);
        $service = ServiceType::query()->create([
            'name' => 'Garden Care',
            'default_fee' => 900,
            'is_active' => true,
        ]);
        $appointmentAt = Carbon::now()->subDay()->setTime(9, 0);
        $appointment = Appointment::query()->create([
            'user_id' => $customer->id,
            'service_type_id' => $service->id,
            'appointment_at' => $appointmentAt,
            'slot_key' => null,
            'appointment_amount' => 900,
            'status' => 'completed',
        ]);
        $original = 'Good plants but PUTANG INA and fucking late.';
        $censored = 'Good plants but **** and **** late.';

        Feedback::query()->create([
            'user_id' => $customer->id,
            'appointment_id' => $appointment->id,
            'service_type_id' => $service->id,
            'rating' => 2,
            'comment' => $original,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard', ['tab' => 'feedbacks']))
            ->assertOk()
            ->assertSeeText($censored)
            ->assertDontSee($original, false);

        $this->actingAs($admin)
            ->get(route('admin.dashboard', ['tab' => 'appointments']))
            ->assertOk()
            ->assertSee($censored, false)
            ->assertDontSee($original, false);

        $this->assertDatabaseHas('feedbacks', ['comment' => $original]);

        $this->actingAs($customer)
            ->get(route('feedback'))
            ->assertOk()
            ->assertSeeText($original);
    }
}

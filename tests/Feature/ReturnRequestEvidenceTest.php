<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestEvidence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReturnRequestEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_evidence_is_private_to_the_claim_owner_and_team(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['role' => 'user']);
        $stranger = User::factory()->create(['role' => 'user']);
        $staff = User::factory()->create(['role' => 'staff']);
        $claim = $this->claimFor($owner, 'RET-EVIDENCE-001');
        $path = "return-evidence/{$claim->id}/damage.jpg";
        Storage::disk('local')->put($path, 'private image bytes');
        $evidence = $claim->evidence()->create([
            'uploaded_by' => $owner->id,
            'path' => $path,
            'original_name' => 'damage.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 19,
        ]);
        $url = route('returns.evidence', [$claim, $evidence]);

        $this->actingAs($owner)->get($url)->assertOk();
        $this->actingAs($staff)->get($url)->assertOk();
        $this->actingAs($stranger)->get($url)->assertForbidden();
        auth()->logout();
        $this->get($url)->assertRedirect(route('login'));
    }

    public function test_evidence_cannot_be_read_through_a_different_claim(): void
    {
        Storage::fake('local');
        $customer = User::factory()->create(['role' => 'user']);
        $firstClaim = $this->claimFor($customer, 'RET-EVIDENCE-002');
        $secondClaim = $this->claimFor($customer, 'RET-EVIDENCE-003');
        $path = "return-evidence/{$firstClaim->id}/damage.png";
        Storage::disk('local')->put($path, 'private image bytes');
        $evidence = ReturnRequestEvidence::query()->create([
            'return_request_id' => $firstClaim->id,
            'uploaded_by' => $customer->id,
            'path' => $path,
            'original_name' => 'damage.png',
            'mime_type' => 'image/png',
            'size_bytes' => 19,
        ]);

        $this->actingAs($customer)
            ->get(route('returns.evidence', [$secondClaim, $evidence]))
            ->assertNotFound();
    }

    private function claimFor(User $customer, string $claimNumber): ReturnRequest
    {
        $order = Order::query()->create([
            'user_id' => $customer->id,
            'order_number' => 'FRS-'.$claimNumber,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 500,
        ]);

        return ReturnRequest::query()->create([
            'claim_number' => $claimNumber,
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }
}

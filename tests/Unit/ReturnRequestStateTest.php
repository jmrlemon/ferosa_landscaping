<?php

namespace Tests\Unit;

use App\Models\ReturnRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReturnRequestStateTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function allowedTransitions(): array
    {
        return [
            'submitted to review' => ['submitted', 'under_review'],
            'submitted needs information' => ['submitted', 'needs_information'],
            'submitted cancellation' => ['submitted', 'cancelled'],
            'review needs information' => ['under_review', 'needs_information'],
            'review approval' => ['under_review', 'approved'],
            'review rejection' => ['under_review', 'rejected'],
            'information supplied' => ['needs_information', 'submitted'],
            'information request cancelled' => ['needs_information', 'cancelled'],
            'approved replacement dispatch' => ['approved', 'replacement_dispatched'],
            'approved refund resolution' => ['approved', 'resolved'],
            'replacement resolution' => ['replacement_dispatched', 'resolved'],
        ];
    }

    #[DataProvider('allowedTransitions')]
    public function test_claim_allows_only_declared_forward_transitions(string $from, string $to): void
    {
        $claim = new ReturnRequest(['status' => $from]);

        $this->assertTrue($claim->canTransitionTo($to));
        $this->assertTrue($claim->canTransitionTo($from));
    }

    public function test_terminal_claim_states_cannot_be_reopened(): void
    {
        foreach (['resolved', 'rejected', 'cancelled'] as $status) {
            $claim = new ReturnRequest(['status' => $status]);

            foreach (ReturnRequest::STATUSES as $candidate) {
                $this->assertSame($candidate === $status, $claim->canTransitionTo($candidate));
            }
        }
    }

    public function test_claim_contract_exposes_supported_issue_and_resolution_types(): void
    {
        $this->assertSame([
            'damaged_on_arrival',
            'unhealthy_on_arrival',
            'wrong_item',
            'missing_quantity',
        ], ReturnRequest::ISSUE_TYPES);

        $this->assertSame([
            'replacement',
            'refund',
            'partial_refund',
            'rejected',
        ], ReturnRequest::RESOLUTION_TYPES);
    }
}

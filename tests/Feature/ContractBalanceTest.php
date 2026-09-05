<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The money arithmetic on a contract.
 *
 * These are the numbers the business bills on, so each case pins down one
 * rule: which payment states count, how the balance rolls up, and what the
 * derived status says.
 */
class ContractBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_contract_with_no_payments_is_fully_outstanding(): void
    {
        $contract = Contract::factory()->worth(10000)->create();

        $this->assertSame(0.0, $contract->paidAmount());
        $this->assertSame(10000.0, $contract->balanceDue());
        $this->assertSame('unpaid', $contract->paymentStatus());
    }

    public function test_completed_payments_reduce_the_balance(): void
    {
        $contract = Contract::factory()->worth(10000)->create();

        Payment::factory()->of(4000)->create(['contract_id' => $contract->id]);

        $this->assertSame(4000.0, $contract->paidAmount());
        $this->assertSame(6000.0, $contract->balanceDue());
        $this->assertSame('partial', $contract->paymentStatus());
    }

    public function test_a_contract_paid_in_full_has_no_balance(): void
    {
        $contract = Contract::factory()->worth(10000)->create();

        Payment::factory()->of(6000)->create(['contract_id' => $contract->id]);
        Payment::factory()->of(4000)->create(['contract_id' => $contract->id]);

        $this->assertSame(10000.0, $contract->paidAmount());
        $this->assertSame(0.0, $contract->balanceDue());
        $this->assertSame('paid', $contract->paymentStatus());
    }

    /**
     * Only completed money counts. A pending gateway order, a failed card and
     * a refund must not be treated as collected.
     */
    public function test_pending_failed_and_refunded_payments_do_not_count_as_paid(): void
    {
        $contract = Contract::factory()->worth(10000)->create();

        Payment::factory()->pending()->of(3000)->create(['contract_id' => $contract->id]);
        Payment::factory()->failed()->of(2000)->create(['contract_id' => $contract->id]);
        Payment::factory()->refunded()->of(1000)->create(['contract_id' => $contract->id]);

        $this->assertSame(0.0, $contract->paidAmount());
        $this->assertSame(10000.0, $contract->balanceDue());
        $this->assertSame('unpaid', $contract->paymentStatus());
    }

    /**
     * Overpayment must not produce a negative balance, which would read as a
     * credit the business does not actually owe.
     */
    public function test_balance_never_goes_negative_when_overpaid(): void
    {
        $contract = Contract::factory()->worth(5000)->create();

        Payment::factory()->of(6000)->create(['contract_id' => $contract->id]);

        $this->assertSame(6000.0, $contract->paidAmount());
        $this->assertSame(0.0, $contract->balanceDue());
        $this->assertSame('paid', $contract->paymentStatus());
    }

    public function test_payments_on_other_contracts_are_not_counted(): void
    {
        $contract = Contract::factory()->worth(10000)->create();
        $other = Contract::factory()->worth(10000)->create();

        Payment::factory()->of(5000)->create(['contract_id' => $other->id]);

        $this->assertSame(0.0, $contract->paidAmount());
        $this->assertSame(10000.0, $contract->balanceDue());
    }

    /**
     * Rupee amounts that are exact to the paisa must settle to a zero balance
     * rather than leaving a fractional remainder behind.
     */
    public function test_fractional_amounts_settle_exactly(): void
    {
        $contract = Contract::factory()->worth(1000.50)->create();

        Payment::factory()->of(333.50)->create(['contract_id' => $contract->id]);
        Payment::factory()->of(667.00)->create(['contract_id' => $contract->id]);

        $this->assertSame(0.0, $contract->balanceDue());
        $this->assertSame('paid', $contract->paymentStatus());
    }
}

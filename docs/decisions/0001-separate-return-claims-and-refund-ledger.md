# ADR 0001: Separate return claims and refund ledger

- Status: Accepted
- Date: 2026-09-17

## Context

Orders already have a fulfillment state machine and payments are an append-only
ledger of money received. A damaged-plant claim can lead to review, replacement
stock, a refund, or rejection after the original order has been delivered.
Reopening the order or storing refunds as negative payments would make both
histories ambiguous and would allow retries to corrupt stock or money totals.

## Decision

Use a separate ReturnRequest aggregate with its own guarded state machine and
per-line outcomes. Keep the original order status unchanged. Store customer
evidence on the private local disk and stream it only after ownership or staff
authorization.

Replacement approval creates a distinct negative replacement stock movement
inside the decision transaction. Returned items do not increase saleable stock
unless an administrator explicitly records an inspected restock.

Refunds use a positive, append-only refunds ledger linked to the order and
claim. Corrections void a row; they never delete it or create a negative
payment. Refunds are capped by both the approved claim amount and unrefunded
money actually received.

Admin authorization is required for claim decisions, refunds, refund voids, and
restocking. Staff authorization is sufficient for review requests and approved
replacement logistics. Important customer updates create an in-app
notification and queue TextBee SMS only for verified phone numbers.

## Consequences

- Fulfillment, inventory, and financial histories remain independently
  reconstructable.
- Retried or concurrent decisions cannot decrement replacement stock twice.
- Refund reporting must use paid, refunded, and net-paid values rather than
  treating a refund as a negative receipt.
- Queue workers are required for customer notifications and SMS retries, while
  notification failure never rolls back a saved decision.
- Automatic gateway refunds and a native Android returns API are intentionally
  outside version 1.

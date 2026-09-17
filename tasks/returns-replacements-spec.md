# Specification: Damaged Plant Returns and Replacements

## Objective

Add a controlled post-delivery claim workflow for live plants or other order
items that arrive damaged, unhealthy, incorrect, or incomplete. A customer can
report affected order lines with photo evidence and request a replacement or
refund. The admin makes the final decision, staff can perform approved
replacement logistics, and every stock, money, status, notification, and audit
change remains traceable.

This is a claim workflow, not a reopening of the order fulfillment state
machine. The original order remains `delivered` or `completed`; the claim owns
its own lifecycle.

## Recommended Business Policy for Version 1

- Customers may file a claim for a delivery or pickup order in `delivered` or
  `completed` status within 24 hours of `customer_confirmed_at`, falling back to
  `delivered_at` when receipt has not yet been confirmed.
- The customer selects each affected order line and a quantity no greater than
  the quantity purchased and not already covered by another active or approved
  claim.
- At least one photo is required for a damaged or unhealthy plant. Allow up to
  five JPG, PNG, or WebP images, each no larger than 5 MiB.
- Supported issue types are `damaged_on_arrival`, `unhealthy_on_arrival`,
  `wrong_item`, and `missing_quantity`. Change-of-mind and damage caused after
  delivery are outside the automatic policy and may be handled manually.
- The customer may prefer replacement or refund, but the admin makes the final
  decision per claimed line: replace, full line refund, partial refund, or
  reject with a required reason.
- No physical return is required by default for a damaged live plant. An admin
  may request a return or pickup when inspection is necessary.
- An approved replacement has no extra product or delivery charge.
- A damaged returned plant is never automatically restored to sellable stock.
  Restocking is a separate, explicit admin disposition after inspection.
- Admins approve or reject claims and process refunds. Staff may view claims,
  request information, and dispatch an already approved replacement, but cannot
  create a financial refund or change an admin decision.

These are product defaults for review, not silently embedded assumptions. The
open questions at the end must be confirmed before implementation begins.

## User Stories

### Customer

- As a customer, I can report one or more damaged plants from my delivered
  order, specify quantities and issues, upload evidence, and state whether I
  prefer replacement or refund.
- As a customer, I can see the claim number, current status, admin response,
  approved outcome, replacement progress, and refund details.
- As a customer, I cannot claim another customer's order, over-claim a
  quantity, submit after the policy window, or access another claim's photos.

### Admin and Staff

- As an admin, I can review evidence and order history, decide each claimed
  line, request more information, approve replacement/refund, or reject with a
  customer-visible reason.
- As staff, I can see and coordinate approved replacement delivery without
  gaining refund or payment permissions.
- As an admin, I can explicitly record the disposition of a physically returned
  item without accidentally restoring damaged plants to saleable inventory.

## Claim State Machine

```text
submitted
  |-- under_review
  |-- needs_information --> submitted
  |-- cancelled

under_review
  |-- needs_information
  |-- approved
  |-- rejected

approved
  |-- replacement_dispatched   (when any replacement is due)
  |-- resolved                 (refund-only or no shipment needed)

replacement_dispatched
  |-- resolved

Terminal: resolved, rejected, cancelled
```

All transitions must use a `canTransitionTo()` check under a database row lock.
No controller may set claim status directly without that check.

## Data Model

### `return_requests`

- `id`, unique `claim_number`
- `order_id`, `user_id`
- `status`
- customer summary and preferred contact notes
- `submitted_at`, `reviewed_at`, `decided_at`, `resolved_at`
- `reviewed_by`, `decided_by`
- customer-visible decision reason and private admin notes
- `return_required`, return instructions, replacement dispatch fields
- timestamps

### `return_request_items`

- `return_request_id`, `order_item_id`
- `quantity_claimed`, issue type, issue description
- preferred resolution
- admin resolution: `replacement`, `refund`, `partial_refund`, or `rejected`
- approved replacement quantity and/or refund amount
- customer-visible line decision note
- disposition: `not_required`, `awaiting_return`, `damaged_discard`, or
  `restocked`

### `return_request_evidence`

- `return_request_id`, uploader user ID
- private-disk path, original filename, MIME type, byte size
- timestamps

### `refunds`

- append-only order refund ledger linked to `order_id` and `return_request_id`
- amount, method, reference, notes, processed timestamp and admin
- optional void timestamp, actor, and reason; rows are never deleted

Refunds must not be represented as negative `payments`. Existing payments keep
their current meaning: money received. Billing adds `totalRefunded()` and
`netPaid()` calculations and caps a refund to the lower of the approved claim
amount and unrefunded money received.

## Inventory Rules

- Add a distinct negative stock movement such as `replacement` for stock sent
  without a second sale.
- Reserve/decrement replacement stock only in the approved replacement
  transaction, under a product row lock. If stock is insufficient, approval
  fails without changing claim state or inventory.
- Claim approval and replacement inventory movement must be idempotent; retries
  cannot decrement stock twice.
- A damaged item that returns physically creates no positive stock movement.
- Only an explicit admin `restocked` disposition may add inspected saleable
  quantity back through `InventoryService` with the claim number as reference.

## Routes and Authorization

Customer routes remain inside `auth` middleware and begin from the owned order:

- `GET /orders/{order}/returns/create`
- `POST /orders/{order}/returns`
- `GET /returns/{returnRequest}`
- `POST /returns/{returnRequest}/information`
- `POST /returns/{returnRequest}/cancel`
- `GET /returns/{returnRequest}/evidence/{evidence}`

Staff/admin routes use `staff` middleware for reading and logistics. Decision
and refund actions additionally require the existing `admin` middleware:

- `GET /admin/returns`
- `GET /admin/returns/{returnRequest}`
- `PUT /admin/returns/{returnRequest}/review`
- `POST /admin/returns/{returnRequest}/dispatch-replacement`
- `POST /admin/returns/{returnRequest}/resolve`
- `POST /admin/returns/{returnRequest}/refunds`

Every customer action performs the project's explicit ownership check before
reading evidence or mutating a claim. Staff actions must reject archived or
inaccessible records consistently with the current admin workspace.

## Storage and Security

- Claim photos are private customer data and must use the `local` disk.
- Evidence is served only through an ownership/role-checked streaming route.
- Validate actual image content and allowed MIME types, cap file count and
  size, generate server-side filenames, and never expose storage paths.
- Create the database claim before finalizing files, clean up stored files if
  the transaction fails, and reject evidence belonging to another claim.
- Throttle claim submission and information replies; escape all descriptions
  and notes in Blade.

## Notifications

- On submission, notify admin/staff in-app with an internal claim URL.
- On `needs_information`, approval, rejection, replacement dispatch, refund,
  and resolution, notify the customer in-app.
- Queue a concise TextBee SMS only for the important customer events above and
  only when the customer has a verified phone number.
- SMS/provider failure must never roll back a saved claim decision, refund, or
  inventory transaction.

## UI Scope

- Customer Orders: show `Report damaged plant` only for eligible orders; show a
  claim badge/timeline once submitted.
- Customer claim form: mobile-first line selection, quantity controls, issue
  description, resolution preference, multi-image preview, policy summary, and
  confirmation before submission.
- Admin workspace: claims queue with status/age filters and urgent-window
  indicators, plus a claim detail screen showing order lines, photos, prior
  claims, stock availability, payment/refund totals, and permitted actions.
- Staff sees logistics controls only after admin approval.
- The Android app needs no native API changes in version 1 because its WebView
  renders the Laravel UI and shares the existing session.

## Commands

Run from `ferosa-laravel/`:

```text
Focused tests: D:\xampp\php\php.exe artisan test tests/Feature/ReturnRequestWorkflowTest.php
Full gate:     D:\xampp\php\php.exe C:\Users\admin\.config\herd-lite\bin\composer.phar check
Frontend:      npm.cmd run build
Development:   composer dev
```

## Code Style and Boundaries

Use explicit state transitions and ownership checks already established by the
project:

```php
abort_unless((int) $returnRequest->user_id === (int) auth()->id(), 403);
abort_unless($lockedClaim->canTransitionTo($nextStatus), 422);
```

- Always: use transactions and row locks for state, stock, and refund writes;
  store evidence privately; audit admin decisions; test SQLite compatibility.
- Ask first: change the 24-hour policy, allow claims without photos, add an
  external refund/payment provider, or add a native Android returns screen.
- Never: overload `Order::STATUS_TRANSITIONS`, directly change `stock_qty`,
  delete financial records, publicly expose evidence, or trust posted prices.

## Success Criteria

- Eligible customers can submit a multi-item damage claim with private evidence.
- Claims cannot exceed purchased or still-claimable quantities.
- Admins can make per-line decisions; staff cannot issue refunds.
- Replacement stock and explicit restocks reconcile in the stock ledger exactly once.
- Refunds are capped, append-only, voidable, and visible on admin/customer billing history.
- Orders keep their original fulfillment state and receipt history.
- In-app/SMS updates are accurate and cannot undo successful transactions.
- Focused tests, `composer check`, `npm run build`, and a staging walkthrough pass.

## Open Questions Requiring Business Approval

1. Confirm the claim window: recommended 24 hours; alternative 48 hours.
2. Confirm that physical return is not required unless an admin requests it.
3. Confirm that approved replacements have no delivery fee.
4. Confirm that only admins may approve outcomes and refunds, while staff handle
   information requests and replacement dispatch.
5. Confirm supported refund methods: recommended cash, GCash, bank transfer, and
   other/manual reference, with no automatic gateway integration in version 1.

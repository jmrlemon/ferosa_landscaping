# Implementation Plan: Damaged Plant Returns and Replacements

## Overview

Build a separate post-delivery claim workflow for damaged, unhealthy, wrong, or
missing plants. Customers select the affected order lines and quantities,
upload private photo evidence, and request replacement or refund. Admins make
per-line decisions; staff can perform approved replacement logistics. The
feature preserves the original order state machine and extends the existing
inventory, billing, audit, notification, and TextBee patterns.

The detailed business and technical contract is in
`tasks/returns-replacements-spec.md`. Implementation must not begin until the
five policy questions in that specification are approved.

## Dependency Graph

```text
Policy approval
      |
      v
Claim schema + state machine
      |
      +--> private evidence boundary
      |          |
      |          v
      +--> customer submission and tracking
      |          |
      |          v
      +--> admin review and per-line decisions
                    |
                    +--> replacement stock/logistics
                    |
                    +--> refund ledger
                              |
                              v
                    notifications + audit
                              |
                              v
                    full UI and staging verification
```

## Architecture Decisions

- Keep `Order::STATUS_TRANSITIONS` unchanged. Returns are not order fulfillment
  states and receive their own model and transition map.
- Use one claim containing multiple claim items because an order can include
  many plant types and only some quantities may be damaged.
- Store original `OrderItem` links and continue using its snapshotted name and
  price, so claims remain understandable if a product is later archived/deleted.
- Put claim photos on the private `local` disk behind an ownership/role-checked
  route, following payment-proof and chat-attachment security patterns.
- Add a dedicated append-only refund ledger rather than negative payments.
- Route all replacement and explicit restock quantities through
  `InventoryService`; never directly write `products.stock_qty`.
- Treat notification/SMS delivery as asynchronous follow-up. Provider failures
  are reported/retried and cannot reverse claim decisions.
- Keep version 1 in Laravel/WebView; do not add native Android endpoints or
  screens unless separately requested.

## Phase 1: Foundation

### Task 1: Lock the Product Policy and Test Matrix

**Description:** Confirm the five open policy choices in the specification and
turn them into named configuration/constants plus a complete test matrix before
writing persistence code.

**Acceptance criteria:**

- [ ] Claim window, evidence rule, return rule, delivery fee, staff permissions,
  and refund methods are explicitly approved.
- [ ] Claim statuses, allowed transitions, issue types, and resolution types are
  fixed in one documented contract.
- [ ] Tests are listed for happy paths, authorization, time boundaries,
  over-claiming, concurrency, stock, refunds, storage, and notifications.

**Verification:** Review `tasks/returns-replacements-spec.md` with the owner and
record any changes before Task 2.

**Dependencies:** None.

**Files likely touched:**

- `tasks/returns-replacements-spec.md`
- `tasks/plan.md`

**Estimated scope:** Small, 2 files.

### Task 2: Add Claim Persistence and State Machine

**Description:** Add migrations and models for return requests and line items,
including relationships, casts, constants, and state transitions. Add model
tests before controller work.

**Acceptance criteria:**

- [ ] One claim belongs to one order/customer and contains one or more order-item lines.
- [ ] Quantities, issue types, preferences, admin outcomes, timestamps, actors,
  and dispositions have database constraints/indexes where practical.
- [ ] Invalid state transitions are rejected by `canTransitionTo()`.

**Verification:** Run focused model/state tests on in-memory SQLite and migration rollback.

**Dependencies:** Task 1.

**Files likely touched:**

- `database/migrations/*_create_return_requests_table.php`
- `database/migrations/*_create_return_request_items_table.php`
- `app/Models/ReturnRequest.php`
- `app/Models/ReturnRequestItem.php`
- `tests/Unit/ReturnRequestStateTest.php`

**Estimated scope:** Medium, 5 files.

### Task 3: Add the Private Evidence Boundary

**Description:** Add evidence persistence, validated multi-image storage on the
private disk, and one streaming action that authorizes the claim owner or team.

**Acceptance criteria:**

- [ ] One to five valid images are accepted for damage/unhealthy claims; invalid
  type, oversized files, excessive count, and path abuse are rejected.
- [ ] Customer A cannot read Customer B's evidence; authenticated staff/admin can.
- [ ] A failed claim transaction removes any files written during that request.

**Verification:** Run focused evidence upload/privacy tests using `Storage::fake('local')`.

**Dependencies:** Task 2.

**Files likely touched:**

- `database/migrations/*_create_return_request_evidence_table.php`
- `app/Models/ReturnRequestEvidence.php`
- `app/Http/Controllers/ReturnRequestController.php`
- `routes/web.php`
- `tests/Feature/ReturnRequestEvidenceTest.php`

**Estimated scope:** Medium, 5 files.

## Checkpoint: Secure Foundation

- [ ] Migrations work on MySQL and in-memory SQLite and roll back cleanly.
- [ ] State transitions reject invalid movement.
- [ ] Evidence never uses the public disk or exposes a raw path.
- [ ] `php artisan test` focused foundation tests pass.

## Phase 2: Customer Claim Slice

### Task 4: Submit an Eligible Multi-Item Claim

**Description:** Build the customer route, form request/service, and mobile-first
claim form linked from eligible order cards. Validate eligibility and quantities
again inside a row-locked transaction.

**Acceptance criteria:**

- [ ] Only the order owner can claim a delivered/completed, non-archived order
  within the configured window.
- [ ] A request can include several lines but cannot exceed each purchased and
  still-claimable quantity, including prior open/approved claims.
- [ ] The created claim, items, and evidence succeed together or roll back together.

**Verification:** Add feature tests for eligible submission, 24-hour boundary,
ownership, invalid state, duplicate/open claim, and over-claiming; run the
focused workflow test and `npm.cmd run build`.

**Dependencies:** Tasks 2-3.

**Files likely touched:**

- `app/Http/Requests/StoreReturnRequest.php`
- `app/Services/ReturnRequestService.php`
- `app/Http/Controllers/ReturnRequestController.php`
- `resources/views/returns/create.blade.php`
- `tests/Feature/ReturnRequestWorkflowTest.php`

**Estimated scope:** Medium, 5 files.

### Task 5: Show Customer Claim Status and Replies

**Description:** Add claim history/detail UI, a status timeline, customer reply
for requested information, and cancellation while the claim is still open.

**Acceptance criteria:**

- [ ] The Orders page shows `Report damaged plant` only when eligible and shows
  an existing claim badge/link otherwise.
- [ ] The owner sees per-line decisions, customer-visible notes, evidence,
  replacement/refund progress, and timestamps.
- [ ] Only `submitted`/`needs_information` claims accept permitted customer
  replies or cancellation; terminal claims remain read-only.

**Verification:** Add authorization/state tests, responsive UI checks, Blade JS
lint, and customer walkthrough at phone width.

**Dependencies:** Task 4.

**Files likely touched:**

- `app/Http/Controllers/ReturnRequestController.php`
- `resources/views/orders.blade.php`
- `resources/views/returns/show.blade.php`
- `routes/web.php`
- `tests/Feature/ReturnRequestWorkflowTest.php`

**Estimated scope:** Medium, 5 files.

## Checkpoint: Customer Flow

- [ ] Customer can submit and track a legitimate multi-item claim end-to-end.
- [ ] Invalid, late, cross-account, duplicate, and excessive claims are blocked.
- [ ] Evidence remains private and the form works in the Android WebView layout.

## Phase 3: Admin Decision and Fulfillment

### Task 6: Build the Admin Claims Queue and Review Screen

**Description:** Add a filterable claims queue and claim detail screen showing
order data, photos, prior claims, current stock, payment/refund totals, and only
the actions allowed for the signed-in role.

**Acceptance criteria:**

- [ ] Admin/staff can filter by status, date, claim/order number, customer, and age.
- [ ] Admin can decide every line and must provide a reason for rejection or
  reduced outcomes; server-calculated price caps are displayed.
- [ ] Staff can request information but cannot forge decision/refund fields.

**Verification:** Add admin/staff access, rendering, validation, and forged-field tests.

**Dependencies:** Tasks 4-5.

**Files likely touched:**

- `app/Http/Controllers/AdminReturnRequestController.php`
- `resources/views/admin/returns/index.blade.php`
- `resources/views/admin/returns/show.blade.php`
- `routes/web.php`
- `tests/Feature/AdminReturnRequestTest.php`

**Estimated scope:** Medium, 5 files.

### Task 7: Fulfill Replacements Through the Stock Ledger

**Description:** Add replacement/resale-restock movement types and service
methods, then connect approved replacement and dispatch actions to the claim.

**Acceptance criteria:**

- [ ] Approval decrements/reserves the approved replacement quantity exactly
  once and records claim number, product, quantity, and actor.
- [ ] Insufficient stock leaves claim state and inventory unchanged with a clear error.
- [ ] Damaged returns add no saleable stock; only explicit inspected restock adds stock.

**Verification:** Extend inventory ledger tests for replacement, idempotency,
insufficient stock, concurrent approval, damaged discard, and explicit restock.

**Dependencies:** Task 6.

**Files likely touched:**

- `app/Models/StockMovement.php`
- `app/Services/InventoryService.php`
- `app/Services/ReturnRequestService.php`
- `app/Http/Controllers/AdminReturnRequestController.php`
- `tests/Feature/ReturnInventoryWorkflowTest.php`

**Estimated scope:** Medium, 5 files.

### Task 8: Record Refunds Without Rewriting Payment History

**Description:** Add an append-only, voidable refund ledger and billing methods,
then expose admin-only refund processing from an approved claim.

**Acceptance criteria:**

- [ ] Refund amount cannot exceed the admin-approved claim amount, affected-line
  server price, or remaining unrefunded payment.
- [ ] Refunds and voids preserve actor, method, reference, reason, and timestamps.
- [ ] Invoice/admin/customer views show paid, refunded, and net amounts without
  deleting or negating original payment rows.

**Verification:** Add focused billing tests for full/partial refunds, cap rules,
duplicate submission, voids, permissions, and legacy `refunded` compatibility.

**Dependencies:** Task 6.

**Files likely touched:**

- `database/migrations/*_create_refunds_table.php`
- `app/Models/Refund.php`
- `app/Services/BillingService.php`
- `app/Http/Controllers/AdminReturnRequestController.php`
- `tests/Feature/ReturnRefundWorkflowTest.php`

**Estimated scope:** Medium, 5 files.

## Checkpoint: Financial and Inventory Integrity

- [ ] Mixed claims can replace some lines and refund others.
- [ ] Stock and refund operations are transactional, capped, auditable, and idempotent.
- [ ] Staff cannot cross the admin financial boundary.
- [ ] Inventory and billing ledger reconciliation tests pass.

## Phase 4: Communication, Audit, and Release

### Task 9: Add Audit, In-App Notifications, and TextBee SMS

**Description:** Audit all meaningful claim changes and notify the correct users.
Use queued notifications and `SendSmsJob` after successful transactions.

**Acceptance criteria:**

- [ ] Submission alerts the team; information request, decision, refund,
  replacement dispatch, and resolution notify the customer.
- [ ] SMS is queued only for a verified phone and contains claim/order number,
  outcome, and a safe internal next step without sensitive photo URLs.
- [ ] Notification failure cannot roll back claim, stock, or refund changes.

**Verification:** Queue/notification fakes prove exact recipients and negative
paths; SMS job tests never contact TextBee.

**Dependencies:** Tasks 6-8.

**Files likely touched:**

- `app/Notifications/ReturnRequestUpdated.php`
- `app/Notifications/WorkCreatedNotice.php` or a focused team notification
- `app/Services/ReturnRequestService.php`
- `app/Http/Controllers/AdminReturnRequestController.php`
- `tests/Feature/ReturnNotificationTest.php`

**Estimated scope:** Medium, 5 files.

### Task 10: Polish, Reconcile, and Stage the Complete Flow

**Description:** Finish accessibility/responsive states, empty/error/loading
copy, operational visibility, and full release verification.

**Acceptance criteria:**

- [ ] Customer/admin screens are keyboard accessible, responsive, and clear for
  empty, late, rejected, awaiting-info, out-of-stock, refunded, and resolved states.
- [ ] Admin can identify aging claims and unresolved approved work; audit entries
  link back to the correct order/claim.
- [ ] No raw storage path, customer-only note, or forbidden action appears for
  the wrong role.

**Verification:** Run focused tests, `composer check`, `npm.cmd run build`, and a
staging walkthrough covering one replacement, one partial refund, one rejection,
private photo access, and actual TextBee delivery.

**Dependencies:** Tasks 1-9.

**Files likely touched:**

- `resources/views/returns/*.blade.php`
- `resources/views/admin/returns/*.blade.php`
- `resources/views/admin/dashboard.blade.php`
- `tests/Feature/ReturnRequestWorkflowTest.php`
- `tests/Feature/AdminReturnRequestTest.php`

**Estimated scope:** Medium, up to 5 files per polish slice.

## Final Definition of Done

- [ ] Approved business policy matches the implemented configuration and UI copy.
- [ ] Original order state/history remains intact throughout a claim.
- [ ] Every claim status change follows the claim state machine under a row lock.
- [ ] Evidence is private and ownership/role tested.
- [ ] Replacement/restock inventory reconciles exactly once.
- [ ] Refund ledger reconciles and never deletes payment history.
- [ ] Permissions, quantities, prices, and refund caps are enforced server-side.
- [ ] Notifications are accurate and asynchronous.
- [ ] `composer check` and `npm.cmd run build` pass.
- [ ] Staging replacement/refund/rejection and handset SMS checks pass.

## Risks and Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Damaged plants are accidentally restocked | High: bad inventory and another poor delivery | Default disposition to `not_required`/`damaged_discard`; require explicit admin restock |
| Customer over-claims across requests | High: excess refund/replacement | Sum prior active/approved quantities under row locks before accepting each line |
| Replacement approval races with another sale | High: negative stock | Use `InventoryService` product locks and fail the whole approval transaction |
| Refund exceeds money received | High: financial loss | Cap by approved line value and remaining unrefunded payment inside `BillingService` |
| Duplicate submit/retry repeats stock or refund | High | State checks, idempotency keys/unique claim references, and transactional ledger writes |
| Evidence is publicly exposed | High: customer privacy breach | Private disk plus explicit owner/staff stream authorization |
| Live-plant condition is disputed | Medium | Short claim window, required photos, delivery proof context, and recorded admin reasoning |
| SMS provider/queue is unavailable | Medium | Preserve in-app history; retry asynchronously; never roll back decisions |
| Feature makes the order state machine brittle | Medium | Keep claim lifecycle in separate models and services |

## Open Questions

- Approve or change the five policy defaults in
  `tasks/returns-replacements-spec.md` before implementation.
- Decide later whether version 2 should add automated courier pickup scheduling,
  store credit, or native Android return screens; none belong in version 1.

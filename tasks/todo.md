# Damaged Plant Returns and Replacements Checklist

## Policy Gate

- [x] Approve a 24-hour claim window (or choose 48 hours).
- [x] Approve photo evidence: 1-5 images, 5 MiB each.
- [x] Approve no physical return unless admin requests it.
- [x] Approve free delivery for an approved replacement.
- [x] Approve admin-only decisions/refunds and staff-only approved logistics.
- [x] Approve manual refund methods: cash, GCash, bank transfer, other.

## Phase 1: Secure Foundation

- [x] Add return-request and return-request-item migrations/models.
- [x] Implement and test the independent claim state machine.
- [x] Add private return-evidence migration/model/storage.
- [x] Add ownership/role-checked evidence streaming.
- [x] Prove invalid files, excessive uploads, and cross-account access are blocked.

## Checkpoint 1

- [x] SQLite/MySQL migrations and rollback are safe.
- [x] State and evidence tests pass.
- [x] No evidence is written to the public disk.

## Phase 2: Customer Flow

- [x] Add eligible-order Report damaged plant action.
- [x] Build multi-item/quantity claim form with issue and preferred resolution.
- [x] Enforce status, ownership, window, and remaining-quantity rules under locks.
- [x] Save claim, lines, and evidence atomically with file cleanup on failure.
- [x] Add claim detail/timeline, information reply, and permitted cancellation.
- [ ] Verify mobile browser and Android WebView layouts.

## Checkpoint 2

- [x] Eligible customer can submit and track a claim.
- [x] Late, duplicate, excessive, invalid-state, and cross-account claims fail safely.

## Phase 3: Admin Review

- [x] Add filterable admin/staff claims queue.
- [x] Add claim detail with evidence, prior claims, stock, and billing context.
- [x] Add request-information workflow.
- [x] Add per-line admin decisions and required decision reasons.
- [x] Prohibit staff from decision and financial fields.

## Phase 4: Replacement Integrity

- [x] Add replacement stock movement/service method.
- [x] Decrement/reserve replacement stock exactly once on approval.
- [x] Fail cleanly on insufficient stock or concurrent approval.
- [x] Add replacement dispatch and resolution tracking.
- [x] Never auto-restock damaged plants.
- [x] Allow explicit inspected restock with claim reference.

## Phase 5: Refund Integrity

- [x] Add append-only, voidable refund ledger.
- [x] Cap refunds by approved value and remaining unrefunded payment.
- [x] Support full and partial refunds without negative payment rows.
- [x] Show paid, refunded, and net amounts on admin/customer billing views.
- [x] Test legacy refunded orders and duplicate submissions.

## Checkpoint 3

- [x] Mixed replacement/refund claims work.
- [x] Stock ledger reconciles exactly.
- [x] Refund ledger reconciles exactly.
- [x] Staff cannot cross admin financial permissions.

## Phase 6: Communication and Audit

- [x] Audit submission, review, decision, stock, refund, dispatch, and resolution.
- [x] Notify team in-app on submission.
- [x] Notify customer in-app on every meaningful update.
- [x] Queue TextBee SMS for verified phones on decision/refund/dispatch/resolution.
- [x] Prove notification failures cannot undo saved business transactions.

## Release Gate

- [x] Focused return, evidence, inventory, refund, permission, and notification tests pass.
- [x] `composer check` passes.
- [x] `npm.cmd run build` passes, including Blade JavaScript lint.
- [ ] Staging replacement flow passes.
- [ ] Staging partial-refund flow passes.
- [ ] Staging rejection flow passes.
- [x] Private evidence access is verified for owner, stranger, staff, and guest.
- [ ] A real handset receives the correct TextBee claim SMS.
- [ ] Queue worker and failed-job monitoring are active in production.

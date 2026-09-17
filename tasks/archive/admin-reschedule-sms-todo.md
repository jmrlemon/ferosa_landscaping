# Admin Reschedule SMS Notification Checklist

## Phase 1: Message Contract

- [x] Add one reusable formatter for the staff/admin reschedule SMS.
- [x] Include Ferosa, service, old schedule, new schedule, and contact instruction.
- [x] Add exact-content and fallback-service tests.

## Phase 2: Successful Admin Flow

- [x] Dispatch one `SendSmsJob` after a successful staff/admin reschedule.
- [x] Send only to a non-empty, phone-verified customer number.
- [x] Preserve the existing database notification and audit log.
- [x] Report dispatch exceptions without undoing the appointment change.
- [x] Make the admin success message accurately state whether SMS was queued.

## Checkpoint: Core Flow

- [x] `php artisan test tests/Feature/StaffRescheduleTest.php` passes.
- [x] Successful move saves the slot, preserves status, audits once, notifies in-app, and queues one SMS.

## Phase 3: Rejection and Fallback Coverage

- [x] Assert no SMS for same, invalid, past, occupied, completed, archived-for-staff, or unauthorized moves.
- [x] Assert no SMS for a missing or unverified phone.
- [x] Assert the in-app notification still works when SMS is unavailable.
- [x] Assert customer self-rescheduling does not send the admin-move SMS.

## Phase 4: Delivery Verification

- [x] Test `SendSmsJob` with `FakeSmsService`; never call real TextBee in tests.
- [x] Verify provider rejection triggers the existing job failure/retry behavior.
- [x] Run focused SMS and reschedule tests.
- [x] Run `composer check`.
- [x] Run `npm run build`.
- [x] Confirm staging uses `SMS_DRIVER=textbee` with valid device ID and API key.
- [x] Confirm `php artisan queue:work` is continuously running.
- [ ] Perform one staging reschedule and verify the handset receives the correct message.

## Definition of Done

- [x] Every successful admin/staff reschedule sends an in-app notification and queues an SMS when a verified phone exists.
- [x] No rejected or unauthorized reschedule queues an SMS.
- [x] Provider outages cannot break or roll back appointment rescheduling.
- [x] Automated checks pass.
- [ ] One real staging handset delivery is confirmed.

# Implementation Plan: Admin Reschedule SMS Notification

## Overview

When an admin or staff member successfully reschedules a customer's upcoming
appointment, Ferosa will continue creating the existing in-app notification and
will also automatically queue a TextBee SMS to the customer's verified phone
number. The SMS will identify the service and show both the previous and new
appointment date/time. Validation failures, authorization failures, unchanged
times, and occupied slots must never queue an SMS.

No database migration or new provider integration is required. The application
already has `SmsService`, TextBee configuration, `SendSmsJob`, a database queue,
and `AppointmentMovedByTeam`. The work is an extension of the existing admin
reschedule success path.

## Dependency Graph

```text
SMS message contract and tests
            |
            v
Admin reschedule success path
            |
            +--> existing in-app notification
            |
            +--> queued SendSmsJob --> SmsService --> TextBee
            |
            v
Admin feedback and failure observability
            |
            v
Focused and full regression verification
```

## Architecture Decisions

- Reuse `SendSmsJob` and `SmsService` rather than calling TextBee from the
  controller. This keeps the admin request fast and gives SMS delivery the
  job's existing three attempts and 30-second retry delay.
- Build the final SMS text while processing the successful reschedule, then
  pass that immutable text into the job. If the appointment is moved again
  before the worker runs, the first queued message must still describe the
  first change accurately.
- Keep the existing queued database notification. SMS is an additional channel,
  not a replacement for the customer's in-app history.
- Send only when the appointment update has succeeded and the customer has a
  non-empty, phone-verified number. A missing or unverified number leaves the
  in-app notification intact and must not fail or roll back the reschedule.
- The admin success message must say the SMS was queued only when a job was
  actually dispatched. Otherwise it should clearly say the customer was
  notified in-app but has no verified SMS number.
- Do not send from the customer self-reschedule route. This feature is limited
  to changes initiated through the staff/admin route.
- Keep TextBee errors asynchronous. The queue worker records exhausted jobs in
  Laravel's failed-job mechanism; an external provider outage must not undo an
  appointment that was already successfully rescheduled.

## Message Contract

Proposed message:

```text
Ferosa: Your Lawn Care appointment was rescheduled from Sep 18, 2026 9:00 AM to Sep 20, 2026 1:00 PM. Contact us if this new schedule does not work for you.
```

The final message must contain:

- Ferosa identification.
- Service name, with `Service` as the safe fallback.
- Previous appointment date and time.
- New appointment date and time.
- A short instruction to contact Ferosa if the new schedule is unsuitable.

Dates must use the application's configured timezone and an unambiguous format
that includes the year.

## Task 1: Define and Test the Reschedule SMS Contract

**Description:** Add a single reusable formatter for the team-moved appointment
message, preferably beside `AppointmentMovedByTeam`, so the database and SMS
messages cannot drift into conflicting old/new schedules.

**Acceptance criteria:**

- [ ] The SMS contains the service, previous schedule, new schedule, and contact instruction.
- [ ] The previous and new values are captured before queue dispatch and do not depend on later model changes.
- [ ] Missing service data uses the existing `Service` fallback without throwing.

**Verification:**

- [ ] Add a focused test for exact message content and date formatting.
- [ ] Run `php artisan test tests/Feature/StaffRescheduleTest.php`.

**Dependencies:** None.

**Files likely touched:**

- `app/Notifications/AppointmentMovedByTeam.php`
- `tests/Feature/StaffRescheduleTest.php`

**Estimated scope:** Small, 2 files.

## Task 2: Queue SMS After a Successful Admin Reschedule

**Description:** Extend `AdminController::rescheduleAppointment()` so its
successful path queues one `SendSmsJob` for the verified customer phone number,
using the message contract from Task 1. Keep the existing audit and database
notification behavior.

**Acceptance criteria:**

- [ ] A valid staff/admin reschedule queues exactly one SMS job to the customer's canonical phone number.
- [ ] The job is queued only after the appointment update succeeds.
- [ ] The existing `AppointmentMovedByTeam` database notification and audit log are still created.
- [ ] An SMS dispatch exception is reported but does not revert the completed reschedule or produce a 500 response.

**Verification:**

- [ ] Queue-fake assertion proves `SendSmsJob` is dispatched once on success.
- [ ] The focused reschedule test proves the saved appointment and audit remain correct.
- [ ] Run `php artisan test tests/Feature/StaffRescheduleTest.php`.

**Dependencies:** Task 1.

**Files likely touched:**

- `app/Http/Controllers/AdminController.php`
- `app/Jobs/SendSmsJob.php` only if a small read-only accessor is needed for test assertions
- `tests/Feature/StaffRescheduleTest.php`

**Estimated scope:** Medium, 2-3 files.

## Checkpoint: Successful Delivery Path

- [ ] Admin/staff can move a scheduled or confirmed appointment.
- [ ] The database stores the new slot and retains the appointment status.
- [ ] One in-app notification and one SMS job are produced.
- [ ] The message accurately contains both old and new schedules.

## Task 3: Protect Negative and Fallback Paths

**Description:** Extend the reschedule feature tests to prove that SMS is never
queued for rejected changes and that customers without a verified phone still
receive the existing in-app notification without breaking the admin workflow.

**Acceptance criteria:**

- [ ] Same-time, invalid-time, past-time, occupied-slot, completed, archived-for-staff, and unauthorized requests queue no SMS.
- [ ] A missing or unverified customer phone queues no SMS and does not block the reschedule.
- [ ] Admin feedback distinguishes `SMS queued` from `in-app notification only`.
- [ ] Customer self-rescheduling does not trigger this admin-initiated SMS message.

**Verification:**

- [ ] Add queue assertions to the existing rejection tests.
- [ ] Add a focused missing/unverified-phone fallback test.
- [ ] Run `php artisan test tests/Feature/StaffRescheduleTest.php` and the customer appointment reschedule tests.

**Dependencies:** Task 2.

**Files likely touched:**

- `app/Http/Controllers/AdminController.php`
- `tests/Feature/StaffRescheduleTest.php`
- The existing customer-reschedule feature test, only if needed for an explicit regression assertion

**Estimated scope:** Medium, 2-3 files.

## Task 4: Verify TextBee Delivery and Operations

**Description:** Prove that the queued job hands the exact phone number and
message to `SmsService`, and document/verify the operational requirement that
the queue worker is running in production.

**Acceptance criteria:**

- [ ] The job calls `SmsService` with the expected recipient and message.
- [ ] A provider rejection makes the job fail so Laravel retries it using the existing retry policy.
- [ ] No test contacts the real TextBee service.
- [ ] The deployment checklist confirms `SMS_DRIVER=textbee`, both TextBee credentials, and an active queue worker.

**Verification:**

- [ ] Add or extend a job test using `FakeSmsService`.
- [ ] Run the focused SMS/job and reschedule tests.
- [ ] Run `composer check` from `ferosa-laravel/`.
- [ ] Run `npm run build` because the repository requires Blade JavaScript validation in the production build.
- [ ] In a configured staging environment, reschedule one test appointment and confirm the handset receives the correct old/new schedule.

**Dependencies:** Tasks 1-3.

**Files likely touched:**

- `tests/Feature/StaffRescheduleTest.php` or a focused `tests/Feature/SendSmsJobTest.php`
- Deployment documentation only if the existing queue/TextBee checklist is not sufficient

**Estimated scope:** Small, 1-2 files.

## Checkpoint: Complete

- [ ] Successful admin reschedules produce both in-app and queued SMS notifications.
- [ ] Rejected reschedules produce neither SMS nor a misleading success message.
- [ ] Missing/unverified phone numbers degrade safely to in-app notification.
- [ ] TextBee failures retry asynchronously and do not undo appointment changes.
- [ ] Focused tests, `composer check`, and `npm run build` pass.
- [ ] Manual staging SMS has been received and its old/new schedule is correct.

## Risks and Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Queue worker is stopped | SMS stays queued and the customer is not notified promptly | Keep the production queue worker supervised and monitor failed/pending jobs |
| TextBee device or credentials are unavailable | Delivery retries and may ultimately fail | Reuse job retries, log/report failures, and inspect failed jobs operationally |
| SMS is queued before a failed appointment update | Customer receives a false schedule | Dispatch only from the successful update path, after persistence |
| Appointment is moved multiple times before jobs run | An older job could describe the wrong change | Queue immutable rendered text containing the captured old/new values |
| Customer has no verified phone | SMS could go to an untrusted or missing number | Require a non-empty verified number and retain the in-app notification fallback |
| Provider accepts an SMS but the HTTP response is lost | A retry can create a duplicate SMS | Keep the text idempotent and operationally monitor retries; exact-once SMS delivery cannot be guaranteed by the provider boundary |
| Long SMS is split into multiple billable segments | Higher cost and fragmented delivery | Keep the template concise and test its representative length |

## Open Questions

- No blocking product question is required for implementation. The first version
  will use the proposed concise message and will not add a reschedule-reason
  field. A reason can be introduced later as a separate audited feature if the
  business wants admins to provide one.

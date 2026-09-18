# Spec: Estimator-Gated Consultation Booking

## Objective

Make the Cost Estimator the required first step for every new customer
appointment. `Book Consultation` transfers a server-verified estimate into the
Schedule page, which displays the estimate instead of asking the customer to
choose a service again.

## Decisions and Assumptions

- The project estimate is indicative information, not the appointment bill.
- The appointment keeps the mapped consultation service's existing starting fee.
- Project types map to active services as follows:
  - Garden Design -> Garden Design Consultation
  - Maintenance -> Routine Maintenance
  - Hardscaping -> Hardscaping Quote
- Existing appointment rescheduling keeps its current service and does not
  require a new estimate.
- A prepared estimate is valid for 24 hours and is consumed only after a new
  appointment is successfully created.

## Data Contract

The server recalculates and snapshots:

- project type and label;
- property size in square metres;
- quality tier and label;
- selected add-ons with configured prices;
- selected active products with database prices and quantities;
- base, add-on, product, total, and typical-range amounts;
- mapped service type and preparation time.

No client-posted total, price, service id, or label is trusted.

## Customer Flow

1. A customer opens Cost Estimator and chooses the project details.
2. `Book Consultation` posts only the selections for server validation and
   calculation.
3. The server stores the prepared estimate in that customer's session and
   redirects to Schedule.
4. Schedule shows a read-only estimate summary, date picker, time slots, and
   optional customer notes. The service dropdown is removed.
5. Booking uses the service mapped by the stored estimate, persists the estimate
   snapshot on the appointment, and clears the session draft after success.
6. Direct new-booking access without a valid estimate redirects to Cost Estimator.

## Security Boundaries

- Always validate project type, tier, size, add-ons, product ids, and quantities.
- Always recalculate prices from `config/estimator.php` and current product rows.
- Require an authenticated, unexpired server session draft for new bookings.
- Ignore client attempts to choose a different service for the booking.
- Preserve existing ownership and state checks for rescheduling.
- Never change appointment billing to the indicative estimate automatically.

## Commands and Project Structure

- Focused tests: `php artisan test tests/Feature/EstimatorBookingFlowTest.php`
- Full gate: `composer check`
- Controllers/services: `app/Http/Controllers`, `app/Services`
- Models/migrations: `app/Models`, `database/migrations`
- UI: `resources/views/estimator.blade.php`, `resources/views/schedule.blade.php`
- Tests: `tests/Feature`

## Success Criteria

- New Schedule access is gated by a prepared estimate on both GET and POST.
- The Schedule page has no customer-editable service selector.
- The displayed and persisted estimate is server-calculated.
- The mapped active service drives availability and appointment creation.
- Consultation fee remains separate from the estimate total.
- Rescheduling remains functional without creating another estimate.
- Relevant tests and the complete project quality gate pass.

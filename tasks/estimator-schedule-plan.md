# Plan: Connect Cost Estimator to Schedule

1. Add failing feature tests for estimator preparation, direct-access gating,
   read-only Schedule summary, server-side price/service integrity, appointment
   persistence, and rescheduling compatibility.
2. Add a focused quote service that validates normalized selections, resolves
   current products/services, and calculates the server-owned snapshot.
3. Add the estimator preparation endpoint and 24-hour session draft lifecycle.
4. Persist an optional JSON estimate snapshot on appointments through a
   reversible migration and model cast.
5. Replace the Schedule service dropdown with an accessible, responsive estimate
   summary and retain a server-derived hidden value only for availability reads.
6. Update existing booking tests to prepare a valid session draft, run focused
   regression suites, apply the local migration, and run `composer check`.
7. Review authorization, tampering, stale-data, financial, UI, and performance
   boundaries; commit the verified local change without deploying it.

# PWD and Senior Discount Request Workflow

## Overview
Let customers declare Senior Citizen or PWD status during eligible product checkout or service booking, submit an ID image for review, and let admins approve or reject the request. Approval applies a discount only to catalogue lines or appointment services configured as legally eligible.

## Architecture decisions
- Keep `DiscountApplication` as the financial record created only after approval; store the customer declaration and private evidence in a separate polymorphic `DiscountRequest`.
- Store evidence on the existing private `local` disk and serve it only through an admin-authorized route.
- Keep catalogue/service eligibility and Philippine tax discount switches fail-closed; new service types and existing catalogue rows default to no eligibility.
- Require COD for an order with a pending discount request so the submitted amount cannot be paid before the admin recalculates the invoice.
- Do not apply BNPC discounts to appointments; only an explicitly eligible statutory service can qualify.

## Implementation slices
1. Add request/evidence persistence, secure evidence viewing, and admin approve/reject service behavior.
2. Add appointment eligibility configuration and customer declaration fields to order checkout and eligible appointment scheduling.
3. Show pending/approved/rejected state and approved VAT/discount breakdown in customer and admin views.

## Acceptance criteria
- A customer can request Senior/PWD status only when the cart or service is configured for an enabled scheme; otherwise no identity evidence is collected.
- A request never changes a total until an admin verifies it; admin approval recalculates only eligible lines and records the verifier.
- Evidence is stored privately, customer ownership is enforced, and only admins can view it.
- Pending discount orders cannot submit GCash payment at the undiscounted total.
- Invalid proof, unsupported service eligibility, payment already recorded, and unauthorized review attempts cannot apply a discount.

## Risks
- Statutory coverage and VAT registration are business-specific. Mitigation: preserve default-disabled schemes and `none` eligibility until the business confirms its obligations.
- ID images are sensitive personal data. Mitigation: private storage, strict admin access, limited file types/size, and no public URL.

{{-- Shared admin type scale. Included by admin.partials.premium-theme (all
     workspace pages) and directly by admin/dashboard.blade.php, which renders
     its own <head>.

     The admin UI leans hard on Tailwind's smallest sizes - table bodies inherit
     `text-xs` (12px) and badges/labels use arbitrary 9-11px values, which is
     below comfortable reading size. These rules lift the whole scale. Adjust the
     numbers here and every admin screen follows. --}}
<style id="ferosa-admin-type-scale">
  .text-\[8px\]  { font-size: 11px !important; }
  .text-\[9px\]  { font-size: 12px !important; }
  .text-\[10px\] { font-size: 13px !important; }
  .text-\[11px\] { font-size: 13.5px !important; }
  .text-\[12px\] { font-size: 14.5px !important; }
  .text-\[13px\] { font-size: 15px !important; }
  .text-xs       { font-size: 14.5px !important; line-height: 1.35 !important; }
  .text-sm       { font-size: 16px !important;   line-height: 1.5 !important; }

  /* Table bodies inherit their size from `table.text-xs`, so most row content
     (customer name, service, date) has no size class of its own. */
  table.text-xs,
  table.text-xs td { font-size: 15px !important; line-height: 1.4 !important; }

  /* Column headers use wide letter-spacing at 10px, which reads smaller than
     its nominal size. */
  thead .text-\[10px\],
  thead .text-\[11px\],
  thead tr { font-size: 13px !important; }

  /* Status / payment pills sit inside rows - keep them a step below body text
     so they still read as secondary. */
  .admin-status-badge  { font-size: 13px !important; }
  .admin-payment-badge { font-size: 11.5px !important; }

  /* Fixed-size circles: avatar initials and sidebar count badges must not
     outgrow their container. */
  .h-7.w-7.text-\[10px\]        { font-size: 12px !important; }
  .min-w-\[18px\].text-\[10px\] { font-size: 11px !important; }

  /* Header count badges (unread messages, unread notifications) were 8px - the
     smallest text on the page was the number saying how much needs attention.
     The circle has to grow with the digits or "9+" clips. */
  .text-\[8px\].min-w-\[13px\] {
    min-width: 17px !important;
    height: 17px !important;
  }

  /* Larger body text needs a little more vertical room in dense tables. */
  table td { padding-top: .85rem !important; padding-bottom: .85rem !important; }
</style>

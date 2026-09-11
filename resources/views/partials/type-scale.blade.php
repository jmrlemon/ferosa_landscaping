{{-- Customer-facing type scale. Included by layouts/customer.blade.php, which
     covers every customer screen plus partials.mobile-bottom-customer.

     The admin workspace has had admin.partials.type-scale for a while; the
     customer side never got an equivalent, so its arbitrary 8-11px values
     shipped at face value. iOS HIG asks for 11pt minimum and Material for 12sp,
     and this is a landscaping business whose customers skew older.

     Deliberately conservative: this lifts only the tiers below the platform
     minimum and leaves `text-xs` (12px) alone. Tailwind's own scale starts
     there, customer pages lean on it heavily, and rescaling it would reflow
     every card and marketing block rather than just making small text legible.
     Adjust the numbers here and every customer screen follows. --}}
<style id="ferosa-customer-type-scale">
  .text-\[8px\]  { font-size: 11px !important; }
  .text-\[9px\]  { font-size: 11.5px !important; }
  .text-\[10px\] { font-size: 12px !important; }
  .text-\[11px\] { font-size: 12.5px !important; }

  /* Count badges are fixed-size circles, so the digits and the circle have to
     grow together or "9+" clips. These are the unread/cart/pending counters in
     the header and the mobile bottom bar. */
  .text-\[8px\].min-w-\[13px\],
  .text-\[8px\].min-w-\[15px\] {
    min-width: 17px !important;
    height: 17px !important;
  }

  .text-\[8px\].min-w-\[16px\] {
    min-width: 18px !important;
    height: 18px !important;
  }
</style>

<style id="ferosa-a11y-focus">
  :focus-visible {
    outline: 3px solid rgba(52, 127, 87, .32);
    outline-offset: 3px;
  }
  a:focus-visible, button:focus-visible, select:focus-visible, input:focus-visible, textarea:focus-visible, [tabindex]:focus-visible {
    outline: 3px solid rgba(52, 127, 87, .32);
    outline-offset: 3px;
  }
  .skip-link {
    position: fixed;
    top: .75rem;
    left: .75rem;
    z-index: 9999;
    transform: translateY(-160%);
    border-radius: .75rem;
    background: #123426;
    color: #fff;
    padding: .75rem 1rem;
    font-size: .875rem;
    font-weight: 700;
    box-shadow: 0 10px 28px rgba(18,52,38,.2);
    transition: transform .18s ease;
  }
  .skip-link:focus {
    transform: translateY(0);
  }
</style>

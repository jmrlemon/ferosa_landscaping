<script>
  (function () {
    const section = document.getElementById('estimator-coverage-section');
    const categoryInput = document.getElementById('category-input');
    const fields = document.getElementById('estimator-coverage-fields');
    const unavailable = document.getElementById('estimator-coverage-unavailable');
    if (!section || !categoryInput || !fields || !unavailable) {
      return;
    }

    const allowedCategories = JSON.parse(section.dataset.allowedCategories || '[]');
    const editable = section.dataset.editable === 'true';

    function updateEstimatorCoverageAvailability() {
      const category = categoryInput.value.trim().toLowerCase();
      const available = allowedCategories.includes(category);
      fields.disabled = !editable || !available;
      fields.classList.toggle('opacity-50', !available);
      unavailable.hidden = available;
    }

    categoryInput.addEventListener('input', updateEstimatorCoverageAvailability);
    categoryInput.addEventListener('change', updateEstimatorCoverageAvailability);
    updateEstimatorCoverageAvailability();
  })();
</script>

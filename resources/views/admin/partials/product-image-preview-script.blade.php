<script>
  (function () {
    const input = document.getElementById('product-image-input');
    const preview = document.getElementById('product-image-preview');
    const placeholder = document.getElementById('product-image-preview-placeholder');
    const status = document.getElementById('product-image-preview-status');
    if (!input || !preview || !placeholder || !status) {
      return;
    }

    const currentImageUrl = preview.dataset.currentSrc || '';
    const currentImageAlt = preview.dataset.currentAlt || '';
    let previewUrl = null;

    function revokePreviewUrl() {
      if (previewUrl) {
        URL.revokeObjectURL(previewUrl);
        previewUrl = null;
      }
    }

    function showPlaceholder(message) {
      preview.removeAttribute('src');
      preview.alt = '';
      preview.classList.add('hidden');
      preview.setAttribute('aria-hidden', 'true');
      placeholder.classList.remove('hidden');
      status.textContent = message;
    }

    function showCurrentImage(message) {
      if (!currentImageUrl) {
        showPlaceholder(message);
        return;
      }

      preview.src = currentImageUrl;
      preview.alt = currentImageAlt;
      preview.classList.remove('hidden');
      preview.setAttribute('aria-hidden', 'false');
      placeholder.classList.add('hidden');
      status.textContent = message;
    }

    input.addEventListener('change', function () {
      const file = input.files && input.files[0];
      revokePreviewUrl();

      if (!file) {
        showCurrentImage(currentImageUrl
          ? 'No replacement selected. The current product image will be kept.'
          : 'No product image selected.');
        return;
      }

      previewUrl = URL.createObjectURL(file);
      preview.src = previewUrl;
      preview.alt = 'Selected product image preview';
      preview.classList.remove('hidden');
      preview.setAttribute('aria-hidden', 'false');
      placeholder.classList.add('hidden');
      status.textContent = 'Previewing selected product image.';
    });

    preview.addEventListener('error', function () {
      const selectedFileFailed = previewUrl !== null;
      revokePreviewUrl();

      if (selectedFileFailed && currentImageUrl) {
        showCurrentImage('The selected file could not be previewed. The current product image will be kept.');
        return;
      }

      showPlaceholder(selectedFileFailed
        ? 'The selected file could not be previewed. Please choose another image.'
        : 'The saved product image is unavailable. Choose a replacement image.');
    });

    if (currentImageUrl && preview.complete && preview.naturalWidth === 0) {
      showPlaceholder('The saved product image is unavailable. Choose a replacement image.');
    }

    window.addEventListener('pagehide', revokePreviewUrl);
  })();
</script>

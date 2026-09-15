<script>
  (function () {
    const form = document.getElementById('ar-model-upload-form');
    const input = document.getElementById('ar-model-input');
    const fileStatus = document.getElementById('ar-model-file-status');
    const height = document.getElementById('ar-model-height');
    const heightConversion = document.getElementById('ar-model-height-conversion');
    const cancel = document.getElementById('ar-model-upload-cancel');
    const progressWrap = document.getElementById('ar-model-upload-progress-wrap');
    const progress = document.getElementById('ar-model-upload-progress');
    const percent = document.getElementById('ar-model-upload-percent');
    if (!form || !input || !fileStatus || !height || !heightConversion ||
        !cancel || !progressWrap || !progress || !percent) return;

    const recommendedBytes = Number(input.dataset.recommendedBytes);
    const maxBytes = Number(input.dataset.maxBytes);
    let activeUpload = null;

    function formatMebibytes(bytes) {
      return (bytes / 1024 / 1024).toFixed(2) + ' MiB';
    }

    function inspectFile() {
      const file = input.files && input.files[0];
      input.setCustomValidity('');
      input.removeAttribute('aria-invalid');
      fileStatus.className = 'mt-2 block text-xs font-semibold text-surface-600';

      if (!file) {
        fileStatus.textContent = input.required
          ? 'Choose a GLB model to inspect its download size.'
          : 'No replacement selected. The current model will be kept.';
        return;
      }

      if (!file.name.toLowerCase().endsWith('.glb')) {
        input.setCustomValidity('Choose a self-contained GLB file.');
        input.setAttribute('aria-invalid', 'true');
        fileStatus.className = 'mt-2 block text-xs font-semibold text-red-700';
        fileStatus.textContent = file.name + ' is not a GLB file.';
        return;
      }

      if (file.size > maxBytes) {
        input.setCustomValidity('This GLB exceeds the 20 MiB mobile AR limit.');
        input.setAttribute('aria-invalid', 'true');
        fileStatus.className = 'mt-2 block text-xs font-semibold text-red-700';
        fileStatus.textContent = file.name + ' is ' + formatMebibytes(file.size) +
          '. The maximum is 20 MiB; optimize it before uploading.';
        return;
      }

      if (file.size > recommendedBytes) {
        fileStatus.className = 'mt-2 block text-xs font-semibold text-amber-700';
        fileStatus.textContent = file.name + ' is ' + formatMebibytes(file.size) +
          '. It can be uploaded, but exceeds the recommended 8 MiB budget and requires phone review.';
        return;
      }

      fileStatus.className = 'mt-2 block text-xs font-semibold text-brand-700';
      fileStatus.textContent = file.name + ' is ' + formatMebibytes(file.size) +
        ' and is inside the recommended download-size budget.';
    }

    function updateHeightConversion() {
      const centimetres = Number(height.value);
      heightConversion.textContent = Number.isFinite(centimetres) && centimetres > 0
        ? 'Displayed AR height: ' + (centimetres / 100).toFixed(2) + ' metres.'
        : 'Enter the real measured height of the object.';
    }

    function setUploading(uploading) {
      form.setAttribute('aria-busy', String(uploading));
      form.querySelectorAll('button[type="submit"]').forEach(function (button) {
        button.disabled = uploading;
      });
      cancel.hidden = !uploading;
      progressWrap.hidden = !uploading;
    }

    input.addEventListener('change', inspectFile);
    height.addEventListener('input', updateHeightConversion);
    updateHeightConversion();

    form.addEventListener('submit', function (event) {
      if (activeUpload) {
        event.preventDefault();
        return;
      }

      inspectFile();
      if (!form.checkValidity()) return;
      event.preventDefault();

      const file = input.files && input.files[0];
      const xhr = new XMLHttpRequest();
      activeUpload = xhr;
      setUploading(true);
      progress.value = 0;
      percent.textContent = '0%';
      fileStatus.textContent = file
        ? 'Uploading ' + file.name + ' securely…'
        : 'Updating the real-world height…';

      xhr.open('POST', form.action);
      xhr.responseType = 'json';
      xhr.setRequestHeader('Accept', 'application/json');
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.upload.addEventListener('progress', function (uploadEvent) {
        if (!uploadEvent.lengthComputable) return;
        const value = Math.min(100, Math.round(uploadEvent.loaded / uploadEvent.total * 100));
        progress.value = value;
        progress.textContent = value + '%';
        percent.textContent = value + '%';
      });
      xhr.upload.addEventListener('load', function () {
        cancel.hidden = true;
        fileStatus.textContent = 'Upload received. Checking the model…';
      });
      xhr.addEventListener('load', function () {
        activeUpload = null;
        if (xhr.status >= 200 && xhr.status < 300 && xhr.response?.redirect_url) {
          fileStatus.textContent = 'Upload received. Checking the model…';
          window.location.assign(xhr.response.redirect_url);
          return;
        }

        setUploading(false);
        fileStatus.className = 'mt-2 block text-xs font-semibold text-red-700';
        const validationMessages = Object.values(xhr.response?.errors || {}).flat();
        const responseMessage = validationMessages.find(function (message) {
          return typeof message === 'string' && message.trim() !== '';
        });

        if (xhr.status === 422 && responseMessage) {
          input.setAttribute('aria-invalid', 'true');
          fileStatus.textContent = responseMessage;
          return;
        }

        fileStatus.textContent = xhr.response?.message ||
          'The upload could not be completed. Check your connection and try again.';
      });
      xhr.addEventListener('error', function () {
        activeUpload = null;
        setUploading(false);
        fileStatus.className = 'mt-2 block text-xs font-semibold text-red-700';
        fileStatus.textContent = 'The upload connection failed. The current model was not changed.';
      });
      xhr.addEventListener('abort', function () {
        activeUpload = null;
        setUploading(false);
        fileStatus.textContent = 'Upload cancelled on this device. Refresh before retrying if it may have reached the server.';
      });
      xhr.send(new FormData(form));
    });

    cancel.addEventListener('click', function () {
      activeUpload?.abort();
    });
  })();
</script>

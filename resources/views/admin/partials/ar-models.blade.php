{{-- Expects $product with the plantModel relation loaded. --}}
<section class="overflow-hidden rounded-xl border border-surface-100 bg-white shadow-sm">
  <div class="border-b border-surface-200 px-5 py-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-2">
        <svg class="h-5 w-5 text-brand-600" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/>
        </svg>
        <h3 class="font-semibold text-surface-900">AR 3D Model</h3>
      </div>
      <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $product->plantModel ? 'bg-brand-50 text-brand-700' : 'bg-amber-50 text-amber-700' }}">
        {{ $product->plantModel ? 'AR Enabled' : 'No model uploaded' }}
      </span>
    </div>
  </div>

  <div class="space-y-4 p-5">
    <div class="rounded-lg border border-surface-100 bg-surface-50 p-4">
      <p class="text-sm font-semibold text-surface-800">Android AR visualizer</p>
      <p class="mt-1 text-xs leading-5 text-surface-500">Upload one self-contained GLB model and enter its real-world height. After uploading, this product will appear in the Android AR catalog.</p>
    </div>

    @if($product->plantModel)
      <div class="grid grid-cols-1 gap-3 rounded-lg border border-brand-100 bg-brand-50/50 p-4 sm:grid-cols-3">
        <div class="sm:col-span-2">
          <p class="text-[10px] font-semibold uppercase tracking-wide text-surface-400">Current model</p>
          <p class="mt-1 break-all text-sm font-semibold text-surface-800">{{ $product->plantModel->file_name }}</p>
          <p class="mt-1 text-xs text-surface-500">{{ number_format($product->plantModel->file_size / 1024 / 1024, 2) }} MB</p>
        </div>
        <div>
          <p class="text-[10px] font-semibold uppercase tracking-wide text-surface-400">Real height</p>
          <p class="mt-1 text-sm font-semibold text-surface-800">{{ $product->plantModel->height_cm }} cm</p>
        </div>
      </div>
    @endif

    <form method="POST" action="{{ route('admin.ar-models.upload', $product) }}" enctype="multipart/form-data" class="space-y-4">
      @csrf
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <label class="block text-sm font-medium text-surface-800">
          {{ $product->plantModel ? 'Replace 3D Model (optional)' : '3D Model File *' }}
          <input name="ar_model"
                 type="file"
                 accept=".glb,model/gltf-binary"
                 {{ $product->plantModel ? '' : 'required' }}
                 class="mt-2 h-10 w-full rounded-lg border border-surface-200 bg-white text-sm text-surface-600 file:mr-3 file:h-full file:border-0 file:bg-surface-100 file:px-3 file:text-sm file:text-surface-700">
          <span class="mt-1 block text-xs font-normal text-surface-400">Accepted: self-contained .glb, up to 100 MB, with visible mesh geometry. Export with Y up; Ferosa aligns the model bottom to the ground.</span>
        </label>

        <label class="block text-sm font-medium text-surface-800">
          Real-world Height (cm) *
          <input name="height_cm"
                 type="number"
                 step="0.1"
                 min="1"
                 max="500"
                 required
                 value="{{ old('height_cm', $product->plantModel?->height_cm) }}"
                 placeholder="Example: 45.0"
                 class="mt-2 h-10 w-full rounded-lg border border-surface-200 px-3 text-base font-normal outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
          <span class="mt-1 block text-xs font-normal text-surface-400">Used to scale the object correctly in AR.</span>
        </label>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <button type="submit" data-saving-label="Uploading..." class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-700 px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-brand-800">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5M4 15v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4"/>
          </svg>
          {{ $product->plantModel ? 'Update AR Model' : 'Upload AR Model' }}
        </button>
        @if($product->plantModel)
          <span class="text-xs text-surface-400">Leave the file empty to update only the height.</span>
        @endif
      </div>
    </form>

    @if($product->plantModel)
      <form method="POST" action="{{ route('admin.ar-models.delete', $product) }}"
            data-confirm-title="Remove the AR model?"
            data-confirm="Customers will no longer be able to view {{ $product->name }} in AR. You can upload a new model afterwards."
            data-confirm-action="Remove model"
            class="border-t border-surface-100 pt-4">
        @csrf
        @method('DELETE')
        <button type="submit" data-saving-label="Removing..." class="text-sm font-medium text-red-600 transition-colors hover:text-red-700">Remove AR Model</button>
      </form>
    @endif
  </div>
</section>

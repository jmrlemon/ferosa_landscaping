@extends('layouts.customer')

@section('title', 'Messages – Ferosa Landscaping')

@section('styles')
<style>
  .bubble-mine {
    background: linear-gradient(135deg, var(--color-brand-500), var(--color-brand-700));
    color: #fff;
    border-radius: 20px 20px 4px 20px;
    box-shadow: 0 1px 3px rgba(18, 52, 38, .22);
  }
  .bubble-theirs {
    background: #fff;
    color: #272522;
    border-radius: 20px 20px 20px 4px;
    border: 1px solid #eae7df;
    box-shadow: 0 1px 2px rgba(18, 52, 38, .05);
  }
  /* A message that has left the browser but is not yet confirmed by the server. */
  .bubble-pending { opacity: .6; }
  #msg-list { scroll-behavior: smooth; }
  #msg-list::-webkit-scrollbar { width: 4px; }
  #msg-list::-webkit-scrollbar-thumb { background: #e5e5e5; border-radius: 10px; }

  .date-separator {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 20px 0;
  }
  .date-separator::before,
  .date-separator::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #e5e5e5;
  }

  /* Make the chat fill the viewport on both web and in-app */
  #chat-shell {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 100vh;
    min-height: 100dvh;
  }

  /* In the Android WebView the composer is a normal flex child at the bottom
     of #chat-shell, not a fixed-position bar. Fixed positioning tracks the CSS
     *layout* viewport, which Chromium keeps constant while only the *visual*
     viewport shrinks under the on-screen keyboard - so the composer stayed put
     and the keyboard covered it.

     For that to work the whole chain has to be locked to the viewport height,
     otherwise a long thread grows #chat-shell past the screen (min-height
     alone only sets a floor) and pushes the composer below the fold. Pinning
     every ancestor to 100dvh leaves #msg-list as the only scrolling element,
     which is also what makes "scroll to newest" behave. The layout sets these
     to auto/visible for ordinary pages, so they are overridden here only. */
  body.in-app {
    height: 100dvh !important;
    overflow: hidden !important;
  }
  body.in-app #app-content-wrapper {
    height: 100dvh !important;
    overflow: hidden !important;
  }
  body.in-app #main-content {
    height: 100% !important;
    overflow: hidden !important;
  }
  body.in-app #chat-shell {
    height: 100%;
    min-height: 0;
  }
</style>
@endsection

@section('content')
<div id="chat-shell" class="max-w-2xl mx-auto w-full bg-surface-50">

  {{-- Header --}}
  <div class="px-5 sm:px-6 py-4 border-b border-surface-100 bg-white flex items-center gap-3 flex-shrink-0">
    {{-- No presence dot: replies are not live, so a green "online" indicator
         would promise something the team cannot keep. --}}
    <div class="w-11 h-11 rounded-full bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center flex-shrink-0 shadow-sm">
      <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
      </svg>
    </div>
    <div class="flex-1">
      {{-- An h1, not a p: this is the page's title, and the chat had no
           top-level heading at all for a screen reader to land on. Same
           classes, so it renders identically. --}}
      <h1 class="text-sm font-semibold text-surface-900">Ferosa Support</h1>
      <p class="text-xs text-surface-500">{{ $businessHours ?: 'Usually replies within a few hours' }}</p>
    </div>
  </div>

  {{-- Status flash --}}
  @if(session('status'))
    <div class="mx-6 mt-3 px-4 py-2.5 bg-brand-50 border border-brand-200 text-brand-700 text-xs rounded-xl flex items-center gap-2">
      <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
      {{ session('status') }}
    </div>
  @endif

  {{-- Messages list --}}
  <div id="msg-list" class="flex-1 overflow-y-auto px-4 sm:px-6 py-5 space-y-1">

    @if($conversation->messages->isEmpty())
      <div class="flex flex-col items-center justify-center h-full py-12">
        <div class="w-16 h-16 rounded-full bg-brand-50 flex items-center justify-center mb-4">
          <svg class="w-8 h-8 text-brand-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
          </svg>
        </div>
        <h2 class="text-base font-semibold text-surface-900 mb-1">Start a conversation</h2>
        <p class="text-surface-400 text-sm text-center max-w-xs">Send us a message and our team will get back to you as soon as possible.</p>
      </div>
    @else
      @php $lastDate = null; @endphp
      @foreach($conversation->messages as $msg)
        @php
          $mine = $msg->sender_id === auth()->id();
          $msgDate = $msg->created_at->format('M j, Y');
        @endphp

        {{-- Date separator --}}
        @if($msgDate !== $lastDate)
          <div class="date-separator">
            <span class="text-[11px] font-medium text-surface-400 whitespace-nowrap">
              @if($msg->created_at->isToday()) Today
              @elseif($msg->created_at->isYesterday()) Yesterday
              @else {{ $msgDate }}
              @endif
            </span>
          </div>
          @php $lastDate = $msgDate; @endphp
        @endif

        <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }} mb-3" data-msg-id="{{ $msg->id }}" data-msg-time="{{ $msg->created_at->toISOString() }}">
          <div class="max-w-[78%] flex flex-col {{ $mine ? 'items-end' : 'items-start' }} gap-0.5">
            @if(!$mine)
              <span class="text-[10px] font-semibold text-brand-600 px-2 mb-0.5">Ferosa Support</span>
            @endif
            @if($msg->hasAttachment())
              @if($msg->attachmentIsImage())
                <a href="{{ $msg->attachmentUrl() }}" target="_blank" rel="noopener" data-lightbox
                   class="block overflow-hidden rounded-2xl border border-surface-200 max-w-[240px]">
                  <img src="{{ $msg->attachmentUrl() }}" alt="{{ $msg->attachment_name }}"
                       loading="lazy" class="block w-full h-auto">
                </a>
              @else
                <a href="{{ $msg->attachmentUrl() }}" target="_blank" rel="noopener"
                   class="flex items-center gap-2.5 rounded-2xl border border-surface-200 bg-white px-3 py-2.5 max-w-[240px] hover:border-brand-400 transition-colors">
                  <svg class="w-5 h-5 text-brand-600 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 2v6h6"/>
                  </svg>
                  <span class="min-w-0">
                    <span class="block truncate text-[12px] font-semibold text-surface-800">{{ $msg->attachment_name }}</span>
                    <span class="block text-[10px] text-surface-400">{{ $msg->attachmentSizeLabel() }}</span>
                  </span>
                </a>
              @endif
            @endif
            @if(filled($msg->body))
              <div class="px-4 py-2.5 text-[13px] leading-relaxed {{ $mine ? 'bubble-mine' : 'bubble-theirs' }}">
                {{ $msg->body }}
              </div>
            @endif
            <span class="text-[10px] text-surface-400 px-2 mt-0.5">
              {{ $msg->created_at->format('g:i A') }}
            </span>
          </div>
        </div>
      @endforeach
    @endif
  </div>

  {{-- Compose --}}
  <div id="compose-bar" class="border-t border-surface-100 bg-white px-4 sm:px-6 py-3 flex-shrink-0 pb-safe">
    {{-- Selected-file chip, shown until the message is sent or the file cleared --}}
    <div id="attach-preview" class="hidden items-center gap-2 mb-2 rounded-xl border border-surface-200 bg-surface-50 px-3 py-2">
      <img id="attach-thumb" alt="" class="hidden h-10 w-10 rounded-lg object-cover">
      <svg id="attach-icon" class="hidden w-5 h-5 text-brand-600 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
        <path stroke-linecap="round" stroke-linejoin="round" d="M14 2v6h6"/>
      </svg>
      <span class="min-w-0 flex-1">
        <span id="attach-name" class="block truncate text-[12px] font-semibold text-surface-800"></span>
        <span id="attach-size" class="block text-[10px] text-surface-400"></span>
      </span>
      <button type="button" id="attach-clear" aria-label="Remove attachment"
        class="shrink-0 w-7 h-7 rounded-full hover:bg-surface-200 flex items-center justify-center text-surface-500">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>

    {{-- data-no-loading opts out of the layout's global submit spinner. That
         handler runs on capture and rewrites the button's innerHTML, but this
         form sends over XHR and only re-enables the button afterwards - so the
         send arrow was replaced by a spinner that never came back. Progress is
         already reported on the message bubble itself ("Sending 40%"). --}}
    <form id="msg-form" method="POST" action="{{ route('messages.store') }}" enctype="multipart/form-data" data-no-loading="true" class="flex items-end gap-2.5">
      @csrf
      <input type="file" id="msg-attachment" name="attachment" class="hidden" aria-label="Message attachment"
             accept="{{ \App\Support\MessageAttachment::accept() }}">
      <button type="button" id="attach-btn" aria-label="Attach a file or picture"
        class="flex-shrink-0 w-10 h-10 rounded-full border border-surface-200 hover:bg-surface-50 flex items-center justify-center transition-colors text-surface-500">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13"/>
        </svg>
      </button>
      <textarea
        id="msg-body"
        name="body"
        rows="1"
        placeholder="Type a message…"
        aria-label="Message"
        maxlength="2000"
        class="flex-1 resize-none border border-surface-200 rounded-2xl px-4 py-2.5 text-sm outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-100 transition-all max-h-32 overflow-y-auto bg-surface-50 placeholder:text-surface-400"
        style="min-height:44px"
        onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();document.getElementById('msg-form').requestSubmit();}"
      ></textarea>
      <button type="submit" aria-label="Send message" data-loading-label=""
        class="flex-shrink-0 w-10 h-10 bg-brand-600 hover:bg-brand-700 active:scale-95 rounded-full flex items-center justify-center transition-all shadow-sm">
        <svg class="w-4 h-4 text-white translate-x-[1px]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.269 20.876L5.999 12zm0 0h7.5"/>
        </svg>
      </button>
    </form>
    <p class="text-[10px] text-surface-400 text-center mt-2 hidden sm:block">Press Enter to send · Shift+Enter for new line</p>
  </div>
</div>

{{-- Image lightbox. Chat photos used to open in a new browser tab, which drops
     the customer out of the conversation and, on the Android WebView, out of
     the app shell entirely. The anchors keep their href as a no-JS fallback. --}}
<div id="img-lightbox" class="hidden fixed inset-0 z-[80] items-center justify-center bg-black/80 p-4"
     role="dialog" aria-modal="true" aria-label="Attachment preview">
  <button type="button" id="img-lightbox-close" aria-label="Close preview"
    class="absolute top-4 right-4 w-10 h-10 rounded-full bg-white/10 text-white flex items-center justify-center hover:bg-white/20 transition-colors">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
    </svg>
  </button>
  {{-- No empty src: the lightbox sets it on open. An `src=""` resolves to the
       current page, so opening Messages re-requested the whole document. --}}
  <img id="img-lightbox-img" alt=""
       class="max-h-[88vh] max-w-full rounded-xl object-contain shadow-2xl">
  <a id="img-lightbox-open" href="#" target="_blank" rel="noopener"
     class="absolute bottom-5 left-1/2 -translate-x-1/2 rounded-full bg-white/10 px-4 py-2 text-xs font-semibold text-white hover:bg-white/20 transition-colors">
    Open full size
  </a>
</div>
@endsection

@section('scripts')
<script>
  // Scroll to bottom on load
  const list = document.getElementById('msg-list');
  list.scrollTop = list.scrollHeight;

  // Auto-grow textarea
  const ta = document.getElementById('msg-body');
  ta.addEventListener('input', () => {
    ta.style.height = 'auto';
    ta.style.height = Math.min(ta.scrollHeight, 128) + 'px';
  });

  // Focus textarea on load
  ta.focus();

  // Poll for new messages every 5 seconds
  let lastMsgId = {{ $conversation->messages->max('id') ?? 0 }};
  let lastTime  = '{{ $conversation->messages->max('created_at')?->toISOString() ?? now()->toISOString() }}';

  // Every message id already on screen. An upload can take several seconds, so
  // a poll can easily land between the server storing the message and this tab
  // reading the response - without this the message would be drawn twice.
  const renderedIds = new Set(
    [...list.querySelectorAll('[data-msg-id]')].map(el => parseInt(el.dataset.msgId, 10))
  );

  // Built with textContent rather than innerHTML: message bodies are user input,
  // so interpolating them into markup would both mangle characters like "<3"
  // and allow injected HTML to run.
  const FILE_ICON_PATH = 'M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z';

  // Same rule as the bubble text: filenames are user-supplied, so they go in
  // through textContent, never innerHTML.
  function buildAttachment(att) {
    const link = document.createElement('a');
    link.href = att.url;
    link.target = '_blank';
    link.rel = 'noopener';
    if (att.is_image) link.dataset.lightbox = '';

    if (att.is_image) {
      link.className = 'block overflow-hidden rounded-2xl border border-surface-200 max-w-[240px]';
      const img = document.createElement('img');
      img.src = att.url;
      img.alt = att.name || '';
      img.loading = 'lazy';
      img.className = 'block w-full h-auto';
      link.appendChild(img);
      return link;
    }

    link.className = 'flex items-center gap-2.5 rounded-2xl border border-surface-200 bg-white px-3 py-2.5 max-w-[240px] hover:border-brand-400 transition-colors';
    link.innerHTML = `<svg class="w-5 h-5 text-brand-600 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="${FILE_ICON_PATH}"/><path stroke-linecap="round" stroke-linejoin="round" d="M14 2v6h6"/></svg>`;

    const meta = document.createElement('span');
    meta.className = 'min-w-0';
    const nameEl = document.createElement('span');
    nameEl.className = 'block truncate text-[12px] font-semibold text-surface-800';
    nameEl.textContent = att.name || 'Attachment';
    const sizeEl = document.createElement('span');
    sizeEl.className = 'block text-[10px] text-surface-400';
    sizeEl.textContent = att.size_label || '';
    meta.append(nameEl, sizeEl);
    link.appendChild(meta);
    return link;
  }

  function buildBubble(msg) {
    const wrap = document.createElement('div');
    wrap.className = `flex ${msg.is_mine ? 'justify-end' : 'justify-start'} mb-3`;
    if (msg.id) wrap.dataset.msgId = msg.id;

    const col = document.createElement('div');
    col.className = `max-w-[78%] flex flex-col ${msg.is_mine ? 'items-end' : 'items-start'} gap-0.5`;

    if (!msg.is_mine) {
      const who = document.createElement('span');
      who.className = 'text-[10px] font-semibold text-brand-600 px-2 mb-0.5';
      who.textContent = 'Ferosa Support';
      col.appendChild(who);
    }

    if (msg.attachment) col.appendChild(buildAttachment(msg.attachment));

    // An attachment can travel without a caption, so only add a text bubble
    // when there is actually something to put in it.
    if (msg.body) {
      const bubble = document.createElement('div');
      bubble.className = `px-4 py-2.5 text-[13px] leading-relaxed ${msg.is_mine ? 'bubble-mine' : 'bubble-theirs'}`;
      bubble.textContent = msg.body;
      col.appendChild(bubble);
    }

    const time = document.createElement('span');
    time.className = 'msg-time text-[10px] text-surface-400 px-2 mt-0.5';
    time.textContent = msg.created_at
      ? new Date(msg.created_at).toLocaleString('en-US', { hour: 'numeric', minute: '2-digit' })
      : 'Sending…';
    col.appendChild(time);

    wrap.appendChild(col);
    return wrap;
  }

  // Set while a message is uploading. Polling mid-upload would draw the sent
  // message alongside its own placeholder for a moment; the send path renders
  // it as soon as the server answers.
  let sendInFlight = false;

  async function pollMessages() {
    if (sendInFlight) return;
    try {
      const res = await fetch(`{{ route('messages.poll') }}?after=${encodeURIComponent(lastTime)}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      if (!res.ok) return;
      const data = await res.json();
      if (data.messages && data.messages.length) {
        data.messages.forEach(msg => {
          lastTime = msg.created_at;
          if (renderedIds.has(msg.id)) return;
          renderedIds.add(msg.id);
          lastMsgId = Math.max(lastMsgId, msg.id);
          list.appendChild(buildBubble(msg));
        });
        list.scrollTop = list.scrollHeight;
      }
    } catch {}
  }

  // Only poll while the tab is actually being looked at.
  let pollTimer = null;
  function startPolling() {
    if (pollTimer === null) pollTimer = setInterval(pollMessages, 5000);
  }
  function stopPolling() {
    clearInterval(pollTimer);
    pollTimer = null;
  }
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      stopPolling();
    } else {
      pollMessages();
      startPolling();
    }
  });
  startPolling();

  // Send over fetch so the page no longer reloads on every message. The bubble
  // appears immediately and is reconciled once the server confirms it.
  const form = document.getElementById('msg-form');
  const sendBtn = form.querySelector('button[type="submit"]');

  // --- Attachment picker -----------------------------------------------
  const MAX_ATTACHMENT_BYTES = {{ \App\Support\MessageAttachment::MAX_KB }} * 1024;
  const RAW_IMAGE_CEILING    = 40 * 1024 * 1024; // pre-shrink guard
  const MAX_IMAGE_EDGE       = 1600;             // long edge after shrinking
  const JPEG_QUALITY         = 0.82;
  const fileInput  = document.getElementById('msg-attachment');
  const attachBtn  = document.getElementById('attach-btn');
  const preview    = document.getElementById('attach-preview');
  const thumb      = document.getElementById('attach-thumb');
  const fileIcon   = document.getElementById('attach-icon');
  const nameLabel  = document.getElementById('attach-name');
  const sizeLabel  = document.getElementById('attach-size');
  let thumbUrl = null;

  function humanSize(bytes) {
    return bytes >= 1048576
      ? (bytes / 1048576).toFixed(1) + ' MB'
      : Math.max(1, Math.round(bytes / 1024)) + ' KB';
  }

  /**
   * Shrink a camera photo before uploading. A modern phone picture is 4-8 MB
   * and several thousand pixels wide, which is slow to upload and far larger
   * than a chat bubble needs. Re-encoding to a 1600px JPEG typically cuts it to
   * a few hundred KB, which is what made sending feel slow.
   *
   * Anything that cannot be decoded (or would not get smaller) is returned
   * untouched, so a failure here never blocks the send.
   */
  async function shrinkImage(file) {
    // GIFs are skipped: re-encoding would flatten the animation.
    if (!file.type.startsWith('image/') || file.type === 'image/gif') return file;

    try {
      // `from-image` applies the EXIF orientation tag. Without it, photos taken
      // in portrait upload sideways, because re-encoding drops the tag that the
      // browser was using to correct them.
      const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
      const scale = Math.min(1, MAX_IMAGE_EDGE / Math.max(bitmap.width, bitmap.height));

      // Already small enough and not a heavy file - leave it alone.
      if (scale === 1 && file.size <= 1024 * 1024) {
        bitmap.close();
        return file;
      }

      const canvas = document.createElement('canvas');
      canvas.width  = Math.round(bitmap.width * scale);
      canvas.height = Math.round(bitmap.height * scale);
      canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
      bitmap.close();

      const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', JPEG_QUALITY));
      if (!blob || blob.size >= file.size) return file;

      const renamed = file.name.replace(/\.[^.]+$/, '') + '.jpg';
      return new File([blob], renamed, { type: 'image/jpeg', lastModified: Date.now() });
    } catch {
      return file;
    }
  }

  function clearAttachment() {
    fileInput.value = '';
    preview.classList.add('hidden');
    preview.classList.remove('flex');
    if (thumbUrl) { URL.revokeObjectURL(thumbUrl); thumbUrl = null; }
    thumb.classList.add('hidden');
    fileIcon.classList.add('hidden');
  }

  attachBtn.addEventListener('click', () => fileInput.click());
  document.getElementById('attach-clear').addEventListener('click', clearAttachment);

  fileInput.addEventListener('change', () => {
    const file = fileInput.files[0];
    if (!file) return clearAttachment();

    // Images are shrunk before upload, so judge them on their post-shrink size
    // rather than rejecting a normal phone photo up front. Everything else has
    // to clear the server limit as-is.
    const isShrinkable = file.type.startsWith('image/') && file.type !== 'image/gif';
    const ceiling = isShrinkable ? RAW_IMAGE_CEILING : MAX_ATTACHMENT_BYTES;

    if (file.size > ceiling) {
      alert(isShrinkable
        ? 'That picture is too large to process. Please choose a smaller one.'
        : 'That file is larger than {{ round(\App\Support\MessageAttachment::MAX_KB / 1024) }} MB. Please choose a smaller one.');
      return clearAttachment();
    }

    nameLabel.textContent = file.name;
    sizeLabel.textContent = humanSize(file.size);

    if (thumbUrl) URL.revokeObjectURL(thumbUrl);
    if (file.type.startsWith('image/')) {
      thumbUrl = URL.createObjectURL(file);
      thumb.src = thumbUrl;
      thumb.classList.remove('hidden');
      fileIcon.classList.add('hidden');
    } else {
      thumbUrl = null;
      thumb.classList.add('hidden');
      fileIcon.classList.remove('hidden');
    }

    preview.classList.remove('hidden');
    preview.classList.add('flex');
  });

  /**
   * Paste-to-attach. A screenshot copied with PrtSc / Win+Shift+S lives on the
   * clipboard as an image blob with no filename, so it never reaches the file
   * picker - which is why pasting into this box did nothing while it works on
   * other sites. Handing the blob to the file input and firing `change` reuses
   * the size check, the shrink pass and the preview exactly as a picked file.
   */
  ta.addEventListener('paste', (e) => {
    const items = e.clipboardData && e.clipboardData.items;
    if (!items) return;

    for (const item of items) {
      if (item.kind !== 'file' || !item.type.startsWith('image/')) continue;

      const blob = item.getAsFile();
      if (!blob) continue;

      // Clipboard images arrive unnamed or as a generic "image.png"; give it a
      // stamped name so the bubble and the stored row are tellable apart.
      const ext = (blob.type.split('/')[1] || 'png').replace('jpeg', 'jpg');
      const named = new File([blob], `pasted-${Date.now()}.${ext}`, { type: blob.type });

      const dt = new DataTransfer();
      dt.items.add(named);
      fileInput.files = dt.files;
      fileInput.dispatchEvent(new Event('change'));

      // Keep the caption the user typed; only the image itself is consumed.
      e.preventDefault();
      return;
    }
  });

  /**
   * XMLHttpRequest rather than fetch(), because only XHR reports upload
   * progress - without it a slow photo upload looks like the app has frozen.
   */
  function postMessage(payload, onProgress) {
    return new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();
      xhr.open('POST', form.action);
      xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name=csrf-token]').content);
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.setRequestHeader('Accept', 'application/json');
      xhr.timeout = 120000;

      xhr.upload.addEventListener('progress', e => {
        if (e.lengthComputable) onProgress(Math.round((e.loaded / e.total) * 100));
      });

      xhr.addEventListener('load', () => {
        let parsed = null;
        try { parsed = JSON.parse(xhr.responseText); } catch {}

        if (xhr.status >= 200 && xhr.status < 300 && parsed?.message) {
          resolve(parsed);
          return;
        }
        // 422 carries Laravel's validation text, which is more useful than a
        // generic failure ("file must not be greater than 12288 kilobytes").
        const detail = parsed?.errors ? Object.values(parsed.errors).flat()[0] : parsed?.message;
        reject(new Error(detail || 'Message could not be sent.'));
      });

      xhr.addEventListener('error',   () => reject(new Error('Network error while sending.')));
      xhr.addEventListener('timeout', () => reject(new Error('Sending timed out. Please try again.')));
      xhr.send(payload);
    });
  }

  form.addEventListener('submit', async function (event) {
    event.preventDefault();

    const body = ta.value.trim();
    const file = fileInput.files[0] || null;
    if (!body && !file) return;

    // The optimistic bubble shows a local preview of the picked image so the
    // chat looks instant; it is replaced by the stored copy on success.
    const localPreview = file
      ? { url: file.type.startsWith('image/') ? URL.createObjectURL(file) : '#',
          name: file.name, is_image: file.type.startsWith('image/'), size_label: humanSize(file.size) }
      : null;

    const optimistic = buildBubble({ body, is_mine: true, created_at: null, attachment: localPreview });
    optimistic.querySelector('.bubble-mine')?.classList.add('bubble-pending');
    list.appendChild(optimistic);
    list.scrollTop = list.scrollHeight;

    ta.value = '';
    ta.style.height = 'auto';
    clearAttachment();
    sendBtn.disabled = true;
    sendInFlight = true;

    const status = optimistic.querySelector('.msg-time');

    try {
      // Shrinking happens after the bubble is on screen, so the chat still
      // feels instant while a big photo is being re-encoded.
      let upload = file;
      if (file) {
        status.textContent = 'Preparing…';
        upload = await shrinkImage(file);
      }

      const payload = new FormData();
      if (body) payload.append('body', body);
      if (upload) payload.append('attachment', upload);

      const data = await postMessage(payload, pct => {
        status.textContent = pct < 100 ? `Sending ${pct}%` : 'Sending…';
      });

      // A poll may have already drawn this message while the upload was in
      // flight; in that case just drop the placeholder instead of adding a
      // second copy.
      if (renderedIds.has(data.message.id)) {
        optimistic.remove();
      } else {
        renderedIds.add(data.message.id);
        optimistic.replaceWith(buildBubble(data.message));
      }
      lastMsgId = Math.max(lastMsgId, data.message.id);
      lastTime  = data.message.created_at;
    } catch (error) {
      // Put the text back so nothing is silently lost. The file cannot be
      // restored into the input, so say so plainly rather than failing quietly.
      optimistic.remove();
      ta.value = body;
      alert(file
        ? (error.message || 'Your message could not be sent.') + '\nPlease attach the file again.'
        : 'Your message could not be sent. Please check your connection and try again.');
    } finally {
      if (localPreview?.is_image) URL.revokeObjectURL(localPreview.url);
      sendInFlight = false;
      sendBtn.disabled = false;
      list.scrollTop = list.scrollHeight;
      ta.focus();
    }
  });

  // --- Image lightbox ---------------------------------------------------
  // Delegated from the list so it covers bubbles that arrive over polling and
  // the optimistic one drawn while an upload is still in flight.
  const lightbox    = document.getElementById('img-lightbox');
  const lightboxImg = document.getElementById('img-lightbox-img');
  const lightboxRaw = document.getElementById('img-lightbox-open');
  let lastFocused = null;

  function openLightbox(url, alt) {
    lastFocused = document.activeElement;
    lightboxImg.src = url;
    lightboxImg.alt = alt || 'Attachment';
    lightboxRaw.href = url;
    lightbox.classList.remove('hidden');
    lightbox.classList.add('flex');
    document.body.style.overflow = 'hidden';
    document.getElementById('img-lightbox-close').focus();
  }

  function closeLightbox() {
    lightbox.classList.add('hidden');
    lightbox.classList.remove('flex');
    // Release the decoded image; a chat can accumulate a lot of them.
    lightboxImg.src = '';
    document.body.style.overflow = '';
    if (lastFocused) lastFocused.focus();
  }

  list.addEventListener('click', (e) => {
    const link = e.target.closest('a[data-lightbox]');
    if (!link) return;
    // Ctrl/middle-click keeps the normal new-tab behaviour.
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
    e.preventDefault();
    const img = link.querySelector('img');
    openLightbox(link.href, img && img.alt);
  });

  document.getElementById('img-lightbox-close').addEventListener('click', closeLightbox);
  lightbox.addEventListener('click', (e) => {
    // Clicking the backdrop closes; clicking the photo itself should not.
    if (e.target === lightbox) closeLightbox();
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !lightbox.classList.contains('hidden')) closeLightbox();
  });
</script>
@endsection

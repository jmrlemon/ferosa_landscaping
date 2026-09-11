<?php

$js = <<<'JS'
  // Mock Products Database
  const products = [
    { id: 1, name: 'Monstera Deliciosa', price: 45.00, category: 'plants', rating: 4.8, reviews: 124, imageBg: 'bg-emerald-100', imgPlaceholder: 'Replace with Monstera photo' },
    { id: 2, name: 'Premium Potting Mix', price: 18.50, category: 'materials', rating: 4.9, reviews: 89, imageBg: 'bg-stone-100', imgPlaceholder: 'Replace with Soil Bag' },
    { id: 3, name: 'Professional Pruning Shears', price: 32.00, category: 'tools', rating: 4.7, reviews: 215, imageBg: 'bg-zinc-100', imgPlaceholder: 'Replace with Shears photo' },
    { id: 4, name: 'Fiddle Leaf Fig', price: 65.00, category: 'plants', rating: 4.5, reviews: 92, imageBg: 'bg-emerald-100', imgPlaceholder: 'Replace with Fig photo' },
    { id: 5, name: 'Liquid Plant Fertilizer', price: 24.00, category: 'materials', rating: 4.6, reviews: 156, imageBg: 'bg-amber-50', imgPlaceholder: 'Replace with Fertilizer' },
    { id: 6, name: 'Heavy Duty Trowel', price: 16.00, category: 'tools', rating: 4.9, reviews: 310, imageBg: 'bg-zinc-100', imgPlaceholder: 'Replace with Trowel photo' },
  ];

  let currentFilter = 'all';
  let cartCount = 0;

  // DOM Elements
  const loadingState = document.getElementById('shop-loading');
  const productGrid = document.getElementById('product-grid');
  const emptyState = document.getElementById('shop-empty');
  const countLabel = document.getElementById('product-count-label');
  const filterSelect = document.getElementById('filter-category');
  const resetBtn = document.getElementById('reset-filter');
  const cartIcon = document.getElementById('floating-cart');
  const cartBadge = document.getElementById('floating-cart-count');
  const toastContainer = document.getElementById('shop-toast-container');

  function updateCartCount() {
    try {
      const cart = JSON.parse(localStorage.getItem('ferosa_cart')) || [];
      cartCount = cart.reduce((total, item) => total + item.qty, 0);
      cartBadge.textContent = cartCount;
      if (cartCount > 0) {
        cartIcon.classList.remove('hidden');
        cartIcon.classList.add('animate-in');
      } else {
        cartIcon.classList.add('hidden');
      }
    } catch(e) {}
  }

  function renderStars(rating) {
    let starsHtml = '';
    for (let i = 1; i <= 5; i++) {
        starsHtml += `<svg width="14" height="14" viewBox="0 0 24 24" fill="${i <= rating ? '#f59e0b' : '#e5e7eb'}"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>`;
    }
    return starsHtml;
  }

  function renderProducts(filter) {
    loadingState.classList.remove('hidden');
    productGrid.classList.add('hidden');
    emptyState.classList.add('hidden');
    countLabel.textContent = 'Loading products...';

    setTimeout(() => {
      loadingState.classList.add('hidden');
      productGrid.innerHTML = '';
      const filtered = products.filter(p => filter === 'all' || p.category === filter);

      if (filtered.length === 0) {
        emptyState.classList.remove('hidden');
        countLabel.textContent = '0 products';
        return;
      }

      filtered.forEach(p => {
        const card = document.createElement('div');
        card.className = 'product-card bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm flex flex-col group';
        card.innerHTML = `
          <div class="h-56 ${p.imageBg} relative overflow-hidden flex items-center justify-center p-6">
            <span class="text-xs font-mono text-gray-500/70 text-center tracking-wider">${p.imgPlaceholder}</span>
            <div class="absolute inset-0 bg-black/5 group-hover:bg-transparent transition-colors"></div>
            <div class="absolute top-3 left-3 bg-white/90 backdrop-blur-sm px-2.5 py-1 rounded-md shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-wider text-green-700">${p.category}</p>
            </div>
            <button onclick="addToCart(${p.id}, '${p.name}')" class="absolute bottom-4 left-1/2 -translate-x-1/2 bg-white text-gray-900 font-bold text-sm px-6 py-2 rounded-full shadow-lg opacity-0 translate-y-4 group-hover:opacity-100 group-hover:translate-y-0 transition-all z-10 flex items-center gap-2 hover:bg-green-50 hover:text-green-700">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>Add
            </button>
          </div>
          <div class="p-5 flex-1 flex flex-col">
            <div class="flex items-center gap-1 mb-2">
                ${renderStars(p.rating)}
                <span class="text-xs text-gray-400 ml-1">(${p.reviews})</span>
            </div>
            <h3 class="font-bold text-gray-900 text-lg leading-snug mb-1">${p.name}</h3>
            <div class="mt-auto pt-4 flex items-center justify-between">
                <p class="text-xl font-bold font-display text-green-700">$${p.price.toFixed(2)}</p>
                <button onclick="addToCart(${p.id}, '${p.name}')" class="w-10 h-10 bg-gray-50 hover:bg-green-600 hover:text-white text-gray-500 rounded-full flex items-center justify-center transition-colors">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                </button>
            </div>
          </div>
        `;
        productGrid.appendChild(card);
      });

      productGrid.classList.remove('hidden');
      countLabel.textContent = `Showing ${filtered.length} product${filtered.length !== 1 ? 's' : ''}`;
    }, 600);
  }

  window.addToCart = function(id, name) {
    const product = products.find(p => p.id === id);
    if (!product) return;

    let cart = [];
    try {
        const raw = localStorage.getItem('ferosa_cart');
        if (raw) cart = JSON.parse(raw);
    } catch(e) {}

    const existing = cart.find(i => i.id === id);
    if (existing) {
        existing.qty += 1;
    } else {
        cart.push({
            id: product.id,
            name: product.name,
            price: product.price,
            qty: 1
        });
    }

    localStorage.setItem('ferosa_cart', JSON.stringify(cart));
    updateCartCount();

    const toast = document.createElement('div');
    toast.className = 'bg-gray-900 text-white text-sm font-semibold px-4 py-3 rounded-xl shadow-xl flex items-center gap-3 transform translate-x-full transition-transform duration-300';
    toast.innerHTML = `
      <div class="w-6 h-6 bg-green-500 rounded-full flex items-center justify-center">
        <svg class="text-white w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
      </div>
      <div>Added ${name} to cart</div>
    `;
    toastContainer.appendChild(toast);
    
    setTimeout(() => { toast.classList.remove('translate-x-full'); }, 10);
    setTimeout(() => {
        toast.classList.add('translate-x-full');
        setTimeout(() => { toast.remove(); }, 300);
    }, 2500);
  };

  filterSelect.addEventListener('change', (e) => {
    currentFilter = e.target.value;
    renderProducts(currentFilter);
  });

  resetBtn.addEventListener('click', () => {
    filterSelect.value = 'all';
    currentFilter = 'all';
    renderProducts(currentFilter);
  });

  document.addEventListener('DOMContentLoaded', () => {
    renderProducts('all');
    updateCartCount();
  });
JS;

$file = 'resources/views/shop.blade.php';
$content = file_get_contents($file);
$content = preg_replace('/@section\(\'scripts\'\).*?@endsection/s', "@section('scripts')\n<script>\n".$js."\n</script>\n@endsection", $content);
file_put_contents($file, $content);

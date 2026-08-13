/* ─── Storage Keys ───────────────────────────────────────────── */
const STOCK_KEY  = 'chev_stock_v1';
const CART_KEY   = 'chev_cart_v1';
const LEADS_KEY  = 'chev_leads_v1';
const ORDERS_KEY = 'chev_orders_v1';

/* ─── State ──────────────────────────────────────────────────── */
let stock   = {};
let cart    = {};
let filtered = [];
let modalQty = 1;
let currentModalId = null;

/* ─── Init ───────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  initLogoSlider();
  initHeroCarousel();
  initStock();
  loadCart();
  buildFilters();
  buildModelTags();
  applyFilters();
  renderOutlet();
  updateStats();

  document.getElementById('searchInput').addEventListener('keydown', e => {
    if (e.key === 'Enter') applyFilters();
  });
});

/* ─── Logo Slider ────────────────────────────────────────────── */
function initLogoSlider() {
  const inner = document.getElementById('logoSliderInner');
  if (!inner) return;
  // Duplicate slides for seamless infinite loop
  inner.innerHTML += inner.innerHTML;
}

/* ─── Hero Photo Carousel ────────────────────────────────────── */
function initHeroCarousel() {
  const photos = document.querySelectorAll('#heroPhotos .hero-photo');
  if (photos.length < 2) return;
  let current = 0;
  setInterval(() => {
    photos[current].classList.remove('active');
    current = (current + 1) % photos.length;
    photos[current].classList.add('active');
  }, 5000);
}

/* ─── Stock ──────────────────────────────────────────────────── */
function initStock() {
  const saved = localStorage.getItem(STOCK_KEY);
  if (saved) {
    stock = JSON.parse(saved);
    PRODUCTS.forEach(p => { if (!(p.id in stock)) stock[p.id] = p.stock; });
    if (typeof OUTLET_PRODUCTS !== 'undefined') {
      OUTLET_PRODUCTS.forEach(p => { if (!(p.id in stock)) stock[p.id] = p.stock; });
    }
  } else {
    PRODUCTS.forEach(p => { stock[p.id] = p.stock; });
    if (typeof OUTLET_PRODUCTS !== 'undefined') {
      OUTLET_PRODUCTS.forEach(p => { stock[p.id] = p.stock; });
    }
  }
  saveStock();
}

function saveStock() { localStorage.setItem(STOCK_KEY, JSON.stringify(stock)); }

function getAvailableStock(id) {
  return Math.max(0, (stock[id] ?? 0) - (cart[id] || 0));
}

/* ─── Cart ───────────────────────────────────────────────────── */
function loadCart() {
  const saved = localStorage.getItem(CART_KEY);
  cart = saved ? JSON.parse(saved) : {};
  renderCart();
  updateCartBadge();
}

function saveCart() { localStorage.setItem(CART_KEY, JSON.stringify(cart)); }

function addToCart(id, qty) {
  qty = parseInt(qty) || 1;
  const available = getAvailableStock(id);
  if (available <= 0) { showToast('Sin stock disponible', 'error'); return false; }
  cart[id] = (cart[id] || 0) + Math.min(qty, available);
  saveCart(); renderCart(); updateCartBadge(); refreshCard(id);
  if (currentModalId === id) refreshModalStock(id);
  showToast('Producto agregado al carrito', 'success');
  return true;
}

function removeFromCart(id) {
  delete cart[id];
  saveCart(); renderCart(); updateCartBadge(); refreshCard(id);
  if (currentModalId === id) refreshModalStock(id);
}

function updateCartQty(id, delta) {
  const newQty = (cart[id] || 0) + delta;
  if (newQty <= 0) { removeFromCart(id); return; }
  if (newQty > (stock[id] || 0)) { showToast('No hay más stock disponible', 'error'); return; }
  cart[id] = newQty;
  saveCart(); renderCart(); updateCartBadge(); refreshCard(id);
  if (currentModalId === id) refreshModalStock(id);
}

function clearCart() {
  cart = {};
  saveCart(); renderCart(); updateCartBadge();
  document.querySelectorAll('.product-card[data-id]').forEach(c => refreshCard(parseInt(c.dataset.id)));
  showToast('Carrito vaciado', 'info');
}

function getCartTotal() {
  return Object.entries(cart).reduce((sum, [id, qty]) => {
    const p = findProduct(parseInt(id));
    return sum + (p ? p.precioVenta * qty : 0);
  }, 0);
}

function getCartCount() { return Object.values(cart).reduce((s, q) => s + q, 0); }

function findProduct(id) {
  return PRODUCTS.find(x => x.id === id) ||
    (typeof OUTLET_PRODUCTS !== 'undefined' ? OUTLET_PRODUCTS.find(x => x.id === id) : null);
}

/* ─── Render Cart ────────────────────────────────────────────── */
function renderCart() {
  const items = Object.entries(cart).filter(([, q]) => q > 0);
  const emptyEl  = document.getElementById('cartEmpty');
  const itemsEl  = document.getElementById('cartItems');
  const footerEl = document.getElementById('cartFooter');
  if (items.length === 0) {
    emptyEl.style.display = 'flex'; itemsEl.innerHTML = ''; footerEl.style.display = 'none';
    return;
  }
  emptyEl.style.display = 'none'; footerEl.style.display = 'block';
  itemsEl.innerHTML = items.map(([idStr, qty]) => {
    const id = parseInt(idStr);
    const p  = findProduct(id);
    if (!p) return '';
    return `<div class="cart-item">
      <div class="cart-item-img"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1.5"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg></div>
      <div class="cart-item-info">
        <div class="cart-item-name">${escHtml(p.titulo)}</div>
        <div class="cart-item-code">${escHtml(p.codigo)}</div>
        <div class="cart-item-bottom">
          <div class="cart-item-price">${formatPrice(p.precioVenta * qty)}</div>
          <div class="cart-item-qty">
            <button onclick="updateCartQty(${id},-1)">−</button>
            <span>${qty}</span>
            <button onclick="updateCartQty(${id},1)">+</button>
          </div>
          <button class="cart-item-remove" onclick="removeFromCart(${id})">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6 6 18M6 6l12 12"/></svg>
          </button>
        </div>
      </div>
    </div>`;
  }).join('');
  document.getElementById('cartSubtotal').textContent = formatPrice(getCartTotal());
}

function updateCartBadge() {
  const count = getCartCount();
  const badge = document.getElementById('cartBadge');
  badge.textContent = count;
  badge.style.display = count > 0 ? 'inline-block' : 'none';
}

function toggleCart() {
  document.getElementById('cartSidebar').classList.toggle('open');
  document.getElementById('cartOverlay').classList.toggle('open');
}

/* ─── WhatsApp cart share ────────────────────────────────────── */
function sendCartWhatsApp() {
  const items = Object.entries(cart).filter(([,q])=>q>0);
  if (!items.length) return;
  let msg = 'Hola! Quiero cotizar los siguientes repuestos Chevrolet:\n\n';
  items.forEach(([idStr,qty])=>{
    const p = findProduct(parseInt(idStr));
    if (p) msg += `• ${p.titulo} (Cód: ${p.codigo}) x${qty} — $${formatNum(p.precioVenta)}\n`;
  });
  msg += `\nTOTAL ESTIMADO: $${formatNum(getCartTotal())}`;
  window.open('https://wa.me/56972306103?text='+encodeURIComponent(msg), '_blank', 'noopener,noreferrer');
}

/* ─── Model Tags ─────────────────────────────────────────────── */
const MODELS = [
  'Onix','Tracker','Aveo','Cruze','Sail','Spark','Montana','Colorado',
  'Trailblazer','Cobalt','Spin','Prisma','S10','Silverado','Captiva',
  'Optra','Corsa','Sonic','Tahoe','Equinox','Trax','Camaro','Traverse'
];

function buildModelTags() {
  const container = document.getElementById('modelTagsScroll');
  container.innerHTML = MODELS.map(m =>
    `<button class="model-tag" onclick="searchByModel('${m}',this)">${m}</button>`
  ).join('');
}

function searchByModel(model, btn) {
  document.querySelectorAll('.model-tag').forEach(t => t.classList.remove('active'));
  const input = document.getElementById('filterModelo');
  if (input.value.trim().toLowerCase() === model.toLowerCase()) {
    input.value = '';
    btn && btn.classList.remove('active');
  } else {
    input.value = model;
    btn && btn.classList.add('active');
  }
  applyFilters();
  document.querySelector('.main').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/* ─── Filters & Search ───────────────────────────────────────── */
function buildFilters() {
  const cats = [...new Set(PRODUCTS.map(p => p.categoria))].sort();
  const catCounts = {};
  PRODUCTS.forEach(p => { catCounts[p.categoria] = (catCounts[p.categoria] || 0) + 1; });
  document.getElementById('filterCategoria').innerHTML = cats.map(c =>
    `<label class="filter-check"><input type="checkbox" class="filter-cat" value="${c}" onchange="applyFilters()"><span>${c}</span><span class="count">${catCounts[c]}</span></label>`
  ).join('');

  const brands = [...new Set(PRODUCTS.map(p => p.marca).filter(Boolean))].sort();
  const brandCounts = {};
  PRODUCTS.forEach(p => { if (p.marca) brandCounts[p.marca] = (brandCounts[p.marca] || 0) + 1; });
  document.getElementById('filterMarca').innerHTML = brands.map(b =>
    `<label class="filter-check"><input type="checkbox" class="filter-brand" value="${b}" onchange="applyFilters()"><span>${b}</span><span class="count">${brandCounts[b]}</span></label>`
  ).join('');
}

function applyFilters() {
  const query      = document.getElementById('searchInput').value.trim().toLowerCase();
  const onlyStock  = document.getElementById('filterStock').checked;
  const modelQuery = document.getElementById('filterModelo').value.trim().toLowerCase();
  const sortVal    = document.getElementById('sortSelect').value;
  const selCats    = [...document.querySelectorAll('.filter-cat:checked')].map(c => c.value);
  const selBrands  = [...document.querySelectorAll('.filter-brand:checked')].map(c => c.value);

  // Sync model tag active state
  document.querySelectorAll('.model-tag').forEach(t => {
    t.classList.toggle('active', t.textContent.trim().toLowerCase() === modelQuery);
  });

  filtered = PRODUCTS.filter(p => {
    if (selCats.length   && !selCats.includes(p.categoria)) return false;
    if (selBrands.length && !selBrands.includes(p.marca))   return false;
    if (onlyStock && (stock[p.id] || 0) <= 0)               return false;
    if (modelQuery) {
      const compat = [p.comp1, p.comp2, p.comp3].join(' ').toLowerCase();
      if (!compat.includes(modelQuery)) return false;
    }
    if (query) {
      const hay = [p.titulo, p.nombre, p.codigo, p.comp1, p.comp2, p.comp3, p.marca, p.categoria].join(' ').toLowerCase();
      const tokens = query.split(/\s+/).filter(Boolean);
      if (!tokens.every(t => hay.includes(t))) return false;
    }
    return true;
  });

  if (sortVal === 'price-asc')    filtered.sort((a,b) => a.precioVenta - b.precioVenta);
  else if (sortVal === 'price-desc') filtered.sort((a,b) => b.precioVenta - a.precioVenta);
  else if (sortVal === 'name-asc')   filtered.sort((a,b) => a.titulo.localeCompare(b.titulo,'es'));

  renderGrid();
}

function doSearch() { applyFilters(); }

function clearFilters() {
  document.getElementById('searchInput').value  = '';
  document.getElementById('filterStock').checked = false;
  document.getElementById('filterModelo').value  = '';
  document.querySelectorAll('.filter-cat,.filter-brand').forEach(c => { c.checked = false; });
  document.getElementById('sortSelect').value = 'default';
  document.querySelectorAll('.model-tag').forEach(t => t.classList.remove('active'));
  applyFilters();
}

/* ─── Render Grid ────────────────────────────────────────────── */
function renderGrid() {
  const grid    = document.getElementById('productGrid');
  const noRes   = document.getElementById('noResults');
  const countEl = document.getElementById('resultCount');
  if (!filtered.length) {
    grid.innerHTML = ''; noRes.style.display = 'block';
    countEl.innerHTML = 'Sin resultados'; return;
  }
  noRes.style.display = 'none';
  countEl.innerHTML = `<strong>${filtered.length}</strong> producto${filtered.length!==1?'s':''} encontrado${filtered.length!==1?'s':''}`;
  grid.innerHTML = filtered.map(p => productCardHTML(p)).join('');
}

/* ─── Product Images ─────────────────────────────────────────── */
function wrenchIconSvg(size, color, strokeW) {
  return `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="${color}" stroke-width="${strokeW}"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg>`;
}

function cardPlaceholderHTML(categoria) {
  return `<div class="card-img-placeholder">${wrenchIconSvg(40, '#bbb', 1.5)}<span>${escHtml(categoria)}</span></div>`;
}

function cardImgFallback(imgEl, categoria) {
  imgEl.outerHTML = cardPlaceholderHTML(categoria);
}

function modalImgFallback(imgEl) {
  imgEl.outerHTML = wrenchIconSvg(64, '#ccc', 1.2);
}

/* ─── Product Card ───────────────────────────────────────────── */
function stockBadgeData(id) {
  const available = getAvailableStock(id);
  if (available === 0) return { cls: 'out-of-stock', label: 'Sin stock' };
  if (available <= 3)  return { cls: 'low-stock',   label: 'Últimas unidades' };
  return { cls: 'in-stock', label: 'Disponible' };
}

function productCardHTML(p, isOutlet = false) {
  const { cls, label } = stockBadgeData(p.id);
  const available = getAvailableStock(p.id);
  const inCart    = cart[p.id] || 0;
  const compats   = [p.comp1, p.comp2, p.comp3].filter(Boolean);
  const compatHtml = compats.length
    ? `<div class="card-compat">${compats.map(c=>`<span>${escHtml(c)}</span>`).join('')}</div>` : '';

  let priceHtml;
  if (isOutlet && p.precioOriginal) {
    priceHtml = `<div class="card-price"><span class="currency">$</span>${formatNum(p.precioVenta)}<span class="price-original">$${formatNum(p.precioOriginal)}</span></div>`;
  } else {
    priceHtml = p.precioVenta > 0
      ? `<div class="card-price"><span class="currency">$</span>${formatNum(p.precioVenta)}</div>`
      : `<div class="card-price">Consultar</div>`;
  }

  let addBtn;
  if (available === 0) {
    addBtn = `<button class="card-add-btn" disabled>Sin stock</button>`;
  } else if (inCart > 0) {
    addBtn = `<div class="card-qty-control">
      <button class="qty-btn" onclick="event.stopPropagation();updateCartQty(${p.id},-1)">−</button>
      <span class="qty-num">${inCart}</span>
      <button class="qty-btn" onclick="event.stopPropagation();updateCartQty(${p.id},1)">+</button>
    </div>`;
  } else {
    addBtn = `<button class="card-add-btn" onclick="event.stopPropagation();addToCart(${p.id},1)">+ Agregar</button>`;
  }

  const cardImgHtml = (p.imagenes && p.imagenes.length)
    ? `<img class="card-img-photo" src="${escHtml(p.imagenes[0])}" alt="${escHtml(p.titulo)}" loading="lazy" onerror="cardImgFallback(this,'${escHtml(p.categoria)}')">`
    : cardPlaceholderHTML(p.categoria);

  return `<div class="product-card ${available===0?'out-of-stock':''}" data-id="${p.id}" onclick="openModal(${p.id})">
    <div class="card-img">
      ${cardImgHtml}
      <div class="card-marca-badge">${escHtml(p.marca)}</div>
      <div class="card-stock-badge ${cls}">${label}</div>
      ${isOutlet && p.descuento ? `<div class="card-outlet-badge">-${p.descuento}%</div>` : ''}
    </div>
    <div class="card-body">
      <div class="card-codigo">${escHtml(p.codigo)}</div>
      <div class="card-title">${escHtml(p.titulo)}</div>
      ${compatHtml}
    </div>
    <div class="card-footer">
      ${priceHtml}
      ${addBtn}
    </div>
  </div>`;
}

function refreshCard(id) {
  const card = document.querySelector(`.product-card[data-id="${id}"]`);
  if (!card) return;
  const p = findProduct(id);
  if (!p) return;
  const isOutlet = typeof OUTLET_PRODUCTS !== 'undefined' && OUTLET_PRODUCTS.some(x=>x.id===id);
  const div = document.createElement('div');
  div.innerHTML = productCardHTML(p, isOutlet);
  const newCard = div.firstElementChild;
  card.replaceWith(newCard);
}

/* ─── Outlet ─────────────────────────────────────────────────── */
function renderOutlet() {
  const grid    = document.getElementById('outletGrid');
  const emptyEl = document.getElementById('outletEmpty');
  const section = document.getElementById('outletSection');
  if (typeof OUTLET_PRODUCTS === 'undefined' || OUTLET_PRODUCTS.length === 0) {
    grid.innerHTML = '';
    emptyEl.style.display = 'block';
    return;
  }
  emptyEl.style.display = 'none';
  grid.innerHTML = OUTLET_PRODUCTS.map(p => productCardHTML(p, true)).join('');
}

/* ─── Modal ──────────────────────────────────────────────────── */
function openModal(id) {
  const p = findProduct(id);
  if (!p) return;
  currentModalId = id; modalQty = 1;
  const available = getAvailableStock(id);
  const compats   = [p.comp1, p.comp2, p.comp3].filter(Boolean);
  const stockColor = available === 0 ? '#dc2626' : available <= 3 ? '#d97706' : '#16a34a';
  const stockText  = available === 0 ? 'Sin stock' : 'Disponible';

  const imagenes = p.imagenes && p.imagenes.length ? p.imagenes : [];
  const modalImgHtml = imagenes.length
    ? `<img id="modalMainImg" src="${escHtml(imagenes[0])}" alt="${escHtml(p.titulo)}" onerror="modalImgFallback(this)">`
    : wrenchIconSvg(64, '#ccc', 1.2);
  const modalThumbsHtml = imagenes.length > 1
    ? `<div class="modal-thumbs">${imagenes.map((img, i) => `<img class="modal-thumb${i===0?' active':''}" src="${escHtml(img)}" onclick="selectModalThumb(this)">`).join('')}</div>`
    : '';

  document.getElementById('modalContent').innerHTML = `
    <div class="modal-img">
      ${modalImgHtml}
    </div>
    ${modalThumbsHtml}
    <div class="modal-body">
      <div class="modal-marca">${escHtml(p.marca)} · ${escHtml(p.categoria)}</div>
      <h2 class="modal-title">${escHtml(p.titulo)}</h2>
      <div class="modal-codigo">Código: ${escHtml(p.codigo)}</div>
      <div class="modal-price-row">
        <div class="modal-price">${p.precioVenta > 0 ? '$'+formatNum(p.precioVenta) : 'Consultar precio'}</div>
        ${p.precioOriginal ? `<span style="color:#888;text-decoration:line-through;font-size:14px">$${formatNum(p.precioOriginal)}</span>` : ''}
      </div>
      <div class="modal-stock-row">
        <span class="modal-stock-label">Disponibilidad:</span>
        <span class="modal-stock-num" id="modalStockNum" style="color:${stockColor}">${stockText}</span>
      </div>
      ${compats.length ? `<div class="modal-section-title">Compatibilidad</div>
        <div class="modal-compat-list">${compats.map(c=>`<span class="modal-compat-tag">${escHtml(c)}</span>`).join('')}</div>` : ''}
      <div class="modal-add-row">
        <div class="modal-qty-control">
          <button onclick="changeModalQty(-1)">−</button>
          <span id="modalQtyNum">1</span>
          <button onclick="changeModalQty(1)">+</button>
        </div>
        <button class="btn-modal-add" id="btnModalAdd" ${available===0?'disabled':''} onclick="addFromModal()">
          ${available===0?'Sin stock':'Agregar al carrito'}
        </button>
      </div>
      <div style="margin-top:12px;display:flex;gap:8px;">
        <a class="btn-call-direct" style="flex:1;text-align:center;font-size:13px;padding:9px;" href="https://wa.me/56972306103?text=${encodeURIComponent('Hola, quiero consultar por: '+p.titulo+' (Cód: '+p.codigo+')')}" target="_blank" rel="noopener noreferrer">
          💬 Consultar por WhatsApp
        </a>
      </div>
    </div>`;

  document.getElementById('modalOverlay').classList.add('open');
  document.getElementById('productModal').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function selectModalThumb(thumbEl) {
  const mainImg = document.getElementById('modalMainImg');
  if (!mainImg) return;
  mainImg.src = thumbEl.src;
  thumbEl.parentElement.querySelectorAll('.modal-thumb').forEach(t => t.classList.remove('active'));
  thumbEl.classList.add('active');
}

function closeModal() {
  currentModalId = null;
  document.getElementById('modalOverlay').classList.remove('open');
  document.getElementById('productModal').classList.remove('open');
  document.body.style.overflow = '';
}

function changeModalQty(delta) {
  if (!currentModalId) return;
  const available = getAvailableStock(currentModalId);
  modalQty = Math.max(1, Math.min(modalQty + delta, available));
  document.getElementById('modalQtyNum').textContent = modalQty;
}

function addFromModal() {
  if (!currentModalId) return;
  addToCart(currentModalId, modalQty);
  closeModal();
}

function refreshModalStock(id) {
  const available = getAvailableStock(id);
  const el  = document.getElementById('modalStockNum');
  const btn = document.getElementById('btnModalAdd');
  if (!el || !btn) return;
  const color = available === 0 ? '#dc2626' : available <= 3 ? '#d97706' : '#16a34a';
  el.textContent = available === 0 ? 'Sin stock' : 'Disponible';
  el.style.color = color;
  btn.disabled   = available === 0;
  btn.textContent = available === 0 ? 'Sin stock' : 'Agregar al carrito';
  if (modalQty > available) { modalQty = Math.max(1, available); const q = document.getElementById('modalQtyNum'); if(q) q.textContent = modalQty; }
}

/* ─── Order Form ─────────────────────────────────────────────── */
function openOrderForm() {
  if (getCartCount() === 0) { showToast('El carrito está vacío', 'error'); return; }
  // Build summary
  const items = Object.entries(cart).filter(([,q])=>q>0);
  let sumHtml = items.map(([idStr,qty])=>{
    const p = findProduct(parseInt(idStr));
    if (!p) return '';
    return `<div class="sum-item"><span>${escHtml(p.titulo)} x${qty}</span><span>${formatPrice(p.precioVenta*qty)}</span></div>`;
  }).join('') + `<div class="sum-total"><span>Total estimado</span><span>${formatPrice(getCartTotal())}</span></div>`;
  document.getElementById('orderSummaryBox').innerHTML = sumHtml;

  document.getElementById('orderOverlay').classList.add('open');
  document.getElementById('orderModal').classList.add('open');
  document.body.style.overflow = 'hidden';
  toggleCart();
}

function closeOrderForm() {
  document.getElementById('orderOverlay').classList.remove('open');
  document.getElementById('orderModal').classList.remove('open');
  document.body.style.overflow = '';
}

function toggleDeliveryFields() {
  const type = document.querySelector('input[name=deliveryType]:checked')?.value;
  document.getElementById('despachoFields').style.display = type === 'despacho' ? 'block' : 'none';
}

function formatRut(input) {
  let v = input.value.replace(/[^0-9kK]/g, '').toUpperCase();
  if (v.length > 9) v = v.substring(0, 9);
  if (v.length > 1) {
    const body = v.slice(0, -1).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    input.value = body + '-' + v.slice(-1);
  } else { input.value = v; }
}

function validateRut(rut) {
  rut = rut.replace(/\./g,'').replace(/-/,'');
  if (rut.length < 2) return false;
  const body = rut.slice(0,-1);
  const dv   = rut.slice(-1).toUpperCase();
  let sum = 0, mult = 2;
  for (let i = body.length - 1; i >= 0; i--) {
    sum += parseInt(body[i]) * mult;
    mult = mult === 7 ? 2 : mult + 1;
  }
  const expected = 11 - (sum % 11);
  const dvCalc = expected === 11 ? '0' : expected === 10 ? 'K' : String(expected);
  return dv === dvCalc;
}

function setErr(id, msg) {
  const el = document.getElementById(id);
  if (el) el.textContent = msg;
}
function clearErr(id) { setErr(id, ''); }

function submitOrder(e) {
  e.preventDefault();
  let valid = true;
  const deliveryType = document.querySelector('input[name=deliveryType]:checked')?.value || 'retiro';
  const rut      = document.getElementById('fRut').value.trim();
  const nombre   = document.getElementById('fNombre').value.trim();
  const email    = document.getElementById('fEmail').value.trim();
  const telefono = document.getElementById('fTelefono').value.trim();
  const notas    = document.getElementById('fNotas').value.trim();

  clearErr('errRut'); clearErr('errNombre'); clearErr('errEmail'); clearErr('errTelefono');

  if (!rut) { setErr('errRut','El RUT es obligatorio'); valid=false; }
  else if (!validateRut(rut)) { setErr('errRut','RUT inválido'); valid=false; }
  if (!nombre) { setErr('errNombre','El nombre es obligatorio'); valid=false; }
  if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { setErr('errEmail','Correo inválido'); valid=false; }
  if (!telefono) { setErr('errTelefono','El teléfono es obligatorio'); valid=false; }

  let direccion='', comuna='', ciudad='';
  if (deliveryType === 'despacho') {
    direccion = document.getElementById('fDireccion').value.trim();
    comuna    = document.getElementById('fComuna').value.trim();
    ciudad    = document.getElementById('fCiudad').value.trim();
    clearErr('errDireccion'); clearErr('errComuna');
    if (!direccion) { setErr('errDireccion','La dirección es obligatoria'); valid=false; }
    if (!comuna)    { setErr('errComuna','La comuna es obligatoria'); valid=false; }
  }
  if (!valid) return;

  const items = Object.entries(cart).filter(([,q])=>q>0).map(([idStr,qty])=>{
    const p = findProduct(parseInt(idStr));
    return p ? { id: p.id, titulo: p.titulo, codigo: p.codigo, qty, precio: p.precioVenta } : null;
  }).filter(Boolean);

  const order = {
    id: 'ORD-' + Date.now(),
    fecha: new Date().toISOString(),
    rut, nombre, email, telefono,
    deliveryType, direccion, comuna, ciudad, notas,
    items,
    total: getCartTotal()
  };

  const orders = JSON.parse(localStorage.getItem(ORDERS_KEY) || '[]');
  orders.push(order);
  localStorage.setItem(ORDERS_KEY, JSON.stringify(orders));

  // Copia al panel de control (backend). Si falla (sin conexión, backend caído),
  // el pedido igual queda en localStorage y se notifica por WhatsApp más abajo.
  fetch('api/orders.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(order)
  }).catch(() => {});

  // Descontar stock definitivamente
  items.forEach(item => {
    stock[item.id] = Math.max(0, (stock[item.id] || 0) - item.qty);
  });
  saveStock();
  clearCart();
  closeOrderForm();

  // Limpiar el formulario para que el siguiente pedido no arranque con los
  // datos del cliente anterior (importante en equipos compartidos de tienda).
  document.getElementById('orderForm').reset();
  ['errRut','errNombre','errEmail','errTelefono','errDireccion','errComuna'].forEach(clearErr);
  toggleDeliveryFields();

  // Mostrar recibo con QR
  openReceiptModal(order);

  // Notificar al ejecutivo por WhatsApp
  const msg = `🚗 *Nuevo pedido — Inalco Chevrolet*\n\n*N°:* ${order.id}\n*Cliente:* ${nombre}\n*RUT:* ${rut}\n*Teléfono:* ${telefono}\n*Email:* ${email}\n*Entrega:* ${deliveryType === 'retiro' ? 'Retiro en bodega' : 'Despacho a '+direccion+', '+comuna}\n\n*Productos:*\n${items.map(i=>`• ${i.titulo} x${i.qty} — $${formatNum(i.precio*i.qty)}`).join('\n')}\n\n*TOTAL: $${formatNum(order.total)}*`;
  setTimeout(() => window.open('https://wa.me/56972306103?text='+encodeURIComponent(msg), '_blank', 'noopener,noreferrer'), 600);
}

/* ─── Call Lead Modal ────────────────────────────────────────── */
function openCallModal() {
  document.getElementById('callOverlay').classList.add('open');
  document.getElementById('callModal').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeCallModal() {
  document.getElementById('callOverlay').classList.remove('open');
  document.getElementById('callModal').classList.remove('open');
  document.body.style.overflow = '';
}

function registerCallLead(e) {
  const nombre   = document.getElementById('clNombre')?.value?.trim() || '';
  const telefono = document.getElementById('clTelefono')?.value?.trim() || '';
  const consulta = document.getElementById('clConsulta')?.value?.trim() || '';
  if (nombre || telefono) {
    saveLead({ tipo: 'llamada', nombre, telefono, consulta });
  }
  closeCallModal();
}

function submitCallLead(e) {
  e.preventDefault();
  const nombre   = document.getElementById('clNombre').value.trim();
  const telefono = document.getElementById('clTelefono').value.trim();
  const consulta = document.getElementById('clConsulta').value.trim();
  if (!telefono) { showToast('Ingresa tu teléfono para que te contactemos', 'error'); return; }
  saveLead({ tipo: 'callback', nombre, telefono, consulta });
  fetch('api/leads.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ nombre, telefono, consulta })
  }).catch(() => {});
  closeCallModal();
  showToast('¡Recibido! Te llamaremos pronto.', 'success');
  const msg = `📞 *Solicitud de llamada*\n\n*Nombre:* ${nombre||'No indicado'}\n*Teléfono:* ${telefono}\n*Consulta:* ${consulta||'Sin especificar'}`;
  window.open('https://wa.me/56972306103?text='+encodeURIComponent(msg), '_blank', 'noopener,noreferrer');
}

function saveLead(data) {
  const leads = JSON.parse(localStorage.getItem(LEADS_KEY) || '[]');
  leads.push({ ...data, fecha: new Date().toISOString(), id: 'LEAD-'+Date.now() });
  localStorage.setItem(LEADS_KEY, JSON.stringify(leads));
}

/* ─── Quote Request Modal ────────────────────────────────────── */
function openQuoteModal() {
  document.getElementById('quoteOverlay').classList.add('open');
  document.getElementById('quoteModal').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeQuoteModal() {
  document.getElementById('quoteOverlay').classList.remove('open');
  document.getElementById('quoteModal').classList.remove('open');
  document.body.style.overflow = '';
}

function submitQuote(e) {
  e.preventDefault();
  let valid = true;
  const nombre   = document.getElementById('qNombre').value.trim();
  const email    = document.getElementById('qEmail').value.trim();
  const telefono = document.getElementById('qTelefono').value.trim();
  const modelo   = document.getElementById('qModelo').value.trim();
  const anio     = document.getElementById('qAnio').value.trim();
  const repuesto = document.getElementById('qRepuesto').value.trim();
  const vin      = document.getElementById('qVin').value.trim();
  const chasis   = document.getElementById('qChasis').value.trim();
  const notas    = document.getElementById('qNotas').value.trim();
  const rut      = document.getElementById('qRut').value.trim();

  ['qErrNombre','qErrEmail','qErrTelefono','qErrModelo','qErrAnio','qErrRepuesto'].forEach(id => setErr(id,''));

  if (!nombre)   { setErr('qErrNombre','El nombre es obligatorio'); valid=false; }
  if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { setErr('qErrEmail','Correo inválido'); valid=false; }
  if (!telefono) { setErr('qErrTelefono','El teléfono es obligatorio'); valid=false; }
  if (!modelo)   { setErr('qErrModelo','El modelo es obligatorio'); valid=false; }
  if (!anio)     { setErr('qErrAnio','El año es obligatorio'); valid=false; }
  if (!repuesto) { setErr('qErrRepuesto','Describe el repuesto que necesitas'); valid=false; }
  if (!valid) return;

  const quoteId = 'COT-' + Date.now();
  const quote = { id: quoteId, fecha: new Date().toISOString(), nombre, rut, email, telefono, modelo, anio, vin, chasis, repuesto, notas };

  const leads = JSON.parse(localStorage.getItem(LEADS_KEY) || '[]');
  leads.push({ ...quote, tipo: 'cotizacion' });
  localStorage.setItem(LEADS_KEY, JSON.stringify(leads));

  const msg = `🔧 *Nueva Solicitud de Cotización — Inalco Chevrolet*\n\n*N°:* ${quoteId}\n*Nombre:* ${nombre}${rut ? '\n*RUT:* '+rut : ''}\n*Email:* ${email}\n*Teléfono:* ${telefono}\n\n*Vehículo:*\n• Modelo: ${modelo}\n• Año: ${anio}${vin ? '\n• VIN: '+vin : ''}${chasis ? '\n• Chasis: '+chasis : ''}\n\n*Repuesto solicitado:*\n${repuesto}${notas ? '\n\n*Notas:* '+notas : ''}`;
  window.open('https://wa.me/56972306103?text=' + encodeURIComponent(msg), '_blank', 'noopener,noreferrer');

  closeQuoteModal();
  showToast('¡Cotización enviada! Te contactaremos pronto.', 'success');
  e.target.reset();
}

/* ─── Transaction Receipt Modal ─────────────────────────────── */
function openReceiptModal(order) {
  const code = order.id;
  document.getElementById('receiptCode').textContent = code;

  // Generar QR
  const qrContainer = document.getElementById('receiptQR');
  qrContainer.innerHTML = '';
  const qrData = `INALCO|${code}|${order.nombre}|${formatNum(order.total)}|${order.fecha.slice(0,10)}`;
  try {
    new QRCode(qrContainer, { text: qrData, width: 180, height: 180, colorDark: '#1a1a2e', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.M });
  } catch(_) {
    qrContainer.innerHTML = `<p style="font-size:11px;color:#888">QR no disponible sin conexión</p>`;
  }

  // Detalles del pedido
  const fmt = n => '$' + formatNum(n);
  const detHtml = `
    <div class="receipt-info-grid">
      <span>Cliente</span><span>${escHtml(order.nombre)}</span>
      <span>RUT</span><span>${escHtml(order.rut)}</span>
      <span>Fecha</span><span>${new Date(order.fecha).toLocaleDateString('es-CL',{day:'2-digit',month:'2-digit',year:'numeric'})}</span>
      <span>Entrega</span><span>${order.deliveryType === 'retiro' ? 'Retiro en bodega' : 'Despacho — '+escHtml(order.comuna)}</span>
    </div>
    <div class="receipt-items">
      ${order.items.map(i=>`<div class="receipt-item"><span>${escHtml(i.titulo)}</span><span>x${i.qty}</span><span>${fmt(i.precio*i.qty)}</span></div>`).join('')}
    </div>
    <div class="receipt-total">TOTAL <strong>${fmt(order.total)}</strong></div>`;
  document.getElementById('receiptDetails').innerHTML = detHtml;

  document.getElementById('receiptOverlay').classList.add('open');
  document.getElementById('receiptModal').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeReceiptModal() {
  document.getElementById('receiptOverlay').classList.remove('open');
  document.getElementById('receiptModal').classList.remove('open');
  document.body.style.overflow = '';
}

/* ─── Policies Modal ─────────────────────────────────────────── */
function openPolicies() {
  document.getElementById('policiesOverlay').classList.add('open');
  document.getElementById('policiesModal').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closePolicies() {
  document.getElementById('policiesOverlay').classList.remove('open');
  document.getElementById('policiesModal').classList.remove('open');
  document.body.style.overflow = '';
}

/* ─── Stats ──────────────────────────────────────────────────── */
function updateStats() {
  document.getElementById('statTotal').textContent = PRODUCTS.length;
  document.getElementById('statInStock').textContent = PRODUCTS.filter(p=>(stock[p.id]||0)>0).length;
}

/* ─── Helpers ────────────────────────────────────────────────── */
function formatPrice(n) { return '$' + formatNum(n); }
function formatNum(n)   { return Math.round(n).toLocaleString('es-CL'); }
function escHtml(s) {
  if (!s) return '';
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showToast(msg, type = 'info') {
  const c = document.getElementById('toastContainer');
  const t = document.createElement('div');
  t.className = `toast ${type}`;
  t.textContent = msg;
  c.appendChild(t);
  requestAnimationFrame(()=>{ requestAnimationFrame(()=>{ t.classList.add('show'); }); });
  setTimeout(()=>{ t.classList.remove('show'); setTimeout(()=>t.remove(),350); }, 2800);
}

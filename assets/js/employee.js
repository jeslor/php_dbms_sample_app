// ==================== SESSION CHECK ====================
(async function () {
    const res = await fetch('employee_api.php?action=checkSession');
    const data = await res.json();
    if (!data.valid) window.location.href = 'index.php';
})();

let cart = [];
let tempItem = null;
let taxRate = 0;
const defaultPageTitle = document.title;

// ==================== HELPERS ====================
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
}
function formatMoney(n) {
    return Number(n).toLocaleString('en-US') + ' UGX';
}
// The server always stores/returns timestamps as Kampala wall-clock time
// (e.g. "2026-10-13 18:02:12") with no timezone marker. Appending +03:00
// before parsing stops the browser from guessing its own local timezone,
// so the displayed time is correct no matter where the employee's device is.
function toKampalaDate(mysqlDateStr) {
    return new Date(String(mysqlDateStr).replace(' ', 'T') + '+03:00');
}
function formatDateTime(mysqlDateStr) {
    return toKampalaDate(mysqlDateStr).toLocaleString('en-US', {
        timeZone: 'Africa/Kampala', year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit'
    });
}
async function apiCall(action, data = {}, method = 'POST') {
    if (method === 'GET') {
        const qs = new URLSearchParams({ action, ...data }).toString();
        const res = await fetch(`employee_api.php?${qs}`);
        return res.json();
    }
    const formData = new FormData();
    formData.append('action', action);
    for (let k in data) formData.append(k, data[k]);
    const res = await fetch('employee_api.php', { method: 'POST', body: formData });
    return res.json();
}
function showToast(msg, type = 'success') {
    const toast = document.getElementById('toast');
    const body = document.getElementById('toast-body');
    body.textContent = msg;
    body.className = 'shadow-xl rounded-xl px-5 py-4 text-sm font-medium min-w-[240px] bg-white border-l-4 ' +
        (type === 'success' ? 'border-emerald-500 text-slate-700' : 'border-red-500 text-slate-700');
    toast.classList.remove('hidden');
    setTimeout(() => toast.classList.add('hidden'), 2500);
}

// ==================== SIDEBAR / NAV ====================
function openSidebar() {
    document.getElementById('sidebar').classList.remove('-translate-x-full');
    document.getElementById('sidebar-overlay').classList.remove('hidden');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.add('-translate-x-full');
    document.getElementById('sidebar-overlay').classList.add('hidden');
}
const PAGE_TITLES = { pos: 'Point of Sale', stock: 'View Stock', sales: 'My Sales History' };
function showSection(id, btn) {
    document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    document.querySelectorAll('.nav-link').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    document.getElementById('page-title').textContent = PAGE_TITLES[id] || '';
    if (id === 'pos') renderItems();
    if (id === 'stock') renderStock();
    if (id === 'sales') renderMySales();
    closeSidebar();
}

document.getElementById('current-date').textContent = new Date().toLocaleDateString('en-US', { timeZone: 'Africa/Kampala', weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

// ==================== POS: ITEMS TABLE ====================
let catFilterPopulated = false;
async function renderItems() {
    const data = await apiCall('getInventory', {}, 'GET');
    if (!catFilterPopulated) await populatePosCategoryFilter(data);
    const term = document.getElementById('search').value.toLowerCase();
    const category = document.getElementById('pos-cat-filter').value;
    const tbody = document.getElementById('items-table');
    let filtered = data.filter(i =>
        (i.name.toLowerCase().includes(term) || (i.sku || '').toLowerCase().includes(term)) &&
        (!category || i.category_name === category)
    );
    if (!filtered.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-16 text-center text-slate-400">No products found</td></tr>';
        return;
    }
    tbody.innerHTML = filtered.map(i => {
        const low = i.stock > 0 && i.stock <= i.low_stock_threshold;
        const stockBadge = low
            ? `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-600">${i.stock} low</span>`
            : `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-600">${i.stock} in stock</span>`;
        return `
        <tr class="border-t border-border hover:bg-slate-50 cursor-pointer" onclick="openSaleModal(${i.id})">
            <td class="px-6 py-3.5 font-medium text-surface">${escapeHtml(i.name)}</td>
            <td class="px-6 py-3.5 text-slate-500 font-mono text-xs">${escapeHtml(i.sku || '—')}</td>
            <td class="px-6 py-3.5"><span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-600">${escapeHtml(i.category_name || 'Uncategorized')}</span></td>
            <td class="px-6 py-3.5 text-slate-600">${formatMoney(i.min_price)} - ${formatMoney(i.max_price)}</td>
            <td class="px-6 py-3.5">${stockBadge}</td>
            <td class="px-6 py-3.5 text-right">
                <button onclick="event.stopPropagation(); openSaleModal(${i.id})" class="inline-flex items-center gap-1.5 text-accent hover:text-white hover:bg-accent px-3 py-1.5 rounded-lg text-xs font-semibold bg-orange-50 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Sell
                </button>
            </td>
        </tr>`;
    }).join('');
}

async function populatePosCategoryFilter(invData) {
    const cats = await apiCall('getCategories', {}, 'GET');
    const select = document.getElementById('pos-cat-filter');
    select.innerHTML = '<option value="">All Categories</option>' + cats.map(c => `<option value="${escapeHtml(c.name)}">${escapeHtml(c.name)}</option>`).join('');
    catFilterPopulated = true;
}

// ==================== ADD TO CART MODAL ====================
async function openSaleModal(id) {
    const products = await apiCall('getInventory', {}, 'GET');
    tempItem = products.find(p => p.id == id);
    if (!tempItem || tempItem.stock <= 0) { showToast('Out of stock', 'error'); return; }
    document.getElementById('modal-title').innerText = tempItem.name;
    document.getElementById('price-hint').innerHTML = `<strong>Stock:</strong> ${tempItem.stock} &nbsp;|&nbsp; <strong>Price Range:</strong> ${formatMoney(tempItem.min_price)} - ${formatMoney(tempItem.max_price)}`;
    document.getElementById('sale-qty').value = 1;
    document.getElementById('sale-price').value = '';
    document.getElementById('item-total').textContent = '0 UGX';
    const modal = document.getElementById('sale-modal');
    modal.classList.remove('hidden'); modal.classList.add('flex');
    document.getElementById('sale-qty').oninput = updateItemTotal;
    document.getElementById('sale-price').oninput = updateItemTotal;
}
function updateItemTotal() {
    const qty = parseInt(document.getElementById('sale-qty').value) || 0;
    const price = parseInt(document.getElementById('sale-price').value) || 0;
    document.getElementById('item-total').textContent = formatMoney(qty * price);
}
function closeModal() {
    const modal = document.getElementById('sale-modal');
    modal.classList.add('hidden'); modal.classList.remove('flex');
}
function addToCart() {
    const qty = parseInt(document.getElementById('sale-qty').value);
    const price = parseInt(document.getElementById('sale-price').value);
    if (!qty || qty <= 0 || !price || price <= 0) return showToast('Enter valid quantity and price', 'error');
    if (price < tempItem.min_price || price > tempItem.max_price) return showToast('Price is outside the allowed range', 'error');
    if (qty > tempItem.stock) return showToast('Not enough stock available', 'error');
    const existing = cart.find(c => c.id === tempItem.id);
    if (existing) {
        if (existing.qty + qty > tempItem.stock) return showToast('Exceeds available stock', 'error');
        existing.qty += qty;
        existing.total = existing.qty * existing.price;
    } else {
        cart.push({ id: tempItem.id, name: tempItem.name, qty, price, total: qty * price });
    }
    closeModal();
    updateCart();
    showToast('Added to cart');
}

// ==================== CART DRAWER ====================
function openCart() {
    document.getElementById('cart-drawer').classList.remove('translate-x-full');
    document.getElementById('cart-overlay').classList.remove('hidden');
}
function closeCart() {
    document.getElementById('cart-drawer').classList.add('translate-x-full');
    document.getElementById('cart-overlay').classList.add('hidden');
}

// ==================== CART ====================
async function updateCart() {
    const list = document.getElementById('cart-list');
    let subtotal = cart.reduce((s, i) => s + i.total, 0);

    const badge = document.getElementById('cart-badge');
    const itemCount = cart.reduce((s, i) => s + i.qty, 0);
    if (itemCount > 0) {
        badge.textContent = itemCount > 99 ? '99+' : itemCount;
        badge.classList.remove('hidden');
    } else {
        badge.classList.add('hidden');
    }

    if (!cart.length) {
        list.innerHTML = '<div class="h-full flex flex-col items-center justify-center text-center text-slate-400 py-16"><div class="text-4xl mb-3">🛒</div>Cart is empty</div>';
    } else {
        list.innerHTML = cart.map((item, idx) => `
            <div class="flex items-center justify-between gap-3 border border-border rounded-xl p-3 hover:bg-slate-50 transition">
                <div class="min-w-0">
                    <div class="font-semibold text-sm text-surface truncate">${escapeHtml(item.name)}</div>
                    <div class="text-xs text-slate-400">${item.qty} × ${formatMoney(item.price)}</div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <div class="font-bold text-sm text-emerald-600">${formatMoney(item.total)}</div>
                    <button onclick="removeFromCart(${idx})" class="w-7 h-7 rounded-full bg-red-50 text-red-500 hover:bg-red-500 hover:text-white transition flex items-center justify-center text-xs">✕</button>
                </div>
            </div>
        `).join('');
    }

    if (!taxRate) {
        try {
            const r = await apiCall('getTaxRate', {}, 'GET');
            taxRate = r.tax_rate || 0;
        } catch (e) { taxRate = 0; }
    }
    const tax = Math.round(subtotal * taxRate / 100);
    const total = subtotal + tax;
    document.getElementById('cart-subtotal').textContent = formatMoney(subtotal);
    document.getElementById('cart-tax-label').textContent = `Tax (${taxRate}%)`;
    document.getElementById('cart-tax').textContent = formatMoney(tax);
    document.getElementById('cart-total').textContent = formatMoney(total);
}
function removeFromCart(idx) {
    cart.splice(idx, 1);
    updateCart();
}
function clearCart() {
    if (!cart.length) return;
    if (confirm('Clear the entire cart?')) {
        cart = [];
        updateCart();
    }
}

// ==================== CHECKOUT / RECEIPT ====================
async function processSale() {
    if (!cart.length) return showToast('Cart is empty', 'error');
    const customerName = document.getElementById('customer-name').value.trim();
    const result = await apiCall('processSale', { cart: JSON.stringify(cart), customer_name: customerName });
    if (!result.success) return showToast(result.error || 'Sale failed', 'error');
    closeCart();
    showReceipt(result.receipt, { reprint: false });
    cart = [];
    updateCart();
    document.getElementById('customer-name').value = '';
    renderItems();
    loadTodayStats();
}

async function viewReceipt(transactionCode) {
    const result = await apiCall('getTransactionReceipt', { transaction_code: transactionCode }, 'GET');
    if (!result.success) return showToast(result.error || 'Could not load receipt', 'error');
    showReceipt(result.receipt, { reprint: true });
}

function showReceipt(r, opts = {}) {
    const store = r.store || {};
    document.getElementById('r-store-name').textContent = store.name || 'Trust Hardware';
    const addrEl = document.getElementById('r-store-address');
    const phoneEl = document.getElementById('r-store-phone');
    addrEl.textContent = store.address || '';
    addrEl.classList.toggle('hidden', !store.address);
    phoneEl.textContent = store.phone || '';
    phoneEl.classList.toggle('hidden', !store.phone);

    document.getElementById('r-txn').textContent = r.transaction_code;
    document.getElementById('r-date').textContent = formatDateTime(r.date);
    document.getElementById('r-cashier').textContent = r.cashier;
    const customerRow = document.getElementById('r-customer-row');
    if (r.customer_name) {
        document.getElementById('r-customer').textContent = r.customer_name;
        customerRow.classList.remove('hidden');
    } else {
        customerRow.classList.add('hidden');
    }
    document.getElementById('r-items').innerHTML = r.items.map(i => `
        <div>
            <div class="flex justify-between font-semibold"><span>${escapeHtml(i.name)}</span><span>${formatMoney(i.total)}</span></div>
            <div class="text-slate-500 text-[11px]">${i.qty} x ${formatMoney(i.price)}</div>
        </div>
    `).join('');
    document.getElementById('r-subtotal').textContent = formatMoney(r.subtotal);
    document.getElementById('r-tax-label').textContent = `Tax (${r.tax_rate}%)`;
    document.getElementById('r-tax').textContent = formatMoney(r.tax);
    document.getElementById('r-total').textContent = formatMoney(r.total);
    document.getElementById('r-footer').textContent = store.footer || 'Thank you for shopping with us!';

    const closeBtn = document.getElementById('receipt-close-btn');
    closeBtn.textContent = opts.reprint ? 'Close' : 'New Sale';

    // The document title becomes the suggested filename when printing or
    // saving as PDF, and also what shows in the browser tab while the
    // receipt is open.
    document.title = `Trust Hardware - Receipt ${r.transaction_code}`;

    const modal = document.getElementById('receipt-modal');
    modal.classList.remove('hidden'); modal.classList.add('flex');
}
function startNewSale() {
    const modal = document.getElementById('receipt-modal');
    modal.classList.add('hidden'); modal.classList.remove('flex');
    document.title = defaultPageTitle;
}

// ==================== STOCK VIEW ====================
async function renderStock() {
    const data = await apiCall('getInventory', {}, 'GET');
    const term = document.getElementById('stock-search').value.toLowerCase();
    const filtered = data.filter(p => p.name.toLowerCase().includes(term) || (p.sku || '').toLowerCase().includes(term));
    const tbody = document.getElementById('stock-table');
    if (!filtered.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-10 text-center text-slate-400">No products found</td></tr>';
        return;
    }
    tbody.innerHTML = filtered.map(p => {
        const low = p.stock > 0 && p.stock <= p.low_stock_threshold;
        const badge = low
            ? `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-600">${p.stock} low</span>`
            : `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-600">${p.stock} in stock</span>`;
        return `
        <tr class="border-t border-border hover:bg-slate-50">
            <td class="px-6 py-3 font-medium text-surface">${escapeHtml(p.name)}</td>
            <td class="px-6 py-3 text-slate-500 font-mono text-xs">${escapeHtml(p.sku || '—')}</td>
            <td class="px-6 py-3"><span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-600">${escapeHtml(p.category_name || 'Uncategorized')}</span></td>
            <td class="px-6 py-3">${badge}</td>
            <td class="px-6 py-3 text-slate-600">${formatMoney(p.min_price)} - ${formatMoney(p.max_price)}</td>
        </tr>`;
    }).join('');
}

// ==================== MY SALES ====================
async function renderMySales() {
    const sales = await apiCall('getSales', {}, 'GET');
    const tbody = document.getElementById('my-sales-table');
    if (!sales.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-10 text-center text-slate-400">No sales recorded yet</td></tr>';
        return;
    }
    tbody.innerHTML = sales.map(s => `
        <tr class="border-t border-border hover:bg-slate-50">
            <td class="px-6 py-3 font-mono text-xs text-slate-500">${escapeHtml(s.transaction_code || '—')}</td>
            <td class="px-6 py-3 text-slate-600">${formatDateTime(s.sale_date)}</td>
            <td class="px-6 py-3 font-medium text-surface">${escapeHtml(s.product_name)}</td>
            <td class="px-6 py-3 text-slate-600">${s.quantity}</td>
            <td class="px-6 py-3 font-semibold text-emerald-600">${formatMoney(s.total)}</td>
            <td class="px-6 py-3 text-right">
                ${s.transaction_code ? `<button onclick="viewReceipt('${escapeHtml(s.transaction_code)}')" class="inline-flex items-center gap-1.5 text-accent hover:text-white hover:bg-accent px-3 py-1.5 rounded-lg text-xs font-semibold bg-orange-50 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z"/></svg>
                    Receipt
                </button>` : '—'}
            </td>
        </tr>
    `).join('');
}

// ==================== TODAY STATS ====================
async function loadTodayStats() {
    const stats = await apiCall('getTodayStats', {}, 'GET');
    document.getElementById('today-sales-count').textContent = stats.sales_count || 0;
    document.getElementById('today-revenue').textContent = formatMoney(stats.revenue || 0);
}

// ==================== INIT ====================
updateCart();
renderItems();
loadTodayStats();

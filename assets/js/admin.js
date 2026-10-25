// ==================== SESSION CHECK ====================
(async function () {
    const res = await fetch('admin_api.php?action=checkSession');
    const data = await res.json();
    if (!data.valid) window.location.href = 'index.php';
})();

let allCategories = [];
let allProducts = [];

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
// so the displayed time is correct no matter where the admin's device is.
function toKampalaDate(mysqlDateStr) {
    return new Date(String(mysqlDateStr).replace(' ', 'T') + '+03:00');
}
function formatDateTime(mysqlDateStr) {
    return toKampalaDate(mysqlDateStr).toLocaleString('en-US', {
        timeZone: 'Africa/Kampala', year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit'
    });
}
function formatDate(mysqlDateStr) {
    return toKampalaDate(mysqlDateStr).toLocaleDateString('en-US', {
        timeZone: 'Africa/Kampala', year: 'numeric', month: 'short', day: 'numeric'
    });
}
async function apiCall(action, data = {}, method = 'POST') {
    if (method === 'GET') {
        const qs = new URLSearchParams({ action, ...data }).toString();
        const res = await fetch(`admin_api.php?${qs}`);
        return res.json();
    }
    const formData = new FormData();
    formData.append('action', action);
    for (let k in data) formData.append(k, data[k]);
    const res = await fetch('admin_api.php', { method: 'POST', body: formData });
    return res.json();
}
function showToast(msg, type = 'success') {
    const toast = document.getElementById('toast');
    const body = document.getElementById('toast-body');
    body.textContent = msg;
    body.className = 'shadow-xl rounded-xl px-5 py-4 text-sm font-medium min-w-[240px] bg-white border-l-4 ' +
        (type === 'success' ? 'border-emerald-500 text-slate-700' : 'border-red-500 text-slate-700');
    toast.classList.remove('hidden');
    setTimeout(() => toast.classList.add('hidden'), 3000);
}
let confirmCallback = null;
function askConfirm(title, body, onConfirm) {
    document.getElementById('confirm-title').textContent = title;
    document.getElementById('confirm-body').textContent = body;
    confirmCallback = onConfirm;
    document.getElementById('confirm-modal').classList.remove('hidden');
    document.getElementById('confirm-modal').classList.add('flex');
}
function closeConfirm() {
    document.getElementById('confirm-modal').classList.add('hidden');
    document.getElementById('confirm-modal').classList.remove('flex');
    confirmCallback = null;
}
document.getElementById('confirm-ok').addEventListener('click', () => {
    if (confirmCallback) confirmCallback();
    closeConfirm();
});

// ==================== SIDEBAR / NAV ====================
function openSidebar() {
    document.getElementById('sidebar').classList.remove('-translate-x-full');
    document.getElementById('sidebar-overlay').classList.remove('hidden');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.add('-translate-x-full');
    document.getElementById('sidebar-overlay').classList.add('hidden');
}
const PAGE_TITLES = { dashboard: 'Dashboard', inventory: 'Inventory', categories: 'Categories', employees: 'Employees', sales: 'Sales History', settings: 'Settings' };
function showSection(id, btn) {
    document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    document.querySelectorAll('.nav-link').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    document.getElementById('page-title').textContent = PAGE_TITLES[id] || '';
    if (id === 'dashboard') renderDashboard();
    if (id === 'inventory') renderInventory();
    if (id === 'categories') renderCategories();
    if (id === 'employees') renderEmployees();
    if (id === 'sales') renderSales();
    if (id === 'settings') loadReceiptSettings();
    closeSidebar();
}

// ==================== DATE ====================
document.getElementById('current-date').textContent = new Date().toLocaleDateString('en-US', { timeZone: 'Africa/Kampala', weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

// ==================== DASHBOARD ====================
function setDashboardToday() {
    const today = new Date().toLocaleDateString('en-CA', { timeZone: 'Africa/Kampala' }); // YYYY-MM-DD
    document.getElementById('dash-from').value = today;
    document.getElementById('dash-to').value = today;
    renderDashboard();
}
function clearDashboardRange() {
    document.getElementById('dash-from').value = '';
    document.getElementById('dash-to').value = '';
    renderDashboard();
}

async function renderDashboard() {
    const from = document.getElementById('dash-from').value;
    const to = document.getElementById('dash-to').value;
    const m = await apiCall('getMetrics', { from, to }, 'GET');
    document.getElementById('m-revenue').textContent = formatMoney(m.revenue);
    document.getElementById('m-profit').textContent = formatMoney(m.profit);
    document.getElementById('m-sales-count').textContent = m.sales_count;
    document.getElementById('m-items').textContent = m.active_items;
    document.getElementById('m-lowstock').textContent = m.low_stock;

    const label = document.getElementById('dash-period-label');
    if (!from && !to) {
        label.textContent = 'Showing all-time totals';
    } else if (from && to && from === to) {
        label.textContent = `Showing ${from}`;
    } else {
        label.textContent = `Showing ${from || 'the beginning'} to ${to || 'now'}`;
    }

    const products = await apiCall('getInventory', {}, 'GET');
    const low = products.filter(p => Number(p.stock) <= Number(p.low_stock_threshold)).slice(0, 8);
    const tbody = document.getElementById('dash-lowstock-table');
    if (!low.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="px-6 py-8 text-center text-slate-400">All stock levels are healthy 🎉</td></tr>';
        return;
    }
    tbody.innerHTML = low.map(p => `
        <tr class="border-t border-border">
            <td class="px-6 py-3 font-medium text-surface">${escapeHtml(p.name)}</td>
            <td class="px-6 py-3 text-slate-500">${escapeHtml(p.sku || '—')}</td>
            <td class="px-6 py-3 text-slate-500">${escapeHtml(p.category_name || 'Uncategorized')}</td>
            <td class="px-6 py-3">${stockBadge(p)}</td>
        </tr>
    `).join('');
}

function stockBadge(p) {
    const stock = Number(p.stock);
    const threshold = Number(p.low_stock_threshold);
    if (stock <= 0) return `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-red-50 text-red-600">Out of stock</span>`;
    if (stock <= threshold) return `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-600">${stock} low</span>`;
    return `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-600">${stock} in stock</span>`;
}

// ==================== INVENTORY ====================
async function renderInventory() {
    allProducts = await apiCall('getInventory', {}, 'GET');
    if (!allCategories.length) allCategories = await apiCall('getCategories', {}, 'GET');
    populateCategoryFilters();

    const term = document.getElementById('inv-search').value.toLowerCase();
    const catFilter = document.getElementById('inv-cat-filter').value;
    const stockFilter = document.getElementById('inv-stock-filter').value;

    let filtered = allProducts.filter(p => {
        const matchesTerm = p.name.toLowerCase().includes(term) || (p.sku || '').toLowerCase().includes(term);
        const matchesCat = !catFilter || p.category_name === catFilter;
        const stock = Number(p.stock), threshold = Number(p.low_stock_threshold);
        let matchesStock = true;
        if (stockFilter === 'low') matchesStock = stock > 0 && stock <= threshold;
        if (stockFilter === 'out') matchesStock = stock <= 0;
        return matchesTerm && matchesCat && matchesStock;
    });

    const tbody = document.getElementById('inventory-table');
    if (!filtered.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="px-6 py-10 text-center text-slate-400">No products found</td></tr>';
        return;
    }
    tbody.innerHTML = filtered.map(p => `
        <tr class="border-t border-border hover:bg-slate-50">
            <td class="px-6 py-3.5 font-medium text-surface">${escapeHtml(p.name)}</td>
            <td class="px-6 py-3.5 text-slate-500 font-mono text-xs">${escapeHtml(p.sku || '—')}</td>
            <td class="px-6 py-3.5">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-600">${escapeHtml(p.category_name || 'Uncategorized')}</span>
            </td>
            <td class="px-6 py-3.5">${stockBadge(p)}</td>
            <td class="px-6 py-3.5 text-slate-500">${p.cost_price !== null && p.cost_price !== undefined ? formatMoney(p.cost_price) : '—'}</td>
            <td class="px-6 py-3.5 text-slate-600">${formatMoney(p.min_price)} - ${formatMoney(p.max_price)}</td>
            <td class="px-6 py-3.5 text-right whitespace-nowrap">
                <button onclick="editProduct(${p.id})" class="text-blue-600 hover:text-white hover:bg-blue-600 px-3 py-1.5 rounded-lg text-xs font-semibold bg-blue-50 transition mr-1.5">Edit</button>
                <button onclick="deleteProduct(${p.id}, '${escapeHtml(p.name).replace(/'/g, "\\'")}')" class="text-red-600 hover:text-white hover:bg-red-600 px-3 py-1.5 rounded-lg text-xs font-semibold bg-red-50 transition">Delete</button>
            </td>
        </tr>
    `).join('');
}

function populateCategoryFilters() {
    const filter = document.getElementById('inv-cat-filter');
    const current = filter.value;
    filter.innerHTML = '<option value="">All Categories</option>' + allCategories.map(c => `<option value="${escapeHtml(c.name)}">${escapeHtml(c.name)}</option>`).join('');
    filter.value = current;

    const select = document.getElementById('p-cat');
    const currentP = select.value;
    select.innerHTML = '<option value="">Select category</option>' + allCategories.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
    select.value = currentP;
}

function openProductModal() {
    document.getElementById('product-modal-title').textContent = 'Add Product';
    document.getElementById('p-id').value = '';
    document.getElementById('p-name').value = '';
    document.getElementById('p-sku').value = '';
    document.getElementById('p-cat').value = '';
    document.getElementById('p-stock').value = '';
    document.getElementById('p-threshold').value = 10;
    document.getElementById('p-cost').value = '';
    document.getElementById('p-min').value = '';
    document.getElementById('p-max').value = '';
    const modal = document.getElementById('product-modal');
    modal.classList.remove('hidden'); modal.classList.add('flex');
}
function closeProductModal() {
    const modal = document.getElementById('product-modal');
    modal.classList.add('hidden'); modal.classList.remove('flex');
}
function editProduct(id) {
    const p = allProducts.find(x => x.id == id);
    if (!p) return;
    document.getElementById('product-modal-title').textContent = 'Edit Product';
    document.getElementById('p-id').value = p.id;
    document.getElementById('p-name').value = p.name;
    document.getElementById('p-sku').value = p.sku || '';
    document.getElementById('p-cat').value = p.category_id || '';
    document.getElementById('p-stock').value = p.stock;
    document.getElementById('p-threshold').value = p.low_stock_threshold;
    document.getElementById('p-cost').value = p.cost_price ?? '';
    document.getElementById('p-min').value = p.min_price;
    document.getElementById('p-max').value = p.max_price;
    const modal = document.getElementById('product-modal');
    modal.classList.remove('hidden'); modal.classList.add('flex');
}
async function saveProduct() {
    const id = document.getElementById('p-id').value;
    const payload = {
        name: document.getElementById('p-name').value.trim(),
        sku: document.getElementById('p-sku').value.trim(),
        category_id: document.getElementById('p-cat').value,
        stock: document.getElementById('p-stock').value || 0,
        low_stock_threshold: document.getElementById('p-threshold').value || 10,
        cost_price: document.getElementById('p-cost').value,
        min_price: document.getElementById('p-min').value,
        max_price: document.getElementById('p-max').value,
    };
    if (!payload.name || payload.min_price === '' || payload.max_price === '') {
        showToast('Please fill in all required fields', 'error');
        return;
    }
    if (Number(payload.min_price) > Number(payload.max_price)) {
        showToast('Min price cannot exceed max price', 'error');
        return;
    }
    if (id) payload.id = id;
    const result = await apiCall(id ? 'updateProduct' : 'addProduct', payload);
    if (result.success) {
        showToast(id ? 'Product updated' : 'Product added');
        closeProductModal();
        renderInventory();
    } else {
        showToast(result.error || 'Something went wrong', 'error');
    }
}
function deleteProduct(id, name) {
    askConfirm('Delete product?', `"${name}" will be permanently removed from inventory.`, async () => {
        const result = await apiCall('deleteProduct', { id });
        if (result.success) { showToast('Product deleted'); renderInventory(); }
        else showToast(result.error || 'Delete failed', 'error');
    });
}

// ==================== CATEGORIES ====================
async function renderCategories() {
    allCategories = await apiCall('getCategories', {}, 'GET');
    const grid = document.getElementById('categories-grid');
    if (!allCategories.length) {
        grid.innerHTML = '<div class="col-span-full text-center text-slate-400 py-10">No categories yet</div>';
        return;
    }
    grid.innerHTML = allCategories.map(c => `
        <div class="bg-white rounded-2xl border border-border shadow-sm p-5 text-center relative group">
            <button onclick="deleteCategory(${c.id}, '${escapeHtml(c.name).replace(/'/g, "\\'")}')" class="absolute top-3 right-3 w-7 h-7 rounded-full bg-red-50 text-red-600 opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-xs">✕</button>
            <div class="w-12 h-12 mx-auto rounded-xl bg-orange-50 text-accent flex items-center justify-center text-xl mb-3">🏷️</div>
            <div class="font-semibold text-surface text-sm">${escapeHtml(c.name)}</div>
            <div class="text-xs text-slate-400 mt-1">${c.product_count} product${c.product_count == 1 ? '' : 's'}</div>
        </div>
    `).join('');
}
function openCategoryModal() {
    document.getElementById('cat-name').value = '';
    const modal = document.getElementById('category-modal');
    modal.classList.remove('hidden'); modal.classList.add('flex');
}
function closeCategoryModal() {
    const modal = document.getElementById('category-modal');
    modal.classList.add('hidden'); modal.classList.remove('flex');
}
async function saveCategory() {
    const name = document.getElementById('cat-name').value.trim();
    if (!name) { showToast('Enter a category name', 'error'); return; }
    const result = await apiCall('addCategory', { name });
    if (result.success) { showToast('Category added'); closeCategoryModal(); renderCategories(); }
    else showToast(result.error || 'Could not add category', 'error');
}
function deleteCategory(id, name) {
    askConfirm('Delete category?', `"${name}" will be removed. Products in this category will become uncategorized.`, async () => {
        const result = await apiCall('deleteCategory', { id });
        if (result.success) { showToast('Category deleted'); renderCategories(); }
        else showToast(result.error || 'Delete failed', 'error');
    });
}

// ==================== EMPLOYEES ====================
async function renderEmployees() {
    const employees = await apiCall('getEmployees', {}, 'GET');
    const tbody = document.getElementById('employees-table');
    if (!employees.length) {
        tbody.innerHTML = '<tr><td colspan="3" class="px-6 py-10 text-center text-slate-400">No employees yet — add one to get started</td></tr>';
        return;
    }
    tbody.innerHTML = employees.map(e => `
        <tr class="border-t border-border hover:bg-slate-50">
            <td class="px-6 py-3.5 font-medium text-surface">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-accent/10 text-accent flex items-center justify-center font-bold text-xs">${escapeHtml(e.full_name || e.username).charAt(0).toUpperCase()}</div>
                    ${escapeHtml(e.full_name || e.username)}
                </div>
            </td>
            <td class="px-6 py-3.5 text-slate-500">${formatDate(e.created_at)}</td>
            <td class="px-6 py-3.5 text-right whitespace-nowrap">
                <button onclick='editEmployee(${JSON.stringify(e.id)})' class="text-blue-600 hover:text-white hover:bg-blue-600 px-3 py-1.5 rounded-lg text-xs font-semibold bg-blue-50 transition mr-1.5">Edit</button>
                <button onclick="deleteEmployee(${e.id}, '${escapeHtml(e.full_name || e.username).replace(/'/g, "\\'")}')" class="text-red-600 hover:text-white hover:bg-red-600 px-3 py-1.5 rounded-lg text-xs font-semibold bg-red-50 transition">Delete</button>
            </td>
        </tr>
    `).join('');
}
let allEmployees = [];
function openEmployeeModal() {
    document.getElementById('employee-modal-title').textContent = 'Add Employee';
    document.getElementById('emp-id').value = '';
    document.getElementById('emp-name').value = '';
    document.getElementById('emp-pass').value = '';
    document.getElementById('emp-pass-label').textContent = 'Password';
    document.getElementById('emp-pass-hint').classList.add('hidden');
    const modal = document.getElementById('employee-modal');
    modal.classList.remove('hidden'); modal.classList.add('flex');
}
function closeEmployeeModal() {
    const modal = document.getElementById('employee-modal');
    modal.classList.add('hidden'); modal.classList.remove('flex');
}
async function editEmployee(id) {
    if (!allEmployees.length) allEmployees = await apiCall('getEmployees', {}, 'GET');
    const emp = allEmployees.find(e => e.id == id);
    if (!emp) return;
    document.getElementById('employee-modal-title').textContent = 'Edit Employee';
    document.getElementById('emp-id').value = emp.id;
    document.getElementById('emp-name').value = emp.full_name || emp.username;
    document.getElementById('emp-pass').value = '';
    document.getElementById('emp-pass-label').textContent = 'New Password (optional)';
    document.getElementById('emp-pass-hint').classList.remove('hidden');
    const modal = document.getElementById('employee-modal');
    modal.classList.remove('hidden'); modal.classList.add('flex');
}
async function saveEmployee() {
    const id = document.getElementById('emp-id').value;
    const name = document.getElementById('emp-name').value.trim();
    const password = document.getElementById('emp-pass').value;
    if (!name) { showToast('Enter a name', 'error'); return; }
    if (!id && password.length < 6) { showToast('Password must be at least 6 characters', 'error'); return; }
    const payload = id ? { id, name, password } : { name, password };
    const result = await apiCall(id ? 'updateEmployee' : 'addEmployee', payload);
    if (result.success) {
        showToast(result.message || 'Saved');
        closeEmployeeModal();
        allEmployees = [];
        renderEmployees();
    } else {
        showToast(result.message || 'Something went wrong', 'error');
    }
}
function deleteEmployee(id, name) {
    askConfirm('Remove employee?', `"${name}" will no longer be able to log in to the POS.`, async () => {
        const result = await apiCall('deleteEmployee', { id });
        if (result.success) { showToast('Employee removed'); allEmployees = []; renderEmployees(); }
        else showToast(result.error || 'Delete failed', 'error');
    });
}

// ==================== SALES ====================
let cashiersLoaded = false;
async function populateCashierFilter() {
    if (cashiersLoaded) return;
    const cashiers = await apiCall('getCashiers', {}, 'GET');
    const select = document.getElementById('sales-cashier');
    const current = select.value;
    select.innerHTML = '<option value="">All Employees</option>' + cashiers.map(c => `<option value="${escapeHtml(c)}">${escapeHtml(c)}</option>`).join('');
    select.value = current;
    cashiersLoaded = true;
}
function filterByCashier(name) {
    cashiersLoaded = false;
    document.getElementById('sales-cashier').value = '';
    showSection('sales', document.querySelector('[onclick*="\'sales\'"]'));
    populateCashierFilter().then(() => {
        document.getElementById('sales-cashier').value = name;
        renderSales();
    });
}
async function renderSales() {
    await populateCashierFilter();
    const search = document.getElementById('sales-search').value;
    const from = document.getElementById('sales-from').value;
    const to = document.getElementById('sales-to').value;
    const cashier = document.getElementById('sales-cashier').value;
    const sales = await apiCall('getSales', { search, from, to, cashier }, 'GET');
    const tbody = document.getElementById('sales-table');
    if (!sales.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="px-6 py-10 text-center text-slate-400">No transactions found</td></tr>';
        return;
    }
    tbody.innerHTML = sales.map(s => `
        <tr class="border-t border-border hover:bg-slate-50">
            <td class="px-6 py-3 font-mono text-xs text-slate-500">${escapeHtml(s.transaction_code || '—')}</td>
            <td class="px-6 py-3 text-slate-600">${formatDateTime(s.sale_date)}</td>
            <td class="px-6 py-3 font-medium text-surface">${escapeHtml(s.product_name)}</td>
            <td class="px-6 py-3 text-slate-600">${s.quantity}</td>
            <td class="px-6 py-3 text-slate-600">${formatMoney(s.price)}</td>
            <td class="px-6 py-3 text-slate-500">${s.cost_price !== null ? formatMoney(s.cost_price) : '—'}</td>
            <td class="px-6 py-3 font-semibold text-emerald-600">${formatMoney(s.total)}</td>
            <td class="px-6 py-3 font-semibold ${s.profit === null ? 'text-slate-400' : (Number(s.profit) >= 0 ? 'text-emerald-600' : 'text-red-600')}">${s.profit !== null ? formatMoney(s.profit) : '—'}</td>
            <td class="px-6 py-3">${s.cashier ? `<button onclick="filterByCashier('${escapeHtml(s.cashier).replace(/'/g, "\\'")}')" class="text-blue-600 hover:text-accent hover:underline font-medium">${escapeHtml(s.cashier)}</button>` : '—'}</td>
        </tr>
    `).join('');
}

// ==================== SETTINGS ====================
async function updateAdminPassword() {
    const current = document.getElementById('admin-current-pass').value;
    const next = document.getElementById('admin-new-pass').value;
    if (!current || !next) { showToast('Fill in both fields', 'error'); return; }
    const result = await apiCall('updateAdminPassword', { current, new: next });
    showToast(result.message || (result.success ? 'Updated' : 'Failed'), result.success ? 'success' : 'error');
    if (result.success) {
        document.getElementById('admin-current-pass').value = '';
        document.getElementById('admin-new-pass').value = '';
    }
}

async function loadReceiptSettings() {
    const s = await apiCall('getReceiptSettings', {}, 'GET');
    document.getElementById('rs-store-name').value = s.store_name || '';
    document.getElementById('rs-store-address').value = s.store_address || '';
    document.getElementById('rs-store-phone').value = s.store_phone || '';
    document.getElementById('rs-footer').value = s.receipt_footer || '';
    document.getElementById('rs-width').value = s.receipt_width || '80';
    document.getElementById('rs-tax-rate').value = s.tax_rate || '0';
}
async function saveReceiptSettings() {
    const payload = {
        store_name: document.getElementById('rs-store-name').value.trim(),
        store_address: document.getElementById('rs-store-address').value.trim(),
        store_phone: document.getElementById('rs-store-phone').value.trim(),
        receipt_footer: document.getElementById('rs-footer').value.trim(),
        receipt_width: document.getElementById('rs-width').value,
        tax_rate: document.getElementById('rs-tax-rate').value || '0',
    };
    const result = await apiCall('updateReceiptSettings', payload);
    showToast(result.message || (result.success ? 'Saved' : 'Failed'), result.success ? 'success' : 'error');
}

// ==================== INIT ====================
renderDashboard();

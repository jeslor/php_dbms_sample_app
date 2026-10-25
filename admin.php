<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}
$displayName = $_SESSION['full_name'] ?: $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - Trust Hardware</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        fontFamily: { sans: ['Inter', 'sans-serif'] },
        colors: {
          surface: '#0F172A',
          bg: '#F8FAFC',
          border: '#E2E8F0',
          accent: '#EA580C',
          'accent-dark': '#C2410C',
        }
      }
    }
  }
</script>
<style>
  body { background: #F8FAFC; }
  ::-webkit-scrollbar { width: 4px; height: 4px; }
  ::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 10px; }
  * { scrollbar-width: thin; scrollbar-color: #CBD5E1 transparent; }
  .section { display: none; }
  .section.active { display: block; animation: fadeIn .25s ease; }
  @keyframes fadeIn { from { opacity:0; transform: translateY(6px);} to { opacity:1; transform:none; } }
  .nav-link.active { background: rgba(234,88,12,0.15); color: #fff; border-right: 3px solid #EA580C; }
  .nav-link.active svg { color: #EA580C; }
  /* iOS Safari's toolbar grows/shrinks during scroll, which 100vh doesn't
     account for and causes jank on a fixed-height, overflow-hidden shell.
     100dvh tracks the real visible viewport; 100vh is the fallback for
     browsers that don't support dvh yet. */
  .app-shell { height: 100vh; height: 100dvh; }
  /* Only elements that are genuinely meant to scroll vertically (the
     sidebar nav, the page's own scroll wrapper) get the touch-scrolling
     treatment. Applying -webkit-overflow-scrolling:touch to the
     horizontal-only table wrappers below was actually making iOS treat
     them as their own independent scroll region and trap the gesture --
     the opposite of what was intended. */
  .overflow-y-auto, .overflow-auto {
    -webkit-overflow-scrolling: touch;
    overscroll-behavior: contain;
  }
  /* Note: CSS forces overflow-y to compute as "auto" on any element whose
     overflow-x is non-visible -- there is no way to declare overflow-y:
     visible back (even with !important) once overflow-x is non-visible on
     the same element. Scoping -webkit-overflow-scrolling away from these
     (above) is the real fix; only restrict horizontal chaining here. */
  .overflow-x-auto {
    overscroll-behavior-x: contain;
  }
</style>
</head>
<body class="font-sans text-slate-800 antialiased">

<div class="app-shell overflow-hidden flex">

  <!-- Mobile overlay -->
  <div id="sidebar-overlay" class="fixed inset-0 bg-black/50 z-30 hidden lg:hidden" onclick="closeSidebar()"></div>

  <!-- Sidebar (always fixed to the viewport so it never stretches with page content) -->
  <aside id="sidebar" class="fixed inset-y-0 left-0 w-[260px] bg-surface text-slate-300 flex flex-col z-40 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out">
    <div class="h-20 flex items-center gap-3 px-6 border-b border-white/10">
      <div class="w-10 h-10 rounded-xl bg-accent flex items-center justify-center text-lg shadow-lg shadow-accent/30">🛠️</div>
      <div>
        <div class="text-white font-extrabold tracking-tight leading-none">TRUST <span class="text-accent">HARDWARE</span></div>
        <div class="text-[11px] text-slate-400 mt-1">Shop Management</div>
      </div>
    </div>

    <div class="px-6 py-4">
      <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide bg-accent/15 text-accent px-3 py-1.5 rounded-full">
        <span class="w-1.5 h-1.5 rounded-full bg-accent"></span> Administrator
      </span>
    </div>

    <nav class="flex-1 px-3 space-y-1 overflow-y-auto">
      <button onclick="showSection('dashboard', this)" class="nav-link active w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium hover:bg-white/5 hover:text-white transition">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
        Dashboard
      </button>
      <button onclick="showSection('inventory', this)" class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium hover:bg-white/5 hover:text-white transition">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7L12 3 4 7m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        Inventory
      </button>
      <button onclick="showSection('categories', this)" class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium hover:bg-white/5 hover:text-white transition">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5.586a1 1 0 01.707.293l7.414 7.414a1 1 0 010 1.414l-7.586 7.586a1 1 0 01-1.414 0L3.293 12.293A1 1 0 013 11.586V7a4 4 0 014-4z"/></svg>
        Categories
      </button>
      <button onclick="showSection('employees', this)" class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium hover:bg-white/5 hover:text-white transition">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-4.13a4 4 0 10-4-4 4 4 0 004 4zm6 4a4 4 0 00-4-4"/></svg>
        Employees
      </button>
      <button onclick="showSection('sales', this)" class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium hover:bg-white/5 hover:text-white transition">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14"/></svg>
        Sales History
      </button>
      <button onclick="showSection('settings', this)" class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium hover:bg-white/5 hover:text-white transition">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        Settings
      </button>
    </nav>

    <div class="p-4 border-t border-white/10">
      <div class="flex items-center gap-3 px-2 py-2 mb-2">
        <div class="w-9 h-9 rounded-full bg-accent/20 text-accent flex items-center justify-center font-bold text-sm"><?= strtoupper(substr($displayName,0,1)) ?></div>
        <div class="min-w-0">
          <div class="text-sm font-semibold text-white truncate"><?= htmlspecialchars($displayName) ?></div>
          <div class="text-[11px] text-slate-400">Administrator</div>
        </div>
      </div>
      <button onclick="location.href='logout.php'" class="w-full flex items-center justify-center gap-2 bg-white/5 hover:bg-red-500/90 text-slate-200 hover:text-white text-sm font-medium py-2.5 rounded-xl transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        Logout
      </button>
    </div>
  </aside>

  <!-- Main -->
  <div class="flex-1 min-w-0 flex flex-col overflow-y-auto lg:ml-[260px]">
    <!-- Topbar -->
    <header class="sticky top-0 z-20 bg-white border-b border-border h-16 shrink-0 flex items-center justify-between px-4 sm:px-8">
      <div class="flex items-center gap-3">
        <button onclick="openSidebar()" class="lg:hidden w-10 h-10 flex items-center justify-center rounded-lg hover:bg-slate-100" aria-label="Open menu">
          <svg class="w-6 h-6 text-slate-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <h1 id="page-title" class="text-lg sm:text-xl font-bold text-surface">Dashboard</h1>
      </div>
      <div class="flex items-center gap-4">
        <span class="hidden sm:block text-sm text-slate-500" id="current-date"></span>
        <div class="w-9 h-9 rounded-full bg-surface text-white flex items-center justify-center font-bold text-sm"><?= strtoupper(substr($displayName,0,1)) ?></div>
      </div>
    </header>

    <main class="flex-1 p-4 sm:p-8">

      <!-- DASHBOARD -->
      <section id="dashboard" class="section active">
        <div class="bg-white rounded-2xl border border-border shadow-sm p-4 sm:p-5 mb-5 flex flex-wrap items-end gap-3">
          <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1.5">From</label>
            <input id="dash-from" type="date" onchange="renderDashboard()" class="px-3 py-2 rounded-xl border border-border bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30">
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1.5">To</label>
            <input id="dash-to" type="date" onchange="renderDashboard()" class="px-3 py-2 rounded-xl border border-border bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30">
          </div>
          <button onclick="setDashboardToday()" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-sm font-semibold text-slate-700 transition">Today</button>
          <button onclick="clearDashboardRange()" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-sm font-semibold text-slate-700 transition">All Time</button>
          <span id="dash-period-label" class="text-xs text-slate-400 ml-1">Showing all-time totals</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-8">
          <div class="bg-white rounded-2xl border border-border p-6 shadow-sm">
            <div class="flex items-center justify-between mb-3">
              <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Revenue</span>
              <div class="w-9 h-9 rounded-lg bg-orange-50 text-accent flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m9-8a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              </div>
            </div>
            <div class="text-2xl font-extrabold text-surface" id="m-revenue">—</div>
            <div class="text-xs text-slate-400 mt-1"><span id="m-sales-count">0</span> transactions</div>
          </div>
          <div class="bg-white rounded-2xl border border-border p-6 shadow-sm">
            <div class="flex items-center justify-between mb-3">
              <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Profit</span>
              <div class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
              </div>
            </div>
            <div class="text-2xl font-extrabold text-surface" id="m-profit">—</div>
            <div class="text-xs text-slate-400 mt-1">Based on products with a cost price set</div>
          </div>
          <div class="bg-white rounded-2xl border border-border p-6 shadow-sm">
            <div class="flex items-center justify-between mb-3">
              <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Active Items</span>
              <div class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7L12 3 4 7m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
              </div>
            </div>
            <div class="text-2xl font-extrabold text-surface" id="m-items">—</div>
            <div class="text-xs text-slate-400 mt-1">Products in catalog</div>
          </div>
          <div class="bg-white rounded-2xl border border-border p-6 shadow-sm">
            <div class="flex items-center justify-between mb-3">
              <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Low Stock Alerts</span>
              <div class="w-9 h-9 rounded-lg bg-red-50 text-red-600 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
              </div>
            </div>
            <div class="text-2xl font-extrabold text-surface" id="m-lowstock">—</div>
            <div class="text-xs text-slate-400 mt-1">Need restocking soon</div>
          </div>
        </div>

        <div class="bg-white rounded-2xl border border-border shadow-sm">
          <div class="px-6 py-5 border-b border-border flex items-center justify-between">
            <h3 class="font-bold text-surface">Low Stock Items</h3>
            <button onclick="showSection('inventory', document.querySelector('[onclick*=inventory]'))" class="text-xs font-semibold text-accent hover:text-accent-dark">View all →</button>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap">
              <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
                <tr><th class="text-left px-6 py-3">Product</th><th class="text-left px-6 py-3">SKU</th><th class="text-left px-6 py-3">Category</th><th class="text-left px-6 py-3">Stock</th></tr>
              </thead>
              <tbody id="dash-lowstock-table"></tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- INVENTORY -->
      <section id="inventory" class="section">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
          <div>
            <h2 class="text-xl font-bold text-surface">Inventory</h2>
            <p class="text-sm text-slate-500">Manage products, stock levels and pricing</p>
          </div>
          <button onclick="openProductModal()" class="hidden sm:inline-flex items-center justify-center gap-2 bg-accent hover:bg-accent-dark text-white font-semibold px-5 py-3 rounded-xl shadow-lg shadow-accent/25 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Add Product
          </button>
        </div>

        <div class="bg-white rounded-2xl border border-border shadow-sm">
          <div class="sticky top-16 z-10 bg-white rounded-t-2xl p-3 sm:p-5 border-b border-border flex flex-wrap sm:flex-nowrap gap-2 sm:gap-3">
            <div class="relative flex-1 min-w-[110px] order-1 sm:order-2 sm:flex-none sm:w-56">
              <select id="inv-cat-filter" onchange="renderInventory()" class="appearance-none w-full px-2.5 py-2 sm:px-4 sm:py-2.5 pr-8 sm:pr-10 rounded-xl border border-border bg-slate-50 text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-accent/30">
                <option value="">All Categories</option>
              </select>
              <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-slate-400 absolute right-2.5 sm:right-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </div>
            <div class="relative flex-1 min-w-[110px] order-1 sm:order-3 sm:flex-none sm:w-48">
              <select id="inv-stock-filter" onchange="renderInventory()" class="appearance-none w-full px-2.5 py-2 sm:px-4 sm:py-2.5 pr-8 sm:pr-10 rounded-xl border border-border bg-slate-50 text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-accent/30">
                <option value="">All Stock Levels</option>
                <option value="low">Low Stock</option>
                <option value="out">Out of Stock</option>
              </select>
              <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-slate-400 absolute right-2.5 sm:right-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </div>
            <button onclick="openProductModal()" class="sm:hidden order-1 shrink-0 w-9 h-9 flex items-center justify-center bg-accent hover:bg-accent-dark text-white rounded-xl shadow-md shadow-accent/25 transition" aria-label="Add Product">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            </button>
            <div class="relative w-full order-2 sm:order-1 sm:w-auto sm:flex-1">
              <svg class="w-4 h-4 sm:w-5 sm:h-5 text-slate-400 absolute left-3 sm:left-3.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
              <input id="inv-search" oninput="renderInventory()" type="text" placeholder="Search by product name or SKU..." class="w-full pl-9 sm:pl-11 pr-4 py-2 sm:py-2.5 rounded-xl border border-border bg-slate-50 focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent text-xs sm:text-sm">
            </div>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap">
              <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
                <tr>
                  <th class="text-left px-6 py-3">Product</th>
                  <th class="text-left px-6 py-3">SKU</th>
                  <th class="text-left px-6 py-3">Category</th>
                  <th class="text-left px-6 py-3">Stock</th>
                  <th class="text-left px-6 py-3">Cost Price</th>
                  <th class="text-left px-6 py-3">Price Range</th>
                  <th class="text-right px-6 py-3">Actions</th>
                </tr>
              </thead>
              <tbody id="inventory-table"></tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- CATEGORIES -->
      <section id="categories" class="section">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
          <div>
            <h2 class="text-xl font-bold text-surface">Categories</h2>
            <p class="text-sm text-slate-500">Organize your product catalog</p>
          </div>
          <button onclick="openCategoryModal()" class="inline-flex items-center justify-center gap-2 bg-accent hover:bg-accent-dark text-white font-semibold px-5 py-3 rounded-xl shadow-lg shadow-accent/25 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Add Category
          </button>
        </div>
        <div id="categories-grid" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-5"></div>
      </section>

      <!-- EMPLOYEES -->
      <section id="employees" class="section">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
          <div>
            <h2 class="text-xl font-bold text-surface">Employees</h2>
            <p class="text-sm text-slate-500">Manage employee accounts for the Point of Sale</p>
          </div>
          <button onclick="openEmployeeModal()" class="inline-flex items-center justify-center gap-2 bg-accent hover:bg-accent-dark text-white font-semibold px-5 py-3 rounded-xl shadow-lg shadow-accent/25 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Add Employee
          </button>
        </div>

        <div class="bg-white rounded-2xl border border-border shadow-sm">
          <div class="overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap">
              <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
                <tr>
                  <th class="text-left px-6 py-3">Name</th>
                  <th class="text-left px-6 py-3">Added</th>
                  <th class="text-right px-6 py-3">Actions</th>
                </tr>
              </thead>
              <tbody id="employees-table"></tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- SALES HISTORY -->
      <section id="sales" class="section">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
          <div>
            <h2 class="text-xl font-bold text-surface">Sales History</h2>
            <p class="text-sm text-slate-500">All completed transactions, by every employee and admin</p>
          </div>
        </div>

        <!-- This list intentionally has no scroll container of its own -- it
             flows with the page. An internal scrollbar here used to compete
             with the page's own scroll (the page wouldn't move if your
             cursor/finger was over this panel), which felt broken on both
             desktop and mobile. -->
        <div class="bg-white rounded-2xl border border-border shadow-sm">
          <div class="sticky top-16 z-10 bg-white rounded-t-2xl p-3 sm:p-5 border-b border-border flex flex-wrap sm:flex-nowrap items-end gap-2 sm:gap-3">
            <div class="relative w-full order-1 sm:w-48 sm:order-1 sm:flex-none">
              <select id="sales-cashier" onchange="renderSales()" class="appearance-none w-full px-2.5 py-2 sm:px-4 sm:py-2.5 pr-8 sm:pr-10 rounded-xl border border-border bg-slate-50 text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-accent/30">
                <option value="">All Employees</option>
              </select>
              <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-slate-400 absolute right-2.5 sm:right-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </div>
            <div class="w-[calc(50%-4px)] sm:w-36 order-2 sm:order-2">
              <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1 sm:hidden">From</label>
              <input id="sales-from" onchange="renderSales()" type="date" class="w-full px-2.5 py-2 sm:px-4 sm:py-2.5 rounded-xl border border-border bg-slate-50 text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-accent/30">
            </div>
            <div class="w-[calc(50%-4px)] sm:w-36 order-2 sm:order-3">
              <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1 sm:hidden">To</label>
              <input id="sales-to" onchange="renderSales()" type="date" class="w-full px-2.5 py-2 sm:px-4 sm:py-2.5 rounded-xl border border-border bg-slate-50 text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-accent/30">
            </div>
            <div class="relative w-full order-3 sm:w-auto sm:flex-1 sm:order-4">
              <svg class="w-4 h-4 sm:w-5 sm:h-5 text-slate-400 absolute left-3 sm:left-3.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
              <input id="sales-search" oninput="renderSales()" type="text" placeholder="Search by product name..." class="w-full pl-9 sm:pl-11 pr-4 py-2 sm:py-2.5 rounded-xl border border-border bg-slate-50 focus:outline-none focus:ring-2 focus:ring-accent/30 text-xs sm:text-sm">
            </div>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap">
              <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
                <tr>
                  <th class="text-left px-6 py-3">Transaction</th>
                  <th class="text-left px-6 py-3">Date &amp; Time</th>
                  <th class="text-left px-6 py-3">Product</th>
                  <th class="text-left px-6 py-3">Qty</th>
                  <th class="text-left px-6 py-3">Unit Price</th>
                  <th class="text-left px-6 py-3">Cost Price</th>
                  <th class="text-left px-6 py-3">Total</th>
                  <th class="text-left px-6 py-3">Profit</th>
                  <th class="text-left px-6 py-3">Cashier</th>
                </tr>
              </thead>
              <tbody id="sales-table"></tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- SETTINGS -->
      <section id="settings" class="section">
        <div class="mb-6">
          <h2 class="text-xl font-bold text-surface">Settings</h2>
          <p class="text-sm text-slate-500">Manage account security and receipt details</p>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 max-w-4xl">
          <div class="bg-white rounded-2xl border border-border shadow-sm p-6">
            <h3 class="font-bold text-surface mb-1">Admin Password</h3>
            <p class="text-xs text-slate-500 mb-5">Update your own login password</p>
            <div class="space-y-3">
              <input id="admin-current-pass" type="password" placeholder="Current password" class="w-full px-4 py-2.5 rounded-xl border border-border bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30">
              <input id="admin-new-pass" type="password" placeholder="New password" class="w-full px-4 py-2.5 rounded-xl border border-border bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30">
              <button onclick="updateAdminPassword()" class="w-full bg-surface hover:bg-slate-800 text-white font-semibold py-2.5 rounded-xl transition">Update Password</button>
            </div>
          </div>

          <div class="bg-white rounded-2xl border border-border shadow-sm p-6">
            <h3 class="font-bold text-surface mb-1">Receipt Settings</h3>
            <p class="text-xs text-slate-500 mb-5">Shown on every printed receipt</p>
            <div class="space-y-3">
              <input id="rs-store-name" type="text" placeholder="Store name" class="w-full px-4 py-2.5 rounded-xl border border-border bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30">
              <input id="rs-store-address" type="text" placeholder="Store address (optional)" class="w-full px-4 py-2.5 rounded-xl border border-border bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30">
              <input id="rs-store-phone" type="text" placeholder="Phone number (optional)" class="w-full px-4 py-2.5 rounded-xl border border-border bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30">
              <input id="rs-footer" type="text" placeholder="Footer message" class="w-full px-4 py-2.5 rounded-xl border border-border bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30">
              <div class="grid grid-cols-2 gap-3">
                <div class="relative">
                  <select id="rs-width" class="appearance-none w-full px-4 py-2.5 pr-10 rounded-xl border border-border bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30">
                    <option value="80">80mm paper</option>
                    <option value="58">58mm paper</option>
                  </select>
                  <svg class="w-4 h-4 text-slate-400 absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </div>
                <div class="relative">
                  <input id="rs-tax-rate" type="number" min="0" max="100" step="0.1" placeholder="Tax %" class="w-full px-4 py-2.5 pr-10 rounded-xl border border-border bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30">
                  <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm">%</span>
                </div>
              </div>
              <button onclick="saveReceiptSettings()" class="w-full bg-accent hover:bg-accent-dark text-white font-semibold py-2.5 rounded-xl transition">Save Receipt Settings</button>
            </div>
          </div>
        </div>
        <p class="text-xs text-slate-400 mt-4">Looking to manage employee accounts? Head to the <button onclick="showSection('employees', document.querySelector('[onclick*=employees]'))" class="text-accent font-semibold hover:underline">Employees</button> page.</p>
      </section>

    </main>
  </div>
</div>

<!-- PRODUCT MODAL -->
<div id="product-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
  <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl">
    <div class="flex items-center justify-between px-6 py-5 border-b border-border">
      <h3 id="product-modal-title" class="font-bold text-lg text-surface">Add Product</h3>
      <button onclick="closeProductModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center">✕</button>
    </div>
    <div class="p-6 space-y-4">
      <input type="hidden" id="p-id">
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Product Name</label>
        <input id="p-name" type="text" placeholder="e.g., Bosch Cordless Drill 18V" class="w-full px-4 py-2.5 rounded-xl border border-border bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30">
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">SKU</label>
          <input id="p-sku" type="text" placeholder="e.g., PWR-0001" class="w-full px-4 py-2.5 rounded-xl border border-border bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Category</label>
          <div class="relative">
            <select id="p-cat" class="appearance-none w-full px-4 py-2.5 pr-10 rounded-xl border border-border bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30">
              <option value="">Select category</option>
            </select>
            <svg class="w-4 h-4 text-slate-400 absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
          </div>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Initial Stock</label>
          <input id="p-stock" type="number" min="0" placeholder="0" class="w-full px-4 py-2.5 rounded-xl border border-border bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Low Stock Alert At</label>
          <input id="p-threshold" type="number" min="0" placeholder="10" value="10" class="w-full px-4 py-2.5 rounded-xl border border-border bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30">
        </div>
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Cost Price (UGX) <span class="font-normal text-slate-400">(optional, used to calculate profit)</span></label>
        <input id="p-cost" type="number" min="0" placeholder="How much you paid per unit" class="w-full px-4 py-2.5 rounded-xl border border-border bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30">
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Min Price (UGX)</label>
          <input id="p-min" type="number" min="0" placeholder="0" class="w-full px-4 py-2.5 rounded-xl border border-border bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Max Price (UGX)</label>
          <input id="p-max" type="number" min="0" placeholder="0" class="w-full px-4 py-2.5 rounded-xl border border-border bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30">
        </div>
      </div>
    </div>
    <div class="flex gap-3 px-6 py-5 border-t border-border">
      <button onclick="saveProduct()" class="flex-1 bg-accent hover:bg-accent-dark text-white font-semibold py-3 rounded-xl transition">Save Product</button>
      <button onclick="closeProductModal()" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-3 rounded-xl transition">Cancel</button>
    </div>
  </div>
</div>

<!-- CATEGORY MODAL -->
<div id="category-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
  <div class="bg-white rounded-2xl w-full max-w-sm shadow-2xl">
    <div class="flex items-center justify-between px-6 py-5 border-b border-border">
      <h3 class="font-bold text-lg text-surface">Add Category</h3>
      <button onclick="closeCategoryModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center">✕</button>
    </div>
    <div class="p-6">
      <label class="block text-xs font-semibold text-slate-600 mb-1.5">Category Name</label>
      <input id="cat-name" type="text" placeholder="e.g., Power Tools" class="w-full px-4 py-2.5 rounded-xl border border-border bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30">
    </div>
    <div class="flex gap-3 px-6 py-5 border-t border-border">
      <button onclick="saveCategory()" class="flex-1 bg-accent hover:bg-accent-dark text-white font-semibold py-3 rounded-xl transition">Save</button>
      <button onclick="closeCategoryModal()" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-3 rounded-xl transition">Cancel</button>
    </div>
  </div>
</div>

<!-- EMPLOYEE MODAL -->
<div id="employee-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
  <div class="bg-white rounded-2xl w-full max-w-sm shadow-2xl">
    <div class="flex items-center justify-between px-6 py-5 border-b border-border">
      <h3 id="employee-modal-title" class="font-bold text-lg text-surface">Add Employee</h3>
      <button onclick="closeEmployeeModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center">✕</button>
    </div>
    <div class="p-6 space-y-4">
      <input type="hidden" id="emp-id">
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Name</label>
        <input id="emp-name" type="text" placeholder="e.g., John Mukasa" class="w-full px-4 py-2.5 rounded-xl border border-border bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30">
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1.5" id="emp-pass-label">Password</label>
        <input id="emp-pass" type="password" placeholder="Enter a password" class="w-full px-4 py-2.5 rounded-xl border border-border bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30">
        <p id="emp-pass-hint" class="text-[11px] text-slate-400 mt-1.5 hidden">Leave blank to keep the current password.</p>
      </div>
    </div>
    <div class="flex gap-3 px-6 py-5 border-t border-border">
      <button onclick="saveEmployee()" class="flex-1 bg-accent hover:bg-accent-dark text-white font-semibold py-3 rounded-xl transition">Save</button>
      <button onclick="closeEmployeeModal()" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-3 rounded-xl transition">Cancel</button>
    </div>
  </div>
</div>

<!-- CONFIRM MODAL -->
<div id="confirm-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
  <div class="bg-white rounded-2xl w-full max-w-sm shadow-2xl p-6 text-center">
    <div class="w-12 h-12 mx-auto rounded-full bg-red-50 text-red-600 flex items-center justify-center mb-4">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
    </div>
    <h3 id="confirm-title" class="font-bold text-surface mb-1">Are you sure?</h3>
    <p id="confirm-body" class="text-sm text-slate-500 mb-6">This action cannot be undone.</p>
    <div class="flex gap-3">
      <button id="confirm-ok" class="flex-1 bg-red-600 hover:bg-red-700 text-white font-semibold py-2.5 rounded-xl transition">Delete</button>
      <button onclick="closeConfirm()" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-2.5 rounded-xl transition">Cancel</button>
    </div>
  </div>
</div>

<!-- TOAST -->
<div id="toast" class="fixed top-5 right-5 z-[100] hidden">
  <div id="toast-body" class="bg-white border-l-4 border-emerald-500 shadow-xl rounded-xl px-5 py-4 text-sm font-medium text-slate-700 min-w-[240px]"></div>
</div>

<script src="assets/js/admin.js?v=<?= filemtime(__DIR__ . '/assets/js/admin.js') ?>"></script>
</body>
</html>

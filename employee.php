<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header('Location: index.php');
    exit();
}
$displayName = $_SESSION['full_name'] ?: $_SESSION['username'];

$stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'receipt_width'");
$receiptWidthMm = (int)($stmt->fetchColumn() ?: 80);
if (!in_array($receiptWidthMm, [58, 80], true)) {
    $receiptWidthMm = 80;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Point of Sale - Trust Hardware</title>
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
<link rel="stylesheet" href="assets/css/print.css?v=<?= filemtime(__DIR__ . '/assets/css/print.css') ?>">
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
  #receipt-content { width: <?= $receiptWidthMm ?>mm; }
  @media print {
    @page { size: <?= $receiptWidthMm ?>mm auto; margin: 0; }
  }
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

  <div id="sidebar-overlay" class="fixed inset-0 bg-black/50 z-30 hidden lg:hidden no-print" onclick="closeSidebar()"></div>

  <aside id="sidebar" class="fixed inset-y-0 left-0 w-[260px] bg-surface text-slate-300 flex flex-col z-40 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out no-print">
    <div class="h-20 flex items-center gap-3 px-6 border-b border-white/10">
      <div class="w-10 h-10 rounded-xl bg-accent flex items-center justify-center text-lg shadow-lg shadow-accent/30">🛠️</div>
      <div>
        <div class="text-white font-extrabold tracking-tight leading-none">TRUST <span class="text-accent">HARDWARE</span></div>
        <div class="text-[11px] text-slate-400 mt-1">Shop Management</div>
      </div>
    </div>

    <div class="px-6 py-4">
      <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide bg-accent/15 text-accent px-3 py-1.5 rounded-full">
        <span class="w-1.5 h-1.5 rounded-full bg-accent"></span> Employee
      </span>
    </div>

    <nav class="flex-1 px-3 space-y-1 overflow-y-auto">
      <button onclick="showSection('pos', this)" class="nav-link active w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium hover:bg-white/5 hover:text-white transition">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        Point of Sale
      </button>
      <button onclick="showSection('stock', this)" class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium hover:bg-white/5 hover:text-white transition">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7L12 3 4 7m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        View Stock
      </button>
      <button onclick="showSection('sales', this)" class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium hover:bg-white/5 hover:text-white transition">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14"/></svg>
        My Sales History
      </button>
    </nav>

    <div class="p-4 border-t border-white/10">
      <div class="flex items-center gap-3 px-2 py-2 mb-2">
        <div class="w-9 h-9 rounded-full bg-accent/20 text-accent flex items-center justify-center font-bold text-sm"><?= strtoupper(substr($displayName,0,1)) ?></div>
        <div class="min-w-0">
          <div class="text-sm font-semibold text-white truncate"><?= htmlspecialchars($displayName) ?></div>
          <div class="text-[11px] text-slate-400">Employee</div>
        </div>
      </div>
      <button onclick="location.href='logout.php'" class="w-full flex items-center justify-center gap-2 bg-white/5 hover:bg-red-500/90 text-slate-200 hover:text-white text-sm font-medium py-2.5 rounded-xl transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        Logout
      </button>
    </div>
  </aside>

  <div class="flex-1 min-w-0 flex flex-col overflow-y-auto lg:ml-[260px] no-print">
    <header class="sticky top-0 z-20 bg-white border-b border-border h-16 shrink-0 flex items-center justify-between px-4 sm:px-8">
      <div class="flex items-center gap-3">
        <button onclick="openSidebar()" class="lg:hidden w-10 h-10 flex items-center justify-center rounded-lg hover:bg-slate-100" aria-label="Open menu">
          <svg class="w-6 h-6 text-slate-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <h1 id="page-title" class="text-lg sm:text-xl font-bold text-surface">Point of Sale</h1>
      </div>
      <div class="flex items-center gap-2 sm:gap-4">
        <span class="hidden sm:block text-sm text-slate-500" id="current-date"></span>
        <button onclick="openCart()" class="relative w-10 h-10 flex items-center justify-center rounded-xl bg-slate-100 hover:bg-slate-200 transition" aria-label="Open cart">
          <svg class="w-5 h-5 text-slate-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
          <span id="cart-badge" class="hidden absolute -top-1.5 -right-1.5 min-w-[20px] h-5 px-1 rounded-full bg-accent text-white text-[11px] font-bold flex items-center justify-center">0</span>
        </button>
        <div class="w-9 h-9 rounded-full bg-surface text-white flex items-center justify-center font-bold text-sm"><?= strtoupper(substr($displayName,0,1)) ?></div>
      </div>
    </header>

    <main class="flex-1 p-4 sm:p-6">

      <!-- POS -->
      <section id="pos" class="section active">
        <div class="flex flex-col">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
              <h2 class="text-xl font-bold text-surface">Products</h2>
              <p class="text-sm text-slate-500">Tap a product to add it to the cart</p>
            </div>
            <div class="grid grid-cols-3 gap-2 text-center">
              <div class="bg-white border border-border rounded-xl px-3 py-1.5">
                <div class="text-[10px] text-slate-400 uppercase font-semibold">Today</div>
                <div class="text-sm font-bold text-surface" id="today-sales-count">0</div>
              </div>
              <div class="bg-white border border-border rounded-xl px-3 py-1.5 col-span-2">
                <div class="text-[10px] text-slate-400 uppercase font-semibold">Revenue</div>
                <div class="text-sm font-bold text-emerald-600" id="today-revenue">0 UGX</div>
              </div>
            </div>
          </div>

          <!-- This list intentionally has no scroll container of its own -- it
               flows with the page. An internal scrollbar here used to compete
               with the page's own scroll (the page wouldn't move if your
               cursor/finger was over this panel), which felt broken on both
               desktop and mobile. -->
          <div class="bg-white rounded-2xl border border-border shadow-sm">
            <div class="sticky top-16 z-10 bg-white rounded-t-2xl p-3 sm:p-5 border-b border-border flex flex-wrap sm:flex-nowrap gap-2 sm:gap-3">
              <div class="relative w-full order-1 sm:order-2 sm:w-56 sm:flex-none">
                <select id="pos-cat-filter" onchange="renderItems()" class="appearance-none w-full px-2.5 py-2 sm:px-4 sm:py-2.5 pr-8 sm:pr-10 rounded-xl border border-border bg-slate-50 text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-accent/30">
                  <option value="">All Categories</option>
                </select>
                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-slate-400 absolute right-2.5 sm:right-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
              </div>
              <div class="relative w-full order-2 sm:order-1 sm:w-auto sm:flex-1">
                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-slate-400 absolute left-3 sm:left-3.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
                <input id="search" onkeyup="renderItems()" type="text" placeholder="Search products by name or SKU..." class="w-full pl-9 sm:pl-11 pr-4 py-2 sm:py-2.5 rounded-xl border border-border bg-slate-50 focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent text-xs sm:text-sm">
              </div>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-sm whitespace-nowrap">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
                  <tr>
                    <th class="text-left px-6 py-3">Product</th>
                    <th class="text-left px-6 py-3">SKU</th>
                    <th class="text-left px-6 py-3">Category</th>
                    <th class="text-left px-6 py-3">Price Range</th>
                    <th class="text-left px-6 py-3">Stock</th>
                    <th class="text-right px-6 py-3">Action</th>
                  </tr>
                </thead>
                <tbody id="items-table"></tbody>
              </table>
            </div>
          </div>
        </div>
      </section>

      <!-- STOCK -->
      <section id="stock" class="section">
        <div class="mb-6">
          <h2 class="text-xl font-bold text-surface">Current Stock Levels</h2>
          <p class="text-sm text-slate-500">Browse all catalog items and availability</p>
        </div>
        <div class="bg-white rounded-2xl border border-border shadow-sm">
          <div class="p-5 border-b border-border">
            <div class="relative">
              <svg class="w-5 h-5 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
              <input id="stock-search" oninput="renderStock()" type="text" placeholder="Search products..." class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-border bg-slate-50 focus:outline-none focus:ring-2 focus:ring-accent/30 text-sm">
            </div>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap">
              <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
                <tr><th class="text-left px-6 py-3">Product</th><th class="text-left px-6 py-3">SKU</th><th class="text-left px-6 py-3">Category</th><th class="text-left px-6 py-3">Stock</th><th class="text-left px-6 py-3">Price Range</th></tr>
              </thead>
              <tbody id="stock-table"></tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- SALES -->
      <section id="sales" class="section">
        <div class="mb-6">
          <h2 class="text-xl font-bold text-surface">My Sales History</h2>
          <p class="text-sm text-slate-500">Transactions you have processed</p>
        </div>
        <div class="bg-white rounded-2xl border border-border shadow-sm">
          <div class="overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap">
              <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
                <tr><th class="text-left px-6 py-3">Transaction</th><th class="text-left px-6 py-3">Date &amp; Time</th><th class="text-left px-6 py-3">Product</th><th class="text-left px-6 py-3">Qty</th><th class="text-left px-6 py-3">Total</th><th class="text-right px-6 py-3">Receipt</th></tr>
              </thead>
              <tbody id="my-sales-table"></tbody>
            </table>
          </div>
        </div>
      </section>

    </main>
  </div>
</div>

<!-- CART DRAWER -->
<div id="cart-overlay" class="fixed inset-0 bg-black/50 z-40 hidden no-print" onclick="closeCart()"></div>
<aside id="cart-drawer" class="fixed inset-y-0 right-0 w-full sm:w-[420px] bg-white z-50 shadow-2xl flex flex-col transform translate-x-full transition-transform duration-300 ease-in-out no-print">
  <div class="p-5 border-b border-border flex items-center justify-between shrink-0">
    <h2 class="font-bold text-surface text-lg">Your Cart</h2>
    <div class="flex items-center gap-2">
      <button onclick="clearCart()" class="text-xs font-semibold text-red-500 hover:text-red-600 bg-red-50 px-3 py-1.5 rounded-lg">Clear All</button>
      <button onclick="closeCart()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center">✕</button>
    </div>
  </div>
  <div id="cart-list" class="flex-1 overflow-y-auto p-4 space-y-3"></div>
  <div class="p-5 border-t border-border bg-slate-50/50 space-y-2 shrink-0">
    <div>
      <label class="block text-xs font-semibold text-slate-600 mb-1.5">Customer Name <span class="font-normal text-slate-400">(optional)</span></label>
      <input id="customer-name" type="text" placeholder="e.g., Joseph Mukasa" class="w-full px-3.5 py-2.5 rounded-xl border border-border bg-white text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 mb-3">
    </div>
    <div class="flex justify-between text-sm text-slate-500"><span>Subtotal</span><span id="cart-subtotal">0 UGX</span></div>
    <div class="flex justify-between text-sm text-slate-500"><span id="cart-tax-label">Tax</span><span id="cart-tax">0 UGX</span></div>
    <div class="flex justify-between text-lg font-extrabold text-surface pt-2 border-t border-border"><span>Total</span><span id="cart-total">0 UGX</span></div>
    <button onclick="processSale()" class="w-full mt-3 bg-accent hover:bg-accent-dark text-white font-bold py-3.5 rounded-xl shadow-lg shadow-accent/25 transition flex items-center justify-center gap-2">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
      Complete Sale
    </button>
  </div>
</aside>

<!-- ADD TO CART MODAL -->
<div id="sale-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4 no-print">
  <div class="bg-white rounded-2xl w-full max-w-sm shadow-2xl">
    <div class="flex items-center justify-between px-6 py-5 border-b border-border">
      <h3 id="modal-title" class="font-bold text-lg text-surface">Add to Cart</h3>
      <button onclick="closeModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center">✕</button>
    </div>
    <div class="p-6 space-y-4">
      <p id="price-hint" class="bg-orange-50 text-accent-dark text-xs rounded-xl p-3 leading-relaxed"></p>
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Quantity</label>
        <input id="sale-qty" type="number" min="1" value="1" class="w-full px-4 py-2.5 rounded-xl border border-border bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30">
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Selling Price (UGX)</label>
        <input id="sale-price" type="number" min="0" placeholder="Enter price within range" class="w-full px-4 py-2.5 rounded-xl border border-border bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30">
      </div>
      <div class="flex justify-between items-center bg-slate-50 rounded-xl px-4 py-3 font-bold text-surface">
        <span>Item Total</span><span id="item-total">0 UGX</span>
      </div>
    </div>
    <div class="flex gap-3 px-6 py-5 border-t border-border">
      <button onclick="addToCart()" class="flex-1 bg-accent hover:bg-accent-dark text-white font-semibold py-3 rounded-xl transition">Add to Cart</button>
      <button onclick="closeModal()" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-3 rounded-xl transition">Cancel</button>
    </div>
  </div>
</div>

<!-- RECEIPT MODAL -->
<div id="receipt-modal" class="fixed inset-0 bg-black/60 z-50 hidden items-center justify-center p-4">
  <div class="bg-white rounded-2xl w-full max-w-sm shadow-2xl overflow-hidden">
    <div id="receipt-content" class="p-5 font-mono text-[12.5px] text-slate-900 leading-snug mx-auto">
      <div class="text-center">
        <div class="font-extrabold text-[15px] tracking-wide uppercase" id="r-store-name">Trust Hardware</div>
        <div class="text-[11px] text-slate-600 mt-0.5" id="r-store-address"></div>
        <div class="text-[11px] text-slate-600" id="r-store-phone"></div>
      </div>
      <div class="border-t border-dashed border-slate-400 my-2.5"></div>
      <div class="text-[11.5px] space-y-0.5">
        <div class="flex justify-between"><span>Receipt #</span><span id="r-txn" class="font-semibold"></span></div>
        <div class="flex justify-between"><span>Date</span><span id="r-date"></span></div>
        <div class="flex justify-between"><span>Served by</span><span id="r-cashier"></span></div>
        <div class="flex justify-between hidden" id="r-customer-row"><span>Customer</span><span id="r-customer"></span></div>
      </div>
      <div class="border-t border-dashed border-slate-400 my-2.5"></div>
      <div id="r-items" class="space-y-1.5 text-[12px]"></div>
      <div class="border-t border-dashed border-slate-400 my-2.5"></div>
      <div class="text-[12px] space-y-1 tabular-nums">
        <div class="flex justify-between"><span>Subtotal</span><span id="r-subtotal"></span></div>
        <div class="flex justify-between"><span id="r-tax-label">Tax</span><span id="r-tax"></span></div>
        <div class="flex justify-between font-extrabold text-sm pt-1.5 border-t border-slate-400 mt-1"><span>TOTAL</span><span id="r-total"></span></div>
      </div>
      <div class="border-t border-dashed border-slate-400 my-2.5"></div>
      <div class="text-center text-[11px] text-slate-600" id="r-footer">Thank you for shopping with us!</div>
    </div>
    <div class="no-print px-6 pb-6 space-y-2">
      <div class="grid grid-cols-2 gap-2">
        <button onclick="window.print()" class="flex items-center justify-center gap-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-2.5 rounded-xl transition text-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z"/></svg>
          Print Receipt
        </button>
        <button onclick="window.print()" class="flex items-center justify-center gap-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-2.5 rounded-xl transition text-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/></svg>
          Save as PDF
        </button>
      </div>
      <button id="receipt-close-btn" onclick="startNewSale()" class="w-full bg-accent hover:bg-accent-dark text-white font-bold py-3 rounded-xl transition">New Sale</button>
    </div>
  </div>
</div>

<!-- TOAST -->
<div id="toast" class="fixed top-5 right-5 z-[100] hidden no-print">
  <div id="toast-body" class="bg-white border-l-4 border-emerald-500 shadow-xl rounded-xl px-5 py-4 text-sm font-medium text-slate-700 min-w-[240px]"></div>
</div>

<script src="assets/js/employee.js?v=<?= filemtime(__DIR__ . '/assets/js/employee.js') ?>"></script>
</body>
</html>

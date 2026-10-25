<?php
require_once 'config/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin.php' : 'employee.php'));
    exit();
}

$error = $_GET['error'] ?? '';
$lockout = $_GET['lockout'] ?? '';
$remaining = $_GET['remaining'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Trust Hardware - Login</title>
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
</head>
<body class="font-sans min-h-screen bg-surface flex items-center justify-center p-5 relative overflow-hidden">

  <!-- decorative background -->
  <div class="absolute inset-0 opacity-[0.07]" style="background-image: radial-gradient(#fff 1px, transparent 1px); background-size: 28px 28px;"></div>
  <div class="absolute -top-32 -left-32 w-96 h-96 bg-accent/20 rounded-full blur-3xl"></div>
  <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-orange-500/10 rounded-full blur-3xl"></div>

  <div class="relative w-full max-w-md">
    <div class="bg-white rounded-2xl shadow-2xl p-8 sm:p-10 border border-border">
      <div class="flex flex-col items-center text-center mb-8">
        <div class="w-16 h-16 rounded-2xl bg-accent flex items-center justify-center text-3xl shadow-lg shadow-accent/30 mb-4">
          🛠️
        </div>
        <h1 class="text-2xl font-extrabold text-surface tracking-tight">TRUST <span class="text-accent">HARDWARE</span></h1>
        <p class="text-sm text-slate-500 mt-1">Shop Management System</p>
      </div>

      <?php if ($lockout === '1'): ?>
        <div class="mb-5 bg-red-50 text-red-600 text-sm font-medium rounded-lg px-4 py-3 text-center">
          Too many failed attempts. Please contact an administrator.
        </div>
      <?php elseif ($error === '1'): ?>
        <div class="mb-5 bg-red-50 text-red-600 text-sm font-medium rounded-lg px-4 py-3 text-center">
          Invalid credentials.
          <?php if ($remaining !== '' && (int)$remaining > 0): ?>
            <?= (int)$remaining ?> attempt<?= (int)$remaining > 1 ? 's' : '' ?> remaining.
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="authenticate.php" class="space-y-5">
        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-1.5">Role</label>
          <div class="relative">
            <select id="role-select" name="role" required onchange="handleRoleChange()"
              class="appearance-none w-full px-4 py-3 pr-10 rounded-xl border border-border bg-slate-50 text-slate-800 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent transition">
              <option value="">Select your role</option>
              <option value="admin">Administrator</option>
              <option value="employee">Employee</option>
            </select>
            <svg class="w-4 h-4 text-slate-400 absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
          </div>
        </div>

        <div id="employee-select-wrapper" class="hidden">
          <label class="block text-sm font-semibold text-slate-700 mb-1.5">Employee</label>
          <div class="relative">
            <select id="employee-select" name="employee"
              class="appearance-none w-full px-4 py-3 pr-10 rounded-xl border border-border bg-slate-50 text-slate-800 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent transition">
              <option value="">Select employee</option>
            </select>
            <svg class="w-4 h-4 text-slate-400 absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
          </div>
        </div>

        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-1.5">Password</label>
          <div class="relative">
            <input type="password" id="password" name="password" required placeholder="••••••••"
              class="w-full px-4 py-3 pr-12 rounded-xl border border-border bg-slate-50 text-slate-800 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent transition">
            <button type="button" onclick="togglePassword()" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600" aria-label="Toggle password visibility">
              <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
              </svg>
            </button>
          </div>
        </div>

        <button type="submit"
          class="w-full bg-accent hover:bg-accent-dark text-white font-semibold py-3 rounded-xl transition shadow-lg shadow-accent/25 active:scale-[0.99]">
          Sign In
        </button>
      </form>

      <p class="text-center text-xs text-slate-400 mt-8">
        &copy; <?= date('Y') ?> Trust Hardware. All rights reserved.
      </p>
    </div>
  </div>

<script>
function togglePassword() {
  const field = document.getElementById('password');
  const isPwd = field.type === 'password';
  field.type = isPwd ? 'text' : 'password';
}

let employeesLoaded = false;
async function handleRoleChange() {
  const role = document.getElementById('role-select').value;
  const wrapper = document.getElementById('employee-select-wrapper');
  const select = document.getElementById('employee-select');
  if (role === 'employee') {
    wrapper.classList.remove('hidden');
    select.setAttribute('required', 'required');
    if (!employeesLoaded) {
      try {
        const res = await fetch('public_api.php?action=listEmployees');
        const employees = await res.json();
        select.innerHTML = '<option value="">Select employee</option>' +
          employees.map(e => `<option value="${e.username.replace(/"/g, '&quot;')}">${e.full_name || e.username}</option>`).join('');
        employeesLoaded = true;
      } catch (e) { /* leave default option */ }
    }
  } else {
    wrapper.classList.add('hidden');
    select.removeAttribute('required');
    select.value = '';
  }
}
</script>
</body>
</html>

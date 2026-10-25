<?php
require_once 'config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function val($arr, $key, $default = null) {
    return isset($arr[$key]) && $arr[$key] !== '' ? $arr[$key] : $default;
}

try {
    switch ($action) {

        case 'checkSession':
            echo json_encode(['valid' => true, 'name' => $_SESSION['full_name'] ?? $_SESSION['username']]);
            break;

        // ---------- DASHBOARD ----------
        case 'getMetrics':
            // Revenue/profit respect an optional date range (admin can view
            // "today", a custom period, or leave blank for all-time).
            // Active Items / Low Stock are current snapshots, not period-based.
            $from = val($_GET, 'from');
            $to = val($_GET, 'to');
            $where = '1=1';
            $params = [];
            if ($from !== null) {
                $where .= ' AND DATE(sale_date) >= ?';
                $params[] = $from;
            }
            if ($to !== null) {
                $where .= ' AND DATE(sale_date) <= ?';
                $params[] = $to;
            }
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS revenue, COALESCE(SUM(profit),0) AS profit, COUNT(DISTINCT transaction_code) AS sales_count FROM sales WHERE $where");
            $stmt->execute($params);
            $period = $stmt->fetch();

            $activeItems = $pdo->query("SELECT COUNT(*) AS c FROM products")->fetch()['c'];
            $lowStock = $pdo->query("SELECT COUNT(*) AS c FROM products WHERE stock <= low_stock_threshold")->fetch()['c'];

            echo json_encode([
                'revenue' => (float)$period['revenue'],
                'profit' => (float)$period['profit'],
                'sales_count' => (int)$period['sales_count'],
                'active_items' => (int)$activeItems,
                'low_stock' => (int)$lowStock,
            ]);
            break;

        // ---------- INVENTORY ----------
        case 'getInventory':
            $stmt = $pdo->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC");
            echo json_encode($stmt->fetchAll());
            break;

        case 'addProduct':
            $stmt = $pdo->prepare("INSERT INTO products (name, sku, category_id, stock, low_stock_threshold, cost_price, min_price, max_price) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $_POST['name'],
                val($_POST, 'sku'),
                val($_POST, 'category_id'),
                $_POST['stock'] ?? 0,
                val($_POST, 'low_stock_threshold', 10),
                val($_POST, 'cost_price'),
                $_POST['min_price'],
                $_POST['max_price'],
            ]);
            echo json_encode(['success' => true]);
            break;

        case 'updateProduct':
            $stmt = $pdo->prepare("UPDATE products SET name=?, sku=?, category_id=?, stock=?, low_stock_threshold=?, cost_price=?, min_price=?, max_price=? WHERE id=?");
            $stmt->execute([
                $_POST['name'],
                val($_POST, 'sku'),
                val($_POST, 'category_id'),
                $_POST['stock'] ?? 0,
                val($_POST, 'low_stock_threshold', 10),
                val($_POST, 'cost_price'),
                $_POST['min_price'],
                $_POST['max_price'],
                $_POST['id'],
            ]);
            echo json_encode(['success' => true]);
            break;

        case 'deleteProduct':
            $stmt = $pdo->prepare("DELETE FROM products WHERE id=?");
            $stmt->execute([$_POST['id']]);
            echo json_encode(['success' => true]);
            break;

        // ---------- CATEGORIES ----------
        case 'getCategories':
            $stmt = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count FROM categories c ORDER BY c.name");
            echo json_encode($stmt->fetchAll());
            break;

        case 'addCategory':
            $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->execute([$_POST['name']]);
            echo json_encode(['success' => true]);
            break;

        case 'deleteCategory':
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id=?");
            $stmt->execute([$_POST['id']]);
            echo json_encode(['success' => true]);
            break;

        // ---------- SALES ----------
        case 'getSales':
            $search = val($_GET, 'search');
            $from = val($_GET, 'from');
            $to = val($_GET, 'to');
            $cashier = val($_GET, 'cashier');
            $sql = "SELECT * FROM sales WHERE 1=1";
            $params = [];
            if ($search !== null) {
                $sql .= " AND product_name LIKE ?";
                $params[] = "%$search%";
            }
            if ($from !== null) {
                $sql .= " AND DATE(sale_date) >= ?";
                $params[] = $from;
            }
            if ($to !== null) {
                $sql .= " AND DATE(sale_date) <= ?";
                $params[] = $to;
            }
            if ($cashier !== null) {
                $sql .= " AND cashier = ?";
                $params[] = $cashier;
            }
            $sql .= " ORDER BY sale_date DESC LIMIT 500";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll());
            break;

        case 'getCashiers':
            $stmt = $pdo->query("SELECT DISTINCT cashier FROM sales WHERE cashier IS NOT NULL ORDER BY cashier");
            echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
            break;

        // ---------- RECEIPT SETTINGS ----------
        case 'getReceiptSettings':
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('store_name','store_address','store_phone','receipt_footer','receipt_width','tax_rate')");
            $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            echo json_encode([
                'store_name' => $rows['store_name'] ?? '',
                'store_address' => $rows['store_address'] ?? '',
                'store_phone' => $rows['store_phone'] ?? '',
                'receipt_footer' => $rows['receipt_footer'] ?? '',
                'receipt_width' => $rows['receipt_width'] ?? '80',
                'tax_rate' => $rows['tax_rate'] ?? '0',
            ]);
            break;

        case 'updateReceiptSettings':
            $width = in_array($_POST['receipt_width'] ?? '80', ['58', '80'], true) ? $_POST['receipt_width'] : '80';
            $taxRate = is_numeric($_POST['tax_rate'] ?? '') ? (string)max(0, min(100, (float)$_POST['tax_rate'])) : '0';
            $fields = [
                'store_name' => trim($_POST['store_name'] ?? ''),
                'store_address' => trim($_POST['store_address'] ?? ''),
                'store_phone' => trim($_POST['store_phone'] ?? ''),
                'receipt_footer' => trim($_POST['receipt_footer'] ?? ''),
                'receipt_width' => $width,
                'tax_rate' => $taxRate,
            ];
            $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
            foreach ($fields as $key => $value) {
                $stmt->execute([$value, $key]);
            }
            echo json_encode(['success' => true, 'message' => 'Receipt settings updated']);
            break;

        // ---------- ACCOUNT ----------
        case 'updateAdminPassword':
            $current = $_POST['current'] ?? '';
            $new = $_POST['new'] ?? '';
            if (strlen($new) < 6) {
                echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters']);
                break;
            }
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE role='admin'");
            $stmt->execute();
            $admin = $stmt->fetch();
            if (!password_verify($current, $admin['password_hash'])) {
                echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
                break;
            }
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash=? WHERE role='admin'");
            $stmt->execute([$hash]);
            echo json_encode(['success' => true, 'message' => 'Password updated']);
            break;

        // ---------- EMPLOYEES ----------
        case 'getEmployees':
            $stmt = $pdo->query("SELECT id, username, full_name, created_at FROM users WHERE role = 'employee' ORDER BY full_name");
            echo json_encode($stmt->fetchAll());
            break;

        case 'addEmployee':
            $name = trim($_POST['name'] ?? '');
            $password = $_POST['password'] ?? '';
            if ($name === '' || strlen($password) < 6) {
                echo json_encode(['success' => false, 'message' => 'Enter a name and a password of at least 6 characters']);
                break;
            }
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$name]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'An employee with this name already exists']);
                break;
            }
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, role, password_hash, full_name) VALUES (?, 'employee', ?, ?)");
            $stmt->execute([$name, $hash, $name]);
            echo json_encode(['success' => true, 'message' => 'Employee added']);
            break;

        case 'updateEmployee':
            $id = $_POST['id'] ?? '';
            $name = trim($_POST['name'] ?? '');
            $password = $_POST['password'] ?? '';
            if ($id === '' || $name === '') {
                echo json_encode(['success' => false, 'message' => 'Name is required']);
                break;
            }
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$name, $id]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'An employee with this name already exists']);
                break;
            }
            if ($password !== '') {
                if (strlen($password) < 6) {
                    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
                    break;
                }
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username=?, full_name=?, password_hash=? WHERE id=? AND role='employee'");
                $stmt->execute([$name, $name, $hash, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username=?, full_name=? WHERE id=? AND role='employee'");
                $stmt->execute([$name, $name, $id]);
            }
            echo json_encode(['success' => true, 'message' => 'Employee updated']);
            break;

        case 'deleteEmployee':
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'employee'");
            $stmt->execute([$_POST['id']]);
            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (PDOException $e) {
    error_log('admin_api.php DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'A database error occurred. Please try again.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

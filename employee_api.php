<?php
require_once 'config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function getStoreSettings($pdo) {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('store_name','store_address','store_phone','receipt_footer')");
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    return [
        'name' => $rows['store_name'] ?? 'Trust Hardware',
        'address' => $rows['store_address'] ?? '',
        'phone' => $rows['store_phone'] ?? '',
        'footer' => $rows['receipt_footer'] ?? 'Thank you for shopping with us!',
    ];
}

try {
    switch ($action) {

        case 'checkSession':
            echo json_encode(['valid' => true, 'name' => $_SESSION['full_name'] ?? $_SESSION['username']]);
            break;

        case 'getInventory':
            // cost_price is deliberately excluded here (not just hidden in the
            // UI) so it never reaches the employee's browser at all -- cost
            // and profit are admin-only data.
            $stmt = $pdo->query("SELECT p.id, p.name, p.sku, p.category_id, p.stock, p.low_stock_threshold, p.min_price, p.max_price, p.created_at, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.stock > 0 ORDER BY p.name");
            echo json_encode($stmt->fetchAll());
            break;

        case 'getCategories':
            $stmt = $pdo->query("SELECT name FROM categories ORDER BY name");
            echo json_encode($stmt->fetchAll());
            break;

        case 'getTaxRate':
            $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'tax_rate'");
            $rate = $stmt->fetchColumn();
            echo json_encode(['tax_rate' => (float)($rate ?: 0)]);
            break;

        case 'processSale':
            $cart = json_decode($_POST['cart'] ?? '[]', true);
            if (!is_array($cart) || !count($cart)) {
                throw new Exception('Cart is empty');
            }

            $taxRateStmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'tax_rate'");
            $taxRate = (float)($taxRateStmt->fetchColumn() ?: 0);

            $transactionCode = 'TH' . date('ymd') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
            $cashier = $_SESSION['full_name'] ?: $_SESSION['username'];
            $customerName = trim($_POST['customer_name'] ?? '') ?: null;

            $pdo->beginTransaction();

            $subtotal = 0;
            $receiptItems = [];

            foreach ($cart as $item) {
                $qty = (int)$item['qty'];
                $price = (float)$item['price'];
                $lineTotal = $qty * $price;

                $stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
                $stmt->execute([$qty, $item['id'], $qty]);
                if ($stmt->rowCount() === 0) {
                    throw new Exception('Insufficient stock for ' . $item['name']);
                }

                // Snapshot the product's current cost price onto the sale record
                // (like product_name/price already are) so profit stays accurate
                // for this sale even if the cost price changes later. Profit is
                // left NULL if no cost price has been set for the product yet.
                $costStmt = $pdo->prepare("SELECT cost_price FROM products WHERE id = ?");
                $costStmt->execute([$item['id']]);
                $costPrice = $costStmt->fetchColumn();
                $costPrice = ($costPrice !== null && $costPrice !== false) ? (float)$costPrice : null;
                $profit = $costPrice !== null ? ($price - $costPrice) * $qty : null;

                $stmt = $pdo->prepare("INSERT INTO sales (transaction_code, product_id, product_name, quantity, price, cost_price, total, profit, cashier, customer_name) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$transactionCode, $item['id'], $item['name'], $qty, $price, $costPrice, $lineTotal, $profit, $cashier, $customerName]);

                $subtotal += $lineTotal;
                $receiptItems[] = [
                    'name' => $item['name'],
                    'qty' => $qty,
                    'price' => $price,
                    'total' => $lineTotal,
                ];
            }

            $pdo->commit();

            $tax = round($subtotal * $taxRate / 100, 2);
            $total = $subtotal + $tax;

            echo json_encode([
                'success' => true,
                'receipt' => [
                    'transaction_code' => $transactionCode,
                    'date' => date('Y-m-d H:i:s'),
                    'cashier' => $cashier,
                    'customer_name' => $customerName,
                    'items' => $receiptItems,
                    'subtotal' => $subtotal,
                    'tax_rate' => $taxRate,
                    'tax' => $tax,
                    'total' => $total,
                    'store' => getStoreSettings($pdo),
                ],
            ]);
            break;

        case 'getSales':
            $stmt = $pdo->prepare("SELECT * FROM sales WHERE cashier = ? ORDER BY sale_date DESC LIMIT 100");
            $stmt->execute([$_SESSION['full_name'] ?: $_SESSION['username']]);
            echo json_encode($stmt->fetchAll());
            break;

        case 'getTransactionReceipt':
            $cashier = $_SESSION['full_name'] ?: $_SESSION['username'];
            $code = $_GET['transaction_code'] ?? '';
            $stmt = $pdo->prepare("SELECT * FROM sales WHERE transaction_code = ? AND cashier = ? ORDER BY id");
            $stmt->execute([$code, $cashier]);
            $rows = $stmt->fetchAll();
            if (!$rows) {
                throw new Exception('Receipt not found');
            }

            $taxRateStmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'tax_rate'");
            $taxRate = (float)($taxRateStmt->fetchColumn() ?: 0);

            $subtotal = 0;
            $receiptItems = [];
            foreach ($rows as $row) {
                $subtotal += (float)$row['total'];
                $receiptItems[] = [
                    'name' => $row['product_name'],
                    'qty' => (int)$row['quantity'],
                    'price' => (float)$row['price'],
                    'total' => (float)$row['total'],
                ];
            }
            $tax = round($subtotal * $taxRate / 100, 2);
            $total = $subtotal + $tax;

            echo json_encode([
                'success' => true,
                'receipt' => [
                    'transaction_code' => $code,
                    'date' => $rows[0]['sale_date'],
                    'cashier' => $cashier,
                    'customer_name' => $rows[0]['customer_name'],
                    'items' => $receiptItems,
                    'subtotal' => $subtotal,
                    'tax_rate' => $taxRate,
                    'tax' => $tax,
                    'total' => $total,
                    'store' => getStoreSettings($pdo),
                ],
            ]);
            break;

        case 'getTodayStats':
            $cashier = $_SESSION['full_name'] ?: $_SESSION['username'];
            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT transaction_code) AS sales_count, COALESCE(SUM(total),0) AS revenue FROM sales WHERE cashier = ? AND DATE(sale_date) = CURDATE()");
            $stmt->execute([$cashier]);
            echo json_encode($stmt->fetch());
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('employee_api.php DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'A database error occurred. Please try again.']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

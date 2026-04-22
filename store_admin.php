<?php
function selectAllAssoc(mysqli $conn, string $sql): array
{
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        throw new RuntimeException(mysqli_error($conn));
    }

    $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);

    return $rows;
}

function getTableColumns(mysqli $conn, string $dbname, string $table): array
{
    $safeDb = mysqli_real_escape_string($conn, $dbname);
    $safeTable = mysqli_real_escape_string($conn, $table);
    $sql = "SELECT COLUMN_NAME, COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = '{$safeDb}' AND TABLE_NAME = '{$safeTable}'
            ORDER BY ORDINAL_POSITION";

    return selectAllAssoc($conn, $sql);
}

function buildDatabaseContext(mysqli $conn, string $dbname, array $tables): string
{
    $blocks = [];

    foreach ($tables as $table) {
        $columns = getTableColumns($conn, $dbname, $table);
        $data = selectAllAssoc($conn, "SELECT * FROM `{$table}`");

        $columnText = [];
        foreach ($columns as $column) {
            $columnText[] = $column['COLUMN_NAME'] . ' (' . $column['COLUMN_TYPE'] . ')';
        }

        $blocks[] = "Table: {$table}\n"
            . "Columns: " . implode(', ', $columnText) . "\n"
            . "Data (JSON):\n"
            . json_encode(
                $data,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
            );
    }

    return implode("\n\n====================\n\n", $blocks);
}

function callMiniMax(string $apiKey, string $apiUrl, string $model, string $systemPrompt, string $context, string $question): array
{
    $payload = [
        'model' => $model,
        'messages' => [
            [
                'role' => 'system',
                'name' => 'Retail DB Assistant',
                'content' => $systemPrompt,
            ],
            [
                'role' => 'user',
                'name' => 'user',
                'content' => $context,
            ],
            [
                'role' => 'user',
                'name' => 'user',
                'content' => $question,
            ],
        ],
        'temperature' => 0.2,
        'top_p' => 0.95,
        'max_completion_tokens' => 2048,
    ];

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('MiniMax call failed: ' . $error);
    }

    $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('MiniMax returned an unparseable response.');
    }

    if ($httpCode >= 400) {
        $message = $decoded['base_resp']['status_msg'] ?? ('HTTP ' . $httpCode);
        throw new RuntimeException('MiniMax API error: ' . $message);
    }

    $answer = $decoded['choices'][0]['message']['content'] ?? '';
    if ($answer === '') {
        $message = $decoded['base_resp']['status_msg'] ?? 'Model returned no content.';
        throw new RuntimeException($message);
    }

    return [
        'answer' => $answer,
        'usage' => $decoded['usage'] ?? [],
        'model' => $decoded['model'] ?? $model,
    ];
}

function getLowStockProducts(mysqli $conn): array
{
    $sql = "SELECT p.product_id, p.product_name, i.quantity, i.min_stock, 
            CASE WHEN i.quantity <= i.min_stock THEN 'LOW_STOCK' ELSE 'OK' END AS stock_status,
            s.supplier_name
            FROM product p
            JOIN inventory i ON p.product_id = i.product_id
            JOIN supplier s ON p.supplier_id = s.supplier_id
            WHERE i.quantity <= i.min_stock AND p.is_active = 1
            ORDER BY (i.min_stock - i.quantity) DESC";
    return selectAllAssoc($conn, $sql);
}

function getInventoryStats(mysqli $conn): array
{
    $sql = "SELECT 
                COUNT(*) as total_products,
                SUM(CASE WHEN quantity <= min_stock THEN 1 ELSE 0 END) as low_stock_count,
                SUM(quantity) as total_quantity,
                ROUND(AVG(quantity), 2) as avg_quantity
            FROM inventory i
            JOIN product p ON i.product_id = p.product_id
            WHERE p.is_active = 1";
    return selectAllAssoc($conn, $sql);
}

function getAllProducts(mysqli $conn): array
{
    $sql = "SELECT p.product_id, p.product_name, i.quantity, i.min_stock, p.sell_price
            FROM product p
            JOIN inventory i ON p.product_id = i.product_id
            WHERE p.is_active = 1
            ORDER BY p.product_name";
    return selectAllAssoc($conn, $sql);
}

$host = 'localhost';
$user = 'root';
$pwd = 'csc3170';
$dbname = 'csc3170_store';
$minimaxApiUrl = 'https://api.minimaxi.com/v1/text/chatcompletion_v2';
$minimaxApiKey = 'sk-cp-xw_X66v36p-ciVQviJaNdQz22HQtpOKcsqpZPY76Mrpy_qFzmBOkwgod9R6D6hIygypTpifZCz1z-oH2fjuP87zsoMIh45DCOlr3Wz3X4B60XxjwBVWG3wg';
$minimaxModel = 'MiniMax-M2.7';
$llmTables = [
    'employee',
    'member',
    'category',
    'supplier',
    'product',
    'inventory',
    'sales_transaction',
    'transaction_item',
    'purchase_order',
    'purchase_order_item',
];

$error = '';
$success = '';
$cols = [];
$rows = [];
$sql = $_POST['sql'] ?? '';
$formAction = $_POST['form_action'] ?? '';
$llmQuestion = trim($_POST['llm_question'] ?? '');
$llmAnswer = '';
$llmError = '';
$llmMeta = [];

// Handle stock adjustment
$stockAction = $_POST['stock_action'] ?? '';
$stockProductId = $_POST['stock_product_id'] ?? '';
$stockQuantity = $_POST['stock_quantity'] ?? '';
$stockOperation = $_POST['stock_operation'] ?? '';

// Handle order actions
$orderAction = $_POST['order_action'] ?? '';
$orderProductId = $_POST['order_product_id'] ?? '';
$orderQuantity = $_POST['order_quantity'] ?? '';
$orderSupplierId = $_POST['order_supplier_id'] ?? '';

$conn = mysqli_connect($host, $user, $pwd, $dbname);
if (!$conn) {
    $error = 'Database connection failed: ' . mysqli_connect_error();
} else {
    mysqli_set_charset($conn, 'utf8mb4');

    // Handle stock adjustment
    if ($stockAction === 'adjust_stock' && $stockProductId && $stockQuantity !== '') {
        $stockQuantity = intval($stockQuantity);
        $productId = intval($stockProductId);
        
        if ($stockOperation === 'add') {
            $sql_update = "UPDATE inventory SET quantity = quantity + $stockQuantity, last_updated = CURRENT_TIMESTAMP WHERE product_id = $productId";
            if (mysqli_query($conn, $sql_update)) {
                $success = "Added $stockQuantity units to product (ID: $productId)";
                // Log inventory change
                $log_sql = "INSERT INTO inventory_log (product_id, change_quantity, balance_after, change_reason, created_at) 
                            SELECT $productId, $stockQuantity, quantity, 'MANUAL_ADD', CURRENT_TIMESTAMP FROM inventory WHERE product_id = $productId";
                mysqli_query($conn, $log_sql);
            } else {
                $error = mysqli_error($conn);
            }
        } elseif ($stockOperation === 'remove') {
            // Check if enough stock
            $check_sql = "SELECT quantity FROM inventory WHERE product_id = $productId";
            $check_result = mysqli_query($conn, $check_sql);
            $current = mysqli_fetch_assoc($check_result);
            if ($current && $current['quantity'] >= $stockQuantity) {
                $sql_update = "UPDATE inventory SET quantity = quantity - $stockQuantity, last_updated = CURRENT_TIMESTAMP WHERE product_id = $productId";
                if (mysqli_query($conn, $sql_update)) {
                    $success = "Removed $stockQuantity units from product (ID: $productId)";
                    $log_sql = "INSERT INTO inventory_log (product_id, change_quantity, balance_after, change_reason, created_at) 
                                SELECT $productId, -$stockQuantity, quantity, 'MANUAL_REMOVE', CURRENT_TIMESTAMP FROM inventory WHERE product_id = $productId";
                    mysqli_query($conn, $log_sql);
                } else {
                    $error = mysqli_error($conn);
                }
            } else {
                $error = "Insufficient stock. Available: " . ($current['quantity'] ?? 0);
            }
        }
    }

    // Handle purchase order creation
    if ($orderAction === 'create_order' && $orderProductId && $orderQuantity && $orderSupplierId) {
        $orderQuantity = intval($orderQuantity);
        $productId = intval($orderProductId);
        $supplierId = intval($orderSupplierId);
        
        // Get product cost price
        $price_sql = "SELECT cost_price FROM product WHERE product_id = $productId";
        $price_result = mysqli_query($conn, $price_sql);
        $product_data = mysqli_fetch_assoc($price_result);
        $unitPrice = $product_data['cost_price'] ?? 0;
        $subtotal = round($orderQuantity * $unitPrice, 2);
        
        mysqli_begin_transaction($conn);
        try {
            // Insert purchase order
            $po_sql = "INSERT INTO purchase_order (supplier_id, employee_id, total_price, receive_time) 
                       VALUES ($supplierId, 1, $subtotal, CURRENT_TIMESTAMP)";
            mysqli_query($conn, $po_sql);
            $poId = mysqli_insert_id($conn);
            
            // Insert purchase order item
            $poi_sql = "INSERT INTO purchase_order_item (purchase_order_id, product_id, unit_price, quantity, subtotal) 
                        VALUES ($poId, $productId, $unitPrice, $orderQuantity, $subtotal)";
            mysqli_query($conn, $poi_sql);
            $poiId = mysqli_insert_id($conn);
            
            // Update inventory
            $inv_sql = "UPDATE inventory SET quantity = quantity + $orderQuantity, last_updated = CURRENT_TIMESTAMP 
                        WHERE product_id = $productId";
            mysqli_query($conn, $inv_sql);
            
            // Log inventory change
            $log_sql = "INSERT INTO inventory_log (product_id, purchase_item_id, change_quantity, balance_after, change_reason, created_at) 
                        SELECT $productId, $poiId, $orderQuantity, quantity, 'PURCHASE', CURRENT_TIMESTAMP 
                        FROM inventory WHERE product_id = $productId";
            mysqli_query($conn, $log_sql);
            
            mysqli_commit($conn);
            $success = "Purchase order created successfully for product (ID: $productId) - Quantity: $orderQuantity";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Order creation failed: " . $e->getMessage();
        }
    }

    // Handle order deletion
    if ($orderAction === 'delete_order' && $_POST['order_id'] ?? '') {
        $orderId = intval($_POST['order_id']);
        $check_sql = "SELECT purchase_order_id FROM purchase_order WHERE purchase_order_id = $orderId";
        $check = mysqli_query($conn, $check_sql);
        if (mysqli_num_rows($check) > 0) {
            $del_sql = "DELETE FROM purchase_order WHERE purchase_order_id = $orderId";
            if (mysqli_query($conn, $del_sql)) {
                $success = "Purchase order (ID: $orderId) deleted successfully";
            } else {
                $error = mysqli_error($conn);
            }
        } else {
            $error = "Order ID not found";
        }
    }

    if ($formAction === 'llm_query') {
        if ($llmQuestion === '') {
            $llmError = 'Please enter a question before initiating LLM query.';
        } else {
            try {
                $context = buildDatabaseContext($conn, $dbname, $llmTables);
                $systemPrompt = "You are a retail management database assistant.\n"
                    . "You can only answer questions based on the provided database context, and cannot fabricate non-existent data.\n"
                    . "If the context is insufficient to answer, clearly state that information is insufficient.\n"
                    . "Please answer in clear, concise English, and cite table names or field names as the basis when possible.\n"
                    . "This is a course demonstration project; no need to generate SQL, and do not suggest write operations.";

                $llmResult = callMiniMax(
                    $minimaxApiKey,
                    $minimaxApiUrl,
                    $minimaxModel,
                    $systemPrompt,
                    "Below is the complete demonstration context of the current database:\n\n" . $context,
                    $llmQuestion
                );

                $llmAnswer = $llmResult['answer'];
                $llmMeta = [
                    'model' => $llmResult['model'],
                    'tables' => count($llmTables),
                    'tokens' => $llmResult['usage']['total_tokens'] ?? null,
                ];
            } catch (Throwable $e) {
                $llmError = $e->getMessage();
            }
        }
    } elseif ($sql) {
        $result = mysqli_query($conn, $sql);
        if (mysqli_errno($conn)) {
            $error = mysqli_error($conn);
        } else {
            if (is_object($result)) {
                while ($c = mysqli_fetch_field($result)) {
                    $cols[] = $c->name;
                }
                $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
                mysqli_free_result($result);
            } else {
                $success = "Query executed successfully";
            }
        }
    }
    
    // Get data for charts and alerts
    $lowStockProducts = getLowStockProducts($conn);
    $inventoryStats = getInventoryStats($conn);
    $allProducts = getAllProducts($conn);
    $suppliers = selectAllAssoc($conn, "SELECT supplier_id, supplier_name FROM supplier WHERE is_active = 1");
    $recentOrders = selectAllAssoc($conn, "SELECT po.purchase_order_id, s.supplier_name, po.total_price, po.receive_time 
                                            FROM purchase_order po 
                                            JOIN supplier s ON po.supplier_id = s.supplier_id 
                                            ORDER BY po.receive_time DESC LIMIT 10");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CSC3170 Store Management System</title>
<link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    :root {
        --bg: #0f1117;
        --bg-soft: rgba(20, 24, 35, 0.82);
        --panel: rgba(18, 22, 32, 0.9);
        --panel-strong: rgba(12, 15, 24, 0.96);
        --border: rgba(214, 181, 113, 0.22);
        --border-strong: rgba(214, 181, 113, 0.42);
        --text: #f4efe6;
        --muted: #b8ad9a;
        --gold: #d6b571;
        --gold-deep: #9f7b2f;
        --green: #7bc39b;
        --red: #ea8f85;
        --warning: #f0a34b;
        --shadow: 0 28px 60px rgba(0, 0, 0, 0.42);
        --radius-xl: 28px;
        --radius-lg: 20px;
        --radius-md: 14px;
        --mono: "Iosevka", "SFMono-Regular", "Consolas", "Menlo", monospace;
        --serif: "STSong", "Songti SC", "Noto Serif CJK SC", "Source Han Serif SC", serif;
        --sans: "Segoe UI", "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif;
    }

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        min-height: 100vh;
        padding: 40px 20px 56px;
        color: var(--text);
        font-family: var(--sans);
        background:
            radial-gradient(circle at top left, rgba(214, 181, 113, 0.18), transparent 28%),
            radial-gradient(circle at 85% 12%, rgba(58, 90, 120, 0.22), transparent 22%),
            linear-gradient(145deg, #090b10 0%, #0f1117 38%, #141925 100%);
        position: relative;
        overflow-x: hidden;
    }

    body::before,
    body::after {
        content: "";
        position: fixed;
        inset: 0;
        pointer-events: none;
    }

    body::before {
        background:
            linear-gradient(rgba(255, 255, 255, 0.028) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 255, 255, 0.028) 1px, transparent 1px);
        background-size: 30px 30px;
        mask-image: linear-gradient(to bottom, rgba(0, 0, 0, 0.58), transparent 92%);
    }

    body::after {
        opacity: 0.06;
        background-image:
            radial-gradient(circle at 20% 20%, #fff 0 1px, transparent 1px),
            radial-gradient(circle at 80% 35%, #fff 0 1px, transparent 1px),
            radial-gradient(circle at 45% 80%, #fff 0 1px, transparent 1px);
        background-size: 180px 180px;
        mix-blend-mode: soft-light;
    }

    .page-shell {
        width: min(1400px, 100%);
        margin: 0 auto;
        position: relative;
        z-index: 1;
    }

    .hero-panel,
    .panel,
    .result-panel,
    .message-panel,
    .alert-panel {
        border: 1px solid var(--border);
        background: linear-gradient(180deg, rgba(23, 28, 40, 0.9), rgba(12, 15, 22, 0.92));
        box-shadow: var(--shadow);
        backdrop-filter: blur(14px);
    }

    .hero-panel {
        border-radius: var(--radius-xl);
        padding: 34px;
        display: grid;
        grid-template-columns: minmax(0, 1.8fr) minmax(280px, 0.9fr);
        gap: 24px;
        margin-bottom: 24px;
        position: relative;
        overflow: hidden;
    }

    .hero-panel::before {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(120deg, rgba(214, 181, 113, 0.12), transparent 45%, rgba(214, 181, 113, 0.08));
        pointer-events: none;
    }

    .eyebrow,
    .panel-kicker,
    .meta-label,
    .helper-label {
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 0.24em;
        font-size: 0.72rem;
        color: var(--gold);
    }

    .hero-title {
        margin: 12px 0 14px;
        font-family: var(--serif);
        font-size: clamp(2.4rem, 4vw, 4.2rem);
        line-height: 0.96;
        letter-spacing: 0.02em;
    }

    .hero-text {
        margin: 0;
        max-width: 58ch;
        font-size: 1.02rem;
        line-height: 1.8;
        color: var(--muted);
    }

    .hero-meta {
        display: grid;
        gap: 14px;
        align-content: center;
    }

    .meta-card {
        border: 1px solid rgba(214, 181, 113, 0.18);
        border-radius: 18px;
        background: rgba(255, 255, 255, 0.03);
        padding: 18px 20px;
    }

    .meta-value {
        margin-top: 8px;
        font-family: var(--mono);
        font-size: 1rem;
        color: #fff6de;
        word-break: break-word;
    }

    .layout-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.15fr) minmax(320px, 0.85fr);
        gap: 24px;
        align-items: start;
        margin-bottom: 24px;
    }

    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 24px;
        margin-bottom: 24px;
    }

    .llm-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 24px;
        margin-bottom: 24px;
    }

    .panel,
    .result-panel,
    .message-panel,
    .alert-panel {
        border-radius: var(--radius-xl);
        padding: 28px;
    }

    .panel-header,
    .result-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        margin-bottom: 20px;
    }

    .panel-header h2,
    .result-header h2 {
        margin: 8px 0 0;
        font-family: var(--serif);
        font-size: 1.6rem;
        letter-spacing: 0.02em;
    }

    .status-badge {
        flex-shrink: 0;
        border-radius: 999px;
        padding: 10px 14px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        font-size: 0.86rem;
        color: var(--text);
        background: rgba(255, 255, 255, 0.04);
    }

    .status-ready {
        border-color: rgba(123, 195, 155, 0.32);
        color: #d5f5e2;
        background: rgba(123, 195, 155, 0.12);
    }

    .status-warning {
        border-color: rgba(240, 163, 75, 0.34);
        color: #fde6c8;
        background: rgba(240, 163, 75, 0.12);
    }

    .status-error {
        border-color: rgba(234, 143, 133, 0.34);
        color: #ffd8d3;
        background: rgba(234, 143, 133, 0.12);
    }

    .editor-form {
        display: grid;
        gap: 16px;
    }

    textarea.form-control {
        min-height: 320px;
        resize: vertical;
        border-radius: 22px;
        border: 1px solid rgba(214, 181, 113, 0.18);
        background:
            linear-gradient(180deg, rgba(14, 18, 27, 0.98), rgba(10, 13, 20, 0.98)),
            linear-gradient(180deg, rgba(214, 181, 113, 0.08), transparent);
        color: #f7f3ec;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
        padding: 22px 24px;
        font-family: var(--mono);
        font-size: 0.98rem;
        line-height: 1.8;
    }

    textarea.form-control:focus {
        background-color: rgba(10, 13, 20, 0.98);
        color: #fff;
        border-color: var(--border-strong);
        box-shadow: 0 0 0 4px rgba(214, 181, 113, 0.12);
    }

    textarea.form-control::placeholder {
        color: rgba(244, 239, 230, 0.34);
    }

    input.form-control, select.form-control {
        border-radius: 14px;
        border: 1px solid rgba(214, 181, 113, 0.18);
        background: rgba(14, 18, 27, 0.98);
        color: #f7f3ec;
        padding: 12px 16px;
        font-family: var(--mono);
        font-size: 0.92rem;
    }

    input.form-control:focus, select.form-control:focus {
        border-color: var(--border-strong);
        box-shadow: 0 0 0 3px rgba(214, 181, 113, 0.12);
        color: #fff;
        background: rgba(10, 13, 20, 0.98);
    }

    .action-row {
        display: flex;
        flex-wrap: wrap;
        gap: 14px;
        align-items: center;
        justify-content: space-between;
    }

    .btn-run {
        border: 0;
        border-radius: 999px;
        padding: 13px 24px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #111;
        background: linear-gradient(135deg, #f0d9a0, #c99639 52%, #f0d9a0);
        box-shadow: 0 12px 28px rgba(201, 150, 57, 0.28);
        transition: transform 0.18s ease, box-shadow 0.18s ease;
    }

    .btn-run:hover {
        transform: translateY(-1px);
        box-shadow: 0 16px 34px rgba(201, 150, 57, 0.36);
    }

    .btn-secondary-custom {
        border: 1px solid rgba(214, 181, 113, 0.3);
        border-radius: 999px;
        padding: 10px 20px;
        background: rgba(255, 255, 255, 0.05);
        color: var(--gold);
        transition: all 0.18s ease;
    }

    .btn-secondary-custom:hover {
        background: rgba(214, 181, 113, 0.15);
        border-color: var(--gold);
    }

    .helper-text {
        color: var(--muted);
        font-size: 0.92rem;
    }

    .alert-badge {
        background: rgba(234, 143, 133, 0.15);
        border-left: 4px solid var(--red);
        padding: 12px 16px;
        border-radius: 12px;
        margin-bottom: 12px;
    }

    .alert-badge.warning {
        border-left-color: var(--warning);
        background: rgba(240, 163, 75, 0.12);
    }

    .llm-answer {
        margin-top: 12px;
        color: #f6eee1;
        line-height: 1.85;
        white-space: pre-wrap;
        word-break: break-word;
    }

    .llm-meta {
        margin-top: 14px;
        color: var(--muted);
        font-size: 0.9rem;
    }

    .shortcut-groups {
        display: grid;
        gap: 18px;
    }

    .shortcut-section {
        border-radius: 20px;
        border: 1px solid rgba(214, 181, 113, 0.14);
        background: rgba(255, 255, 255, 0.025);
        padding: 18px;
    }

    .shortcut-section h3 {
        margin: 10px 0 14px;
        font-family: var(--serif);
        font-size: 1.15rem;
    }

    .button-grid {
        display: grid;
        gap: 10px;
    }

    .query-btn {
        width: 100%;
        text-align: left;
        border-radius: 14px;
        border: 1px solid rgba(214, 181, 113, 0.14);
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.045), rgba(255, 255, 255, 0.02));
        color: var(--text);
        padding: 12px 14px;
        transition: transform 0.18s ease, border-color 0.18s ease, background 0.18s ease;
    }

    .query-btn:hover {
        transform: translateX(3px);
        border-color: rgba(214, 181, 113, 0.38);
        background: linear-gradient(180deg, rgba(214, 181, 113, 0.12), rgba(255, 255, 255, 0.03));
    }

    .message-panel {
        margin-bottom: 24px;
    }

    .message-panel.error {
        border-color: rgba(234, 143, 133, 0.3);
        background: linear-gradient(180deg, rgba(53, 22, 20, 0.88), rgba(27, 12, 12, 0.92));
    }

    .message-panel.success {
        border-color: rgba(123, 195, 155, 0.28);
        background: linear-gradient(180deg, rgba(18, 46, 34, 0.88), rgba(12, 24, 19, 0.92));
    }

    .message-title {
        margin: 8px 0 0;
        font-family: var(--serif);
        font-size: 1.4rem;
    }

    .message-body {
        margin: 12px 0 0;
        color: #f6eee1;
        line-height: 1.8;
        word-break: break-word;
    }

    .result-panel {
        overflow: hidden;
    }

    .result-meta {
        color: var(--muted);
        font-size: 0.92rem;
    }

    .table-shell {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 1px;
    }

    .table {
        margin: 0;
        color: #f4efe6;
        min-width: 100%;
        background-color: transparent;
    }

    .table thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        border-bottom: 1px solid rgba(214, 181, 113, 0.3);
        background: #0a0c14;
        color: var(--gold);
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        font-size: 0.78rem;
        padding: 16px 18px;
        white-space: nowrap;
    }

    .table tbody tr {
        transition: background 0.16s ease;
        background-color: transparent;
    }

    .table tbody tr:nth-child(odd) {
        background-color: rgba(255, 255, 255, 0.03);
    }

    .table tbody tr:nth-child(even) {
        background-color: transparent;
    }

    .table tbody tr:hover {
        background: rgba(214, 181, 113, 0.1);
    }

    .table tbody td {
        padding: 15px 18px;
        border-color: rgba(214, 181, 113, 0.12);
        vertical-align: top;
        white-space: nowrap;
        color: #f4efe6;
        background-color: transparent;
    }

    .chart-container {
        position: relative;
        height: 250px;
        margin: 20px 0;
    }

    .stats-card {
        text-align: center;
        padding: 16px;
        background: rgba(255, 255, 255, 0.03);
        border-radius: var(--radius-md);
    }

    .stats-number {
        font-size: 2.5rem;
        font-weight: 700;
        font-family: var(--mono);
        color: var(--gold);
    }

    .stats-label {
        font-size: 0.85rem;
        color: var(--muted);
        margin-top: 8px;
    }

    @media (max-width: 1024px) {
        .hero-panel,
        .layout-grid {
            grid-template-columns: 1fr;
        }

        .hero-panel,
        .panel,
        .result-panel,
        .message-panel {
            padding: 24px;
        }

        textarea.form-control {
            min-height: 260px;
        }
    }

    @media (max-width: 640px) {
        body {
            padding: 20px 12px 32px;
        }

        .hero-title {
            font-size: 2.1rem;
        }

        .panel-header,
        .result-header,
        .action-row {
            flex-direction: column;
            align-items: stretch;
        }

        .btn-run {
            width: 100%;
        }

        .table tbody td,
        .table thead th {
            white-space: normal;
        }
    }
</style>
</head>
<body>
<div class="page-shell">
    <section class="hero-panel">
        <div>
            <p class="eyebrow">CSC3170 Store</p>
            <h1 class="hero-title">Database Control Center</h1>
            <p class="hero-text">A lightweight SQL workbench for course projects. Execute queries, manage inventory, create purchase orders, and analyze data with AI assistance.</p>
        </div>
        <div class="hero-meta">
            <div class="meta-card">
                <p class="meta-label">Database</p>
                <div class="meta-value"><?=htmlspecialchars($dbname, ENT_QUOTES, 'UTF-8')?></div>
            </div>
            <div class="meta-card">
                <p class="meta-label">Interface</p>
                <div class="meta-value">Web SQL Runner</div>
            </div>
            <div class="meta-card">
                <p class="meta-label">Mode</p>
                <div class="meta-value">Read / Write Query Execution</div>
            </div>
        </div>
    </section>

    <?php if (!empty($lowStockProducts)): ?>
    <div class="alert-panel" style="margin-bottom: 24px; border-left: 4px solid var(--warning);">
        <p class="panel-kicker" style="color: var(--warning);">⚠️ LOW STOCK ALERT</p>
        <h2 class="message-title" style="font-size: 1.2rem;">Products below minimum stock level</h2>
        <?php foreach ($lowStockProducts as $low): ?>
        <div class="alert-badge warning">
            <strong><?=htmlspecialchars($low['product_name'], ENT_QUOTES, 'UTF-8')?></strong> - 
            Current: <?=htmlspecialchars($low['quantity'], ENT_QUOTES, 'UTF-8')?> | 
            Minimum: <?=htmlspecialchars($low['min_stock'], ENT_QUOTES, 'UTF-8')?> | 
            Supplier: <?=htmlspecialchars($low['supplier_name'], ENT_QUOTES, 'UTF-8')?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Dashboard Charts Section -->
    <div class="dashboard-grid">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <p class="panel-kicker">Analytics</p>
                    <h2>Inventory Overview</h2>
                </div>
            </div>
            <div class="stats-card" style="margin-bottom: 20px;">
                <div class="stats-number"><?=htmlspecialchars($inventoryStats[0]['total_products'] ?? 0, ENT_QUOTES, 'UTF-8')?></div>
                <div class="stats-label">Total Active Products</div>
            </div>
            <div class="stats-card" style="margin-bottom: 20px;">
                <div class="stats-number"><?=htmlspecialchars($inventoryStats[0]['low_stock_count'] ?? 0, ENT_QUOTES, 'UTF-8')?></div>
                <div class="stats-label">Low Stock Items</div>
            </div>
            <div class="stats-card">
                <div class="stats-number"><?=htmlspecialchars($inventoryStats[0]['total_quantity'] ?? 0, ENT_QUOTES, 'UTF-8')?></div>
                <div class="stats-label">Total Units in Stock</div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <p class="panel-kicker">Visualization</p>
                    <h2>Stock Distribution</h2>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="stockChart"></canvas>
            </div>
            <div class="chart-container">
                <canvas id="categoryChart"></canvas>
            </div>
        </section>
    </div>

    <div class="layout-grid">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <p class="panel-kicker">SQL Workspace</p>
                    <h2>Execute SQL Statements</h2>
                </div>
                <span class="status-badge <?=$error ? 'status-error' : 'status-ready'?>"><?=$error ? 'Execution Error' : 'Ready'?></span>
            </div>

            <form method="post" class="editor-form">
                <input type="hidden" name="form_action" value="sql_execute">
                <textarea name="sql" class="form-control" rows="6" placeholder="Enter your SQL statement"><?=htmlspecialchars($sql, ENT_QUOTES, 'UTF-8')?></textarea>
                <div class="action-row">
                    <button class="btn-run" type="submit">Execute SQL</button>
                    <span class="helper-text"><?=$sql ? 'SQL preserved in textbox above.' : 'Supports SELECT, INSERT, UPDATE, DELETE.'?></span>
                </div>
            </form>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <p class="panel-kicker">Quick Queries</p>
                    <h2>Common Queries</h2>
                </div>
            </div>

            <div class="shortcut-groups">
                <section class="shortcut-section">
                    <p class="helper-label">Employee</p>
                    <h3>Employee Module</h3>
                    <div class="button-grid">
                        <button class="query-btn" type="button" onclick="setSql('SELECT * FROM employee WHERE is_active=1')">Active Employees</button>
                        <button class="query-btn" type="button" onclick="setSql('SELECT * FROM employee WHERE is_active=0')">Resigned Employees</button>
                        <button class="query-btn" type="button" onclick="setSql('SELECT job_position,COUNT(*) cnt,AVG(salary) avg FROM employee GROUP BY job_position')">Salary Statistics</button>
                    </div>
                </section>

                <section class="shortcut-section">
                    <p class="helper-label">Inventory</p>
                    <h3>Products & Stock</h3>
                    <div class="button-grid">
                        <button class="query-btn" type="button" onclick="setSql('SELECT p.*,c.category_name,s.supplier_name,i.quantity FROM product p JOIN category c ON p.category_id=c.category_id JOIN supplier s ON p.supplier_id=s.supplier_id JOIN inventory i ON p.product_id=i.product_id')">Product Inventory List</button>
                        <button class="query-btn" type="button" onclick="setSql('SELECT * FROM supplier')">Supplier List</button>
                    </div>
                </section>

                <section class="shortcut-section">
                    <p class="helper-label">Sales</p>
                    <h3>Sales Module</h3>
                    <div class="button-grid">
                        <button class="query-btn" type="button" onclick="setSql('SELECT * FROM sales_transaction ORDER BY transaction_time DESC LIMIT 20')">Recent 20 Orders</button>
                    </div>
                </section>

                <section class="shortcut-section">
                    <p class="helper-label">Purchase</p>
                    <h3>Purchase Module</h3>
                    <div class="button-grid">
                        <button class="query-btn" type="button" onclick="setSql('SELECT * FROM purchase_order ORDER BY receive_time DESC LIMIT 20')">Recent Purchase Orders</button>
                    </div>
                </section>
            </div>
        </section>
    </div>

    <!-- Stock Management Section -->
    <div class="layout-grid" style="margin-bottom: 24px;">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <p class="panel-kicker">Operations</p>
                    <h2>Stock Adjustment</h2>
                </div>
            </div>
            <form method="post" class="editor-form">
                <input type="hidden" name="form_action" value="">
                <input type="hidden" name="stock_action" value="adjust_stock">
                <div class="row g-3">
                    <div class="col-12">
                        <select name="stock_product_id" class="form-control" required>
                            <option value="">Select Product</option>
                            <?php foreach ($allProducts as $product): ?>
                            <option value="<?=$product['product_id']?>">
                                <?=htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8')?> 
                                (Stock: <?=$product['quantity']?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <input type="number" name="stock_quantity" class="form-control" placeholder="Quantity" required min="1">
                    </div>
                    <div class="col-6">
                        <select name="stock_operation" class="form-control" required>
                            <option value="add">Add Stock (+)</option>
                            <option value="remove">Remove Stock (-)</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button class="btn-run" type="submit" style="width: 100%;">Apply Stock Change</button>
                    </div>
                </div>
            </form>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <p class="panel-kicker">Operations</p>
                    <h2>Create Purchase Order</h2>
                </div>
            </div>
            <form method="post" class="editor-form">
                <input type="hidden" name="form_action" value="">
                <input type="hidden" name="order_action" value="create_order">
                <div class="row g-3">
                    <div class="col-12">
                        <select name="order_product_id" class="form-control" required>
                            <option value="">Select Product</option>
                            <?php foreach ($allProducts as $product): ?>
                            <option value="<?=$product['product_id']?>">
                                <?=htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8')?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <input type="number" name="order_quantity" class="form-control" placeholder="Quantity" required min="1">
                    </div>
                    <div class="col-6">
                        <select name="order_supplier_id" class="form-control" required>
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?=$supplier['supplier_id']?>">
                                <?=htmlspecialchars($supplier['supplier_name'], ENT_QUOTES, 'UTF-8')?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <button class="btn-run" type="submit" style="width: 100%;">Create Order & Restock</button>
                    </div>
                </div>
            </form>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <p class="panel-kicker">Operations</p>
                    <h2>Delete Order</h2>
                </div>
            </div>
            <form method="post" class="editor-form">
                <input type="hidden" name="form_action" value="">
                <input type="hidden" name="order_action" value="delete_order">
                <div class="row g-3">
                    <div class="col-12">
                        <select name="order_id" class="form-control" required>
                            <option value="">Select Order to Delete</option>
                            <?php foreach ($recentOrders as $order): ?>
                            <option value="<?=$order['purchase_order_id']?>">
                                Order #<?=$order['purchase_order_id']?> - 
                                <?=htmlspecialchars($order['supplier_name'], ENT_QUOTES, 'UTF-8')?> - 
                                $<?=$order['total_price']?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <button class="btn-secondary-custom" type="submit" style="width: 100%; background: rgba(234, 143, 133, 0.15); border-color: var(--red);">Delete Order</button>
                    </div>
                </div>
            </form>
        </section>
    </div>

    <div class="llm-grid">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <p class="panel-kicker">LLM Query</p>
                    <h2>Natural Language Database Query</h2>
                </div>
                <span class="status-badge <?=($llmError || $error) ? 'status-error' : 'status-ready'?>"><?=($llmError || $error) ? 'Needs Attention' : 'Ready'?></span>
            </div>

            <form method="post" class="editor-form">
                <input type="hidden" name="form_action" value="llm_query">
                <textarea name="llm_question" class="form-control" rows="5" placeholder="Example: What are the current active employees? Which products have low stock? How are recent purchase orders and sales?"><?=htmlspecialchars($llmQuestion, ENT_QUOTES, 'UTF-8')?></textarea>
                <div class="action-row">
                    <button class="btn-run" type="submit">LLM Query</button>
                    <span class="helper-text">The database context is injected when you click the button.</span>
                </div>
            </form>
        </section>

        <?php if ($llmError): ?>
            <section class="message-panel error">
                <p class="panel-kicker">LLM Feedback</p>
                <h2 class="message-title">LLM Query Failed</h2>
                <p class="message-body"><?=htmlspecialchars($llmError, ENT_QUOTES, 'UTF-8')?></p>
            </section>
        <?php elseif ($llmAnswer): ?>
            <section class="message-panel success">
                <p class="panel-kicker">LLM Answer</p>
                <h2 class="message-title">Query Result</h2>
                <div class="llm-answer"><?=htmlspecialchars($llmAnswer, ENT_QUOTES, 'UTF-8')?></div>
                <div class="llm-meta">
                    Based on <?=$llmMeta['tables'] ?? count($llmTables)?> tables,
                    Model: <?=htmlspecialchars((string)($llmMeta['model'] ?? $minimaxModel), ENT_QUOTES, 'UTF-8')?>
                    <?php if (!empty($llmMeta['tokens'])): ?>
                        , Total Tokens: <?=htmlspecialchars((string)$llmMeta['tokens'], ENT_QUOTES, 'UTF-8')?>
                    <?php endif ?>
                </div>
            </section>
        <?php endif ?>
    </div>

    <?php if ($error): ?>
        <section class="message-panel error">
            <p class="panel-kicker">Execution Feedback</p>
            <h2 class="message-title">Execution Failed</h2>
            <p class="message-body"><?=htmlspecialchars($error, ENT_QUOTES, 'UTF-8')?></p>
        </section>
    <?php endif ?>

    <?php if ($success): ?>
        <section class="message-panel success">
            <p class="panel-kicker">Execution Feedback</p>
            <h2 class="message-title">Success</h2>
            <p class="message-body"><?=htmlspecialchars($success, ENT_QUOTES, 'UTF-8')?></p>
        </section>
    <?php endif ?>

    <?php if ($cols): ?>
        <section class="result-panel">
            <div class="result-header">
                <div>
                    <p class="panel-kicker">Result Grid</p>
                    <h2>Query Results</h2>
                </div>
                <div class="result-meta"><?=count($rows)?> rows, <?=count($cols)?> columns</div>
            </div>
            <div class="table-shell">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <?php foreach ($cols as $c): ?>
                                <th><?=htmlspecialchars($c, ENT_QUOTES, 'UTF-8')?></th>
                            <?php endforeach ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <?php foreach ($r as $v): ?>
                                    <td><?=htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8')?></td>
                                <?php endforeach ?>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php elseif ($sql && !$error && !$success): ?>
        <section class="message-panel success">
            <p class="panel-kicker">Execution Feedback</p>
            <h2 class="message-title">Success</h2>
            <p class="message-body">Statement executed successfully, no result set to display.</p>
        </section>
    <?php endif ?>
</div>

<script>
function setSql(sql) {
    const textarea = document.querySelector('[name=sql]');
    textarea.value = sql;
    textarea.focus();
    textarea.setSelectionRange(textarea.value.length, textarea.value.length);
}

// Chart.js initialization
document.addEventListener('DOMContentLoaded', function() {
    <?php
    // Get category distribution data
    $categoryData = selectAllAssoc($conn, "
        SELECT c.category_name, COUNT(p.product_id) as product_count
        FROM category c
        JOIN product p ON c.category_id = p.category_id
        WHERE p.is_active = 1
        GROUP BY c.category_id
        LIMIT 10
    ");
    $categoryNames = array_column($categoryData, 'category_name');
    $categoryCounts = array_column($categoryData, 'product_count');
    
    // Get top 10 products by stock
    $topProducts = selectAllAssoc($conn, "
        SELECT p.product_name, i.quantity
        FROM product p
        JOIN inventory i ON p.product_id = i.product_id
        WHERE p.is_active = 1
        ORDER BY i.quantity DESC
        LIMIT 10
    ");
    $productNames = array_column($topProducts, 'product_name');
    $productQuantities = array_column($topProducts, 'quantity');
    ?>
    
    // Stock Distribution Chart
    const stockCtx = document.getElementById('stockChart').getContext('2d');
    new Chart(stockCtx, {
        type: 'bar',
        data: {
            labels: <?=json_encode($productNames)?>,
            datasets: [{
                label: 'Current Stock Quantity',
                data: <?=json_encode($productQuantities)?>,
                backgroundColor: 'rgba(214, 181, 113, 0.7)',
                borderColor: '#d6b571',
                borderWidth: 1,
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    labels: { color: '#f4efe6' }
                }
            },
            scales: {
                y: {
                    ticks: { color: '#b8ad9a' },
                    grid: { color: 'rgba(214, 181, 113, 0.1)' }
                },
                x: {
                    ticks: { color: '#b8ad9a', maxRotation: 45, minRotation: 45 },
                    grid: { display: false }
                }
            }
        }
    });
    
    // Category Distribution Pie Chart
    const categoryCtx = document.getElementById('categoryChart').getContext('2d');
    new Chart(categoryCtx, {
        type: 'pie',
        data: {
            labels: <?=json_encode($categoryNames)?>,
            datasets: [{
                data: <?=json_encode($categoryCounts)?>,
                backgroundColor: [
                    '#d6b571', '#7bc39b', '#ea8f85', '#f0a34b', 
                    '#6bb5d0', '#c98bbf', '#9b8b72', '#5fa89a',
                    '#b5873e', '#7d6b45'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'right',
                    labels: { color: '#f4efe6', font: { size: 10 } }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ' + context.raw + ' products';
                        }
                    }
                }
            }
        }
    });
});
</script>
</body>
</html>

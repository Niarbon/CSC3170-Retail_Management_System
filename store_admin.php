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
    $sql = "SELECT p.product_id, p.product_name, i.quantity, i.min_stock, p.sell_price, p.cost_price
            FROM product p
            JOIN inventory i ON p.product_id = i.product_id
            WHERE p.is_active = 1
            ORDER BY p.product_name";
    return selectAllAssoc($conn, $sql);
}

function getActiveEmployees(mysqli $conn): array
{
    $sql = "SELECT employee_id, employee_name FROM employee WHERE is_active = 1 ORDER BY employee_name";
    return selectAllAssoc($conn, $sql);
}

function getActiveMembers(mysqli $conn): array
{
    $sql = "SELECT member_id, member_name, points, phone_number FROM member WHERE is_active = 1 ORDER BY member_name";
    return selectAllAssoc($conn, $sql);
}

function getActiveSuppliers(mysqli $conn): array
{
    $sql = "SELECT supplier_id, supplier_name FROM supplier WHERE is_active = 1 ORDER BY supplier_name";
    return selectAllAssoc($conn, $sql);
}

function getRecentSalesTransactions(mysqli $conn): array
{
    $sql = "SELECT st.transaction_id, 
                    e.employee_name,
                    m.member_name,
                    st.transaction_time,
                    st.total_price,
                    st.discount,
                    st.payment_method,
                    (SELECT COUNT(*) FROM transaction_item WHERE transaction_id = st.transaction_id) as item_count
            FROM sales_transaction st
            LEFT JOIN employee e ON st.employee_id = e.employee_id
            LEFT JOIN member m ON st.member_id = m.member_id
            ORDER BY st.transaction_time DESC
            LIMIT 30";
    return selectAllAssoc($conn, $sql);
}

function getRecentPurchaseOrders(mysqli $conn): array
{
    $sql = "SELECT po.purchase_order_id, 
                    s.supplier_name,
                    e.employee_name,
                    po.receive_time,
                    po.total_price,
                    (SELECT COUNT(*) FROM purchase_order_item WHERE purchase_order_id = po.purchase_order_id) as item_count
            FROM purchase_order po
            LEFT JOIN supplier s ON po.supplier_id = s.supplier_id
            LEFT JOIN employee e ON po.employee_id = e.employee_id
            ORDER BY po.receive_time DESC
            LIMIT 30";
    return selectAllAssoc($conn, $sql);
}

function getTransactionItems(mysqli $conn, int $transactionId): array
{
    $sql = "SELECT ti.transaction_item_id, ti.product_id, p.product_name, ti.quantity, ti.unit_price, ti.subtotal
            FROM transaction_item ti
            JOIN product p ON ti.product_id = p.product_id
            WHERE ti.transaction_id = $transactionId";
    return selectAllAssoc($conn, $sql);
}

function createNewMember(mysqli $conn, string $name, string $phone): ?int
{
    $name = mysqli_real_escape_string($conn, $name);
    $phone = mysqli_real_escape_string($conn, $phone);
    $joinDate = date('Y-m-d');
    
    $sql = "INSERT INTO member (member_name, phone_number, points, join_date, is_active) 
            VALUES ('$name', '$phone', 0, '$joinDate', 1)";
    
    if (mysqli_query($conn, $sql)) {
        return mysqli_insert_id($conn);
    }
    return null;
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
$scrollTo = '';
$showTransactionResult = false;
$lastCreatedTransaction = null;

// Handle stock adjustment
$stockAction = $_POST['stock_action'] ?? '';
$stockProductId = $_POST['stock_product_id'] ?? '';
$stockQuantity = $_POST['stock_quantity'] ?? '';
$stockOperation = $_POST['stock_operation'] ?? '';

// Handle sales order actions
$orderAction = $_POST['order_action'] ?? '';
$orderProductIds = $_POST['order_product_ids'] ?? [];
$orderQuantities = $_POST['order_quantities'] ?? [];
$employeeId = $_POST['employee_id'] ?? '';
$memberId = $_POST['member_id'] ?? '';
$paymentMethod = $_POST['payment_method'] ?? '';
$discount = $_POST['discount'] ?? 0;

// Handle new member creation
$newMemberAction = $_POST['new_member_action'] ?? '';
$newMemberName = $_POST['new_member_name'] ?? '';
$newMemberPhone = $_POST['new_member_phone'] ?? '';

// Handle purchase order actions
$purchaseAction = $_POST['purchase_action'] ?? '';
$purchaseProductIds = $_POST['purchase_product_ids'] ?? [];
$purchaseQuantities = $_POST['purchase_quantities'] ?? [];
$purchaseSupplierId = $_POST['purchase_supplier_id'] ?? '';

// Handle delete item from sales transaction
$deleteItemAction = $_POST['delete_item_action'] ?? '';
$deleteTransactionId = $_POST['delete_transaction_id'] ?? '';
$deleteItemId = $_POST['delete_item_id'] ?? '';

// Handle delete entire transaction
$deleteTransactionAction = $_POST['delete_transaction_action'] ?? '';
$deleteFullTransactionId = $_POST['delete_full_transaction_id'] ?? '';

$conn = mysqli_connect($host, $user, $pwd, $dbname);
if (!$conn) {
    $error = 'Database connection failed: ' . mysqli_connect_error();
} else {
    mysqli_set_charset($conn, 'utf8mb4');

    // Handle new member creation from sales form
    if ($newMemberAction === 'create_member' && !empty($newMemberName) && !empty($newMemberPhone)) {
        $newId = createNewMember($conn, $newMemberName, $newMemberPhone);
        if ($newId) {
            $success = "New member created successfully! Member ID: $newId - Name: " . htmlspecialchars($newMemberName);
            // Set this as the selected member for the order
            $memberId = $newId;
            $scrollTo = 'sales_order_form';
        } else {
            $error = "Failed to create member. Phone number may already exist.";
        }
    }

    // Handle stock adjustment
    if ($stockAction === 'adjust_stock' && $stockProductId && $stockQuantity !== '') {
        $scrollTo = 'result';
        $stockQuantity = intval($stockQuantity);
        $productId = intval($stockProductId);
        
        if ($stockOperation === 'add') {
            $sql_update = "UPDATE inventory SET quantity = quantity + $stockQuantity, last_updated = CURRENT_TIMESTAMP WHERE product_id = $productId";
            if (mysqli_query($conn, $sql_update)) {
                $success = "Added $stockQuantity units to product (ID: $productId)";
                $log_sql = "INSERT INTO inventory_log (product_id, change_quantity, balance_after, change_reason, created_at) 
                            SELECT $productId, $stockQuantity, quantity, 'MANUAL_ADD', CURRENT_TIMESTAMP FROM inventory WHERE product_id = $productId";
                mysqli_query($conn, $log_sql);
            } else {
                $error = mysqli_error($conn);
            }
        } elseif ($stockOperation === 'remove') {
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

    // Handle multi-item purchase order creation
    if ($purchaseAction === 'create_purchase' && !empty($purchaseProductIds) && $purchaseSupplierId) {
        $scrollTo = 'result';
        $supplierId = intval($purchaseSupplierId);
        
        $items = [];
        foreach ($purchaseProductIds as $index => $productId) {
            if (!empty($productId) && !empty($purchaseQuantities[$index]) && intval($purchaseQuantities[$index]) > 0) {
                $items[] = [
                    'product_id' => intval($productId),
                    'quantity' => intval($purchaseQuantities[$index])
                ];
            }
        }
        
        if (empty($items)) {
            $error = "Please select at least one product with valid quantity.";
        } else {
            mysqli_begin_transaction($conn);
            try {
                $totalPrice = 0;
                
                foreach ($items as &$item) {
                    $price_sql = "SELECT cost_price FROM product WHERE product_id = {$item['product_id']}";
                    $price_result = mysqli_query($conn, $price_sql);
                    $product_data = mysqli_fetch_assoc($price_result);
                    $item['unit_price'] = $product_data['cost_price'];
                    $item['subtotal'] = round($item['quantity'] * $item['unit_price'], 2);
                    $totalPrice += $item['subtotal'];
                }
                
                $po_sql = "INSERT INTO purchase_order (supplier_id, employee_id, total_price, receive_time) 
                           VALUES ($supplierId, 1, $totalPrice, CURRENT_TIMESTAMP)";
                mysqli_query($conn, $po_sql);
                $poId = mysqli_insert_id($conn);
                
                foreach ($items as $item) {
                    $poi_sql = "INSERT INTO purchase_order_item (purchase_order_id, product_id, unit_price, quantity, subtotal) 
                                VALUES ($poId, {$item['product_id']}, {$item['unit_price']}, {$item['quantity']}, {$item['subtotal']})";
                    mysqli_query($conn, $poi_sql);
                    $poiId = mysqli_insert_id($conn);
                    
                    $inv_sql = "UPDATE inventory SET quantity = quantity + {$item['quantity']}, last_updated = CURRENT_TIMESTAMP 
                                WHERE product_id = {$item['product_id']}";
                    mysqli_query($conn, $inv_sql);
                    
                    $log_sql = "INSERT INTO inventory_log (product_id, purchase_item_id, change_quantity, balance_after, change_reason, created_at) 
                                SELECT {$item['product_id']}, $poiId, {$item['quantity']}, quantity, 'PURCHASE', CURRENT_TIMESTAMP 
                                FROM inventory WHERE product_id = {$item['product_id']}";
                    mysqli_query($conn, $log_sql);
                }
                
                mysqli_commit($conn);
                $success = "Purchase order created successfully! Order ID: $poId - " . count($items) . " item(s). Total: $$totalPrice";
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = "Purchase order creation failed: " . $e->getMessage();
            }
        }
    }

    // Handle delete item from sales transaction
    if ($deleteItemAction === 'delete_item' && $deleteTransactionId && $deleteItemId) {
        $scrollTo = 'result';
        $transactionId = intval($deleteTransactionId);
        $itemId = intval($deleteItemId);
        
        mysqli_begin_transaction($conn);
        try {
            $item_sql = "SELECT product_id, quantity, subtotal FROM transaction_item WHERE transaction_item_id = $itemId";
            $item_result = mysqli_query($conn, $item_sql);
            $item = mysqli_fetch_assoc($item_result);
            
            if ($item) {
                $inv_sql = "UPDATE inventory SET quantity = quantity + {$item['quantity']} WHERE product_id = {$item['product_id']}";
                mysqli_query($conn, $inv_sql);
                
                $del_sql = "DELETE FROM transaction_item WHERE transaction_item_id = $itemId";
                mysqli_query($conn, $del_sql);
                
                $update_total = "UPDATE sales_transaction SET total_price = (SELECT IFNULL(SUM(subtotal), 0) FROM transaction_item WHERE transaction_id = $transactionId) WHERE transaction_id = $transactionId";
                mysqli_query($conn, $update_total);
                
                $check_items = "SELECT COUNT(*) as cnt FROM transaction_item WHERE transaction_id = $transactionId";
                $check_result = mysqli_query($conn, $check_items);
                $item_count = mysqli_fetch_assoc($check_result);
                
                if ($item_count['cnt'] == 0) {
                    $del_trans = "DELETE FROM sales_transaction WHERE transaction_id = $transactionId";
                    mysqli_query($conn, $del_trans);
                    $success = "Item removed and transaction deleted (no items remaining)";
                } else {
                    $success = "Item removed from transaction successfully. Inventory restored.";
                }
                
                $log_sql = "INSERT INTO inventory_log (product_id, change_quantity, balance_after, change_reason, created_at) 
                            SELECT {$item['product_id']}, {$item['quantity']}, quantity, 'SALE_ITEM_REMOVED', CURRENT_TIMESTAMP 
                            FROM inventory WHERE product_id = {$item['product_id']}";
                mysqli_query($conn, $log_sql);
            }
            
            mysqli_commit($conn);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Failed to delete item: " . $e->getMessage();
        }
    }

    // Handle delete entire sales transaction
    if ($deleteTransactionAction === 'delete_full_transaction' && $deleteFullTransactionId) {
        $scrollTo = 'result';
        $transactionId = intval($deleteFullTransactionId);
        
        mysqli_begin_transaction($conn);
        try {
            $items_sql = "SELECT product_id, quantity FROM transaction_item WHERE transaction_id = $transactionId";
            $items_result = mysqli_query($conn, $items_sql);
            while ($item = mysqli_fetch_assoc($items_result)) {
                $inv_sql = "UPDATE inventory SET quantity = quantity + {$item['quantity']} WHERE product_id = {$item['product_id']}";
                mysqli_query($conn, $inv_sql);
                
                $log_sql = "INSERT INTO inventory_log (product_id, change_quantity, balance_after, change_reason, created_at) 
                            SELECT {$item['product_id']}, {$item['quantity']}, quantity, 'TRANSACTION_DELETED', CURRENT_TIMESTAMP 
                            FROM inventory WHERE product_id = {$item['product_id']}";
                mysqli_query($conn, $log_sql);
            }
            
            $del_sql = "DELETE FROM sales_transaction WHERE transaction_id = $transactionId";
            mysqli_query($conn, $del_sql);
            
            mysqli_commit($conn);
            $success = "Sales transaction (ID: $transactionId) deleted successfully. Inventory restored.";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Failed to delete transaction: " . $e->getMessage();
        }
    }

    // Handle multi-item sales order creation
    if ($orderAction === 'create_sale' && !empty($orderProductIds) && $employeeId) {
        $scrollTo = 'result';
        $empId = intval($employeeId);
        $memId = !empty($memberId) ? intval($memberId) : null;
        $payMethod = mysqli_real_escape_string($conn, $paymentMethod);
        $discountAmount = floatval($discount);
        
        $items = [];
        foreach ($orderProductIds as $index => $productId) {
            if (!empty($productId) && !empty($orderQuantities[$index]) && intval($orderQuantities[$index]) > 0) {
                $items[] = [
                    'product_id' => intval($productId),
                    'quantity' => intval($orderQuantities[$index])
                ];
            }
        }
        
        if (empty($items)) {
            $error = "Please select at least one product with valid quantity.";
        } else {
            mysqli_begin_transaction($conn);
            try {
                $totalPrice = 0;
                
                foreach ($items as &$item) {
                    $price_sql = "SELECT sell_price FROM product WHERE product_id = {$item['product_id']}";
                    $price_result = mysqli_query($conn, $price_sql);
                    $product_data = mysqli_fetch_assoc($price_result);
                    $item['unit_price'] = $product_data['sell_price'];
                    $item['subtotal'] = round($item['quantity'] * $item['unit_price'], 2);
                    $totalPrice += $item['subtotal'];
                    
                    $stock_sql = "SELECT quantity FROM inventory WHERE product_id = {$item['product_id']}";
                    $stock_result = mysqli_query($conn, $stock_sql);
                    $stock_data = mysqli_fetch_assoc($stock_result);
                    if ($stock_data['quantity'] < $item['quantity']) {
                        throw new Exception("Insufficient stock for product ID: {$item['product_id']}. Available: {$stock_data['quantity']}");
                    }
                }
                
                $finalPrice = max(0, $totalPrice - $discountAmount);
                
                $memberSql = $memId ? $memId : "NULL";
                $trans_sql = "INSERT INTO sales_transaction (employee_id, member_id, transaction_time, total_price, discount, payment_method) 
                              VALUES ($empId, $memberSql, CURRENT_TIMESTAMP, $finalPrice, $discountAmount, '$payMethod')";
                mysqli_query($conn, $trans_sql);
                $transId = mysqli_insert_id($conn);
                
                $itemsList = [];
                foreach ($items as $item) {
                    $ti_sql = "INSERT INTO transaction_item (transaction_id, product_id, unit_price, quantity, subtotal) 
                               VALUES ($transId, {$item['product_id']}, {$item['unit_price']}, {$item['quantity']}, {$item['subtotal']})";
                    mysqli_query($conn, $ti_sql);
                    $tiId = mysqli_insert_id($conn);
                    
                    $inv_sql = "UPDATE inventory SET quantity = quantity - {$item['quantity']}, last_updated = CURRENT_TIMESTAMP 
                                WHERE product_id = {$item['product_id']}";
                    mysqli_query($conn, $inv_sql);
                    
                    $log_sql = "INSERT INTO inventory_log (product_id, transaction_item_id, change_quantity, balance_after, change_reason, created_at) 
                                SELECT {$item['product_id']}, $tiId, -{$item['quantity']}, quantity, 'SALE', CURRENT_TIMESTAMP 
                                FROM inventory WHERE product_id = {$item['product_id']}";
                    mysqli_query($conn, $log_sql);
                    
                    $itemsList[] = $item;
                }
                
                if ($memId) {
                    $pointsEarned = floor($finalPrice);
                    $points_sql = "UPDATE member SET points = points + $pointsEarned WHERE member_id = $memId";
                    mysqli_query($conn, $points_sql);
                    
                    $points_log_sql = "INSERT INTO points_log (member_id, transaction_id, points_delta, balance_after, change_reason, created_at) 
                                       SELECT $memId, $transId, $pointsEarned, points, 'EARN_FROM_PURCHASE', CURRENT_TIMESTAMP 
                                       FROM member WHERE member_id = $memId";
                    mysqli_query($conn, $points_log_sql);
                }
                
                mysqli_commit($conn);
                
                $showTransactionResult = true;
                $memberText = $memId ? "Member (ID: $memId)" : "Guest";
                $success = "✅ Sales transaction created successfully! Transaction ID: $transId - " . count($items) . " item(s) sold to $memberText. Subtotal: $$totalPrice, Discount: $$discountAmount, Total: $$finalPrice";
                
                $transactionDetail = selectAllAssoc($conn, "
                    SELECT st.transaction_id, e.employee_name, 
                           IFNULL(m.member_name, 'Guest') as customer_name,
                           st.transaction_time, st.total_price, st.discount, st.payment_method,
                           (SELECT COUNT(*) FROM transaction_item WHERE transaction_id = st.transaction_id) as item_count
                    FROM sales_transaction st
                    LEFT JOIN employee e ON st.employee_id = e.employee_id
                    LEFT JOIN member m ON st.member_id = m.member_id
                    WHERE st.transaction_id = $transId
                ");
                
                if (!empty($transactionDetail)) {
                    $cols = ['transaction_id', 'employee_name', 'customer_name', 'transaction_time', 'total_price', 'discount', 'payment_method', 'item_count'];
                    $rows = $transactionDetail;
                    
                    $itemsDetail = selectAllAssoc($conn, "
                        SELECT p.product_name, ti.quantity, ti.unit_price, ti.subtotal
                        FROM transaction_item ti
                        JOIN product p ON ti.product_id = p.product_id
                        WHERE ti.transaction_id = $transId
                    ");
                    
                    if (!empty($itemsDetail)) {
                        $rows[0]['items'] = json_encode($itemsDetail);
                    }
                }
                
                $lastCreatedTransaction = $transId;
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = "Sale creation failed: " . $e->getMessage();
            }
        }
    }

    if ($formAction === 'llm_query') {
        $scrollTo = 'llm';
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
    } elseif ($sql && !$showTransactionResult) {
        $scrollTo = 'result';
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
    
    // Get data for charts and alerts (always refresh)
    $lowStockProducts = getLowStockProducts($conn);
    $inventoryStats = getInventoryStats($conn);
    $allProducts = getAllProducts($conn);
    $employees = getActiveEmployees($conn);
    $members = getActiveMembers($conn);
    $suppliers = getActiveSuppliers($conn);
    $recentTransactions = getRecentSalesTransactions($conn);
    $recentPurchaseOrders = getRecentPurchaseOrders($conn);
    
    // Get items for selected transaction (for delete item dropdown)
    $selectedTransactionForDelete = $_POST['selected_transaction_for_delete'] ?? '';
    $transactionItems = [];
    if ($selectedTransactionForDelete) {
        $transactionItems = getTransactionItems($conn, intval($selectedTransactionForDelete));
    }
}

// Generate scroll JavaScript
$scrollScript = '';
if ($scrollTo === 'result') {
    $scrollScript = '<script>document.addEventListener("DOMContentLoaded", function() { document.getElementById("result-section").scrollIntoView({ behavior: "smooth", block: "start" }); });</script>';
} elseif ($scrollTo === 'llm') {
    $scrollScript = '<script>document.addEventListener("DOMContentLoaded", function() { document.getElementById("llm-section").scrollIntoView({ behavior: "smooth", block: "start" }); });</script>';
} elseif ($scrollTo === 'sales_order_form') {
    $scrollScript = '<script>document.addEventListener("DOMContentLoaded", function() { document.getElementById("sales-order-form").scrollIntoView({ behavior: "smooth", block: "start" }); });</script>';
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
        --info: #6bb5d0;
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

    .btn-add-item {
        border-radius: 999px;
        padding: 8px 16px;
        background: rgba(123, 195, 155, 0.2);
        color: var(--green);
        border: 1px solid var(--green);
        margin-top: 10px;
    }

    .btn-new-member {
        border-radius: 999px;
        padding: 8px 16px;
        background: rgba(107, 181, 208, 0.2);
        color: var(--info);
        border: 1px solid var(--info);
        margin-left: 10px;
        white-space: nowrap;
    }

    .btn-new-member:hover {
        background: rgba(107, 181, 208, 0.35);
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

    .item-row {
        display: flex;
        gap: 12px;
        margin-bottom: 12px;
        align-items: center;
    }

    .item-row select {
        flex: 2;
    }

    .item-row input {
        flex: 1;
    }

    .form-row {
        display: flex;
        gap: 12px;
        margin-bottom: 12px;
        flex-wrap: wrap;
    }

    .form-row > * {
        flex: 1;
        min-width: 120px;
    }

    .field-label {
        font-size: 0.7rem;
        color: var(--gold);
        margin-bottom: 4px;
        display: block;
        letter-spacing: 0.05em;
    }

    .input-wrapper {
        margin-bottom: 8px;
    }

    .member-select-wrapper {
        display: flex;
        gap: 10px;
        align-items: flex-end;
    }

    .member-select-wrapper select {
        flex: 2;
    }

    .member-select-wrapper button {
        flex-shrink: 0;
    }

    .discount-preview {
        font-size: 0.85rem;
        color: var(--green);
        margin-top: 8px;
        padding: 8px 12px;
        background: rgba(123, 195, 155, 0.1);
        border-radius: 12px;
        text-align: center;
    }

    .new-transaction-badge {
        background: rgba(123, 195, 155, 0.2);
        border: 1px solid var(--green);
        border-radius: 20px;
        padding: 2px 10px;
        font-size: 0.7rem;
        margin-left: 10px;
        color: var(--green);
    }

    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(5px);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }

    .modal-content {
        background: linear-gradient(180deg, rgba(23, 28, 40, 0.98), rgba(12, 15, 22, 0.98));
        border: 1px solid var(--border-strong);
        border-radius: var(--radius-xl);
        padding: 30px;
        width: 90%;
        max-width: 450px;
        box-shadow: var(--shadow);
    }

    .modal-content h3 {
        margin-top: 0;
        color: var(--gold);
        font-family: var(--serif);
    }

    .modal-buttons {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        margin-top: 20px;
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
        
        .item-row {
            flex-direction: column;
        }
        
        .form-row {
            flex-direction: column;
        }
        
        .member-select-wrapper {
            flex-direction: column;
        }
        
        .member-select-wrapper button {
            width: 100%;
            margin-left: 0;
            margin-top: 8px;
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
            <p class="hero-text">A lightweight SQL workbench for course projects. Execute queries, manage inventory, create sales orders, and analyze data with AI assistance.</p>
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
                        <button class="query-btn" type="button" onclick="setSql('SELECT * FROM sales_transaction ORDER BY transaction_time DESC LIMIT 20')">Recent Sales Transactions</button>
                        <button class="query-btn" type="button" onclick="setSql('SELECT * FROM member ORDER BY points DESC')">Member Points Ranking</button>
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
                <input type="hidden" name="stock_action" value="adjust_stock">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="input-wrapper">
                            <span class="field-label">📦 SELECT PRODUCT</span>
                            <select name="stock_product_id" class="form-control" required>
                                <option value="">Select Product</option>
                                <?php foreach ($allProducts as $product): ?>
                                <option value="<?=$product['product_id']?>">
                                    <?=htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8')?> 
                                    (Stock: <?=$product['quantity']?> | Price: $<?=$product['sell_price']?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="input-wrapper">
                            <span class="field-label">🔢 QUANTITY</span>
                            <input type="number" name="stock_quantity" class="form-control" placeholder="Quantity" required min="1">
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="input-wrapper">
                            <span class="field-label">⚙️ OPERATION</span>
                            <select name="stock_operation" class="form-control" required>
                                <option value="add">➕ Add Stock (+)</option>
                                <option value="remove">➖ Remove Stock (-)</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-12">
                        <button class="btn-run" type="submit" style="width: 100%;">Apply Stock Change</button>
                    </div>
                </div>
            </form>
        </section>

        <!-- Multi-Item Sales Order Creation (Customer Sales) -->
        <section class="panel" id="sales-order-form">
            <div class="panel-header">
                <div>
                    <p class="panel-kicker">Operations</p>
                    <h2>🛒 Create Sales Order (Customer)</h2>
                </div>
            </div>
            <form method="post" class="editor-form" id="orderForm" onchange="updateDiscountPreview()" onkeyup="updateDiscountPreview()">
                <input type="hidden" name="order_action" value="create_sale">
                <div class="row g-3">
                    <div class="form-row">
                        <div class="input-wrapper" style="flex:1">
                            <span class="field-label">👤 CASHIER</span>
                            <select name="employee_id" class="form-control" required>
                                <option value="">Select Cashier</option>
                                <?php foreach ($employees as $emp): ?>
                                <option value="<?=$emp['employee_id']?>">
                                    <?=htmlspecialchars($emp['employee_name'], ENT_QUOTES, 'UTF-8')?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="input-wrapper" style="flex:2">
                            <span class="field-label">💎 CUSTOMER</span>
                            <div class="member-select-wrapper">
                                <select name="member_id" class="form-control" id="memberSelect">
                                    <option value="">👤 Guest (No Member)</option>
                                    <?php foreach ($members as $mem): ?>
                                    <option value="<?=$mem['member_id']?>" <?=($memberId == $mem['member_id']) ? 'selected' : ''?>>
                                        ⭐ <?=htmlspecialchars($mem['member_name'], ENT_QUOTES, 'UTF-8')?> 
                                        (Points: <?=$mem['points']?> | Tel: <?=htmlspecialchars($mem['phone_number'], ENT_QUOTES, 'UTF-8')?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn-new-member" onclick="openNewMemberModal()">➕ New Member</button>
                            </div>
                        </div>
                        <div class="input-wrapper" style="flex:1">
                            <span class="field-label">💳 PAYMENT METHOD</span>
                            <select name="payment_method" class="form-control" required>
                                <option value="">Select Method</option>
                                <option value="CASH">💰 Cash</option>
                                <option value="CARD">💳 Card</option>
                                <option value="MOBILE_PAY">📱 Mobile Pay</option>
                                <option value="BANK_TRANSFER">🏦 Bank Transfer</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="input-wrapper">
                            <span class="field-label">🏷️ DISCOUNT (USD)</span>
                            <input type="number" name="discount" id="discountInput" class="form-control" placeholder="Discount amount in USD" step="0.01" min="0" value="0" onchange="updateDiscountPreview()" onkeyup="updateDiscountPreview()">
                        </div>
                        <div class="discount-preview" id="discountPreview">
                            Subtotal: $0.00 → After Discount: $0.00
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="input-wrapper">
                            <span class="field-label">🛒 ITEMS (Select product and quantity)</span>
                            <div id="itemsContainer">
                                <div class="item-row">
                                    <select name="order_product_ids[]" class="form-control productSelect" required onchange="updateDiscountPreview()">
                                        <option value="">Select Product</option>
                                        <?php foreach ($allProducts as $product): ?>
                                        <option value="<?=$product['product_id']?>" data-price="<?=$product['sell_price']?>" data-stock="<?=$product['quantity']?>">
                                            <?=htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8')?> 
                                            ($<?=$product['sell_price']?> | Stock: <?=$product['quantity']?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="number" name="order_quantities[]" class="form-control quantityInput" placeholder="Quantity (min 1)" required min="1" onchange="updateDiscountPreview()" onkeyup="updateDiscountPreview()">
                                </div>
                            </div>
                            <button type="button" class="btn-add-item" onclick="addOrderItem()">+ Add Another Item</button>
                        </div>
                    </div>
                    <div class="col-12">
                        <button class="btn-run" type="submit" style="width: 100%;">✅ Create Sales Order</button>
                    </div>
                </div>
            </form>
        </section>
    </div>

    <!-- New Member Modal -->
    <div id="newMemberModal" class="modal-overlay">
        <div class="modal-content">
            <h3>✨ Create New Member</h3>
            <form method="post" id="newMemberForm">
                <input type="hidden" name="new_member_action" value="create_member">
                <div class="input-wrapper" style="margin-bottom: 15px;">
                    <span class="field-label">👤 MEMBER NAME</span>
                    <input type="text" name="new_member_name" class="form-control" placeholder="Enter member name" required>
                </div>
                <div class="input-wrapper" style="margin-bottom: 15px;">
                    <span class="field-label">📞 PHONE NUMBER</span>
                    <input type="tel" name="new_member_phone" class="form-control" placeholder="Enter phone number" required>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn-secondary-custom" onclick="closeNewMemberModal()">Cancel</button>
                    <button type="submit" class="btn-run">Create Member</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Purchase Order Creation Section -->
    <div class="layout-grid" style="margin-bottom: 24px;">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <p class="panel-kicker">Operations</p>
                    <h2>📦 Create Purchase Order (Supplier)</h2>
                </div>
            </div>
            <form method="post" class="editor-form" id="purchaseForm">
                <input type="hidden" name="purchase_action" value="create_purchase">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="input-wrapper">
                            <span class="field-label">🏭 SELECT SUPPLIER</span>
                            <select name="purchase_supplier_id" class="form-control" required>
                                <option value="">Select Supplier</option>
                                <?php foreach ($suppliers as $sup): ?>
                                <option value="<?=$sup['supplier_id']?>">
                                    🏢 <?=htmlspecialchars($sup['supplier_name'], ENT_QUOTES, 'UTF-8')?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="input-wrapper">
                            <span class="field-label">📦 ITEMS TO PURCHASE</span>
                            <div id="purchaseItemsContainer">
                                <div class="item-row">
                                    <select name="purchase_product_ids[]" class="form-control purchaseProductSelect" required>
                                        <option value="">Select Product</option>
                                        <?php foreach ($allProducts as $product): ?>
                                        <option value="<?=$product['product_id']?>" data-cost="<?=$product['cost_price']?>">
                                            <?=htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8')?> 
                                            (Cost: $<?=$product['cost_price']?> | Stock: <?=$product['quantity']?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="number" name="purchase_quantities[]" class="form-control" placeholder="Quantity" required min="1">
                                </div>
                            </div>
                            <button type="button" class="btn-add-item" onclick="addPurchaseItem()">+ Add Another Product</button>
                        </div>
                    </div>
                    <div class="col-12">
                        <button class="btn-run" type="submit" style="width: 100%;">✅ Create Purchase Order & Restock</button>
                    </div>
                </div>
            </form>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <p class="panel-kicker">Operations</p>
                    <h2>🗑️ Delete Transaction Item</h2>
                </div>
            </div>
            <form method="post" class="editor-form" onchange="this.submit()">
                <input type="hidden" name="form_action" value="">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="input-wrapper">
                            <span class="field-label">📋 SELECT TRANSACTION</span>
                            <select name="selected_transaction_for_delete" class="form-control" onchange="this.form.submit()">
                                <option value="">Select Transaction to View Items</option>
                                <?php foreach ($recentTransactions as $trans): ?>
                                <option value="<?=$trans['transaction_id']?>" <?=($selectedTransactionForDelete == $trans['transaction_id']) ? 'selected' : ''?>>
                                    Transaction #<?=$trans['transaction_id']?> - 
                                    <?=htmlspecialchars($trans['employee_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8')?> - 
                                    $<?=$trans['total_price']?> (<?=$trans['item_count']?> items)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </form>
            
            <?php if ($selectedTransactionForDelete && !empty($transactionItems)): ?>
            <form method="post" style="margin-top: 20px;">
                <input type="hidden" name="delete_item_action" value="delete_item">
                <input type="hidden" name="delete_transaction_id" value="<?=htmlspecialchars($selectedTransactionForDelete, ENT_QUOTES, 'UTF-8')?>">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="input-wrapper">
                            <span class="field-label">🗑️ SELECT ITEM TO REMOVE</span>
                            <select name="delete_item_id" class="form-control" required>
                                <option value="">Select Item to Remove</option>
                                <?php foreach ($transactionItems as $item): ?>
                                <option value="<?=$item['transaction_item_id']?>">
                                    <?=htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8')?> - 
                                    Qty: <?=$item['quantity']?> - 
                                    Price: $<?=$item['unit_price']?> - 
                                    Subtotal: $<?=$item['subtotal']?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-12">
                        <button class="btn-secondary-custom" type="submit" style="width: 100%; background: rgba(234, 143, 133, 0.15); border-color: var(--red);">Remove Selected Item & Restore Stock</button>
                    </div>
                </div>
            </form>
            <?php elseif ($selectedTransactionForDelete && empty($transactionItems)): ?>
            <div class="alert-badge warning" style="margin-top: 20px;">
                No items found in this transaction.
            </div>
            <?php endif; ?>
        </section>
    </div>

    <!-- Delete Entire Transaction Section -->
    <div class="layout-grid" style="margin-bottom: 24px;">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <p class="panel-kicker">Operations</p>
                    <h2>🗑️ Delete Entire Transaction</h2>
                </div>
            </div>
            <form method="post" class="editor-form">
                <input type="hidden" name="delete_transaction_action" value="delete_full_transaction">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="input-wrapper">
                            <span class="field-label">📋 SELECT TRANSACTION TO DELETE</span>
                            <select name="delete_full_transaction_id" class="form-control" required>
                                <option value="">Select Transaction to Delete</option>
                                <?php foreach ($recentTransactions as $trans): ?>
                                <option value="<?=$trans['transaction_id']?>">
                                    Transaction #<?=$trans['transaction_id']?> - 
                                    <?=htmlspecialchars($trans['employee_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8')?> - 
                                    $<?=$trans['total_price']?> - 
                                    <?=$trans['transaction_time']?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-12">
                        <button class="btn-secondary-custom" type="submit" style="width: 100%; background: rgba(234, 143, 133, 0.15); border-color: var(--red);">Delete Entire Transaction & Restore Stock</button>
                    </div>
                </div>
            </form>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <p class="panel-kicker">Operations</p>
                    <h2>📋 View Purchase Orders</h2>
                </div>
            </div>
            <div class="table-shell" style="max-height: 300px; overflow-y: auto;">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>PO ID</th>
                            <th>Supplier</th>
                            <th>Employee</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Items</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentPurchaseOrders as $po): ?>
                        <tr>
                            <td>#<?=htmlspecialchars($po['purchase_order_id'], ENT_QUOTES, 'UTF-8')?></td>
                            <td><?=htmlspecialchars($po['supplier_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8')?></td>
                            <td><?=htmlspecialchars($po['employee_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8')?></td>
                            <td><?=htmlspecialchars($po['receive_time'], ENT_QUOTES, 'UTF-8')?></td>
                            <td>$<?=number_format($po['total_price'], 2)?></td>
                            <td><?=htmlspecialchars($po['item_count'], ENT_QUOTES, 'UTF-8')?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentPurchaseOrders)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">No purchase orders found</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <!-- Recent Sales Transactions Table -->
    <div class="result-panel" style="margin-bottom: 24px;" id="result-section">
        <div class="result-header">
            <div>
                <p class="panel-kicker">Sales History</p>
                <h2>🛒 Recent Sales Transactions 
                    <?php if ($lastCreatedTransaction): ?>
                        <span class="new-transaction-badge">New! Transaction #<?=$lastCreatedTransaction?></span>
                    <?php endif; ?>
                </h2>
            </div>
            <div class="result-meta"><?=count($recentTransactions)?> transactions</div>
        </div>
        <div class="table-shell">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Transaction ID</th>
                        <th>Cashier</th>
                        <th>Customer</th>
                        <th>Date & Time</th>
                        <th>Subtotal</th>
                        <th>Discount</th>
                        <th>Final Amount</th>
                        <th>Payment Method</th>
                        <th>Items</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentTransactions as $trans): ?>
                    <tr <?=($lastCreatedTransaction == $trans['transaction_id']) ? 'style="background: rgba(123, 195, 155, 0.15); border-left: 3px solid #7bc39b;"' : ''?>>
                        <td>#<?=htmlspecialchars($trans['transaction_id'], ENT_QUOTES, 'UTF-8')?>
                            <?php if ($lastCreatedTransaction == $trans['transaction_id']): ?>
                                <span class="new-transaction-badge" style="margin-left: 8px;">NEW</span>
                            <?php endif; ?>
                        </td>
                        <td><?=htmlspecialchars($trans['employee_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8')?></td>
                        <td><?=htmlspecialchars($trans['member_name'] ?? 'Guest', ENT_QUOTES, 'UTF-8')?></td>
                        <td><?=htmlspecialchars($trans['transaction_time'], ENT_QUOTES, 'UTF-8')?></td>
                        <td>$<?=number_format($trans['total_price'] + $trans['discount'], 2)?></td>
                        <td>-$<?=number_format($trans['discount'], 2)?></td>
                        <td><strong>$<?=number_format($trans['total_price'], 2)?></strong></td>
                        <td><?=htmlspecialchars($trans['payment_method'], ENT_QUOTES, 'UTF-8')?></td>
                        <td><?=htmlspecialchars($trans['item_count'], ENT_QUOTES, 'UTF-8')?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentTransactions)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center;">No sales transactions found</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Query Results Section (for SQL queries and newly created transaction details) -->
    <?php if ($cols && !empty($rows)): ?>
    <div class="result-panel" style="margin-bottom: 24px;">
        <div class="result-header">
            <div>
                <p class="panel-kicker">Query Results</p>
                <h2>📊 Transaction Details</h2>
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
                        <?php foreach ($r as $key => $v): ?>
                            <?php if ($key === 'items' && is_string($v)): ?>
                                <td>
                                    <details>
                                        <summary style="color: var(--green); cursor: pointer;">📋 View Items</summary>
                                        <pre style="margin-top: 8px; font-size: 0.8rem; color: var(--muted);"><?=htmlspecialchars(json_decode($v) ? json_encode(json_decode($v), JSON_PRETTY_PRINT) : $v, ENT_QUOTES, 'UTF-8')?></pre>
                                    </details>
                                </td>
                            <?php else: ?>
                                <td><?=htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8')?></td>
                            <?php endif; ?>
                        <?php endforeach ?>
                    </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="llm-grid" id="llm-section">
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
                <textarea name="llm_question" class="form-control" rows="5" placeholder="Example: What are the current active employees? Which products have low stock? How are recent sales transactions? Which member has the most points?"><?=htmlspecialchars($llmQuestion, ENT_QUOTES, 'UTF-8')?></textarea>
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

    <?php if ($success && !$showTransactionResult): ?>
        <section class="message-panel success">
            <p class="panel-kicker">Execution Feedback</p>
            <h2 class="message-title">Success</h2>
            <p class="message-body"><?=htmlspecialchars($success, ENT_QUOTES, 'UTF-8')?></p>
        </section>
    <?php endif ?>
</div>

<script>
let itemCount = 1;
let purchaseItemCount = 1;

function setSql(sql) {
    const textarea = document.querySelector('[name=sql]');
    textarea.value = sql;
    textarea.focus();
    textarea.setSelectionRange(textarea.value.length, textarea.value.length);
}

function openNewMemberModal() {
    document.getElementById('newMemberModal').style.display = 'flex';
}

function closeNewMemberModal() {
    document.getElementById('newMemberModal').style.display = 'none';
    document.getElementById('newMemberForm').reset();
}

function addOrderItem() {
    const container = document.getElementById('itemsContainer');
    const originalRow = container.querySelector('.item-row');
    const newRow = originalRow.cloneNode(true);
    
    newRow.querySelector('select').value = '';
    newRow.querySelector('input').value = '';
    
    newRow.querySelector('select').addEventListener('change', updateDiscountPreview);
    newRow.querySelector('input').addEventListener('change', updateDiscountPreview);
    newRow.querySelector('input').addEventListener('keyup', updateDiscountPreview);
    
    container.appendChild(newRow);
    itemCount++;
}

function addPurchaseItem() {
    const container = document.getElementById('purchaseItemsContainer');
    const originalRow = container.querySelector('.item-row');
    const newRow = originalRow.cloneNode(true);
    
    newRow.querySelector('select').value = '';
    newRow.querySelector('input').value = '';
    
    container.appendChild(newRow);
    purchaseItemCount++;
}

function calculateSubtotal() {
    let subtotal = 0;
    const productSelects = document.querySelectorAll('.productSelect');
    const quantityInputs = document.querySelectorAll('.quantityInput');
    
    for (let i = 0; i < productSelects.length; i++) {
        const select = productSelects[i];
        const quantity = parseInt(quantityInputs[i]?.value) || 0;
        const selectedOption = select.options[select.selectedIndex];
        const price = selectedOption ? parseFloat(selectedOption.dataset.price) || 0 : 0;
        
        if (quantity > 0 && price > 0) {
            subtotal += price * quantity;
        }
    }
    return subtotal;
}

function updateDiscountPreview() {
    const subtotal = calculateSubtotal();
    const discount = parseFloat(document.getElementById('discountInput')?.value) || 0;
    const finalAmount = Math.max(0, subtotal - discount);
    
    const previewDiv = document.getElementById('discountPreview');
    if (previewDiv) {
        previewDiv.innerHTML = `💰 Subtotal: $${subtotal.toFixed(2)} → 🏷️ Discount: -$${discount.toFixed(2)} → ✅ Final: $${finalAmount.toFixed(2)}`;
        if (discount > subtotal) {
            previewDiv.style.color = '#ea8f85';
        } else {
            previewDiv.style.color = '#7bc39b';
        }
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('newMemberModal');
    if (event.target === modal) {
        closeNewMemberModal();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const productSelects = document.querySelectorAll('.productSelect');
    const quantityInputs = document.querySelectorAll('.quantityInput');
    
    productSelects.forEach(select => {
        select.addEventListener('change', updateDiscountPreview);
    });
    
    quantityInputs.forEach(input => {
        input.addEventListener('change', updateDiscountPreview);
        input.addEventListener('keyup', updateDiscountPreview);
    });
    
    updateDiscountPreview();
    
    <?php
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
<?=$scrollScript?>
</body>
</html>

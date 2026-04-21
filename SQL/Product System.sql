-- Product System.sql
USE csc3170_store;

-- =========================================================
-- Product, supplier, procurement and inventory increase logic
-- =========================================================

-- 1. Insert supplier
INSERT INTO supplier (
    supplier_name,
    contact_person,
    phone_number,
    address,
    is_active
) VALUES
    ('Fresh Farm Supply', 'Chen Wei', '13900000001', 'Shenzhen, China', 1);

-- 2. Insert category
INSERT INTO category (
    category_name,
    description
) VALUES
    ('Beverages', 'Drinks and liquid refreshments');

-- 3. Insert product
INSERT INTO product (
    category_id,
    supplier_id,
    product_name,
    cost_price,
    sell_price,
    barcode,
    description,
    is_active
) VALUES
    (1, 1, 'Green Tea 500ml', 2.80, 4.50, '6901234567890', 'Bottled green tea', 1);

-- 4. Create initial inventory record (one product -> one inventory row)
INSERT INTO inventory (
    product_id,
    quantity,
    min_stock,
    last_updated
) VALUES
(1, 0, 20, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE
    quantity = VALUES(quantity),
    min_stock = VALUES(min_stock),
    last_updated = VALUES(last_updated);

-- =========================================================
-- 5. Procurement transaction with automatic stock increase
--    MySQL script using variables + transaction for robustness
-- =========================================================
START TRANSACTION;

SET @supplier_id = 1;
SET @employee_id = 1;
SET @product_id = 1;
SET @purchase_quantity = 120;
SET @purchase_unit_price = 2.60;
SET @purchase_subtotal = ROUND(@purchase_quantity * @purchase_unit_price, 2);

INSERT INTO purchase_order (
    supplier_id,
    employee_id,
    total_price,
    receive_time
) VALUES (
    @supplier_id,
    @employee_id,
    @purchase_subtotal,
    CURRENT_TIMESTAMP
);

SET @purchase_order_id = LAST_INSERT_ID();

INSERT INTO purchase_order_item (
    purchase_order_id,
    product_id,
    unit_price,
    quantity,
    subtotal
) VALUES (
    @purchase_order_id,
    @product_id,
    @purchase_unit_price,
    @purchase_quantity,
    @purchase_subtotal
);

SET @purchase_item_id = LAST_INSERT_ID();

UPDATE inventory
SET quantity = quantity + @purchase_quantity,
    last_updated = CURRENT_TIMESTAMP
WHERE product_id = @product_id;

INSERT INTO inventory_log (
    product_id,
    purchase_item_id,
    transaction_item_id,
    change_quantity,
    balance_after,
    change_reason,
    created_at
) VALUES (
    @product_id,
    @purchase_item_id,
    NULL,
    @quantity,
    (SELECT quantity FROM inventory WHERE product_id = @product_id), -- 获取更新后的当前库存
    'PURCHASE_RECEIPT', -- 变动原因：采购入库
    CURRENT_TIMESTAMP
);

COMMIT;

-- =========================================================
-- 6. Product and inventory query
-- =========================================================
SELECT
    p.product_id,
    p.product_name,
    c.category_name,
    s.supplier_name,
    p.barcode,
    p.cost_price,
    p.sell_price,
    i.quantity,
    i.min_stock,
    CASE
        WHEN i.quantity <= i.min_stock THEN 'LOW_STOCK'
        ELSE 'NORMAL'
    END AS stock_status
FROM product p
JOIN category c ON p.category_id = c.category_id
JOIN supplier s ON p.supplier_id = s.supplier_id
JOIN inventory i ON p.product_id = i.product_id
ORDER BY p.product_id;

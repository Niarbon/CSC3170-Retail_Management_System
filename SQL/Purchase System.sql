-- Purchase System.sql
USE csc3170_store;

-- =========================================================
-- 1. Insert purchase order
-- =========================================================
START TRANSACTION;

SET @supplier_id = 1;
SET @employee_id = 1;
SET @product_id = 1;
SET @quantity = 80;
SET @unit_price = 2.75;
SET @subtotal = ROUND(@quantity * @unit_price, 2);

INSERT INTO purchase_order (
    supplier_id,
    employee_id,
    total_price,
    receive_time
) VALUES (
    @supplier_id,
    @employee_id,
    @subtotal,
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
    @unit_price,
    @quantity,
    @subtotal
);

SET @purchase_item_id = LAST_INSERT_ID();



-- Optional but practical: inventory also increases when goods are received.
UPDATE inventory
SET quantity = quantity + @quantity,
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
    @purchase_quantity,
    (SELECT quantity FROM inventory WHERE product_id = @product_id), -- 获取更新后的当前库存
    'PURCHASE_RECEIPT', -- 变动原因：采购入库
    CURRENT_TIMESTAMP
);

COMMIT;

-- =========================================================
-- 2. Supplier performance analysis
--    a) supply frequency
--    b) average unit price comparison
-- =========================================================
SELECT
    s.supplier_id,
    s.supplier_name,
    COUNT(DISTINCT po.purchase_order_id) AS supply_frequency,
    IFNULL(SUM(poi.quantity), 0) AS total_units_supplied,
    IFNULL(ROUND(AVG(poi.unit_price), 2), 0.00) AS avg_unit_price,
    IFNULL(ROUND(SUM(poi.subtotal), 2), 0.00) AS total_purchase_amount
FROM supplier s
JOIN purchase_order po
    ON s.supplier_id = po.supplier_id
JOIN purchase_order_item poi
    ON po.purchase_order_id = poi.purchase_order_id
GROUP BY s.supplier_id, s.supplier_name
ORDER BY supply_frequency DESC, avg_unit_price ASC, s.supplier_name ASC;

-- Supplier average unit price compared with product-level market average
SELECT
    s.supplier_id,
    s.supplier_name,
    p.product_id,
    p.product_name,
    ROUND(AVG(poi.unit_price), 2) AS supplier_avg_unit_price,
    ROUND(prod_avg.avg_unit_price_all_suppliers, 2) AS market_avg_unit_price,
    ROUND(AVG(poi.unit_price) - prod_avg.avg_unit_price_all_suppliers, 2) AS price_diff_from_market
FROM supplier s
JOIN purchase_order po
    ON s.supplier_id = po.supplier_id
JOIN purchase_order_item poi
    ON po.purchase_order_id = poi.purchase_order_id
JOIN product p
    ON poi.product_id = p.product_id
JOIN (
    SELECT
        product_id,
        AVG(unit_price) AS avg_unit_price_all_suppliers
    FROM purchase_order_item
    GROUP BY product_id
) AS prod_avg
    ON prod_avg.product_id = p.product_id
GROUP BY s.supplier_id, s.supplier_name, p.product_id, p.product_name, prod_avg.avg_unit_price_all_suppliers
ORDER BY p.product_id, supplier_avg_unit_price ASC, s.supplier_name ASC;

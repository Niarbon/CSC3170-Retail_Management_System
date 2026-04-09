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

-- =========================================================
-- 2. Update supplier supply record
-- =========================================================
INSERT INTO supplier_supply_record (
    supplier_id,
    product_id,
    purchase_item_id,
    unit_price,
    quantity,
    received_at
) VALUES (
    @supplier_id,
    @product_id,
    @purchase_item_id,
    @unit_price,
    @quantity,
    CURRENT_TIMESTAMP
);

-- Optional but practical: inventory also increases when goods are received.
UPDATE inventory
SET quantity = quantity + @quantity,
    last_updated = CURRENT_TIMESTAMP
WHERE product_id = @product_id;

COMMIT;

-- =========================================================
-- 3. Supplier performance analysis
--    a) supply frequency
--    b) average unit price comparison
-- =========================================================
SELECT
    s.supplier_id,
    s.supplier_name,
    COUNT(DISTINCT po.purchase_order_id) AS supply_frequency,
    SUM(poi.quantity) AS total_units_supplied,
    ROUND(AVG(poi.unit_price), 2) AS avg_unit_price,
    ROUND(SUM(poi.subtotal), 2) AS total_purchase_amount
FROM supplier s
LEFT JOIN purchase_order po
    ON s.supplier_id = po.supplier_id
LEFT JOIN purchase_order_item poi
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

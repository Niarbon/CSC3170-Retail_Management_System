-- 模拟数据填充

USE csc3170_store;


-- 1. category

INSERT INTO category (category_name, description) VALUES
('Snacks',        '零食类，含薯片、饼干、糖果'),
('Dairy',         '乳制品，含牛奶、酸奶、奶酪'),
('Fresh Produce', '新鲜蔬菜水果'),
('Frozen Foods',  '冷冻食品，含速冻水饺、汤圆'),
('Personal Care', '个人护理，含洗发水、沐浴露'),
('Stationery',    '文具办公用品'),
('Household',     '家居日用品');

-- 2. supplier

INSERT INTO supplier (supplier_name, contact_person, phone_number, address) VALUES
('Sunrise Dairy Ltd.',      'Jack Liu',   '13800000003', 'Zone B, Foshan'),
('Ocean Snack Factory',     'Amy Zhang',  '13800000004', 'Industrial Park, Zhuhai'),
('Green Valley Frozen',     'Bob Huang',  '13800000005', 'Cold Chain Hub, Dongguan'),
('CleanLife Personal Care', 'Sara Wu',    '13800000006', 'Chemical Zone, Zhongshan'),
('OfficeMax Supplies',      'Kevin Li',   '13800000007', 'Trade Center, Jiangmen'),
('HomeEase Products',       'Nancy Zhao', '13800000008', 'Export Zone, Huizhou'),
('Golden Grain Co.',        'Lisa Chen',  '13800000009', 'District 3, Shenzhen');

-- 3. employee
INSERT INTO employee (employee_name, salary, job_position, phone_number, hire_date, is_active) VALUES
('Bob Li',      7200.00, 'Supervisor',      '13800000011', '2022-06-15', 1),
('Carol Wang',  5800.00, 'Cashier',         '13800000012', '2023-08-20', 1),
('David Chen',  8500.00, 'Manager',         '13800000013', '2021-01-10', 1),
('Eva Liu',     6000.00, 'Cashier',         '13800000014', '2024-02-01', 1),
('Frank Huang', 7000.00, 'Warehouse Staff', '13800000015', '2022-11-05', 1),
('Grace Zhou',  5500.00, 'Cashier',         '13800000016', '2024-05-18', 1),
('Henry Wu',    9000.00, 'Store Manager',   '13800000017', '2020-07-01', 1),
('Iris Sun',    6200.00, 'Cashier',         '13800000018', '2023-01-15', 1);

-- 4. Member
INSERT INTO member (member_name, phone_number, points, join_date) VALUES
('Michael Luo',  '13700000001', 1200, '2023-04-10'),
('Sophie Tang',  '13700000002',  850, '2023-07-22'),
('William Xiao', '13700000003', 3400, '2022-12-05'),
('Emma Fang',    '13700000004',  200, '2024-01-18'),
('Oliver Jiang', '13700000005', 5600, '2022-05-30'),
('Chloe Bai',    '13700000006',  750, '2023-09-09'),
('Ethan Gong',   '13700000007', 1100, '2024-03-01'),
('Mia Deng',     '13700000008', 2900, '2023-02-14'),
('Liam Hou',     '13700000009',  430, '2024-06-20'),
('Ava Peng',     '13700000010', 6800, '2021-11-11');

-- 5. product
SET @c_snk  = (SELECT category_id FROM category WHERE category_name = 'Snacks');
SET @c_dai  = (SELECT category_id FROM category WHERE category_name = 'Dairy');
SET @c_fre  = (SELECT category_id FROM category WHERE category_name = 'Fresh Produce');
SET @c_frz  = (SELECT category_id FROM category WHERE category_name = 'Frozen Foods');
SET @c_per  = (SELECT category_id FROM category WHERE category_name = 'Personal Care');
SET @c_sta  = (SELECT category_id FROM category WHERE category_name = 'Stationery');
SET @c_hou  = (SELECT category_id FROM category WHERE category_name = 'Household');

SET @s_dairy  = (SELECT supplier_id FROM supplier WHERE supplier_name = 'Sunrise Dairy Ltd.');
SET @s_snack  = (SELECT supplier_id FROM supplier WHERE supplier_name = 'Ocean Snack Factory');
SET @s_froz   = (SELECT supplier_id FROM supplier WHERE supplier_name = 'Green Valley Frozen');
SET @s_clean  = (SELECT supplier_id FROM supplier WHERE supplier_name = 'CleanLife Personal Care');
SET @s_office = (SELECT supplier_id FROM supplier WHERE supplier_name = 'OfficeMax Supplies');
SET @s_home   = (SELECT supplier_id FROM supplier WHERE supplier_name = 'HomeEase Products');
SET @s_grain  = (SELECT supplier_id FROM supplier WHERE supplier_name = 'Golden Grain Co.');

INSERT INTO product (category_id, supplier_id, product_name, cost_price, sell_price, barcode, description) VALUES
(@c_snk, @s_snack, 'Potato Chips Original',  3.20,  6.00, '6902345600001', '原味薯片'),
(@c_snk, @s_snack, 'Chocolate Biscuit',      4.00,  7.50, '6902345600002', '巧克力饼干'),
(@c_snk, @s_snack, 'Gummy Bears 100g',       2.50,  5.00, '6902345600003', '橡皮熊软糖'),
(@c_dai, @s_dairy, 'Whole Milk 1L',          5.00,  8.50, '6902345600004', '全脂牛奶'),
(@c_dai, @s_dairy, 'Strawberry Yogurt 200g', 3.50,  6.00, '6902345600005', '草莓酸奶'),
(@c_dai, @s_dairy, 'Cheese Slice 10pcs',     8.00, 14.00, '6902345600006', '切片奶酪'),
(@c_fre, @s_grain, 'Apple 1kg',              5.00,  9.00, '6902345600007', '苹果'),
(@c_fre, @s_grain, 'Banana 1kg',             3.00,  5.50, '6902345600008', '香蕉'),
(@c_fre, @s_grain, 'Cherry Tomato 500g',     4.00,  7.00, '6902345600009', '小番茄'),
(@c_frz, @s_froz,  'Pork Dumpling 500g',     9.00, 15.00, '6902345600010', '猪肉水饺'),
(@c_frz, @s_froz,  'Tang Yuan 400g',         7.00, 12.00, '6902345600011', '汤圆'),
(@c_per, @s_clean, 'Shampoo 400ml',         12.00, 22.00, '6902345600012', '洗发水'),
(@c_per, @s_clean, 'Body Wash 500ml',       10.00, 18.00, '6902345600013', '沐浴露'),
(@c_sta, @s_office,'Ballpoint Pen 10pcs',    4.00,  8.00, '6902345600014', '圆珠笔套装'),
(@c_sta, @s_office,'A4 Paper 500 sheets',   15.00, 25.00, '6902345600015', 'A4复印纸'),
(@c_hou, @s_home,  'Dish Soap 500ml',        5.00,  9.50, '6902345600016', '洗洁精'),
(@c_hou, @s_home,  'Trash Bags 30pcs',       4.50,  8.00, '6902345600017', '垃圾袋'),
(@c_hou, @s_home,  'Paper Towel 6 rolls',    8.00, 14.00, '6902345600018', '厨房纸巾');

-- 6. inventory
INSERT INTO inventory (product_id, quantity, min_stock)
SELECT product_id, qty, min_s FROM (
    SELECT '6902345600001' AS bc, 150 AS qty, 30 AS min_s UNION ALL
    SELECT '6902345600002',  100, 20 UNION ALL
    SELECT '6902345600003',   90, 20 UNION ALL
    SELECT '6902345600004',   60, 10 UNION ALL
    SELECT '6902345600005',   75, 15 UNION ALL
    SELECT '6902345600006',   40, 10 UNION ALL
    SELECT '6902345600007',  200, 40 UNION ALL
    SELECT '6902345600008',  180, 40 UNION ALL
    SELECT '6902345600009',  120, 25 UNION ALL
    SELECT '6902345600010',   50, 10 UNION ALL
    SELECT '6902345600011',   45, 10 UNION ALL
    SELECT '6902345600012',   60, 10 UNION ALL
    SELECT '6902345600013',   55, 10 UNION ALL
    SELECT '6902345600014',  120, 20 UNION ALL
    SELECT '6902345600015',   80, 15 UNION ALL
    SELECT '6902345600016',  100, 20 UNION ALL
    SELECT '6902345600017',  150, 30 UNION ALL
    SELECT '6902345600018',   70, 15
) t JOIN product p ON p.barcode = t.bc;

-- 7. purchase_order 
SET @e_frank = (SELECT employee_id FROM employee WHERE phone_number = '13800000015');
SET @e_bob   = (SELECT employee_id FROM employee WHERE phone_number = '13800000011');

-- 采购单 A：Ocean Snack Factory → 零食
START TRANSACTION;
INSERT INTO purchase_order (supplier_id, employee_id, total_price, receive_time)
VALUES (@s_snack, @e_frank, 1910.00, '2025-02-18 14:00:00');
SET @po_a = LAST_INSERT_ID();
INSERT INTO purchase_order_item (purchase_order_id, product_id, unit_price, quantity, subtotal) VALUES
(@po_a, (SELECT product_id FROM product WHERE barcode='6902345600001'), 3.20, 300,  960.00),
(@po_a, (SELECT product_id FROM product WHERE barcode='6902345600002'), 4.00, 200,  800.00),
(@po_a, (SELECT product_id FROM product WHERE barcode='6902345600003'), 2.50,  60,  150.00);
INSERT INTO supplier_supply_record (supplier_id, product_id, purchase_item_id, unit_price, quantity, received_at)
SELECT @s_snack, product_id, purchase_item_id, unit_price, quantity, '2025-02-18 14:00:00'
FROM purchase_order_item WHERE purchase_order_id = @po_a;
COMMIT;

-- 采购单 B：Sunrise Dairy Ltd. → 乳制品
START TRANSACTION;
INSERT INTO purchase_order (supplier_id, employee_id, total_price, receive_time)
VALUES (@s_dairy, @e_bob, 1680.00, '2025-03-01 09:30:00');
SET @po_b = LAST_INSERT_ID();
INSERT INTO purchase_order_item (purchase_order_id, product_id, unit_price, quantity, subtotal) VALUES
(@po_b, (SELECT product_id FROM product WHERE barcode='6902345600004'), 5.00, 100,  500.00),
(@po_b, (SELECT product_id FROM product WHERE barcode='6902345600005'), 3.50, 200,  700.00),
(@po_b, (SELECT product_id FROM product WHERE barcode='6902345600006'), 8.00,  60,  480.00);
INSERT INTO supplier_supply_record (supplier_id, product_id, purchase_item_id, unit_price, quantity, received_at)
SELECT @s_dairy, product_id, purchase_item_id, unit_price, quantity, '2025-03-01 09:30:00'
FROM purchase_order_item WHERE purchase_order_id = @po_b;
COMMIT;

-- 采购单 C：Green Valley Frozen → 冷冻食品
START TRANSACTION;
INSERT INTO purchase_order (supplier_id, employee_id, total_price, receive_time)
VALUES (@s_froz, @e_frank, 1015.00, '2025-03-10 11:00:00');
SET @po_c = LAST_INSERT_ID();
INSERT INTO purchase_order_item (purchase_order_id, product_id, unit_price, quantity, subtotal) VALUES
(@po_c, (SELECT product_id FROM product WHERE barcode='6902345600010'), 9.00, 70,  630.00),
(@po_c, (SELECT product_id FROM product WHERE barcode='6902345600011'), 7.00, 55,  385.00);
INSERT INTO supplier_supply_record (supplier_id, product_id, purchase_item_id, unit_price, quantity, received_at)
SELECT @s_froz, product_id, purchase_item_id, unit_price, quantity, '2025-03-10 11:00:00'
FROM purchase_order_item WHERE purchase_order_id = @po_c;
COMMIT;

-- 采购单 D：CleanLife Personal Care → 个护
START TRANSACTION;
INSERT INTO purchase_order (supplier_id, employee_id, total_price, receive_time)
VALUES (@s_clean, @e_bob, 1760.00, '2025-03-15 15:00:00');
SET @po_d = LAST_INSERT_ID();
INSERT INTO purchase_order_item (purchase_order_id, product_id, unit_price, quantity, subtotal) VALUES
(@po_d, (SELECT product_id FROM product WHERE barcode='6902345600012'), 12.00, 80,  960.00),
(@po_d, (SELECT product_id FROM product WHERE barcode='6902345600013'), 10.00, 80,  800.00);
INSERT INTO supplier_supply_record (supplier_id, product_id, purchase_item_id, unit_price, quantity, received_at)
SELECT @s_clean, product_id, purchase_item_id, unit_price, quantity, '2025-03-15 15:00:00'
FROM purchase_order_item WHERE purchase_order_id = @po_d;
COMMIT;

-- 8. sales_transaction 
SET @e_carol = (SELECT employee_id FROM employee WHERE phone_number = '13800000012');
SET @e_eva   = (SELECT employee_id FROM employee WHERE phone_number = '13800000014');
SET @e_grace = (SELECT employee_id FROM employee WHERE phone_number = '13800000016');
SET @e_iris  = (SELECT employee_id FROM employee WHERE phone_number = '13800000018');

-- 交易 1
INSERT INTO sales_transaction (employee_id, member_id, transaction_time, total_price, discount, payment_method)
VALUES (@e_carol, 1, '2025-03-20 09:15:00', 22.00, 0.00, 'CASH');
SET @tx = LAST_INSERT_ID();
INSERT INTO transaction_item (transaction_id, product_id, unit_price, quantity, subtotal) VALUES
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600001'), 6.00, 2, 12.00),
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600004'), 8.50, 1,  8.50),
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600008'), 5.50, 1,  5.50);

-- 交易 2
INSERT INTO sales_transaction (employee_id, member_id, transaction_time, total_price, discount, payment_method)
VALUES (@e_eva, 3, '2025-03-20 11:30:00', 47.50, 2.50, 'CARD');
SET @tx = LAST_INSERT_ID();
INSERT INTO transaction_item (transaction_id, product_id, unit_price, quantity, subtotal) VALUES
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600004'), 8.50, 2, 17.00),
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600005'), 6.00, 2, 12.00),
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600002'), 7.50, 2, 15.00),
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600003'), 5.00, 1,  5.00);

-- 交易 3
INSERT INTO sales_transaction (employee_id, member_id, transaction_time, total_price, discount, payment_method)
VALUES (@e_carol, NULL, '2025-03-20 14:00:00', 29.50, 0.00, 'MOBILE_PAY');
SET @tx = LAST_INSERT_ID();
INSERT INTO transaction_item (transaction_id, product_id, unit_price, quantity, subtotal) VALUES
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600010'), 15.00, 1, 15.00),
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600007'),  9.00, 1,  9.00),
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600008'),  5.50, 1,  5.50);

-- 交易 4
INSERT INTO sales_transaction (employee_id, member_id, transaction_time, total_price, discount, payment_method)
VALUES (@e_grace, 5, '2025-03-21 10:20:00', 64.00, 5.00, 'CARD');
SET @tx = LAST_INSERT_ID();
INSERT INTO transaction_item (transaction_id, product_id, unit_price, quantity, subtotal) VALUES
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600012'), 22.00, 1, 22.00),
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600013'), 18.00, 1, 18.00),
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600016'),  9.50, 2, 19.00),
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600017'),  8.00, 1,  8.00);

-- 交易 5
INSERT INTO sales_transaction (employee_id, member_id, transaction_time, total_price, discount, payment_method)
VALUES (@e_iris, 8, '2025-03-21 16:45:00', 35.50, 0.00, 'CASH');
SET @tx = LAST_INSERT_ID();
INSERT INTO transaction_item (transaction_id, product_id, unit_price, quantity, subtotal) VALUES
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600009'),  7.00, 2, 14.00),
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600006'), 14.00, 1, 14.00),
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600003'),  5.00, 2, 10.00);

-- 交易 6
INSERT INTO sales_transaction (employee_id, member_id, transaction_time, total_price, discount, payment_method)
VALUES (@e_carol, 2, '2025-03-22 09:00:00', 41.00, 0.00, 'MOBILE_PAY');
SET @tx = LAST_INSERT_ID();
INSERT INTO transaction_item (transaction_id, product_id, unit_price, quantity, subtotal) VALUES
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600014'),  8.00, 2, 16.00),
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600015'), 25.00, 1, 25.00);

-- 交易 7
INSERT INTO sales_transaction (employee_id, member_id, transaction_time, total_price, discount, payment_method)
VALUES (@e_grace, 10, '2025-03-23 13:30:00', 58.00, 3.00, 'BANK_TRANSFER');
SET @tx = LAST_INSERT_ID();
INSERT INTO transaction_item (transaction_id, product_id, unit_price, quantity, subtotal) VALUES
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600011'), 12.00, 2, 24.00),
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600010'), 15.00, 1, 15.00),
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600018'), 14.00, 1, 14.00),
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600008'),  5.50, 1,  5.50);

-- 交易 8
INSERT INTO sales_transaction (employee_id, member_id, transaction_time, total_price, discount, payment_method)
VALUES (@e_eva, 4, '2025-03-24 10:10:00', 19.50, 0.00, 'CASH');
SET @tx = LAST_INSERT_ID();
INSERT INTO transaction_item (transaction_id, product_id, unit_price, quantity, subtotal) VALUES
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600001'), 6.00, 2, 12.00),
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600005'), 6.00, 1,  6.00),
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600009'), 7.00, 1,  7.00);

-- 交易 9
INSERT INTO sales_transaction (employee_id, member_id, transaction_time, total_price, discount, payment_method)
VALUES (@e_iris, 6, '2025-03-25 15:00:00', 52.00, 2.00, 'CARD');
SET @tx = LAST_INSERT_ID();
INSERT INTO transaction_item (transaction_id, product_id, unit_price, quantity, subtotal) VALUES
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600004'),  8.50, 3, 25.50),
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600005'),  6.00, 2, 12.00),
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600016'),  9.50, 1,  9.50),
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600017'),  8.00, 1,  8.00);

-- 交易 10
INSERT INTO sales_transaction (employee_id, member_id, transaction_time, total_price, discount, payment_method)
VALUES (@e_carol, 7, '2025-03-26 17:30:00', 73.00, 0.00, 'MOBILE_PAY');
SET @tx = LAST_INSERT_ID();
INSERT INTO transaction_item (transaction_id, product_id, unit_price, quantity, subtotal) VALUES
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600012'), 22.00, 1, 22.00),
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600015'), 25.00, 1, 25.00),
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600018'), 14.00, 1, 14.00),
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600014'),  8.00, 1,  8.00),
(@tx, (SELECT product_id FROM product WHERE barcode='6902345600009'),  7.00, 1,  7.00);



SELECT '✅ Mock data loaded successfully!' AS status;
SELECT CONCAT(TABLE_NAME, ': ', TABLE_ROWS, ' rows') AS summary
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'csc3170_store'
ORDER BY TABLE_NAME;

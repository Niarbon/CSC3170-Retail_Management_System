-- 模拟数据填充 - 真实无存储过程版
USE csc3170_store;

-- 1. category
INSERT INTO category (category_name, description) VALUES
('Snacks',        '零食类，含薯片、饼干、糖果、膨化食品'),
('Dairy',         '乳制品，含牛奶、酸奶、奶酪、奶油'),
('Fresh Produce', '新鲜蔬菜水果、生鲜食材'),
('Frozen Foods',  '冷冻食品，水饺、汤圆、冷冻肉类'),
('Personal Care', '个人护理，洗发水、沐浴露、牙膏'),
('Stationery',    '文具办公用品，笔、纸、文件夹'),
('Household',     '家居日用品，清洁、厨房、收纳'),
('Drinks',        '瓶装饮料、矿泉水、茶饮、咖啡'),
('Bread',         '面包、蛋糕、点心、烘焙食品'),
('Grains',        '米、面、粮油、干货杂粮');

-- 2. supplier
INSERT INTO supplier (supplier_name, contact_person, phone_number, address) VALUES
('Sunrise Dairy Ltd.',      'Jack Liu',   '13822234567', 'Zone B, Foshan'),
('Ocean Snack Factory',     'Amy Zhang',  '13933388776', 'Industrial Park, Zhuhai'),
('Green Valley Frozen',     'Bob Huang',  '13511129876', 'Cold Chain Hub, Dongguan'),
('CleanLife Personal Care', 'Sara Wu',    '13622234565', 'Chemical Zone, Zhongshan'),
('OfficeMax Supplies',      'Kevin Li',   '13733345678', 'Trade Center, Jiangmen'),
('HomeEase Products',       'Nancy Zhao', '13855567890', 'Export Zone, Huizhou'),
('Golden Grain Co.',        'Lisa Chen',  '13966678901', 'District 3, Shenzhen'),
('Best Bakery Supply',      'Tom He',     '15011123456', 'Food Zone, Guangzhou'),
('Spring Water Group',      'Ivy Lin',    '15122234567', 'High-Tech Zone, Xiamen'),
('Metro Wholesale',         'Paul Ye',    '15833345678', 'Logistics Park, Shanghai');

-- 3. employee
INSERT INTO employee (employee_name, salary, job_position, phone_number, hire_date, is_active) VALUES
('Bob Li',      7200.00, 'Supervisor',      '13812345678', '2022-06-15', 1),
('Carol Wang',  5800.00, 'Cashier',         '13987654321', '2023-08-20', 1),
('David Chen',  8500.00, 'Manager',         '13523456789', '2021-01-10', 1),
('Eva Liu',     6000.00, 'Cashier',         '13634567890', '2024-02-01', 1),
('Frank Huang', 7000.00, 'Warehouse Staff', '13745678901', '2022-11-05', 1),
('Grace Zhou',  5500.00, 'Cashier',         '13856789012', '2024-05-18', 1),
('Henry Wu',    9000.00, 'Store Manager',   '13967890123', '2020-07-01', 1),
('Iris Sun',    6200.00, 'Cashier',         '15078901234', '2023-01-15', 1),
('Jayden Ma',   5600.00, 'Cashier',         '15189012345', '2024-09-01', 1),
('Kelly Feng',  6800.00, 'Inventory Clerk', '15290123456', '2023-04-12', 1),
('Leo Zhao',    7300.00, 'Supervisor',      '15312345678', '2022-08-10', 1),
('Mandy Qian',  5900.00, 'Cashier',         '15723456789', '2024-01-15', 1);

-- 4. Member
INSERT INTO member (member_name, phone_number, points, join_date) VALUES
('Michael Luo',  '13711122333', 1200, '2023-04-10'),
('Sophie Tang',  '13822233444',  850, '2023-07-22'),
('William Xiao', '13933344555', 3400, '2022-12-05'),
('Emma Fang',    '13544455666',  200, '2024-01-18'),
('Oliver Jiang', '13655566777', 5600, '2022-05-30'),
('Chloe Bai',    '13766677888',  750, '2023-09-09'),
('Ethan Gong',   '13877788999', 1100, '2024-03-01'),
('Mia Deng',     '13988899000', 2900, '2023-02-14'),
('Liam Hou',     '15011122334',  430, '2024-06-20'),
('Ava Peng',     '15122233445', 6800, '2021-11-11'),
('Noah Hu',      '15233344556',  950, '2023-05-05'),
('Charlotte Xu', '15344455667', 1400, '2022-10-12'),
('Lucas Ye',     '15755566778', 2200, '2023-01-30'),
('Amelia Han',   '15866677889',  350, '2024-02-28'),
('Elijah Zhong', '15977788990', 4100, '2022-08-19'),
('Mila Fang',    '17888899001',  880, '2023-12-05'),
('Theodore Guo', '17899900112', 1750, '2023-06-17'),
('Aria Liu',     '18011122335',  520, '2024-03-22'),
('Isaiah Wang',  '18122233446', 3100, '2022-09-13'),
('Lily Cheng',   '18233344557', 1320, '2023-08-08');

-- 5. product
SET @c_snk  = (SELECT category_id FROM category WHERE category_name = 'Snacks');
SET @c_dai  = (SELECT category_id FROM category WHERE category_name = 'Dairy');
SET @c_fre  = (SELECT category_id FROM category WHERE category_name = 'Fresh Produce');
SET @c_frz  = (SELECT category_id FROM category WHERE category_name = 'Frozen Foods');
SET @c_per  = (SELECT category_id FROM category WHERE category_name = 'Personal Care');
SET @c_sta  = (SELECT category_id FROM category WHERE category_name = 'Stationery');
SET @c_hou  = (SELECT category_id FROM category WHERE category_name = 'Household');
SET @c_dri  = (SELECT category_id FROM category WHERE category_name = 'Drinks');
SET @c_bre  = (SELECT category_id FROM category WHERE category_name = 'Bread');
SET @c_gra  = (SELECT category_id FROM category WHERE category_name = 'Grains');

SET @s_dairy  = (SELECT supplier_id FROM supplier WHERE supplier_name = 'Sunrise Dairy Ltd.');
SET @s_snack  = (SELECT supplier_id FROM supplier WHERE supplier_name = 'Ocean Snack Factory');
SET @s_froz   = (SELECT supplier_id FROM supplier WHERE supplier_name = 'Green Valley Frozen');
SET @s_clean  = (SELECT supplier_id FROM supplier WHERE supplier_name = 'CleanLife Personal Care');
SET @s_office = (SELECT supplier_id FROM supplier WHERE supplier_name = 'OfficeMax Supplies');
SET @s_home   = (SELECT supplier_id FROM supplier WHERE supplier_name = 'HomeEase Products');
SET @s_grain  = (SELECT supplier_id FROM supplier WHERE supplier_name = 'Golden Grain Co.');
SET @s_bakery = (SELECT supplier_id FROM supplier WHERE supplier_name = 'Best Bakery Supply');
SET @s_water  = (SELECT supplier_id FROM supplier WHERE supplier_name = 'Spring Water Group');

INSERT INTO product (category_id, supplier_id, product_name, cost_price, sell_price, barcode, description) VALUES
(@c_snk, @s_snack, 'Potato Chips Original',      3.20,  5.90, '6921168500011', '原味薯片'),
(@c_snk, @s_snack, 'Chocolate Biscuit',          4.00,  7.30, '6921168500028', '巧克力饼干'),
(@c_snk, @s_snack, 'Gummy Bears 100g',           2.50,  4.80, '6921168500035', '橡皮熊软糖'),
(@c_snk, @s_snack, 'Corn Puffs 50g',             1.80,  3.30, '6921168500042', '玉米泡芙'),
(@c_snk, @s_snack, 'Candy Bag 150g',             3.10,  5.40, '6921168500059', '什锦糖果包'),

(@c_dai, @s_dairy, 'Whole Milk 1L',              5.00,  8.20, '6921168500066', '全脂牛奶'),
(@c_dai, @s_dairy, 'Strawberry Yogurt 200g',     3.50,  5.80, '6921168500073', '草莓酸奶'),
(@c_dai, @s_dairy, 'Cheese Slice 10pcs',         8.00, 13.50, '6921168500080', '切片奶酪'),
(@c_dai, @s_dairy, 'Low-Fat Yogurt 200g',        3.30,  5.30, '6921168500097', '低脂酸奶'),
(@c_dai, @s_dairy, 'Sweet Cream 250ml',         6.20,  9.80, '6921168500103', '淡奶油'),

(@c_fre, @s_grain, 'Apple 1kg',                  5.00,  8.80, '6921168500110', '苹果'),
(@c_fre, @s_grain, 'Banana 1kg',                 3.00,  5.30, '6921168500127', '香蕉'),
(@c_fre, @s_grain, 'Cherry Tomato 500g',         4.00,  6.80, '6921168500134', '小番茄'),
(@c_fre, @s_grain, 'Orange 1kg',                4.50,  7.30, '6921168500141', '橙子'),
(@c_fre, @s_grain, 'Carrot 1kg',                 2.80,  4.40, '6921168500158', '胡萝卜'),

(@c_frz, @s_froz,  'Pork Dumpling 500g',         9.00, 14.80, '6921168500165', '猪肉水饺'),
(@c_frz, @s_froz,  'Tang Yuan 400g',             7.00, 11.80, '6921168500172', '汤圆'),
(@c_frz, @s_froz,  'Frozen Chicken Wings 500g',  8.50, 13.80, '6921168500189', '冷冻鸡翅'),
(@c_frz, @s_froz,  'Frozen Vegetables 400g',     5.40,  8.40, '6921168500196', '冷冻蔬菜'),

(@c_per, @s_clean, 'Shampoo 400ml',             12.00, 21.80, '6921168500202', '洗发水'),
(@c_per, @s_clean, 'Body Wash 500ml',           10.00, 17.80, '6921168500219', '沐浴露'),
(@c_per, @s_clean, 'Toothpaste 120g',            4.50,  7.40, '6921168500226', '牙膏'),
(@c_per, @s_clean, 'Facial Cleanser 150ml',      9.80, 15.80, '6921168500233', '洗面奶'),

(@c_sta, @s_office,'Ballpoint Pen 10pcs',        4.00,  7.80, '6921168500240', '圆珠笔套装'),
(@c_sta, @s_office,'A4 Paper 500 sheets',       15.00, 24.80, '6921168500257', 'A4复印纸'),
(@c_sta, @s_office,'Stapler',                    6.50, 10.80, '6921168500264', '订书机'),
(@c_sta, @s_office,'Notebook A5 80pages',        3.20,  5.40, '6921168500271', '软皮笔记本'),

(@c_hou, @s_home,  'Dish Soap 500ml',            5.00,  9.40, '6921168500288', '洗洁精'),
(@c_hou, @s_home,  'Trash Bags 30pcs',           4.50,  7.90, '6921168500295', '垃圾袋'),
(@c_hou, @s_home,  'Paper Towel 6 rolls',        8.00, 13.80, '6921168500301', '厨房纸巾'),
(@c_hou, @s_home,  'Laundry Detergent 1L',       9.60, 15.80, '6921168500318', '洗衣液'),
(@c_hou, @s_home,  'Toilet Paper 10 rolls',     12.50, 19.80, '6921168500325', '卷纸10卷'),

(@c_dri, @s_water, 'Mineral Water 550ml',        1.00,  1.90, '6921168500332', '矿泉水'),
(@c_dri, @s_water, 'Lemon Tea 500ml',            2.40,  3.90, '6921168500349', '柠檬茶'),
(@c_dri, @s_water, 'Coffee Drink 250ml',         3.80,  6.40, '6921168500356', '即饮咖啡'),

(@c_bre, @s_bakery,'Toast Bread 300g',           4.20,  6.90, '6921168500363', '吐司面包'),
(@c_bre, @s_bakery,'Cake Slice',                 5.50,  8.90, '6921168500370', '蛋糕切块'),

(@c_gra, @s_grain, 'Rice 5kg',                  28.00, 44.80, '6921168500387', '大米5kg'),
(@c_gra, @s_grain, 'Noodles 500g',               3.50,  5.40, '6921168500394', '挂面');

-- 6. inventory
INSERT INTO inventory (product_id, quantity, min_stock)
SELECT product_id, 100, 20 FROM product;

-- 7. purchase_order
SET @e_frank = (SELECT employee_id FROM employee WHERE phone_number = '13745678901');
SET @e_bob   = (SELECT employee_id FROM employee WHERE phone_number = '13812345678');
SET @e_kelly = (SELECT employee_id FROM employee WHERE phone_number = '15290123456');

START TRANSACTION;
INSERT INTO purchase_order (supplier_id, employee_id, total_price, receive_time)
VALUES (@s_snack, @e_frank, 2280.50, '2025-02-18 14:00:00');
SET @po_a = LAST_INSERT_ID();
INSERT INTO purchase_order_item (purchase_order_id, product_id, unit_price, quantity, subtotal)
SELECT @po_a, product_id, cost_price, 180, cost_price*180 FROM product WHERE category_id = @c_snk;
INSERT INTO supplier_supply_record (supplier_id, product_id, purchase_item_id, unit_price, quantity, received_at)
SELECT @s_snack, product_id, purchase_item_id, unit_price, quantity, '2025-02-18 14:00:00'
FROM purchase_order_item WHERE purchase_order_id = @po_a;
COMMIT;

START TRANSACTION;
INSERT INTO purchase_order (supplier_id, employee_id, total_price, receive_time)
VALUES (@s_dairy, @e_bob, 1920.70, '2025-03-01 09:30:00');
SET @po_b = LAST_INSERT_ID();
INSERT INTO purchase_order_item (purchase_order_id, product_id, unit_price, quantity, subtotal)
SELECT @po_b, product_id, cost_price, 110, cost_price*110 FROM product WHERE category_id = @c_dai;
INSERT INTO supplier_supply_record (supplier_id, product_id, purchase_item_id, unit_price, quantity, received_at)
SELECT @s_dairy, product_id, purchase_item_id, unit_price, quantity, '2025-03-01 09:30:00'
FROM purchase_order_item WHERE purchase_order_id = @po_b;
COMMIT;

START TRANSACTION;
INSERT INTO purchase_order (supplier_id, employee_id, total_price, receive_time)
VALUES (@s_froz, @e_frank, 1450.20, '2025-03-10 11:00:00');
SET @po_c = LAST_INSERT_ID();
INSERT INTO purchase_order_item (purchase_order_id, product_id, unit_price, quantity, subtotal)
SELECT @po_c, product_id, cost_price, 75, cost_price*75 FROM product WHERE category_id = @c_frz;
INSERT INTO supplier_supply_record (supplier_id, product_id, purchase_item_id, unit_price, quantity, received_at)
SELECT @s_froz, product_id, purchase_item_id, unit_price, quantity, '2025-03-10 11:00:00'
FROM purchase_order_item WHERE purchase_order_id = @po_c;
COMMIT;

START TRANSACTION;
INSERT INTO purchase_order (supplier_id, employee_id, total_price, receive_time)
VALUES (@s_clean, @e_bob, 2100.30, '2025-03-15 15:00:00');
SET @po_d = LAST_INSERT_ID();
INSERT INTO purchase_order_item (purchase_order_id, product_id, unit_price, quantity, subtotal)
SELECT @po_d, product_id, cost_price, 85, cost_price*85 FROM product WHERE category_id = @c_per;
INSERT INTO supplier_supply_record (supplier_id, product_id, purchase_item_id, unit_price, quantity, received_at)
SELECT @s_clean, product_id, purchase_item_id, unit_price, quantity, '2025-03-15 15:00:00'
FROM purchase_order_item WHERE purchase_order_id = @po_d;
COMMIT;

-- 8. sales_transaction（25笔固定订单，无存储过程）
INSERT INTO sales_transaction (employee_id, member_id, transaction_time, total_price, discount, payment_method) VALUES
(2, 1, '2025-04-01 10:15:00', 45.90, 0.00, 'CASH'),
(4, 2, '2025-04-01 14:30:00', 78.50, 5.00, 'CARD'),
(6, 3, '2025-04-02 09:45:00', 32.00, 0.00, 'MOBILE_PAY'),
(8, 4, '2025-04-02 16:20:00', 120.00, 10.00, 'CASH'),
(12, 5, '2025-04-03 11:00:00', 65.80, 0.00, 'CARD'),
(2, 6, '2025-04-03 15:10:00', 89.90, 8.00, 'MOBILE_PAY'),
(4, 7, '2025-04-04 10:30:00', 25.50, 0.00, 'CASH'),
(6, 8, '2025-04-04 13:45:00', 150.00, 15.00, 'CARD'),
(8, 9, '2025-04-05 09:20:00', 48.30, 0.00, 'MOBILE_PAY'),
(12, 10, '2025-04-05 17:00:00', 99.90, 10.00, 'CASH'),
(2, 11, '2025-04-06 10:05:00', 39.90, 0.00, 'CARD'),
(4, 12, '2025-04-06 14:50:00', 72.00, 6.00, 'MOBILE_PAY'),
(6, 13, '2025-04-07 09:35:00', 55.60, 0.00, 'CASH'),
(8, 14, '2025-04-07 16:10:00', 130.00, 12.00, 'CARD'),
(12, 15, '2025-04-08 11:25:00', 28.00, 0.00, 'MOBILE_PAY'),
(2, 16, '2025-04-08 15:35:00', 67.50, 5.00, 'CASH'),
(4, 17, '2025-04-09 10:40:00', 92.80, 8.00, 'CARD'),
(6, 18, '2025-04-09 13:20:00', 36.40, 0.00, 'MOBILE_PAY'),
(8, 19, '2025-04-10 09:10:00', 115.00, 11.00, 'CASH'),
(12, 20, '2025-04-10 17:30:00', 59.90, 0.00, 'CARD'),
(2, 1, '2025-04-11 10:20:00', 42.00, 0.00, 'MOBILE_PAY'),
(4, 3, '2025-04-11 14:40:00', 85.00, 7.00, 'CASH'),
(6, 5, '2025-04-12 09:55:00', 31.50, 0.00, 'CARD'),
(8, 7, '2025-04-12 16:30:00', 105.00, 9.00, 'MOBILE_PAY'),
(12, 9, '2025-04-13 11:15:00', 70.20, 0.00, 'CASH');

-- 为前5笔订单添加商品明细（可根据需要扩展）
INSERT INTO transaction_item (transaction_id, product_id, unit_price, quantity, subtotal) VALUES
(1, 1, 5.90, 2, 11.80),
(1, 6, 8.20, 3, 24.60),
(1, 30, 9.40, 1, 9.40),

(2, 10, 9.80, 5, 49.00),
(2, 15, 4.40, 5, 22.00),
(2, 21, 21.80, 0, 0.00),

(3, 34, 3.90, 5, 19.50),
(3, 37, 8.90, 1, 8.90),
(3, 39, 5.40, 1, 5.40),

(4, 31, 13.80, 4, 55.20),
(4, 32, 19.80, 3, 59.40),
(4, 38, 44.80, 0, 0.00),

(5, 12, 5.30, 10, 53.00),
(5, 26, 10.80, 1, 10.80),
(5, 36, 6.90, 0, 0.00);

SELECT '✅ 无存储过程版模拟数据已成功导入！' AS status;
SELECT CONCAT(TABLE_NAME, ': ', TABLE_ROWS, ' 条数据') AS summary
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'csc3170_store'
ORDER BY TABLE_NAME;

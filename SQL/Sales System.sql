-- Sales System.sql

USE csc3170_store;

START TRANSACTION;

-- 1. Set business parameters
SET @emp_id = 2;              
SET @mem_id = 1;              
SET @prod_id = 1;             
SET @qty = 2;                 
SET @unit_price = 5.90;       
SET @total = @qty * @unit_price;
SET @points_earned = @total;  

-- 2. Create the Sales Order Master Table
INSERT INTO sales_transaction (
    employee_id, member_id, total_price, payment_method
) VALUES (
    @emp_id, @mem_id, @total, 'MOBILE_PAY'
);
SET @trans_id = LAST_INSERT_ID();

-- 3. Create Order Details
INSERT INTO transaction_item (
    transaction_id, product_id, unit_price, quantity, subtotal
) VALUES (
    @trans_id, @prod_id, @unit_price, @qty, @total
);
SET @trans_item_id = LAST_INSERT_ID();

-- 4. Deduct inventory and record the outbound transaction
UPDATE inventory 
SET quantity = quantity - @qty, 
    last_updated = CURRENT_TIMESTAMP
WHERE product_id = @prod_id;

INSERT INTO inventory_log (
    product_id, transaction_item_id, change_quantity, balance_after, change_reason
) VALUES (
    @prod_id, @trans_item_id, -@qty, 
    (SELECT quantity FROM inventory WHERE product_id = @prod_id),
    'SALE'
);

-- 5. Update member points and record the points history
UPDATE member 
SET points = points + @points_earned 
WHERE member_id = @mem_id;

INSERT INTO points_log (
    member_id, transaction_id, points_delta, balance_after, change_reason
) VALUES (
    @mem_id, @trans_id, @points_earned,
    (SELECT points FROM member WHERE member_id = @mem_id),
    'CONSUMPTION_REWARD'
);

COMMIT;

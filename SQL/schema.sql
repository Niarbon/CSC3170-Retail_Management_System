-- CSC3170 Database Project
-- schema.sql
-- Target: MySQL 8.0+
-- Notes:
-- 1) Bonus requirements intentionally ignored.
-- 2) Designed in 3NF with integrity constraints and practical indexes.

DROP DATABASE IF EXISTS csc3170_store;
CREATE DATABASE csc3170_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE csc3170_store;

CREATE TABLE category (
    category_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_category_name UNIQUE (category_name)
) ENGINE=InnoDB;

CREATE TABLE supplier (
    supplier_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(100) NOT NULL,
    contact_person VARCHAR(100) NULL,
    phone_number VARCHAR(20) NOT NULL,
    address VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_supplier_name UNIQUE (supplier_name),
    CONSTRAINT uq_supplier_phone UNIQUE (phone_number),
    CONSTRAINT chk_supplier_phone_len CHECK (CHAR_LENGTH(phone_number) BETWEEN 6 AND 20)
) ENGINE=InnoDB;

CREATE TABLE employee (
    employee_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_name VARCHAR(100) NOT NULL,
    salary DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    job_position VARCHAR(50) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    hire_date DATE NOT NULL,
    leave_date DATE NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_employee_phone UNIQUE (phone_number),
    CONSTRAINT chk_employee_salary_nonnegative CHECK (salary >= 0),
    CONSTRAINT chk_employee_phone_len CHECK (CHAR_LENGTH(phone_number) BETWEEN 6 AND 20),
    CONSTRAINT chk_employee_dates CHECK (leave_date IS NULL OR leave_date >= hire_date)
) ENGINE=InnoDB;

CREATE TABLE member (
    member_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    points DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    join_date DATE NULL, 
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_member_phone UNIQUE (phone_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product (
    product_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    product_name VARCHAR(100) NOT NULL,
    cost_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    sell_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    barcode VARCHAR(50) NOT NULL,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_product_barcode UNIQUE (barcode),
    CONSTRAINT uq_product_name_supplier UNIQUE (supplier_id, product_name),
    CONSTRAINT chk_product_cost_price_nonnegative CHECK (cost_price >= 0),
    CONSTRAINT chk_product_sell_price_nonnegative CHECK (sell_price >= 0),
    CONSTRAINT chk_product_barcode_len CHECK (CHAR_LENGTH(barcode) BETWEEN 4 AND 50),
    CONSTRAINT fk_product_category
        FOREIGN KEY (category_id) REFERENCES category(category_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_product_supplier
        FOREIGN KEY (supplier_id) REFERENCES supplier(supplier_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE inventory (
    inventory_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    min_stock INT NOT NULL DEFAULT 0,
    last_updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_inventory_product UNIQUE (product_id),
    CONSTRAINT chk_inventory_quantity_nonnegative CHECK (quantity >= 0),
    CONSTRAINT chk_inventory_min_stock_nonnegative CHECK (min_stock >= 0),
    CONSTRAINT fk_inventory_product
        FOREIGN KEY (product_id) REFERENCES product(product_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE sales_transaction (
    transaction_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    member_id INT UNSIGNED NULL,
    transaction_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_method VARCHAR(50) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_sales_transaction_total_nonnegative CHECK (total_price >= 0),
    CONSTRAINT chk_sales_transaction_discount_nonnegative CHECK (discount >= 0),
    CONSTRAINT chk_sales_transaction_payment_method CHECK (payment_method IN ('CASH', 'CARD', 'MOBILE_PAY', 'BANK_TRANSFER', 'OTHER')),
    CONSTRAINT fk_sales_transaction_employee
        FOREIGN KEY (employee_id) REFERENCES employee(employee_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_sales_transaction_member
        FOREIGN KEY (member_id) REFERENCES member(member_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE transaction_item (
    transaction_item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    CONSTRAINT uq_transaction_product UNIQUE (transaction_id, product_id),
    CONSTRAINT chk_transaction_item_unit_price_nonnegative CHECK (unit_price >= 0),
    CONSTRAINT chk_transaction_item_quantity_positive CHECK (quantity >= 0),
    CONSTRAINT chk_transaction_item_subtotal_nonnegative CHECK (subtotal >= 0),
    CONSTRAINT fk_transaction_item_transaction
        FOREIGN KEY (transaction_id) REFERENCES sales_transaction(transaction_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_transaction_item_product
        FOREIGN KEY (product_id) REFERENCES product(product_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE purchase_order (
    purchase_order_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    total_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    receive_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_purchase_order_total_nonnegative CHECK (total_price >= 0),
    CONSTRAINT fk_purchase_order_supplier
        FOREIGN KEY (supplier_id) REFERENCES supplier(supplier_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_purchase_order_employee
        FOREIGN KEY (employee_id) REFERENCES employee(employee_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE purchase_order_item (
    purchase_item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    CONSTRAINT uq_purchase_order_product UNIQUE (purchase_order_id, product_id),
    CONSTRAINT chk_purchase_item_unit_price_nonnegative CHECK (unit_price >= 0),
    CONSTRAINT chk_purchase_item_quantity_positive CHECK (quantity > 0),
    CONSTRAINT chk_purchase_item_subtotal_nonnegative CHECK (subtotal >= 0),
    CONSTRAINT fk_purchase_item_order
        FOREIGN KEY (purchase_order_id) REFERENCES purchase_order(purchase_order_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_purchase_item_product
        FOREIGN KEY (product_id) REFERENCES product(product_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE points_log (
    points_log_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 
    member_id INT UNSIGNED NOT NULL, 
    transaction_id INT UNSIGNED NULL, 
	points_delta INT NOT NULL,       
	balance_after INT NOT NULL, 
    change_reason VARCHAR(255) NULL, -- 例如 '消费赠送', '积分抵现'
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, 
    CONSTRAINT fk_points_log_member
        FOREIGN KEY (member_id) REFERENCES member(member_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_points_log_transaction
        FOREIGN KEY (transaction_id) REFERENCES sales_transaction(transaction_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE inventory_log (
    inventory_log_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 
    product_id INT UNSIGNED NOT NULL, 
    purchase_item_id INT UNSIGNED NULL, 
    transaction_item_id INT UNSIGNED NULL, 
    change_quantity INT NOT NULL,    -- 正数代表入库，负数代表出库
    balance_after INT NOT NULL, 
    change_reason VARCHAR(50) NULL,  -- 例如 'PURCHASE', 'SALE', 'RETURN'
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, 
    CONSTRAINT fk_inventory_log_product
        FOREIGN KEY (product_id) REFERENCES product(product_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_inventory_log_purchase
        FOREIGN KEY (purchase_item_id) REFERENCES purchase_order_item(purchase_item_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT fk_inventory_log_transaction
        FOREIGN KEY (transaction_item_id) REFERENCES transaction_item(transaction_item_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB;


-- Indexes for frequent JOIN / WHERE columns.
CREATE INDEX idx_supplier_phone_number ON supplier(phone_number);
CREATE INDEX idx_employee_phone_number ON employee(phone_number);
CREATE INDEX idx_member_phone_number ON member(phone_number);
CREATE INDEX idx_product_barcode ON product(barcode);
CREATE INDEX idx_product_category_id ON product(category_id);
CREATE INDEX idx_product_supplier_id ON product(supplier_id);
CREATE INDEX idx_inventory_quantity ON inventory(quantity);
CREATE INDEX idx_sales_transaction_employee_id ON sales_transaction(employee_id);
CREATE INDEX idx_sales_transaction_member_id ON sales_transaction(member_id);
CREATE INDEX idx_sales_transaction_time ON sales_transaction(transaction_time);
CREATE INDEX idx_transaction_item_product_id ON transaction_item(product_id);
CREATE INDEX idx_purchase_order_supplier_id ON purchase_order(supplier_id);
CREATE INDEX idx_purchase_order_employee_id ON purchase_order(employee_id);
CREATE INDEX idx_purchase_order_receive_time ON purchase_order(receive_time);
CREATE INDEX idx_purchase_order_item_product_id ON purchase_order_item(product_id);
CREATE INDEX idx_points_log_member_id ON points_log(member_id);
CREATE INDEX idx_points_log_created_at ON points_log(created_at);
CREATE INDEX idx_inventory_log_product_id ON inventory_log(product_id);
CREATE INDEX idx_inventory_log_created_at ON inventory_log(created_at);
CREATE INDEX idx_inventory_log_reason ON inventory_log(change_reason);

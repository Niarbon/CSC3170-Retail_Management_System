CREATE TABLE CATEGORY (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL,
    description VARCHAR(255)
);

CREATE TABLE SUPPLIER (
    supplier_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    contact_person VARCHAR(100),
    phone_number VARCHAR(20),
    address VARCHAR(255)
);

CREATE TABLE PRODUCT (
    product_id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT NOT NULL,
    supplier_id INT NOT NULL,
    product_name VARCHAR(100) NOT NULL,
    cost_price DECIMAL(10,2) NOT NULL,
    sell_price DECIMAL(10,2) NOT NULL,
    barcode VARCHAR(50) UNIQUE,
    description VARCHAR(255),
    FOREIGN KEY (category_id) REFERENCES CATEGORY(category_id),
    FOREIGN KEY (supplier_id) REFERENCES SUPPLIER(supplier_id)
);

CREATE TABLE INVENTORY (
    inventory_id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL UNIQUE,
    quantity INT NOT NULL DEFAULT 0,
    min_stock INT NOT NULL DEFAULT 0,
    last_updated DATETIME NOT NULL,
    FOREIGN KEY (product_id) REFERENCES PRODUCT(product_id)
);

CREATE TABLE EMPLOYEE (
    employee_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    salary DECIMAL(10,2) NOT NULL,
    job_position VARCHAR(50) NOT NULL,
    phone_number VARCHAR(20),
    hire_date DATE NOT NULL
);

CREATE TABLE MEMBER (
    member_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20) NOT NULL UNIQUE,
    points DECIMAL(10,2) NOT NULL DEFAULT 0,
    join_date DATE NOT NULL
);

CREATE TABLE TRANSACTION (
    transaction_id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    member_id INT,
	original_amount DECIMAL NOT NULL,
    discount DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_price DECIMAL(10,2) NOT NULL,
    transaction_time DATETIME NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    FOREIGN KEY (employee_id) REFERENCES EMPLOYEE(employee_id),
    FOREIGN KEY (member_id) REFERENCES MEMBER(member_id)
);

CREATE TABLE TRANSACTION_ITEM (
    transaction_item_id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_id INT NOT NULL,
    product_id INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (transaction_id) REFERENCES TRANSACTION(transaction_id),
    FOREIGN KEY (product_id) REFERENCES PRODUCT(product_id)
);

CREATE TABLE PURCHASE_ORDER (
    purchase_order_id INT PRIMARY KEY AUTO_INCREMENT,
    supplier_id INT NOT NULL,
    employee_id INT NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    receive_time DATETIME NOT NULL,
    FOREIGN KEY (supplier_id) REFERENCES SUPPLIER(supplier_id),
    FOREIGN KEY (employee_id) REFERENCES EMPLOYEE(employee_id)
);

CREATE TABLE PURCHASE_ORDER_ITEM (
    purchase_item_id INT PRIMARY KEY AUTO_INCREMENT,
    purchase_order_id INT NOT NULL,
    product_id INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (purchase_order_id) REFERENCES PURCHASE_ORDER(purchase_order_id),
    FOREIGN KEY (product_id) REFERENCES PRODUCT(product_id)
);

CREATE TABLE POINTS_LOG (
    points_log_id INT PRIMARY KEY AUTO_INCREMENT, 
    member_id INT NOT NULL, 
    transaction_id INT, 
    points_delta DECIMAL(10,2) NOT NULL, 
    balance_after DECIMAL(10,2) NOT NULL, 
    create_time DATETIME NOT NULL, 
    change_reason VARCHAR(255),
    FOREIGN KEY (member_id) REFERENCES MEMBER(member_id),
    FOREIGN KEY (transaction_id) REFERENCES TRANSACTION(transaction_id)
);

CREATE TABLE INVENTORY_LOG (
    inventory_log_id INT PRIMARY KEY AUTO_INCREMENT, 
    product_id INT NOT NULL, 
    purchase_item_id INT, 
    transaction_item_id INT, 
    change_quantity INT NOT NULL, 
    balance_after INT NOT NULL, 
    create_time DATETIME NOT NULL, 
    change_reason VARCHAR(25),
    FOREIGN KEY (product_id) REFERENCES PRODUCT(product_id),
    FOREIGN KEY (purchase_item_id) REFERENCES PURCHASE_ORDER_ITEM(purchase_item_id),
    FOREIGN KEY (transaction_item_id) REFERENCES TRANSACTION_ITEM(transaction_item_id)
);

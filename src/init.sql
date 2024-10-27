CREATE DATABASE IF NOT EXISTS ecommerce;
USE ecommerce;

-- Create Categories table
CREATE TABLE Categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(100) UNIQUE NOT NULL
);

-- Create Products table
CREATE TABLE Products (
    product_id INT PRIMARY KEY AUTO_INCREMENT,
    product_name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    stock_quantity INT NOT NULL,
    category_id INT,
    FOREIGN KEY (category_id) REFERENCES Categories(category_id)
);

-- Create Orders table
CREATE TABLE Orders (
    order_id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT,
    order_quantity INT NOT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES Products(product_id)
);

-- Create Order_logs table
CREATE TABLE Order_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT,
    order_quantity INT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES Orders(order_id)
);

-- Create trigger for order logging
DELIMITER //
CREATE TRIGGER after_order_insert 
AFTER INSERT ON Orders 
FOR EACH ROW 
BEGIN
    INSERT INTO Order_logs (order_id, order_quantity) 
    VALUES (NEW.order_id, NEW.order_quantity);
END;//
DELIMITER ;

-- Create stored procedure
DELIMITER //
CREATE PROCEDURE GetProductDetails(IN p_product_id INT)
BEGIN
    SELECT 
        p.product_name,
        c.category_name,
        COUNT(o.order_id) as total_orders
    FROM Products p
    LEFT JOIN Categories c ON p.category_id = c.category_id
    LEFT JOIN Orders o ON p.product_id = o.product_id
    WHERE p.product_id = p_product_id
    GROUP BY p.product_id, p.product_name, c.category_name;
END;//
DELIMITER ;

-- Insert sample data
INSERT INTO Categories (category_name) VALUES 
    ('Electronics'),
    ('Books'),
    ('Clothing');

INSERT INTO Products (product_name, price, stock_quantity, category_id) VALUES 
    ('Laptop', 83000, 10, 1),
    ('Smartphone', 41500, 20, 1),
    ('Tablet', 24900, 15, 1),
    ('Harry Potter', 7470, 50, 2),
    ('Percy Jackson', 3820, 30, 2),
    ('T-Shirt', 1660, 100, 3),
    ('Jeans', 4980, 75, 3);


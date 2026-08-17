CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    image_url VARCHAR(255) NOT NUll 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO products (name, price, image_url) VAlUES
('Apple', 10, 'http://localhost:9000/shop-products/apple.jpg'),
('Orange', 11, 'http://localhost:9000/shop-products/orange.jpg');
CREATE DATABASE IF NOT EXISTS instrumentu_noma
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE instrumentu_noma;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(30),
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_id INT UNSIGNED NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    price_per_day DECIMAL(10, 2) NOT NULL,
    security_deposit DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    condition_description TEXT,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_products_owner
        FOREIGN KEY (owner_id) REFERENCES users(id),

    CONSTRAINT chk_products_price
        CHECK (price_per_day >= 0 AND security_deposit >= 0)
);

CREATE TABLE reservations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    renter_id INT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    status ENUM(
        'pending',
        'confirmed',
        'cancelled',
        'completed'
    ) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_reservations_product
        FOREIGN KEY (product_id) REFERENCES products(id),

    CONSTRAINT fk_reservations_renter
        FOREIGN KEY (renter_id) REFERENCES users(id),

    CONSTRAINT chk_reservations_dates
        CHECK (end_date > start_date),

    CONSTRAINT chk_reservations_price
        CHECK (total_price >= 0)
);
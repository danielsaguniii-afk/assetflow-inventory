-- AssetFlow Inventory Management System
-- Run this SQL to set up the database

CREATE DATABASE IF NOT EXISTS inventory_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE inventory_db;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(60) UNIQUE NOT NULL,
    password   VARCHAR(255) NOT NULL,
    full_name  VARCHAR(120) NOT NULL,
    role       ENUM('admin','staff') DEFAULT 'staff',
    status     ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default users
-- Passwords are set via seed-users.php (run it once after importing schema)
-- admin → admin123 | staff → staff123
INSERT INTO users (username, password, full_name, role) VALUES
('admin', 'PLACEHOLDER_RUN_SEED', 'System Administrator', 'admin'),
('staff', 'PLACEHOLDER_RUN_SEED', 'Staff User', 'staff')
ON DUPLICATE KEY UPDATE username = username;


CREATE TABLE IF NOT EXISTS categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    description TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Assets (products/items) table
CREATE TABLE IF NOT EXISTS assets (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    asset_code   VARCHAR(50) UNIQUE NOT NULL,
    name         VARCHAR(150) NOT NULL,
    category_id  INT,
    description  TEXT,
    unit         VARCHAR(30) DEFAULT 'pcs',
    unit_cost    DECIMAL(12,2) DEFAULT 0.00,
    stock_qty    INT DEFAULT 0,
    min_stock    INT DEFAULT 0,
    location     VARCHAR(100),
    status       ENUM('active','inactive') DEFAULT 'active',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Stock In transactions
CREATE TABLE IF NOT EXISTS stock_in (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    reference_no VARCHAR(60) UNIQUE NOT NULL,
    asset_id     INT NOT NULL,
    qty          INT NOT NULL,
    unit_cost    DECIMAL(12,2) DEFAULT 0.00,
    total_cost   DECIMAL(14,2) GENERATED ALWAYS AS (qty * unit_cost) STORED,
    supplier     VARCHAR(150),
    received_by  VARCHAR(100),
    remarks      TEXT,
    date_in      DATE NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
);

-- Stock Out transactions
CREATE TABLE IF NOT EXISTS stock_out (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    reference_no VARCHAR(60) UNIQUE NOT NULL,
    asset_id     INT NOT NULL,
    qty          INT NOT NULL,
    issued_to    VARCHAR(150),
    department   VARCHAR(100),
    purpose      TEXT,
    released_by  VARCHAR(100),
    date_out     DATE NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
);

-- Seed categories
INSERT INTO categories (name, description) VALUES
('Electronics',   'Computers, peripherals, and electronic devices'),
('Furniture',     'Office furniture and fixtures'),
('Office Supplies','Consumables and stationery'),
('Tools & Equipment','Hand tools, power tools, and machinery'),
('Vehicles',      'Company vehicles and transportation assets')
ON DUPLICATE KEY UPDATE name = name;

-- Seed sample assets
INSERT INTO assets (asset_code, name, category_id, unit, unit_cost, stock_qty, min_stock, location) VALUES
('AST-0001', 'Laptop Dell Inspiron 15',    1, 'unit',  45000.00, 12, 3,  'IT Storage Room'),
('AST-0002', 'Wireless Mouse Logitech',    1, 'pcs',    850.00,  35, 10, 'IT Storage Room'),
('AST-0003', 'Office Chair (Ergonomic)',   2, 'unit',  8500.00,   8, 2,  'Warehouse A'),
('AST-0004', 'A4 Bond Paper (ream)',       3, 'ream',   250.00,  60, 20, 'Supply Room'),
('AST-0005', 'HP LaserJet Toner Cartridge',1, 'pcs',  3200.00,  14, 5,  'Supply Room'),
('AST-0006', 'Electric Drill Bosch',       4, 'unit',  6500.00,   5, 1,  'Tool Room'),
('AST-0007', 'Standing Desk',             2, 'unit', 12000.00,   4, 1,  'Warehouse B'),
('AST-0008', 'HDMI Cable 3m',             1, 'pcs',   350.00,  22, 8,  'IT Storage Room')
ON DUPLICATE KEY UPDATE name = name;

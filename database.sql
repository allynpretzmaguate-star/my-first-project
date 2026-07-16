-- =========================================================
-- AI-Powered Client Information Encoding System
-- Database Schema
-- Import this file via phpMyAdmin (XAMPP) before running the app
-- =========================================================

CREATE DATABASE IF NOT EXISTS client_encoding_system
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE client_encoding_system;

-- ---------------------------------------------------------
-- Users (Admin / Encoder)
-- ---------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,          -- bcrypt hash
    role ENUM('admin','encoder') NOT NULL DEFAULT 'encoder',
    status ENUM('active','disabled') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Clients (the encoded records)
-- ---------------------------------------------------------
CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) DEFAULT NULL,
    last_name VARCHAR(100) NOT NULL,
    suffix VARCHAR(20) DEFAULT NULL,
    birth_date DATE DEFAULT NULL,
    sex ENUM('Male','Female','Other') DEFAULT NULL,
    civil_status ENUM('Single','Married','Widowed','Separated','Divorced') DEFAULT NULL,
    nationality VARCHAR(50) DEFAULT 'Filipino',
    address TEXT DEFAULT NULL,
    contact_number VARCHAR(30) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,
    id_type VARCHAR(50) DEFAULT NULL,          -- e.g. National ID, Passport, Driver's License
    id_number VARCHAR(100) DEFAULT NULL,
    document_image VARCHAR(255) DEFAULT NULL,  -- stored filename of scanned document
    notes TEXT DEFAULT NULL,
    status ENUM('active','archived') NOT NULL DEFAULT 'active',
    created_by INT DEFAULT NULL,
    updated_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    FULLTEXT KEY ft_search (first_name, last_name, address, id_number)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- OCR scan raw results (audit trail of what AI extracted
-- vs. what the encoder actually saved)
-- ---------------------------------------------------------
CREATE TABLE ocr_scans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT DEFAULT NULL,
    scanned_by INT DEFAULT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    raw_text MEDIUMTEXT DEFAULT NULL,
    parsed_json JSON DEFAULT NULL,
    confidence FLOAT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    FOREIGN KEY (scanned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Activity logs
-- ---------------------------------------------------------
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,          -- e.g. LOGIN, CREATE_CLIENT, DELETE_CLIENT
    description VARCHAR(255) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Seed data: default admin account
-- Username: admin | Password: Admin@123
-- (hash generated with PHP password_hash, bcrypt)
-- CHANGE THIS PASSWORD after first login.
-- ---------------------------------------------------------
INSERT INTO users (full_name, username, email, password, role)
VALUES (
    'System Administrator',
    'admin',
    'admin@example.com',
    '$2b$10$14Snh8.nmGKVqkvb49fb/.PJYQzFfY2I2G5secQNh4aitIN0VDHeu',
    'admin'
);
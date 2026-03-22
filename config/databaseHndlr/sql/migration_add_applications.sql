-- Migration: Add New Tables for Account Applications System
-- Date: March 3, 2026
-- Run this in phpMyAdmin to add the new tables

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS maia_alta_hoa;
USE maia_alta_hoa;

-- Account Applications Table (for self-service registration)
CREATE TABLE IF NOT EXISTS account_applications (
    application_id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    unit_number VARCHAR(20) NOT NULL,
    lot_number VARCHAR(20),
    block_number VARCHAR(20),
    resident_type ENUM('owner', 'tenant') NOT NULL,
    id_proof_path VARCHAR(255), -- Path to uploaded ID
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    rejection_reason TEXT,
    reviewed_by INT,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_email (email),
    INDEX idx_created_at (created_at)
);

-- SMS Logs Table (for tracking SMS notifications)
CREATE TABLE IF NOT EXISTS sms_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    phone_number VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    error_message TEXT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_sent_at (sent_at)
);

-- Email Logs Table (for tracking email notifications)
CREATE TABLE IF NOT EXISTS email_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    email_address VARCHAR(100) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    error_message TEXT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_sent_at (sent_at)
);

-- Verify tables were created
SELECT 'Migration completed successfully!' as Status;
SHOW TABLES LIKE '%applications%';
SHOW TABLES LIKE '%logs%';

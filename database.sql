-- Maia Alta Homes HOA Management System Database
-- Created: November 7, 2025

CREATE DATABASE IF NOT EXISTS maia_alta_hoa;
USE maia_alta_hoa;

-- Users Table (Admin and Residents)
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    account_number VARCHAR(20) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    contact_number VARCHAR(20),
    user_role ENUM('admin', 'resident') DEFAULT 'resident',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Households Table
CREATE TABLE households (
    household_id INT PRIMARY KEY AUTO_INCREMENT,
    unit_number VARCHAR(20) UNIQUE NOT NULL,
    lot_number VARCHAR(20),
    block_number VARCHAR(20),
    street_address VARCHAR(255),
    owner_id INT,
    resident_type ENUM('owner', 'tenant') DEFAULT 'owner',
    move_in_date DATE,
    status ENUM('occupied', 'vacant') DEFAULT 'occupied',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Link users to households (for multiple residents per household)
CREATE TABLE household_members (
    member_id INT PRIMARY KEY AUTO_INCREMENT,
    household_id INT NOT NULL,
    user_id INT NOT NULL,
    relationship ENUM('owner', 'spouse', 'child', 'relative', 'tenant') DEFAULT 'owner',
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (household_id) REFERENCES households(household_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Monthly Dues/Fees Table
CREATE TABLE monthly_dues (
    dues_id INT PRIMARY KEY AUTO_INCREMENT,
    household_id INT NOT NULL,
    due_month VARCHAR(20) NOT NULL,
    due_year INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    due_date DATE NOT NULL,
    status ENUM('unpaid', 'paid', 'overdue') DEFAULT 'unpaid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (household_id) REFERENCES households(household_id) ON DELETE CASCADE,
    UNIQUE KEY unique_household_month_year (household_id, due_month, due_year)
);

-- Payments Table
CREATE TABLE payments (
    payment_id INT PRIMARY KEY AUTO_INCREMENT,
    dues_id INT NOT NULL,
    household_id INT NOT NULL,
    payment_date DATE NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'bank_transfer', 'gcash', 'check') NOT NULL,
    reference_number VARCHAR(100),
    proof_of_payment VARCHAR(255),
    verified_by INT,
    verified_at TIMESTAMP NULL,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dues_id) REFERENCES monthly_dues(dues_id) ON DELETE CASCADE,
    FOREIGN KEY (household_id) REFERENCES households(household_id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Announcements Table
CREATE TABLE announcements (
    announcement_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    announcement_type ENUM('general', 'maintenance', 'event', 'urgent') DEFAULT 'general',
    posted_by INT NOT NULL,
    post_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    expiry_date DATE NULL,
    status ENUM('active', 'archived') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (posted_by) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Events/Activities Table
CREATE TABLE events (
    event_id INT PRIMARY KEY AUTO_INCREMENT,
    event_name VARCHAR(255) NOT NULL,
    event_description TEXT,
    event_date DATE NOT NULL,
    event_time TIME,
    location VARCHAR(255),
    organizer_id INT NOT NULL,
    max_participants INT,
    status ENUM('upcoming', 'ongoing', 'completed', 'cancelled') DEFAULT 'upcoming',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organizer_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Facility Bookings Table
CREATE TABLE facility_bookings (
    booking_id INT PRIMARY KEY AUTO_INCREMENT,
    household_id INT NOT NULL,
    facility_name VARCHAR(100) NOT NULL,
    booking_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    purpose VARCHAR(255),
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    approved_by INT,
    approved_at TIMESTAMP NULL,
    booking_fee DECIMAL(10,2) DEFAULT 0.00,
    payment_status ENUM('unpaid', 'paid') DEFAULT 'unpaid',
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (household_id) REFERENCES households(household_id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Maintenance Requests/Concerns Table
CREATE TABLE maintenance_requests (
    request_id INT PRIMARY KEY AUTO_INCREMENT,
    household_id INT NOT NULL,
    requested_by INT NOT NULL,
    category ENUM('electrical', 'plumbing', 'structural', 'landscaping', 'security', 'other') NOT NULL,
    subject VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    assigned_to INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (household_id) REFERENCES households(household_id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Vehicles Table
CREATE TABLE vehicles (
    vehicle_id INT PRIMARY KEY AUTO_INCREMENT,
    household_id INT NOT NULL,
    plate_number VARCHAR(20) UNIQUE NOT NULL,
    vehicle_type ENUM('car', 'motorcycle', 'suv', 'van', 'truck') NOT NULL,
    vehicle_make VARCHAR(50),
    vehicle_model VARCHAR(50),
    vehicle_color VARCHAR(30),
    sticker_number VARCHAR(20),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (household_id) REFERENCES households(household_id) ON DELETE CASCADE
);

-- Documents Table
CREATE TABLE documents (
    document_id INT PRIMARY KEY AUTO_INCREMENT,
    document_name VARCHAR(255) NOT NULL,
    document_type ENUM('minutes', 'financial', 'rules', 'memo', 'other') NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_by INT NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    description TEXT,
    status ENUM('active', 'archived') DEFAULT 'active',
    FOREIGN KEY (uploaded_by) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Insert default admin account
-- Password: admin123 (plain text for now - will add hashing later)
INSERT INTO users (account_number, password, email, first_name, last_name, contact_number, user_role, status) 
VALUES ('ADMIN001', 'admin123', 'treasurer@maiaalta.com', 'Admin', 'Treasurer', '09123456789', 'admin', 'active');

-- Sample resident data (created by admin)
INSERT INTO users (account_number, password, email, first_name, last_name, contact_number, user_role, status, created_by) 
VALUES 
('MAIA-2025-001', 'admin123', 'john.doe@example.com', 'John', 'Doe', '09111111111', 'resident', 'active', 1),
('MAIA-2025-002', 'admin123', 'mary.smith@example.com', 'Mary', 'Smith', '09222222222', 'resident', 'active', 1);

-- Sample households
INSERT INTO households (unit_number, lot_number, block_number, street_address, owner_id, resident_type, move_in_date, status)
VALUES
('Unit 101', 'Lot 1', 'Block A', 'Maia Alta Street', 2, 'owner', '2020-01-15', 'occupied'),
('Unit 102', 'Lot 2', 'Block A', 'Maia Alta Street', 3, 'owner', '2020-03-20', 'occupied');

-- Link users to households
INSERT INTO household_members (household_id, user_id, relationship, is_primary)
VALUES
(1, 2, 'owner', TRUE),
(2, 3, 'owner', TRUE);

-- Sample monthly dues
INSERT INTO monthly_dues (household_id, due_month, due_year, amount, due_date, status)
VALUES
(1, 'November', 2025, 150.00, '2025-11-10', 'unpaid'),
(1, 'October', 2025, 150.00, '2025-10-10', 'paid'),
(2, 'November', 2025, 150.00, '2025-11-10', 'unpaid');

-- Sample payment
INSERT INTO payments (dues_id, household_id, payment_date, amount_paid, payment_method, reference_number, verified_by, verified_at)
VALUES
(2, 1, '2025-10-08', 150.00, 'gcash', 'GC12345678', 1, '2025-10-08 14:30:00');

-- Update dues status for paid record
UPDATE monthly_dues SET status = 'paid' WHERE dues_id = 2;

-- Notification Preferences Table
CREATE TABLE notification_preferences (
    preference_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    email_notifications BOOLEAN DEFAULT TRUE,
    sms_notifications BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_preferences (user_id)
);

-- Insert default preferences for existing users
INSERT INTO notification_preferences (user_id, email_notifications, sms_notifications)
SELECT user_id, TRUE, FALSE FROM users WHERE user_role = 'resident';

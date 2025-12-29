CREATE DATABASE capitalrise_db;

USE capitalrise_db;

-- Users Table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id VARCHAR(20) UNIQUE,
    name VARCHAR(100),
    email VARCHAR(100),
    mobile VARCHAR(20),
    password VARCHAR(255),
    sponsor_id VARCHAR(20),
    balance DECIMAL(10,2) DEFAULT 0.00,
    fund DECIMAL(10,2) DEFAULT 0.00,
    current_package VARCHAR(50),
    kyc_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    status ENUM('active', 'blocked') DEFAULT 'active',
    join_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP,
    created_by VARCHAR(50),
    device_info TEXT,
    ip_address VARCHAR(50),
    INDEX (user_id),
    INDEX (sponsor_id)
);

-- Admin Table
CREATE TABLE admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id VARCHAR(50) UNIQUE,
    name VARCHAR(100),
    password VARCHAR(255),
    email VARCHAR(100),
    last_login TIMESTAMP,
    ip_address VARCHAR(50)
);

-- Packages Table
CREATE TABLE packages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    package_name VARCHAR(50),
    amount DECIMAL(10,2),
    daily_task_percent DECIMAL(5,2),
    referral_commission DECIMAL(5,2),
    validity_days INT,
    is_active BOOLEAN DEFAULT 1
);

-- User Packages History
CREATE TABLE user_packages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id VARCHAR(20),
    package_name VARCHAR(50),
    amount DECIMAL(10,2),
    purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expiry_date DATE,
    status ENUM('active', 'expired') DEFAULT 'active',
    daily_income DECIMAL(10,2),
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    INDEX (user_id),
    INDEX (expiry_date)
);

-- Transactions Table
CREATE TABLE transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_id VARCHAR(50) UNIQUE,
    user_id VARCHAR(20),
    type ENUM('deposit', 'withdrawal', 'task_income', 'referral_commission', 'package_purchase', 'kyc_bonus'),
    amount DECIMAL(10,2),
    status ENUM('pending', 'approved', 'rejected', 'completed'),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_method VARCHAR(50),
    upi_transaction_id VARCHAR(100),
    user_upi_id VARCHAR(100),
    approved_by VARCHAR(50),
    approved_at TIMESTAMP NULL,
    device_info TEXT,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    INDEX (user_id),
    INDEX (created_at)
);

-- Daily Tasks Table
CREATE TABLE daily_tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id VARCHAR(20),
    task_date DATE,
    clicks_completed INT DEFAULT 0,
    total_income DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_date (user_id, task_date),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Task Income Log
CREATE TABLE task_income_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id VARCHAR(20),
    task_date DATE,
    click_number INT,
    amount DECIMAL(10,2),
    package_name VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    INDEX (user_id, task_date)
);

-- Withdrawal Requests
CREATE TABLE withdrawal_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_id VARCHAR(50) UNIQUE,
    user_id VARCHAR(20),
    amount DECIMAL(10,2),
    fee DECIMAL(10,2),
    net_amount DECIMAL(10,2),
    method ENUM('bank', 'upi', 'paytm'),
    account_details TEXT,
    status ENUM('pending', 'approved', 'rejected'),
    transaction_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    processed_by VARCHAR(50),
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    INDEX (user_id),
    INDEX (status)
);

-- KYC Submissions
CREATE TABLE kyc_submissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id VARCHAR(20) UNIQUE,
    full_name VARCHAR(100),
    dob DATE,
    aadhar_number VARCHAR(20),
    pan_number VARCHAR(20),
    bank_account VARCHAR(50),
    ifsc_code VARCHAR(20),
    aadhar_front TEXT,
    aadhar_back TEXT,
    pan_card TEXT,
    status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified_by VARCHAR(50),
    verified_at TIMESTAMP NULL,
    rejection_reason TEXT,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Referral System
CREATE TABLE referrals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sponsor_id VARCHAR(20),
    referral_id VARCHAR(20),
    referral_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    commission_paid BOOLEAN DEFAULT 0,
    commission_amount DECIMAL(10,2),
    FOREIGN KEY (sponsor_id) REFERENCES users(user_id),
    FOREIGN KEY (referral_id) REFERENCES users(user_id),
    UNIQUE KEY unique_referral (sponsor_id, referral_id)
);

-- System Settings
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(50) UNIQUE,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Activity Logs
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id VARCHAR(50),
    user_type ENUM('user', 'admin'),
    action VARCHAR(100),
    details TEXT,
    ip_address VARCHAR(50),
    device_info TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (created_at)
);

-- Insert Default Admin
INSERT INTO admins (admin_id, name, password, email) VALUES 
('admin', 'Super Admin', MD5('admin123'), 'admin@capitalrise.com');

-- Insert Packages
INSERT INTO packages (package_name, amount, daily_task_percent, referral_commission, validity_days) VALUES
('BRONZE', 1000, 5.00, 10.00, 30),
('PLATINUM', 3000, 5.00, 10.00, 30),
('SILVER', 5000, 5.00, 10.00, 30),
('GOLDEN', 10000, 5.00, 10.00, 30),
('DIAMOND', 20000, 5.00, 10.00, 30),
('DEFENDER', 30000, 5.00, 10.00, 30),
('CROWN', 50000, 5.00, 10.00, 30),
('LEGEND', 70000, 5.00, 10.00, 30),
('CONQUERER', 100000, 5.00, 10.00, 30),
('GOLD', 200000, 5.00, 10.00, 30),
('CRYSTAL', 500000, 5.00, 10.00, 30);

-- Insert Default Settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('min_deposit', '100', 'Minimum deposit amount'),
('min_withdrawal', '200', 'Minimum withdrawal amount'),
('withdrawal_fee', '5', 'Withdrawal fee percentage'),
('referral_commission', '10', 'Referral commission percentage'),
('daily_task_percent', '5', 'Daily task income percentage'),
('package_validity', '30', 'Package validity in days'),
('upi_id', 'capitalrise@ybl', 'Platform UPI ID'),
('bank_details', 'State Bank of India\nAccount: 123456789012\nIFSC: SBIN0001234\nName: CapitalRise Trading', 'Bank details'),
('platform_name', 'CapitalRise Trading Platform', 'Platform name'),
('contact_number', '+91 98765 43210', 'Contact number'),
('telegram_channel', 'https://t.me/CapitalRise', 'Telegram channel');
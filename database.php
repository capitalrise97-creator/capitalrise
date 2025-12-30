CREATE DATABASE IF NOT EXISTS capitalrise_db;
USE capitalrise_db;

-- ================= USERS =================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(36) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    mobile VARCHAR(15) NOT NULL,
    password VARCHAR(255) NOT NULL,
    sponsor_id VARCHAR(36),
    join_date DATETIME DEFAULT CURRENT_TIMESTAMP,

    balance DECIMAL(15,2) DEFAULT 0.00,
    fund DECIMAL(15,2) DEFAULT 0.00,
    total_income DECIMAL(15,2) DEFAULT 0.00,
    today_income DECIMAL(15,2) DEFAULT 0.00,
    referral_income DECIMAL(15,2) DEFAULT 0.00,

    package VARCHAR(50) DEFAULT 'None',
    kyc_status ENUM('Pending','Under Review','Approved','Rejected') DEFAULT 'Pending',
    status ENUM('Active','Blocked') DEFAULT 'Active',
    rank VARCHAR(50) DEFAULT 'Beginner',
    referrals INT DEFAULT 0,

    last_login DATETIME,
    created_by VARCHAR(50),
    device_info TEXT,
    ip_address VARCHAR(50),

    INDEX idx_user_id (user_id),
    INDEX idx_sponsor_id (sponsor_id)
);

-- ================= PACKAGES =================
CREATE TABLE packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    daily_income_percent DECIMAL(5,2) NOT NULL,
    referral_commission_percent DECIMAL(5,2) NOT NULL,
    validity_days INT NOT NULL,
    features TEXT,
    status ENUM('Active','Inactive') DEFAULT 'Active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ================= USER PACKAGES =================
CREATE TABLE user_packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    package_id INT NOT NULL,
    package_name VARCHAR(50) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    activation_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    expiry_date DATETIME NOT NULL,
    status ENUM('Active','Expired') DEFAULT 'Active',
    daily_income DECIMAL(15,2) NOT NULL,
    total_earned DECIMAL(15,2) DEFAULT 0.00,

    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
);

-- ================= DEPOSIT REQUESTS =================
CREATE TABLE deposit_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id VARCHAR(50) UNIQUE NOT NULL,
    user_id VARCHAR(36) NOT NULL,
    name VARCHAR(100) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    method VARCHAR(50) NOT NULL,
    upi_transaction_id VARCHAR(100),
    user_upi_id VARCHAR(100),
    date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
    notes TEXT,
    approved_by VARCHAR(50),
    approved_at DATETIME,
    device_info TEXT,

    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
);

-- ================= WITHDRAWAL REQUESTS =================
CREATE TABLE withdrawal_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id VARCHAR(50) UNIQUE NOT NULL,
    user_id VARCHAR(36) NOT NULL,
    name VARCHAR(100) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    method VARCHAR(50) NOT NULL,
    account_details TEXT NOT NULL,
    date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Pending','Approved','Rejected','Processing') DEFAULT 'Pending',
    fee DECIMAL(15,2) DEFAULT 0.00,
    net_amount DECIMAL(15,2) DEFAULT 0.00,
    transaction_id VARCHAR(100),
    processed_date DATETIME,
    approved_by VARCHAR(50),
    notes TEXT,
    device_info TEXT,

    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
);

-- ================= KYC =================
CREATE TABLE kyc_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(36) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    dob DATE NOT NULL,
    aadhar_number VARBINARY(255) NOT NULL,
    pan_number VARBINARY(255) NOT NULL,
    bank_account VARCHAR(50) NOT NULL,
    ifsc_code VARCHAR(20) NOT NULL,
    aadhar_front VARCHAR(255),
    aadhar_back VARCHAR(255),
    pan_card VARCHAR(255),
    submitted_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Pending','Under Review','Approved','Rejected') DEFAULT 'Pending',
    reviewed_by VARCHAR(50),
    reviewed_date DATETIME,
    notes TEXT,
    device_info TEXT
);

-- ================= DAILY TASKS =================
CREATE TABLE daily_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    task_date DATE NOT NULL,
    clicks_completed INT DEFAULT 0,
    total_clicks_needed INT DEFAULT 15,
    today_income DECIMAL(15,2) DEFAULT 0.00,
    income_credited ENUM('Yes','No') DEFAULT 'No',
    last_click_time DATETIME,
    status ENUM('Pending','Completed') DEFAULT 'Pending',

    UNIQUE KEY unique_user_date (user_id, task_date),
    INDEX idx_user_date (user_id, task_date)
);

-- ================= TASK INCOME HISTORY =================
CREATE TABLE task_income_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    task_date DATE NOT NULL,
    click_number INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    package_name VARCHAR(50),
    fund_amount DECIMAL(15,2),
    click_time DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_user_date (user_id, task_date)
);

-- ================= TRANSACTIONS =================
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id VARCHAR(50) UNIQUE NOT NULL,
    user_id VARCHAR(36) NOT NULL,
    type ENUM(
        'Deposit','Withdrawal','Task Income',
        'Referral Commission','Package Activation',
        'Registration Bonus','KYC Bonus','Admin Fund Added'
    ) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Pending','Completed','Approved','Rejected') NOT NULL,
    description TEXT,
    reference_id VARCHAR(100),
    device_info TEXT,

    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_date (date)
);

-- ================= REFERRAL INCOME =================
CREATE TABLE referral_income (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sponsor_id VARCHAR(36) NOT NULL,
    referral_id VARCHAR(36) NOT NULL,
    referral_name VARCHAR(100) NOT NULL,
    package_name VARCHAR(50),
    commission_percent DECIMAL(5,2) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Paid','Pending') DEFAULT 'Paid',

    INDEX idx_sponsor_id (sponsor_id),
    INDEX idx_referral_id (referral_id)
);

-- ================= ADMINS =================
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('Super Admin','Admin','Support') DEFAULT 'Admin',
    status ENUM('Active','Inactive') DEFAULT 'Active',
    last_login DATETIME,
    ip_address VARCHAR(50),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ================= SETTINGS =================
CREATE TABLE platform_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    description TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ================= ADMIN LOGS =================
CREATE TABLE admin_activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id VARCHAR(50) NOT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(50),
    device_info TEXT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_admin_id (admin_id),
    INDEX idx_timestamp (timestamp)
);

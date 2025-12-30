<?php
// ========================================
// CapitalRise - install.php (VERCEL SAFE)
// ========================================

header('Content-Type: application/json');
require_once 'config.php';

$conn = getDBConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$sql = [];

/* ================= USERS ================= */
$sql[] = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(36) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    mobile VARCHAR(15),
    balance DECIMAL(15,2) DEFAULT 0.00,
    fund DECIMAL(15,2) DEFAULT 0.00,
    package VARCHAR(50) DEFAULT 'None',
    kyc_status ENUM('Pending','Under Review','Approved','Rejected') DEFAULT 'Pending',
    status ENUM('Active','Blocked') DEFAULT 'Active',
    referrals INT DEFAULT 0,
    total_income DECIMAL(15,2) DEFAULT 0.00,
    sponsor_id VARCHAR(36),
    join_date DATE,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_sponsor (sponsor_id)
)";

/* ================= ADMINS ================= */
$sql[] = "CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id VARCHAR(36) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    role ENUM('Super Admin','Admin','Support') DEFAULT 'Super Admin',
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

/* ================= DEPOSIT ================= */
$sql[] = "CREATE TABLE IF NOT EXISTS deposit_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id VARCHAR(50) UNIQUE NOT NULL,
    user_id VARCHAR(36) NOT NULL,
    name VARCHAR(100) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    method VARCHAR(50) DEFAULT 'UPI',
    upi_transaction_id VARCHAR(100),
    user_upi_id VARCHAR(100),
    status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
    notes TEXT,
    approved_by VARCHAR(36),
    approved_at DATETIME,
    device_info TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
)";

/* ================= WITHDRAWAL ================= */
$sql[] = "CREATE TABLE IF NOT EXISTS withdrawal_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id VARCHAR(50) UNIQUE NOT NULL,
    user_id VARCHAR(36) NOT NULL,
    name VARCHAR(100) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    method VARCHAR(50),
    account_details TEXT,
    fee DECIMAL(15,2) DEFAULT 0.00,
    net_amount DECIMAL(15,2) DEFAULT 0.00,
    status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
    transaction_id VARCHAR(100),
    approved_by VARCHAR(36),
    approved_at DATETIME,
    device_info TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
)";

/* ================= KYC ================= */
$sql[] = "CREATE TABLE IF NOT EXISTS kyc_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(36) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    dob DATE,
    aadhar_number VARBINARY(255),
    pan_number VARBINARY(255),
    bank_account VARCHAR(50),
    ifsc_code VARCHAR(20),
    status ENUM('Pending','Under Review','Approved','Rejected') DEFAULT 'Pending',
    reviewed_by VARCHAR(36),
    reviewed_at DATETIME,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id)
)";

/* ================= TRANSACTIONS ================= */
$sql[] = "CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id VARCHAR(50) UNIQUE NOT NULL,
    user_id VARCHAR(36) NOT NULL,
    type VARCHAR(50),
    amount DECIMAL(15,2),
    description TEXT,
    status VARCHAR(50),
    device_info TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
)";

/* ================= DAILY TASKS ================= */
$sql[] = "CREATE TABLE IF NOT EXISTS daily_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    task_date DATE NOT NULL,
    clicks_completed INT DEFAULT 0,
    today_income DECIMAL(15,2) DEFAULT 0.00,
    income_credited ENUM('Yes','No') DEFAULT 'No',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_date (user_id, task_date)
)";

/* ================= SETTINGS ================= */
$sql[] = "CREATE TABLE IF NOT EXISTS platform_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

/* ================= EXECUTE ================= */
foreach ($sql as $query) {
    if (!$conn->query($query)) {
        echo json_encode([
            'success' => false,
            'message' => 'Install failed',
            'error' => $conn->error
        ]);
        exit;
    }
}

/* ================= DEFAULT ADMIN ================= */
$adminPass = password_hash('admin123', PASSWORD_BCRYPT);
$stmt = $conn->prepare(
    "INSERT IGNORE INTO admins (admin_id, name, password, email, role)
     VALUES ('admin', 'Super Admin', ?, 'admin@capitalrise.com', 'Super Admin')"
);
$stmt->bind_param("s", $adminPass);
$stmt->execute();

/* ================= DEFAULT SETTINGS ================= */
$settings = [
    'min_deposit' => '100',
    'min_withdrawal' => '200',
    'withdrawal_fee_percent' => '5',
    'referral_commission_percent' => '10',
    'daily_task_income_percent' => '5',
    'package_validity_days' => '30',
    'upi_id' => 'capitalrise@ybl',
    'bank_details' => "State Bank of India\nAccount: 123456789012\nIFSC: SBIN0001234",
    'platform_balance' => '10000'
];

$stmt = $conn->prepare(
    "INSERT IGNORE INTO platform_settings (setting_key, setting_value) VALUES (?, ?)"
);

foreach ($settings as $k => $v) {
    $stmt->bind_param("ss", $k, $v);
    $stmt->execute();
}

echo json_encode([
    'success' => true,
    'message' => 'CapitalRise database installed successfully'
]);

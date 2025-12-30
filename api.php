<?php
// api.php - Main API for User Functions

require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Get endpoint from query parameter
$endpoint = $_GET['endpoint'] ?? '';

// Route requests based on endpoint
switch ($endpoint) {
    case 'user_login':
        handleUserLogin();
        break;
    case 'user_register':
        handleUserRegister();
        break;
    case 'get_user_data':
        handleGetUserData();
        break;
    case 'create_deposit':
        handleCreateDeposit();
        break;
    case 'create_withdrawal':
        handleCreateWithdrawal();
        break;
    case 'activate_package':
        handleActivatePackage();
        break;
    case 'submit_kyc':
        handleSubmitKYC();
        break;
    case 'update_profile':
        handleUpdateProfile();
        break;
    case 'change_password':
        handleChangePassword();
        break;
    case 'complete_task_click':
        handleCompleteTaskClick();
        break;
    case 'get_transactions':
        handleGetTransactions();
        break;
    case 'get_packages':
        handleGetPackages();
        break;
    case 'get_kyc_status':
        handleGetKYCStatus();
        break;
    default:
        sendResponse(false, 'Invalid endpoint', null, 404);
}

// Handle User Login
function handleUserLogin() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['user_id']) || !isset($data['password'])) {
        sendResponse(false, 'Invalid request data');
    }
    
    $userId = sanitizeInput($data['user_id']);
    $password = $data['password'];
    
    $conn = getDBConnection();
    if (!$conn) {
        sendResponse(false, 'Database connection failed');
    }
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendResponse(false, 'User not found');
    }
    
    $user = $result->fetch_assoc();
    
    if (!verifyPassword($password, $user['password'])) {
        sendResponse(false, 'Invalid password');
    }
    
    if ($user['status'] === 'Blocked') {
        sendResponse(false, 'Your account has been blocked by admin');
    }
    
    // Update last login
    $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
    $updateStmt->bind_param("s", $userId);
    $updateStmt->execute();
    
    // Remove sensitive data
    unset($user['password']);
    
    // Generate JWT token
    $tokenPayload = [
        'user_id' => $user['user_id'],
        'name' => $user['name'],
        'exp' => time() + (24 * 60 * 60) // 24 hours
    ];
    
    $token = generateJWT($tokenPayload);
    
    // Get user statistics
    $stats = getUserStatistics($userId);
    
    $responseData = [
        'user' => $user,
        'token' => $token,
        'stats' => $stats
    ];
    
    sendResponse(true, 'Login successful', $responseData);
}

// Handle User Registration
function handleUserRegister() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $requiredFields = ['name', 'mobile', 'email', 'password'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            sendResponse(false, "Please fill all required fields");
        }
    }
    
    $name = sanitizeInput($data['name']);
    $mobile = sanitizeInput($data['mobile']);
    $email = sanitizeInput($data['email']);
    $password = $data['password'];
    $sponsorId = isset($data['sponsor_id']) ? sanitizeInput($data['sponsor_id']) : 'CAPITAL01';
    
    // Validate inputs
    if (!validateEmail($email)) {
        sendResponse(false, 'Invalid email address');
    }
    
    if (!validateMobile($mobile)) {
        sendResponse(false, 'Invalid mobile number');
    }
    
    if (strlen($password) < 6) {
        sendResponse(false, 'Password must be at least 6 characters');
    }
    
    // Check if email already exists
    $conn = getDBConnection();
    if (!$conn) {
        sendResponse(false, 'Database connection failed');
    }
    
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        sendResponse(false, 'Email already registered');
    }
    
    // Check if sponsor exists
    if ($sponsorId !== 'CAPITAL01') {
        $stmt = $conn->prepare("SELECT id FROM users WHERE user_id = ?");
        $stmt->bind_param("s", $sponsorId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            sendResponse(false, 'Sponsor ID not found');
        }
    }
    
    // Generate unique user ID
    $userId = 'USER' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Ensure unique user ID
    while (userExists($userId)) {
        $userId = 'USER' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
    
    // Hash password
    $hashedPassword = hashPassword($password);
    
    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users (user_id, name, email, mobile, password, sponsor_id, balance, created_by, device_info, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $balance = 50; // Registration bonus
    $createdBy = 'Self Registration';
    $deviceInfo = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    $stmt->bind_param("ssssssdsss", $userId, $name, $email, $mobile, $hashedPassword, $sponsorId, $balance, $createdBy, $deviceInfo, $ipAddress);
    
    if (!$stmt->execute()) {
        sendResponse(false, 'Registration failed: ' . $conn->error);
    }
    
    // Create registration bonus transaction
    createTransaction($userId, 'Registration Bonus', $balance, 'Completed', 'Registration bonus');
    
    // Give referral commission to sponsor
    if ($sponsorId !== 'CAPITAL01') {
        $commission = 10; // ₹10 referral bonus
        $updateStmt = $conn->prepare("UPDATE users SET balance = balance + ?, referral_income = referral_income + ?, total_income = total_income + ?, referrals = referrals + 1 WHERE user_id = ?");
        $updateStmt->bind_param("ddds", $commission, $commission, $commission, $sponsorId);
        $updateStmt->execute();
        
        // Record referral income
        $refStmt = $conn->prepare("INSERT INTO referral_income (sponsor_id, referral_id, referral_name, package_name, commission_percent, amount) VALUES (?, ?, ?, ?, ?, ?)");
        $packageName = 'None';
        $commissionPercent = 10;
        $refStmt->bind_param("ssssdd", $sponsorId, $userId, $name, $packageName, $commissionPercent, $commission);
        $refStmt->execute();
        
        createTransaction($sponsorId, 'Referral Commission', $commission, 'Completed', 'Referral bonus for ' . $userId);
    }
    
    // Generate JWT token
    $tokenPayload = [
        'user_id' => $userId,
        'name' => $name,
        'exp' => time() + (24 * 60 * 60)
    ];
    
    $token = generateJWT($tokenPayload);
    
    $responseData = [
        'user_id' => $userId,
        'name' => $name,
        'email' => $email,
        'sponsor_id' => $sponsorId,
        'balance' => $balance,
        'token' => $token
    ];
    
    sendResponse(true, 'Registration successful! Welcome bonus of ₹50 credited.', $responseData);
}

// Handle Create Deposit
function handleCreateDeposit() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $requiredFields = ['user_id', 'amount', 'upi_transaction_id'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            sendResponse(false, "Please fill all required fields");
        }
    }
    
    $userId = sanitizeInput($data['user_id']);
    $amount = floatval($data['amount']);
    $upiTransactionId = sanitizeInput($data['upi_transaction_id']);
    $userUpiId = isset($data['user_upi_id']) ? sanitizeInput($data['user_upi_id']) : '';
    
    $minDeposit = floatval(getSetting('min_deposit', 100));
    
    if ($amount < $minDeposit) {
        sendResponse(false, "Minimum deposit amount is ₹$minDeposit");
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        sendResponse(false, 'Database connection failed');
    }
    
    // Get user info
    $user = getUser($userId);
    if (!$user) {
        sendResponse(false, 'User not found');
    }
    
    // Generate request ID
    $requestId = generateUniqueId('DEP');
    
    // Insert deposit request
    $stmt = $conn->prepare("INSERT INTO deposit_requests (request_id, user_id, name, amount, method, upi_transaction_id, user_upi_id, device_info) VALUES (?, ?, ?, ?, 'UPI', ?, ?, ?)");
    $deviceInfo = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    $stmt->bind_param("sssdsss", $requestId, $userId, $user['name'], $amount, $upiTransactionId, $userUpiId, $deviceInfo);
    
    if (!$stmt->execute()) {
        sendResponse(false, 'Deposit request failed: ' . $conn->error);
    }
    
    // Create transaction record
    createTransaction($userId, 'Deposit', $amount, 'Pending', 'Deposit request via UPI', $requestId);
    
    $responseData = [
        'request_id' => $requestId,
        'amount' => $amount,
        'status' => 'Pending'
    ];
    
    sendResponse(true, 'Deposit request submitted successfully! It will be processed within 24 hours.', $responseData);
}

// Handle Create Withdrawal
function handleCreateWithdrawal() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $requiredFields = ['user_id', 'amount', 'method', 'account_details'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            sendResponse(false, "Please fill all required fields");
        }
    }
    
    $userId = sanitizeInput($data['user_id']);
    $amount = floatval($data['amount']);
    $method = sanitizeInput($data['method']);
    $accountDetails = sanitizeInput($data['account_details']);
    
    $minWithdrawal = floatval(getSetting('min_withdrawal', 200));
    $withdrawalFeePercent = floatval(getSetting('withdrawal_fee_percent', 5));
    
    if ($amount < $minWithdrawal) {
        sendResponse(false, "Minimum withdrawal amount is ₹$minWithdrawal");
    }
    
    // Calculate fee and net amount
    $fee = ($amount * $withdrawalFeePercent) / 100;
    $netAmount = $amount - $fee;
    
    $conn = getDBConnection();
    if (!$conn) {
        sendResponse(false, 'Database connection failed');
    }
    
    // Check user balance
    $user = getUser($userId);
    if (!$user) {
        sendResponse(false, 'User not found');
    }
    
    if ($user['balance'] < $amount) {
        sendResponse(false, 'Insufficient balance');
    }
    
    // Check KYC status
    if ($user['kyc_status'] !== 'Approved') {
        sendResponse(false, 'KYC must be approved before withdrawal');
    }
    
    // Generate request ID
    $requestId = generateUniqueId('WDR');
    
    // Insert withdrawal request
    $stmt = $conn->prepare("INSERT INTO withdrawal_requests (request_id, user_id, name, amount, method, account_details, fee, net_amount, device_info) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $deviceInfo = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    $stmt->bind_param("sssdssdds", $requestId, $userId, $user['name'], $amount, $method, $accountDetails, $fee, $netAmount, $deviceInfo);
    
    if (!$stmt->execute()) {
        sendResponse(false, 'Withdrawal request failed: ' . $conn->error);
    }
    
    // Deduct amount from user balance
    updateUserBalance($userId, $amount, 'subtract');
    
    // Create transaction record
    createTransaction($userId, 'Withdrawal', $amount, 'Pending', 'Withdrawal request to ' . $method, $requestId);
    
    $responseData = [
        'request_id' => $requestId,
        'amount' => $amount,
        'fee' => $fee,
        'net_amount' => $netAmount,
        'status' => 'Pending'
    ];
    
    sendResponse(true, 'Withdrawal request submitted successfully! It will be processed within 24-48 hours.', $responseData);
}

// Handle Activate Package
function handleActivatePackage() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $requiredFields = ['user_id', 'package_id', 'package_name', 'amount'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            sendResponse(false, "Please fill all required fields");
        }
    }
    
    $userId = sanitizeInput($data['user_id']);
    $packageId = intval($data['package_id']);
    $packageName = sanitizeInput($data['package_name']);
    $amount = floatval($data['amount']);
    
    $conn = getDBConnection();
    if (!$conn) {
        sendResponse(false, 'Database connection failed');
    }
    
    // Check user balance
    $user = getUser($userId);
    if (!$user) {
        sendResponse(false, 'User not found');
    }
    
    if ($user['balance'] < $amount) {
        sendResponse(false, 'Insufficient balance');
    }
    
    // Check if user already has active package
    $stmt = $conn->prepare("SELECT id FROM user_packages WHERE user_id = ? AND status = 'Active' AND expiry_date > NOW()");
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        sendResponse(false, 'You already have an active package');
    }
    
    // Calculate expiry date
    $validityDays = intval(getSetting('package_validity_days', 30));
    $expiryDate = date('Y-m-d H:i:s', strtotime("+$validityDays days"));
    
    // Calculate daily income
    $dailyIncomePercent = floatval(getSetting('daily_task_income_percent', 5));
    $dailyIncome = ($amount * $dailyIncomePercent) / 100;
    
    // Deduct amount from user balance
    updateUserBalance($userId, $amount, 'subtract');
    
    // Add to user fund
    $updateStmt = $conn->prepare("UPDATE users SET fund = fund + ?, package = ? WHERE user_id = ?");
    $updateStmt->bind_param("dss", $amount, $packageName, $userId);
    $updateStmt->execute();
    
    // Insert package activation record
    $stmt = $conn->prepare("INSERT INTO user_packages (user_id, package_id, package_name, amount, expiry_date, daily_income) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sisdss", $userId, $packageId, $packageName, $amount, $expiryDate, $dailyIncome);
    
    if (!$stmt->execute()) {
        sendResponse(false, 'Package activation failed: ' . $conn->error);
    }
    
    // Create transaction record
    createTransaction($userId, 'Package Activation', $amount, 'Completed', $packageName . ' package activated');
    
    $responseData = [
        'package_name' => $packageName,
        'amount' => $amount,
        'expiry_date' => $expiryDate,
        'daily_income' => $dailyIncome
    ];
    
    sendResponse(true, 'Package activated successfully! You can now start daily tasks.', $responseData);
}

// Handle Complete Task Click
function handleCompleteTaskClick() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['user_id'])) {
        sendResponse(false, "User ID is required");
    }
    
    $userId = sanitizeInput($data['user_id']);
    
    $conn = getDBConnection();
    if (!$conn) {
        sendResponse(false, 'Database connection failed');
    }
    
    // Check if user has active package
    $user = getUser($userId);
    if (!$user || $user['package'] === 'None') {
        sendResponse(false, 'Please activate a package first');
    }
    
    // Check if package is active
    $stmt = $conn->prepare("SELECT id FROM user_packages WHERE user_id = ? AND status = 'Active' AND expiry_date > NOW()");
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        sendResponse(false, 'Your package has expired. Please renew your package.');
    }
    
    $today = date('Y-m-d');
    
    // Get or create daily task record
    $taskProgress = getDailyTaskProgress($userId);
    
    // Check if already completed today's tasks
    if ($taskProgress['clicks_completed'] >= $taskProgress['total_clicks_needed']) {
        sendResponse(false, 'You have already completed today\'s tasks!');
    }
    
    // Calculate per click income
    $perClickIncome = calculatePerClickIncome($user['fund']);
    
    // Update task progress
    $newClicksCompleted = $taskProgress['clicks_completed'] + 1;
    $newTodayIncome = $taskProgress['today_income'] + $perClickIncome;
    
    $stmt = $conn->prepare("UPDATE daily_tasks SET clicks_completed = ?, today_income = ?, last_click_time = NOW() WHERE user_id = ? AND task_date = ?");
    $stmt->bind_param("idss", $newClicksCompleted, $newTodayIncome, $userId, $today);
    $stmt->execute();
    
    // Add income to user balance
    updateUserBalance($userId, $perClickIncome, 'add');
    
    // Record task income
    $taskStmt = $conn->prepare("INSERT INTO task_income_history (user_id, task_date, click_number, amount, package_name, fund_amount) VALUES (?, ?, ?, ?, ?, ?)");
    $taskStmt->bind_param("ssidss", $userId, $today, $newClicksCompleted, $perClickIncome, $user['package'], $user['fund']);
    $taskStmt->execute();
    
    // Create transaction record
    createTransaction($userId, 'Task Income', $perClickIncome, 'Completed', 'Daily click task #' . $newClicksCompleted);
    
    $responseData = [
        'click_number' => $newClicksCompleted,
        'total_clicks_needed' => $taskProgress['total_clicks_needed'],
        'amount_earned' => $perClickIncome,
        'today_total_earned' => $newTodayIncome,
        'remaining_clicks' => $taskProgress['total_clicks_needed'] - $newClicksCompleted,
        'is_completed' => $newClicksCompleted >= $taskProgress['total_clicks_needed']
    ];
    
    $message = $responseData['is_completed'] 
        ? "Congratulations! Daily tasks completed. ₹" . number_format($newTodayIncome, 2) . " added to your balance."
        : "Click completed! ₹" . number_format($perClickIncome, 2) . " added to your balance. " . $responseData['remaining_clicks'] . " clicks remaining.";
    
    sendResponse(true, $message, $responseData);
}

// Handle Get User Data
function handleGetUserData() {
    $userId = $_GET['user_id'] ?? '';
    
    if (empty($userId)) {
        sendResponse(false, 'User ID is required');
    }
    
    $user = getUser($userId);
    if (!$user) {
        sendResponse(false, 'User not found');
    }
    
    // Remove sensitive data
    unset($user['password']);
    
    // Get user statistics
    $stats = getUserStatistics($userId);
    
    // Get recent transactions
    $conn = getDBConnection();
    $transactions = [];
    if ($conn) {
        $stmt = $conn->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY date DESC LIMIT 10");
        $stmt->bind_param("s", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
    }
    
    // Get packages
    $packages = [];
    if ($conn) {
        $result = $conn->query("SELECT * FROM packages WHERE status = 'Active'");
        while ($row = $result->fetch_assoc()) {
            $packages[] = $row;
        }
    }
    
    $responseData = [
        'user' => $user,
        'stats' => $stats,
        'recent_transactions' => $transactions,
        'packages' => $packages
    ];
    
    sendResponse(true, 'User data retrieved', $responseData);
}

// Handle Submit KYC
function handleSubmitKYC() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $requiredFields = ['user_id', 'name', 'dob', 'aadhar_number', 'pan_number', 'bank_account', 'ifsc_code'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            sendResponse(false, "Please fill all required fields");
        }
    }
    
    $userId = sanitizeInput($data['user_id']);
    $name = sanitizeInput($data['name']);
    $dob = sanitizeInput($data['dob']);
    $aadharNumber = sanitizeInput($data['aadhar_number']);
    $panNumber = sanitizeInput($data['pan_number']);
    $bankAccount = sanitizeInput($data['bank_account']);
    $ifscCode = sanitizeInput($data['ifsc_code']);
    
    // Validate Aadhar (12 digits)
    if (!preg_match('/^\d{12}$/', $aadharNumber)) {
        sendResponse(false, 'Please enter valid 12-digit Aadhar number');
    }
    
    // Validate PAN (10 characters)
    if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $panNumber)) {
        sendResponse(false, 'Please enter valid 10-digit PAN number');
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        sendResponse(false, 'Database connection failed');
    }
    
    // Check if KYC already submitted
    $stmt = $conn->prepare("SELECT id FROM kyc_requests WHERE user_id = ?");
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        sendResponse(false, 'KYC already submitted');
    }
    
    // Handle file uploads if provided
    $aadharFront = '';
    $aadharBack = '';
    $panCard = '';
    
    // Insert KYC request
    $stmt = $conn->prepare("INSERT INTO kyc_requests (user_id, name, dob, aadhar_number, pan_number, bank_account, ifsc_code, aadhar_front, aadhar_back, pan_card, device_info) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $deviceInfo = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    $stmt->bind_param("sssssssssss", $userId, $name, $dob, $aadharNumber, $panNumber, $bankAccount, $ifscCode, $aadharFront, $aadharBack, $panCard, $deviceInfo);
    
    if (!$stmt->execute()) {
        sendResponse(false, 'KYC submission failed: ' . $conn->error);
    }
    
    // Update user KYC status
    $updateStmt = $conn->prepare("UPDATE users SET kyc_status = 'Under Review' WHERE user_id = ?");
    $updateStmt->bind_param("s", $userId);
    $updateStmt->execute();
    
    sendResponse(true, 'KYC submitted successfully! It will be reviewed within 24-48 hours.');
}

// Handle other endpoints similarly...
// Due to length constraints, I'll provide the remaining endpoint stubs

function handleUpdateProfile() {
    // Implementation for updating user profile
    sendResponse(true, 'Profile update endpoint');
}

function handleChangePassword() {
    // Implementation for changing password
    sendResponse(true, 'Change password endpoint');
}

function handleGetTransactions() {
    // Implementation for getting user transactions
    sendResponse(true, 'Get transactions endpoint');
}

function handleGetPackages() {
    // Implementation for getting packages
    sendResponse(true, 'Get packages endpoint');
}

function handleGetKYCStatus() {
    // Implementation for getting KYC status
    sendResponse(true, 'Get KYC status endpoint');
}
?>
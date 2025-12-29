<?php
require_once 'config.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => '', 'data' => []];

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Get endpoint from URL
$endpoint = isset($_GET['action']) ? $_GET['action'] : '';

switch ($method) {
    case 'POST':
        handlePostRequest($endpoint, $conn, $response);
        break;
    case 'GET':
        handleGetRequest($endpoint, $conn, $response);
        break;
    default:
        $response['message'] = 'Method not allowed';
        http_response_code(405);
}

echo json_encode($response);
$conn->close();

function handlePostRequest($endpoint, $conn, &$response) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    switch ($endpoint) {
        case 'user_login':
            userLogin($data, $conn, $response);
            break;
        case 'admin_login':
            adminLogin($data, $conn, $response);
            break;
        case 'user_register':
            userRegister($data, $conn, $response);
            break;
        case 'update_profile':
            updateProfile($data, $conn, $response);
            break;
        case 'change_password':
            changePassword($data, $conn, $response);
            break;
        case 'submit_kyc':
            submitKYC($data, $conn, $response);
            break;
        case 'add_fund':
            addFund($data, $conn, $response);
            break;
        case 'process_withdrawal':
            processWithdrawal($data, $conn, $response);
            break;
        case 'activate_package':
            activatePackage($data, $conn, $response);
            break;
        case 'complete_task':
            completeTask($data, $conn, $response);
            break;
        case 'admin_approve_deposit':
            adminApproveDeposit($data, $conn, $response);
            break;
        case 'admin_approve_withdrawal':
            adminApproveWithdrawal($data, $conn, $response);
            break;
        case 'admin_approve_kyc':
            adminApproveKYC($data, $conn, $response);
            break;
        case 'admin_add_user':
            adminAddUser($data, $conn, $response);
            break;
        case 'admin_update_settings':
            adminUpdateSettings($data, $conn, $response);
            break;
        default:
            $response['message'] = 'Invalid endpoint';
    }
}

function handleGetRequest($endpoint, $conn, &$response) {
    switch ($endpoint) {
        case 'get_user_dashboard':
            getDashboardData($conn, $response);
            break;
        case 'get_user_transactions':
            getUserTransactions($conn, $response);
            break;
        case 'get_packages':
            getPackages($conn, $response);
            break;
        case 'get_activation_history':
            getActivationHistory($conn, $response);
            break;
        case 'get_daily_tasks':
            getDailyTasks($conn, $response);
            break;
        case 'get_admin_dashboard':
            getAdminDashboard($conn, $response);
            break;
        case 'get_all_users':
            getAllUsers($conn, $response);
            break;
        case 'get_pending_requests':
            getPendingRequests($conn, $response);
            break;
        case 'get_all_transactions':
            getAllTransactions($conn, $response);
            break;
        default:
            $response['message'] = 'Invalid endpoint';
    }
}

// User Login Function
function userLogin($data, $conn, &$response) {
    if (!isset($data['user_id']) || !isset($data['password'])) {
        $response['message'] = 'User ID and password required';
        return;
    }
    
    $user_id = $conn->real_escape_string($data['user_id']);
    $password = md5($data['password']);
    
    $sql = "SELECT * FROM users WHERE user_id = ? AND password = ? AND status = 'active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $user_id, $password);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Update last login
        $update_sql = "UPDATE users SET last_login = NOW() WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("s", $user_id);
        $update_stmt->execute();
        
        // Set session
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['user_type'] = 'user';
        $_SESSION['user_name'] = $user['name'];
        
        // Log activity
        logActivity($user['user_id'], 'user', 'Login', 'User logged in', $conn);
        
        $response['success'] = true;
        $response['message'] = 'Login successful';
        $response['data'] = [
            'user_id' => $user['user_id'],
            'name' => $user['name'],
            'balance' => $user['balance'],
            'fund' => $user['fund'],
            'package' => $user['current_package']
        ];
    } else {
        $response['message'] = 'Invalid credentials or account blocked';
    }
}

// Admin Login Function
function adminLogin($data, $conn, &$response) {
    if (!isset($data['admin_id']) || !isset($data['password'])) {
        $response['message'] = 'Admin ID and password required';
        return;
    }
    
    $admin_id = $conn->real_escape_string($data['admin_id']);
    $password = md5($data['password']);
    
    $sql = "SELECT * FROM admins WHERE admin_id = ? AND password = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $admin_id, $password);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        
        // Update last login
        $update_sql = "UPDATE admins SET last_login = NOW(), ip_address = ? WHERE admin_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $ip = $_SERVER['REMOTE_ADDR'];
        $update_stmt->bind_param("ss", $ip, $admin_id);
        $update_stmt->execute();
        
        // Set session
        $_SESSION['user_id'] = $admin['admin_id'];
        $_SESSION['user_type'] = 'admin';
        $_SESSION['user_name'] = $admin['name'];
        
        // Log activity
        logActivity($admin['admin_id'], 'admin', 'Login', 'Admin logged in', $conn);
        
        $response['success'] = true;
        $response['message'] = 'Admin login successful';
        $response['data'] = [
            'admin_id' => $admin['admin_id'],
            'name' => $admin['name']
        ];
    } else {
        $response['message'] = 'Invalid admin credentials';
    }
}

// User Registration
function userRegister($data, $conn, &$response) {
    $required = ['name', 'email', 'mobile', 'password'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            $response['message'] = "Field $field is required";
            return;
        }
    }
    
    // Generate unique user ID
    $user_id = generateUniqueID('USER', $conn);
    
    $name = $conn->real_escape_string($data['name']);
    $email = $conn->real_escape_string($data['email']);
    $mobile = $conn->real_escape_string($data['mobile']);
    $password = md5($data['password']);
    $sponsor_id = isset($data['sponsor_id']) ? $conn->real_escape_string($data['sponsor_id']) : 'CAPITAL01';
    
    // Check if email already exists
    $check_sql = "SELECT id FROM users WHERE email = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        $response['message'] = 'Email already registered';
        return;
    }
    
    // Check if sponsor exists
    if ($sponsor_id != 'CAPITAL01') {
        $sponsor_sql = "SELECT id FROM users WHERE user_id = ?";
        $sponsor_stmt = $conn->prepare($sponsor_sql);
        $sponsor_stmt->bind_param("s", $sponsor_id);
        $sponsor_stmt->execute();
        
        if ($sponsor_stmt->get_result()->num_rows == 0) {
            $response['message'] = 'Sponsor ID not found';
            return;
        }
    }
    
    // Insert user
    $sql = "INSERT INTO users (user_id, name, email, mobile, password, sponsor_id, balance, created_by, device_info, ip_address) 
            VALUES (?, ?, ?, ?, ?, ?, 50, 'Self Registration', ?, ?)";
    $stmt = $conn->prepare($sql);
    $device_info = $_SERVER['HTTP_USER_AGENT'];
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt->bind_param("ssssssss", $user_id, $name, $email, $mobile, $password, $sponsor_id, $device_info, $ip_address);
    
    if ($stmt->execute()) {
        // Add registration bonus transaction
        $trans_id = 'TRN' . time() . mt_rand(100, 999);
        $trans_sql = "INSERT INTO transactions (transaction_id, user_id, type, amount, status, description, payment_method) 
                     VALUES (?, ?, 'kyc_bonus', 50, 'completed', 'Registration bonus', 'system')";
        $trans_stmt = $conn->prepare($trans_sql);
        $trans_stmt->bind_param("ss", $trans_id, $user_id);
        $trans_stmt->execute();
        
        // Add referral if sponsor exists
        if ($sponsor_id != 'CAPITAL01') {
            // Update sponsor's referral count
            $update_sponsor = "UPDATE users SET referrals = referrals + 1 WHERE user_id = ?";
            $update_stmt = $conn->prepare($update_sponsor);
            $update_stmt->bind_param("s", $sponsor_id);
            $update_stmt->execute();
            
            // Add referral record
            $ref_sql = "INSERT INTO referrals (sponsor_id, referral_id, commission_amount) VALUES (?, ?, 10)";
            $ref_stmt = $conn->prepare($ref_sql);
            $ref_stmt->bind_param("ss", $sponsor_id, $user_id);
            $ref_stmt->execute();
            
            // Add commission to sponsor
            $commission_sql = "UPDATE users SET balance = balance + 10, referral_income = referral_income + 10 WHERE user_id = ?";
            $commission_stmt = $conn->prepare($commission_sql);
            $commission_stmt->bind_param("s", $sponsor_id);
            $commission_stmt->execute();
            
            // Log commission transaction
            $com_trans_id = 'COM' . time() . mt_rand(100, 999);
            $com_sql = "INSERT INTO transactions (transaction_id, user_id, type, amount, status, description) 
                       VALUES (?, ?, 'referral_commission', 10, 'completed', 'Referral commission for $user_id')";
            $com_stmt = $conn->prepare($com_sql);
            $com_stmt->bind_param("ss", $com_trans_id, $sponsor_id);
            $com_stmt->execute();
        }
        
        // Log activity
        logActivity($user_id, 'user', 'Registration', 'New user registered', $conn);
        
        $response['success'] = true;
        $response['message'] = 'Registration successful';
        $response['data'] = [
            'user_id' => $user_id,
            'name' => $name,
            'sponsor_id' => $sponsor_id,
            'balance' => 50
        ];
    } else {
        $response['message'] = 'Registration failed: ' . $conn->error;
    }
}

// Activate Package
function activatePackage($data, $conn, &$response) {
    if (!isUserLoggedIn()) {
        $response['message'] = 'Please login first';
        return;
    }
    
    $user_id = $_SESSION['user_id'];
    $package_name = $conn->real_escape_string($data['package_name']);
    $amount = floatval($data['amount']);
    
    // Get user current balance
    $user_sql = "SELECT balance FROM users WHERE user_id = ?";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->bind_param("s", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user = $user_result->fetch_assoc();
    
    if ($user['balance'] < $amount) {
        $response['message'] = 'Insufficient balance';
        return;
    }
    
    // Get package details
    $pkg_sql = "SELECT * FROM packages WHERE package_name = ?";
    $pkg_stmt = $conn->prepare($pkg_sql);
    $pkg_stmt->bind_param("s", $package_name);
    $pkg_stmt->execute();
    $pkg_result = $pkg_stmt->get_result();
    
    if ($pkg_result->num_rows == 0) {
        $response['message'] = 'Invalid package';
        return;
    }
    
    $package = $pkg_result->fetch_assoc();
    
    // Calculate expiry date
    $expiry_date = date('Y-m-d', strtotime("+{$package['validity_days']} days"));
    $daily_income = ($amount * $package['daily_task_percent']) / 100;
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Deduct balance
        $update_balance = "UPDATE users SET balance = balance - ?, fund = fund + ?, current_package = ? WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_balance);
        $update_stmt->bind_param("ddss", $amount, $amount, $package_name, $user_id);
        $update_stmt->execute();
        
        // Add package record
        $pkg_insert = "INSERT INTO user_packages (user_id, package_name, amount, expiry_date, daily_income) 
                      VALUES (?, ?, ?, ?, ?)";
        $pkg_insert_stmt = $conn->prepare($pkg_insert);
        $pkg_insert_stmt->bind_param("ssdss", $user_id, $package_name, $amount, $expiry_date, $daily_income);
        $pkg_insert_stmt->execute();
        
        // Record transaction
        $trans_id = 'PKG' . time() . mt_rand(100, 999);
        $trans_sql = "INSERT INTO transactions (transaction_id, user_id, type, amount, status, description) 
                     VALUES (?, ?, 'package_purchase', ?, 'completed', 'Package activation: $package_name')";
        $trans_stmt = $conn->prepare($trans_sql);
        $trans_stmt->bind_param("ssd", $trans_id, $user_id, $amount);
        $trans_stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        // Log activity
        logActivity($user_id, 'user', 'Package Activation', "Activated $package_name package", $conn);
        
        $response['success'] = true;
        $response['message'] = 'Package activated successfully';
        $response['data'] = [
            'package' => $package_name,
            'amount' => $amount,
            'expiry_date' => $expiry_date,
            'daily_income' => $daily_income
        ];
        
    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = 'Transaction failed: ' . $e->getMessage();
    }
}

// Complete Daily Task
function completeTask($data, $conn, &$response) {
    if (!isUserLoggedIn()) {
        $response['message'] = 'Please login first';
        return;
    }
    
    $user_id = $_SESSION['user_id'];
    $today = date('Y-m-d');
    
    // Check if user has active package
    $user_sql = "SELECT u.fund, p.daily_task_percent FROM users u 
                LEFT JOIN user_packages p ON u.user_id = p.user_id 
                WHERE u.user_id = ? AND p.status = 'active' AND p.expiry_date >= ?";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->bind_param("ss", $user_id, $today);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    
    if ($user_result->num_rows == 0) {
        $response['message'] = 'No active package found';
        return;
    }
    
    $user_data = $user_result->fetch_assoc();
    $fund = $user_data['fund'];
    $daily_percent = $user_data['daily_task_percent'];
    
    // Calculate per click income
    $daily_income = ($fund * $daily_percent) / 100;
    $per_click_income = $daily_income / 15;
    
    // Get today's task progress
    $task_sql = "SELECT * FROM daily_tasks WHERE user_id = ? AND task_date = ?";
    $task_stmt = $conn->prepare($task_sql);
    $task_stmt->bind_param("ss", $user_id, $today);
    $task_stmt->execute();
    $task_result = $task_stmt->get_result();
    
    $conn->begin_transaction();
    
    try {
        if ($task_result->num_rows > 0) {
            $task = $task_result->fetch_assoc();
            $clicks_completed = $task['clicks_completed'];
            
            if ($clicks_completed >= 15) {
                $response['message'] = 'Daily tasks already completed';
                return;
            }
            
            // Update task
            $update_task = "UPDATE daily_tasks SET clicks_completed = clicks_completed + 1, 
                           total_income = total_income + ? WHERE user_id = ? AND task_date = ?";
            $update_stmt = $conn->prepare($update_task);
            $update_stmt->bind_param("dss", $per_click_income, $user_id, $today);
            $update_stmt->execute();
            
        } else {
            // Create new task record
            $insert_task = "INSERT INTO daily_tasks (user_id, task_date, clicks_completed, total_income) 
                           VALUES (?, ?, 1, ?)";
            $insert_stmt = $conn->prepare($insert_task);
            $insert_stmt->bind_param("ssd", $user_id, $today, $per_click_income);
            $insert_stmt->execute();
        }
        
        // Update user balance
        $update_user = "UPDATE users SET balance = balance + ?, total_income = total_income + ?, 
                       today_income = today_income + ? WHERE user_id = ?";
        $update_user_stmt = $conn->prepare($update_user);
        $update_user_stmt->bind_param("ddds", $per_click_income, $per_click_income, $per_click_income, $user_id);
        $update_user_stmt->execute();
        
        // Log task income
        $task_log = "INSERT INTO task_income_log (user_id, task_date, click_number, amount) 
                    VALUES (?, ?, ?, ?)";
        $log_stmt = $conn->prepare($task_log);
        
        // Get current click number
        $current_click = $task_result->num_rows > 0 ? $task['clicks_completed'] + 1 : 1;
        $log_stmt->bind_param("ssid", $user_id, $today, $current_click, $per_click_income);
        $log_stmt->execute();
        
        // Record transaction
        $trans_id = 'TASK' . time() . mt_rand(100, 999);
        $trans_sql = "INSERT INTO transactions (transaction_id, user_id, type, amount, status, description) 
                     VALUES (?, ?, 'task_income', ?, 'completed', 'Daily task click #$current_click')";
        $trans_stmt = $conn->prepare($trans_sql);
        $trans_stmt->bind_param("ssd", $trans_id, $user_id, $per_click_income);
        $trans_stmt->execute();
        
        $conn->commit();
        
        // Log activity
        logActivity($user_id, 'user', 'Task Completed', "Completed click #$current_click", $conn);
        
        $response['success'] = true;
        $response['message'] = 'Task completed successfully';
        $response['data'] = [
            'per_click_income' => $per_click_income,
            'current_click' => $current_click,
            'remaining_clicks' => 15 - $current_click
        ];
        
    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = 'Task completion failed: ' . $e->getMessage();
    }
}

// Admin Dashboard Data
function getAdminDashboard($conn, &$response) {
    if (!isAdminLoggedIn()) {
        $response['message'] = 'Admin access required';
        return;
    }
    
    $today = date('Y-m-d');
    
    // Get stats
    $stats = [];
    
    // Total users
    $sql = "SELECT COUNT(*) as total_users, 
                   SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users,
                   SUM(CASE WHEN DATE(join_date) = CURDATE() THEN 1 ELSE 0 END) as today_users
            FROM users";
    $result = $conn->query($sql);
    $stats = $result->fetch_assoc();
    
    // Total deposits
    $deposit_sql = "SELECT 
                   SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as total_deposits,
                   SUM(CASE WHEN status = 'approved' AND DATE(created_at) = CURDATE() THEN amount ELSE 0 END) as today_deposits
                   FROM transactions WHERE type = 'deposit'";
    $deposit_result = $conn->query($deposit_sql);
    $deposit_stats = $deposit_result->fetch_assoc();
    
    // Total withdrawals
    $withdrawal_sql = "SELECT 
                      SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as total_withdrawals,
                      SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_withdrawals
                      FROM withdrawal_requests";
    $withdrawal_result = $conn->query($withdrawal_sql);
    $withdrawal_stats = $withdrawal_result->fetch_assoc();
    
    // Total investments
    $investment_sql = "SELECT SUM(amount) as total_investments FROM user_packages WHERE status = 'active'";
    $investment_result = $conn->query($investment_sql);
    $investment_stats = $investment_result->fetch_assoc();
    
    // Pending requests
    $pending_sql = "SELECT 
                   (SELECT COUNT(*) FROM transactions WHERE status = 'pending' AND type = 'deposit') as pending_deposits,
                   (SELECT COUNT(*) FROM withdrawal_requests WHERE status = 'pending') as pending_withdrawals,
                   (SELECT COUNT(*) FROM kyc_submissions WHERE status = 'pending') as pending_kyc";
    $pending_result = $conn->query($pending_sql);
    $pending_stats = $pending_result->fetch_assoc();
    
    // Recent activity
    $activity_sql = "SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 10";
    $activity_result = $conn->query($activity_sql);
    $recent_activity = [];
    
    while ($row = $activity_result->fetch_assoc()) {
        $recent_activity[] = $row;
    }
    
    $response['success'] = true;
    $response['data'] = [
        'stats' => array_merge($stats, $deposit_stats, $withdrawal_stats, $investment_stats, $pending_stats),
        'recent_activity' => $recent_activity
    ];
}

// Get all users for admin
function getAllUsers($conn, &$response) {
    if (!isAdminLoggedIn()) {
        $response['message'] = 'Admin access required';
        return;
    }
    
    $sql = "SELECT user_id, name, email, mobile, balance, current_package, status, kyc_status, 
                   join_date, last_login, sponsor_id, fund
            FROM users ORDER BY join_date DESC";
    $result = $conn->query($sql);
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    $response['success'] = true;
    $response['data'] = $users;
}

// Get pending requests
function getPendingRequests($conn, &$response) {
    if (!isAdminLoggedIn()) {
        $response['message'] = 'Admin access required';
        return;
    }
    
    $data = [];
    
    // Pending deposits
    $deposit_sql = "SELECT t.*, u.name FROM transactions t 
                   JOIN users u ON t.user_id = u.user_id
                   WHERE t.status = 'pending' AND t.type = 'deposit'";
    $deposit_result = $conn->query($deposit_sql);
    
    $data['deposits'] = [];
    while ($row = $deposit_result->fetch_assoc()) {
        $data['deposits'][] = $row;
    }
    
    // Pending withdrawals
    $withdrawal_sql = "SELECT w.*, u.name FROM withdrawal_requests w 
                      JOIN users u ON w.user_id = u.user_id
                      WHERE w.status = 'pending'";
    $withdrawal_result = $conn->query($withdrawal_sql);
    
    $data['withdrawals'] = [];
    while ($row = $withdrawal_result->fetch_assoc()) {
        $data['withdrawals'][] = $row;
    }
    
    // Pending KYC
    $kyc_sql = "SELECT k.*, u.name, u.email, u.mobile FROM kyc_submissions k 
               JOIN users u ON k.user_id = u.user_id
               WHERE k.status = 'pending'";
    $kyc_result = $conn->query($kyc_sql);
    
    $data['kyc'] = [];
    while ($row = $kyc_result->fetch_assoc()) {
        $data['kyc'][] = $row;
    }
    
    $response['success'] = true;
    $response['data'] = $data;
}

// Get all transactions
function getAllTransactions($conn, &$response) {
    if (!isAdminLoggedIn()) {
        $response['message'] = 'Admin access required';
        return;
    }
    
    $sql = "SELECT t.*, u.name FROM transactions t 
           LEFT JOIN users u ON t.user_id = u.user_id
           ORDER BY t.created_at DESC LIMIT 100";
    $result = $conn->query($sql);
    
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
    
    $response['success'] = true;
    $response['data'] = $transactions;
}

// Admin approve deposit
function adminApproveDeposit($data, $conn, &$response) {
    if (!isAdminLoggedIn()) {
        $response['message'] = 'Admin access required';
        return;
    }
    
    $admin_id = $_SESSION['user_id'];
    $transaction_id = $conn->real_escape_string($data['transaction_id']);
    
    $conn->begin_transaction();
    
    try {
        // Get transaction details
        $sql = "SELECT * FROM transactions WHERE transaction_id = ? AND status = 'pending'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $transaction_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            throw new Exception('Transaction not found or already processed');
        }
        
        $transaction = $result->fetch_assoc();
        $user_id = $transaction['user_id'];
        $amount = $transaction['amount'];
        
        // Update transaction status
        $update_sql = "UPDATE transactions SET status = 'approved', 
                      approved_by = ?, approved_at = NOW() WHERE transaction_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ss", $admin_id, $transaction_id);
        $update_stmt->execute();
        
        // Update user balance
        $user_sql = "UPDATE users SET balance = balance + ? WHERE user_id = ?";
        $user_stmt = $conn->prepare($user_sql);
        $user_stmt->bind_param("ds", $amount, $user_id);
        $user_stmt->execute();
        
        $conn->commit();
        
        // Log activity
        logActivity($admin_id, 'admin', 'Deposit Approved', "Approved deposit for $user_id - ₹$amount", $conn);
        
        $response['success'] = true;
        $response['message'] = 'Deposit approved successfully';
        
    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = 'Approval failed: ' . $e->getMessage();
    }
}

// Admin approve withdrawal
function adminApproveWithdrawal($data, $conn, &$response) {
    if (!isAdminLoggedIn()) {
        $response['message'] = 'Admin access required';
        return;
    }
    
    $admin_id = $_SESSION['user_id'];
    $request_id = $conn->real_escape_string($data['request_id']);
    $transaction_id = $conn->real_escape_string($data['bank_transaction_id']);
    
    $conn->begin_transaction();
    
    try {
        // Get withdrawal request
        $sql = "SELECT * FROM withdrawal_requests WHERE request_id = ? AND status = 'pending'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            throw new Exception('Withdrawal request not found or already processed');
        }
        
        $withdrawal = $result->fetch_assoc();
        $user_id = $withdrawal['user_id'];
        $amount = $withdrawal['amount'];
        $fee = $withdrawal['fee'];
        
        // Update withdrawal status
        $update_sql = "UPDATE withdrawal_requests SET status = 'approved', 
                      processed_at = NOW(), processed_by = ?, transaction_id = ? WHERE request_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sss", $admin_id, $transaction_id, $request_id);
        $update_stmt->execute();
        
        // Record transaction
        $trans_sql = "INSERT INTO transactions (transaction_id, user_id, type, amount, status, description, approved_by, approved_at) 
                     VALUES (?, ?, 'withdrawal', ?, 'approved', 'Withdrawal processed', ?, NOW())";
        $trans_stmt = $conn->prepare($trans_sql);
        $trans_stmt->bind_param("ssdss", $request_id, $user_id, $amount, $admin_id, $admin_id);
        $trans_stmt->execute();
        
        $conn->commit();
        
        // Log activity
        logActivity($admin_id, 'admin', 'Withdrawal Approved', "Approved withdrawal for $user_id - ₹$amount", $conn);
        
        $response['success'] = true;
        $response['message'] = 'Withdrawal approved successfully';
        
    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = 'Approval failed: ' . $e->getMessage();
    }
}

// Admin approve KYC
function adminApproveKYC($data, $conn, &$response) {
    if (!isAdminLoggedIn()) {
        $response['message'] = 'Admin access required';
        return;
    }
    
    $admin_id = $_SESSION['user_id'];
    $user_id = $conn->real_escape_string($data['user_id']);
    $status = $conn->real_escape_string($data['status']);
    $reason = isset($data['reason']) ? $conn->real_escape_string($data['reason']) : '';
    
    $conn->begin_transaction();
    
    try {
        if ($status == 'verified') {
            // Update KYC status
            $sql = "UPDATE kyc_submissions SET status = 'verified', 
                   verified_by = ?, verified_at = NOW() WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $admin_id, $user_id);
            $stmt->execute();
            
            // Update user KYC status
            $user_sql = "UPDATE users SET kyc_status = 'approved' WHERE user_id = ?";
            $user_stmt = $conn->prepare($user_sql);
            $user_stmt->bind_param("s", $user_id);
            $user_stmt->execute();
            
            // Add KYC bonus
            $bonus_sql = "UPDATE users SET balance = balance + 50, total_income = total_income + 50 WHERE user_id = ?";
            $bonus_stmt = $conn->prepare($bonus_sql);
            $bonus_stmt->bind_param("s", $user_id);
            $bonus_stmt->execute();
            
            // Record bonus transaction
            $trans_id = 'KYC' . time() . mt_rand(100, 999);
            $trans_sql = "INSERT INTO transactions (transaction_id, user_id, type, amount, status, description) 
                         VALUES (?, ?, 'kyc_bonus', 50, 'completed', 'KYC verification bonus')";
            $trans_stmt = $conn->prepare($trans_sql);
            $trans_stmt->bind_param("ss", $trans_id, $user_id);
            $trans_stmt->execute();
            
            $message = 'KYC verified successfully with ₹50 bonus';
            
        } else if ($status == 'rejected') {
            // Update KYC status
            $sql = "UPDATE kyc_submissions SET status = 'rejected', 
                   verified_by = ?, verified_at = NOW(), rejection_reason = ? WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $admin_id, $reason, $user_id);
            $stmt->execute();
            
            // Update user KYC status
            $user_sql = "UPDATE users SET kyc_status = 'rejected' WHERE user_id = ?";
            $user_stmt = $conn->prepare($user_sql);
            $user_stmt->bind_param("s", $user_id);
            $user_stmt->execute();
            
            $message = 'KYC rejected';
        } else {
            throw new Exception('Invalid status');
        }
        
        $conn->commit();
        
        // Log activity
        logActivity($admin_id, 'admin', 'KYC ' . ucfirst($status), "KYC $status for $user_id", $conn);
        
        $response['success'] = true;
        $response['message'] = $message;
        
    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = 'KYC processing failed: ' . $e->getMessage();
    }
}

// Admin add user
function adminAddUser($data, $conn, &$response) {
    if (!isAdminLoggedIn()) {
        $response['message'] = 'Admin access required';
        return;
    }
    
    $admin_id = $_SESSION['user_id'];
    
    // Similar to userRegister but with admin privileges
    // ... (implementation similar to userRegister with admin specific fields)
}

// Admin update settings
function adminUpdateSettings($data, $conn, &$response) {
    if (!isAdminLoggedIn()) {
        $response['message'] = 'Admin access required';
        return;
    }
    
    $admin_id = $_SESSION['user_id'];
    
    foreach ($data as $key => $value) {
        $sql = "INSERT INTO system_settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $key, $value, $value);
        $stmt->execute();
    }
    
    // Log activity
    logActivity($admin_id, 'admin', 'Settings Updated', 'Updated system settings', $conn);
    
    $response['success'] = true;
    $response['message'] = 'Settings updated successfully';
}
?>
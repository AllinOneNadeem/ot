<?php
// ============================================================
// AUTHENTICATION - Login & Register
// ============================================================
// This file handles all authentication functions
// ============================================================

// ============================================================
// LOGIN FUNCTION
// ============================================================

/**
 * Handle user login
 * @param object $pdo - PDO database connection
 * @param string $email - User email
 * @param string $password - User password
 * @return array - Response with success status and message
 */
function loginUser($pdo, $email, $password) {
    try {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id, email, password, name, role, balance, banned FROM Users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        // Check if user is banned
        if ($user['banned'] == 1) {
            return ['success' => false, 'message' => 'Your account has been banned. Please contact support.'];
        }

        // Verify password
        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Invalid password'];
        }

        // Update last active time
        $stmt = $pdo->prepare("UPDATE Users SET last_active = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);

        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'] ?? 'User';
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_balance'] = $user['balance'];

        return [
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'balance' => $user['balance']
            ]
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

// ============================================================
// REGISTER FUNCTION
// ============================================================

/**
 * Handle user registration
 * @param object $pdo - PDO database connection
 * @param string $name - User name
 * @param string $email - User email
 * @param string $password - User password
 * @return array - Response with success status and message
 */
function registerUser($pdo, $name, $email, $password) {
    try {
        // Validate inputs
        if (empty($name) || empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'All fields are required'];
        }

        if (strlen($password) < 6) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }

        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM Users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email already registered'];
        }

        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Insert user
        $stmt = $pdo->prepare("INSERT INTO Users (name, email, password, role, balance, createdAt) VALUES (?, ?, ?, 'user', 0, NOW())");
        $stmt->execute([$name, $email, $hashedPassword]);

        $userId = $pdo->lastInsertId();

        // Set session
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_role'] = 'user';
        $_SESSION['user_balance'] = 0;

        return [
            'success' => true,
            'message' => 'Registration successful',
            'user' => [
                'id' => $userId,
                'name' => $name,
                'email' => $email,
                'role' => 'user',
                'balance' => 0
            ]
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

// ============================================================
// LOGOUT FUNCTION
// ============================================================

/**
 * Handle user logout
 */
function logoutUser() {
    // Unset all session variables
    $_SESSION = array();

    // Destroy the session
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();
    return ['success' => true, 'message' => 'Logged out successfully'];
}

// ============================================================
// SESSION CHECK FUNCTIONS
// ============================================================

/**
 * Check if user is logged in and redirect if not
 * @param object $pdo - PDO database connection
 * @param string $redirectUrl - URL to redirect to if not logged in
 */
function requireLogin($pdo, $redirectUrl = 'index.php') {
    if (!isset($_SESSION['user_id'])) {
        header("Location: $redirectUrl");
        exit();
    }

    // Verify user still exists
    $stmt = $pdo->prepare("SELECT id FROM Users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        session_destroy();
        header("Location: $redirectUrl");
        exit();
    }
}

/**
 * Check if user is admin and redirect if not
 * @param object $pdo - PDO database connection
 * @param string $redirectUrl - URL to redirect to if not admin
 */
function requireAdmin($pdo, $redirectUrl = 'index.php') {
    requireLogin($pdo, $redirectUrl);

    $stmt = $pdo->prepare("SELECT role FROM Users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || $user['role'] !== 'admin') {
        header("Location: $redirectUrl");
        exit();
    }
}

// ============================================================
// AJAX HANDLERS FOR LOGIN/REGISTER
// ============================================================

// If this file is called directly with action parameter
if (isset($_GET['action']) || isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    if ($action === 'login') {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        echo json_encode(loginUser($pdo, $email, $password));
        exit();
    }
    
    if ($action === 'register') {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        echo json_encode(registerUser($pdo, $name, $email, $password));
        exit();
    }
    
    if ($action === 'logout') {
        echo json_encode(logoutUser());
        exit();
    }
}
?>
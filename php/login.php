<?php
// User Login

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$data = getRequestBody();

if ($data === null) {
    sendResponse(['success' => false, 'message' => 'Invalid request data'], 400);
}

$action = $data['action'] ?? 'login';

switch ($action) {
    case 'login':
        handleLogin($data);
        break;
    case 'validate_session':
        handleValidateSession($data);
        break;
    case 'logout':
        handleLogout($data);
        break;
    default:
        sendResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function handleLogin($data)
{
    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';

    $errors = [];

    if (empty($username)) {
        $errors['username'] = 'Username or email is required';
    }

    if (empty($password)) {
        $errors['password'] = 'Password is required';
    }

    if (!empty($errors)) {
        sendResponse(['success' => false, 'message' => 'Validation failed', 'errors' => $errors], 400);
    }

    $pdo = getMySQLConnection();

    if ($pdo === null) {
        sendResponse(['success' => false, 'message' => 'Database connection failed'], 500);
    }

    try {
        // Find user by username or email
        $stmt = $pdo->prepare("
            SELECT id, first_name, last_name, email, username, password 
            FROM users 
            WHERE username = :username OR email = :email
        ");

        $stmt->execute([
            ':username' => $username,
            ':email' => $username
        ]);

        $user = $stmt->fetch();

        if (!$user) {
            sendResponse([
                'success' => false,
                'message' => 'Invalid username or password'
            ], 401);
        }

        if (!verifyPassword($password, $user['password'])) {
            sendResponse([
                'success' => false,
                'message' => 'Invalid username or password'
            ], 401);
        }

        $token = generateToken();

        // Store session in Redis
        $sessionData = [
            'userId' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'firstName' => $user['first_name'],
            'lastName' => $user['last_name'],
            'loginTime' => date('c'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];

        $sessionStored = storeSession($token, $sessionData);

        if (!$sessionStored) {
            error_log("Warning: Failed to store session in Redis");
        }

        // Update last login
        $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
        $updateStmt->execute([':id' => $user['id']]);

        sendResponse([
            'success' => true,
            'message' => 'Login successful',
            'token' => $token,
            'userId' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'firstName' => $user['first_name'],
            'lastName' => $user['last_name']
        ]);

    } catch (PDOException $e) {
        error_log("Login Error: " . $e->getMessage());
        sendResponse(['success' => false, 'message' => 'Login failed. Please try again.'], 500);
    }
}

function handleValidateSession($data)
{
    $token = $data['token'] ?? '';

    if (empty($token)) {
        sendResponse(['success' => false, 'valid' => false, 'message' => 'No token provided'], 401);
    }

    $session = validateSession($token);

    if ($session === null) {
        sendResponse(['success' => false, 'valid' => false, 'message' => 'Invalid or expired session'], 401);
    }

    sendResponse([
        'success' => true,
        'valid' => true,
        'userId' => $session['userId'],
        'username' => $session['username']
    ]);
}

function handleLogout($data)
{
    $token = $data['token'] ?? '';

    if (!empty($token)) {
        deleteSession($token);
    }

    sendResponse([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
}

<?php
// User Registration

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$data = getRequestBody();

if ($data === null) {
    sendResponse(['success' => false, 'message' => 'Invalid request data'], 400);
}

$firstName = trim($data['firstName'] ?? '');
$lastName = trim($data['lastName'] ?? '');
$email = trim($data['email'] ?? '');
$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';

$errors = [];

if (empty($firstName)) {
    $errors['firstName'] = 'First name is required';
} elseif (strlen($firstName) < 2) {
    $errors['firstName'] = 'First name must be at least 2 characters';
}

if (empty($lastName)) {
    $errors['lastName'] = 'Last name is required';
} elseif (strlen($lastName) < 2) {
    $errors['lastName'] = 'Last name must be at least 2 characters';
}

if (empty($email)) {
    $errors['email'] = 'Email is required';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address';
}

if (empty($username)) {
    $errors['username'] = 'Username is required';
} elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
    $errors['username'] = 'Username must be 3-20 characters (letters, numbers, underscore only)';
}

if (empty($password)) {
    $errors['password'] = 'Password is required';
} elseif (strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters';
}

if (!empty($errors)) {
    sendResponse(['success' => false, 'message' => 'Validation failed', 'errors' => $errors], 400);
}

$pdo = getMySQLConnection();

if ($pdo === null) {
    sendResponse(['success' => false, 'message' => 'Database connection failed'], 500);
}

try {
    // Check if email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);

    if ($stmt->fetch()) {
        sendResponse([
            'success' => false,
            'message' => 'Email already registered',
            'errors' => ['email' => 'This email is already registered']
        ], 400);
    }

    // Check if username exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
    $stmt->execute([':username' => $username]);

    if ($stmt->fetch()) {
        sendResponse([
            'success' => false,
            'message' => 'Username already taken',
            'errors' => ['username' => 'This username is already taken']
        ], 400);
    }

    $hashedPassword = hashPassword($password);

    // Insert user
    $stmt = $pdo->prepare("
        INSERT INTO users (first_name, last_name, email, username, password, created_at, updated_at) 
        VALUES (:first_name, :last_name, :email, :username, :password, NOW(), NOW())
    ");

    $stmt->execute([
        ':first_name' => $firstName,
        ':last_name' => $lastName,
        ':email' => $email,
        ':username' => $username,
        ':password' => $hashedPassword
    ]);

    $userId = $pdo->lastInsertId();

    // Create profile in MongoDB
    try {
        $profileData = [
            'user_id' => (int) $userId,
            'username' => $username,
            'email' => $email,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'age' => null,
            'dob' => null,
            'contact' => null,
            'address' => null,
            'city' => null,
            'country' => null,
            'bio' => null,
            'createdAt' => date('c'),
            'updatedAt' => date('c')
        ];
        mongoInsert($profileData);
    } catch (Exception $e) {
        error_log("MongoDB Profile Creation Error: " . $e->getMessage());
    }

    sendResponse([
        'success' => true,
        'message' => 'Registration successful! Please login to continue.',
        'userId' => $userId
    ], 201);

} catch (PDOException $e) {
    error_log("Registration Error: " . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'Registration failed. Please try again.'], 500);
}

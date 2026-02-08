<?php
// User Profile

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$data = getRequestBody();

if ($data === null) {
    sendResponse(['success' => false, 'message' => 'Invalid request data'], 400);
}

$action = $data['action'] ?? '';

switch ($action) {
    case 'validate_session':
        handleValidateSession($data);
        break;
    case 'get_profile':
        handleGetProfile($data);
        break;
    case 'update_profile':
        handleUpdateProfile($data);
        break;
    default:
        sendResponse(['success' => false, 'message' => 'Invalid action'], 400);
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

function handleGetProfile($data)
{
    $token = $data['token'] ?? '';

    if (empty($token)) {
        sendResponse(['success' => false, 'message' => 'No token provided'], 401);
    }

    $session = validateSession($token);

    if ($session === null) {
        sendResponse(['success' => false, 'message' => 'Invalid or expired session'], 401);
    }

    $userId = $session['userId'];

    // Try MongoDB first
    try {
        $profile = mongoFind(['user_id' => (int) $userId]);

        if ($profile) {
            $profileData = [
                'userId' => $userId,
                'username' => $profile['username'] ?? $session['username'],
                'email' => $profile['email'] ?? $session['email'],
                'firstName' => $profile['firstName'] ?? '',
                'lastName' => $profile['lastName'] ?? '',
                'age' => $profile['age'] ?? null,
                'dob' => $profile['dob'] ?? null,
                'contact' => $profile['contact'] ?? null,
                'address' => $profile['address'] ?? null,
                'city' => $profile['city'] ?? null,
                'country' => $profile['country'] ?? null,
                'bio' => $profile['bio'] ?? null,
                'createdAt' => $profile['createdAt'] ?? null,
                'updatedAt' => $profile['updatedAt'] ?? null
            ];

            sendResponse([
                'success' => true,
                'data' => $profileData
            ]);
        }
    } catch (Exception $e) {
        error_log("MongoDB Get Profile Error: " . $e->getMessage());
    }

    // Fallback to MySQL
    $pdo = getMySQLConnection();

    if ($pdo !== null) {
        try {
            $stmt = $pdo->prepare("
                SELECT id, first_name, last_name, email, username, created_at, updated_at 
                FROM users 
                WHERE id = :id
            ");

            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch();

            if ($user) {
                sendResponse([
                    'success' => true,
                    'data' => [
                        'userId' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'firstName' => $user['first_name'],
                        'lastName' => $user['last_name'],
                        'age' => null,
                        'dob' => null,
                        'contact' => null,
                        'address' => null,
                        'city' => null,
                        'country' => null,
                        'bio' => null,
                        'createdAt' => $user['created_at'],
                        'updatedAt' => $user['updated_at']
                    ]
                ]);
            }
        } catch (PDOException $e) {
            error_log("MySQL Get Profile Error: " . $e->getMessage());
        }
    }

    // Return session data as fallback
    sendResponse([
        'success' => true,
        'data' => [
            'userId' => $session['userId'],
            'username' => $session['username'],
            'email' => $session['email'],
            'firstName' => $session['firstName'] ?? '',
            'lastName' => $session['lastName'] ?? '',
            'age' => null,
            'dob' => null,
            'contact' => null,
            'address' => null,
            'city' => null,
            'country' => null,
            'bio' => null,
            'createdAt' => $session['loginTime'],
            'updatedAt' => null
        ]
    ]);
}

function handleUpdateProfile($data)
{
    $token = $data['token'] ?? '';

    if (empty($token)) {
        sendResponse(['success' => false, 'message' => 'No token provided'], 401);
    }

    $session = validateSession($token);

    if ($session === null) {
        sendResponse(['success' => false, 'message' => 'Invalid or expired session'], 401);
    }

    $userId = $session['userId'];

    $errors = [];

    $firstName = trim($data['firstName'] ?? '');
    $lastName = trim($data['lastName'] ?? '');
    $age = $data['age'] ?? null;
    $dob = $data['dob'] ?? null;
    $contact = trim($data['contact'] ?? '');
    $address = trim($data['address'] ?? '');
    $city = trim($data['city'] ?? '');
    $country = trim($data['country'] ?? '');
    $bio = trim($data['bio'] ?? '');

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

    if ($age !== null && $age !== '') {
        $age = (int) $age;
        if ($age < 1 || $age > 150) {
            $errors['age'] = 'Please enter a valid age';
        }
    } else {
        $age = null;
    }

    if (!empty($contact)) {
        if (!preg_match('/^[\d\s\-\+\(\)]{10,20}$/', $contact)) {
            $errors['contact'] = 'Please enter a valid contact number';
        }
    }

    if (!empty($errors)) {
        sendResponse(['success' => false, 'message' => 'Validation failed', 'errors' => $errors], 400);
    }

    // Update MongoDB
    $mongoUpdated = false;

    try {
        $updateData = [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'age' => $age,
            'dob' => $dob,
            'contact' => $contact,
            'address' => $address,
            'city' => $city,
            'country' => $country,
            'bio' => $bio,
            'updatedAt' => date('c')
        ];

        $mongoUpdated = mongoUpdate(['user_id' => (int) $userId], $updateData, true);

    } catch (Exception $e) {
        error_log("MongoDB Update Profile Error: " . $e->getMessage());
    }

    // Update MySQL
    $pdo = getMySQLConnection();
    $mysqlUpdated = false;

    if ($pdo !== null) {
        try {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET first_name = :first_name, last_name = :last_name, updated_at = NOW() 
                WHERE id = :id
            ");

            $stmt->execute([
                ':first_name' => $firstName,
                ':last_name' => $lastName,
                ':id' => $userId
            ]);

            $mysqlUpdated = ($stmt->rowCount() > 0);

        } catch (PDOException $e) {
            error_log("MySQL Update Profile Error: " . $e->getMessage());
        }
    }

    if ($mongoUpdated || $mysqlUpdated) {
        sendResponse([
            'success' => true,
            'message' => 'Profile updated successfully'
        ]);
    } else {
        sendResponse([
            'success' => true,
            'message' => 'Profile saved'
        ]);
    }
}

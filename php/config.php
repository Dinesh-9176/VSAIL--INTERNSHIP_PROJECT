<?php
// Database Configuration

if (!defined('VSAIL_APP')) {
    define('VSAIL_APP', true);
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// MySQL Settings
define('MYSQL_HOST', 'localhost');
define('MYSQL_PORT', '3306');
define('MYSQL_DATABASE', 'vsail_db');
define('MYSQL_USERNAME', 'root');
define('MYSQL_PASSWORD', '');
define('MYSQL_CHARSET', 'utf8mb4');

// MongoDB Settings
define('MONGODB_HOST', 'localhost');
define('MONGODB_PORT', '27017');
define('MONGODB_DATABASE', 'vsail_db');
define('MONGODB_COLLECTION', 'user_profiles');

// Redis Settings
define('REDIS_HOST', '127.0.0.1');
define('REDIS_PORT', 6379);
define('REDIS_PASSWORD', null);
define('REDIS_SESSION_PREFIX', 'vsail_session:');
define('SESSION_EXPIRY', 86400);

// MySQL Connection
function getMySQLConnection()
{
    try {
        $dsn = "mysql:host=" . MYSQL_HOST . ";port=" . MYSQL_PORT . ";dbname=" . MYSQL_DATABASE . ";charset=" . MYSQL_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($dsn, MYSQL_USERNAME, MYSQL_PASSWORD, $options);
        return $pdo;
    } catch (PDOException $e) {
        error_log("MySQL Connection Error: " . $e->getMessage());
        return null;
    }
}

// MongoDB Connection
function getMongoDBManager()
{
    try {
        if (!extension_loaded('mongodb')) {
            error_log("MongoDB extension not loaded");
            return null;
        }
        $uri = "mongodb://" . MONGODB_HOST . ":" . MONGODB_PORT;
        $manager = new MongoDB\Driver\Manager($uri);
        return $manager;
    } catch (Exception $e) {
        error_log("MongoDB Connection Error: " . $e->getMessage());
        return null;
    }
}

// MongoDB Insert
function mongoInsert($document)
{
    try {
        $manager = getMongoDBManager();
        if ($manager === null)
            return false;

        $bulk = new MongoDB\Driver\BulkWrite();
        $bulk->insert($document);
        $namespace = MONGODB_DATABASE . '.' . MONGODB_COLLECTION;
        $manager->executeBulkWrite($namespace, $bulk);
        return true;
    } catch (Exception $e) {
        error_log("MongoDB Insert Error: " . $e->getMessage());
        return false;
    }
}

// MongoDB Find
function mongoFind($filter)
{
    try {
        $manager = getMongoDBManager();
        if ($manager === null)
            return null;

        $query = new MongoDB\Driver\Query($filter, ['limit' => 1]);
        $namespace = MONGODB_DATABASE . '.' . MONGODB_COLLECTION;
        $cursor = $manager->executeQuery($namespace, $query);
        $documents = $cursor->toArray();

        if (count($documents) > 0) {
            return (array) $documents[0];
        }
        return null;
    } catch (Exception $e) {
        error_log("MongoDB Find Error: " . $e->getMessage());
        return null;
    }
}

// MongoDB Update
function mongoUpdate($filter, $update, $upsert = false)
{
    try {
        $manager = getMongoDBManager();
        if ($manager === null)
            return false;

        $bulk = new MongoDB\Driver\BulkWrite();
        $bulk->update($filter, ['$set' => $update], ['upsert' => $upsert]);
        $namespace = MONGODB_DATABASE . '.' . MONGODB_COLLECTION;
        $result = $manager->executeBulkWrite($namespace, $bulk);

        return ($result->getModifiedCount() > 0 || $result->getUpsertedCount() > 0);
    } catch (Exception $e) {
        error_log("MongoDB Update Error: " . $e->getMessage());
        return false;
    }
}

function getMongoDBCollection()
{
    return getMongoDBManager();
}

// Redis Connection
function getRedisConnection()
{
    try {
        if (!extension_loaded('redis')) {
            error_log("Redis extension not loaded");
            return null;
        }
        $redis = new Redis();
        $redis->connect(REDIS_HOST, REDIS_PORT);
        if (REDIS_PASSWORD) {
            $redis->auth(REDIS_PASSWORD);
        }
        return $redis;
    } catch (Exception $e) {
        error_log("Redis Connection Error: " . $e->getMessage());
        return null;
    }
}

// Generate Token
function generateToken($length = 64)
{
    return bin2hex(random_bytes($length / 2));
}

// Store Session
function storeSession($token, $data)
{
    $redis = getRedisConnection();
    if ($redis === null) {
        error_log("Failed to store session: Redis not available");
        return false;
    }
    try {
        $key = REDIS_SESSION_PREFIX . $token;
        $value = json_encode($data);
        $redis->setex($key, SESSION_EXPIRY, $value);
        $redis->close();
        return true;
    } catch (Exception $e) {
        error_log("Redis Store Session Error: " . $e->getMessage());
        return false;
    }
}

// Get Session
function getSession($token)
{
    $redis = getRedisConnection();
    if ($redis === null) {
        return null;
    }
    try {
        $key = REDIS_SESSION_PREFIX . $token;
        $value = $redis->get($key);
        $redis->close();
        if ($value === false) {
            return null;
        }
        return json_decode($value, true);
    } catch (Exception $e) {
        error_log("Redis Get Session Error: " . $e->getMessage());
        return null;
    }
}

// Delete Session
function deleteSession($token)
{
    $redis = getRedisConnection();
    if ($redis === null) {
        return false;
    }
    try {
        $key = REDIS_SESSION_PREFIX . $token;
        $redis->del($key);
        $redis->close();
        return true;
    } catch (Exception $e) {
        error_log("Redis Delete Session Error: " . $e->getMessage());
        return false;
    }
}

// Validate Session
function validateSession($token)
{
    if (empty($token)) {
        return null;
    }
    return getSession($token);
}

// Send Response
function sendResponse($data, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

// Get Request Body
function getRequestBody()
{
    $input = file_get_contents('php://input');
    if (empty($input)) {
        return null;
    }
    return json_decode($input, true);
}

// Hash Password
function hashPassword($password)
{
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

// Verify Password
function verifyPassword($password, $hash)
{
    return password_verify($password, $hash);
}

<?php
// K
// POST /api/auth/login.php
// Body (JSON):
//   - email     (string, required)
//   - password  (string, required)

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validation.php';

requireMethod('POST');

$data = getJsonBody();

// Validate required fields
$errors = validateRequired($data, ['email', 'password']);
if (!empty($errors)) {
    jsonError('Validation failed.', 422, $errors);
}

$email    = strtolower(trim($data['email']));
$password = $data['password'];

// Validate email format
if (!validateEmail($email)) {
    jsonError('Invalid email address.', 422);
}

$db = getDB();

// Fetch user by email
$stmt = $db->prepare('
    SELECT id, email, password_hash, role, first_name, last_name, is_active
    FROM users
    WHERE email = :email
');
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();

if (!$user) {
    jsonError('Invalid email or password.', 401);
}

// Check if account is active
if (!$user['is_active']) {
    jsonError('This account has been deactivated.', 403);
}

// Verify password
if (!password_verify($password, $user['password_hash'])) {
    jsonError('Invalid email or password.', 401);
}

// Set session
$_SESSION['user_id']    = (int) $user['id'];
$_SESSION['email']      = $user['email'];
$_SESSION['role']       = $user['role'];
$_SESSION['first_name'] = $user['first_name'];
$_SESSION['last_name']  = $user['last_name'];

jsonSuccess([
    'user_id'    => (int) $user['id'],
    'email'      => $user['email'],
    'role'       => $user['role'],
    'first_name' => $user['first_name'],
    'last_name'  => $user['last_name'],
], 'Login successful.');

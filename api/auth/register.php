<?php
// ============================================================
// KONEKT — User Registration
// ============================================================
// POST /api/auth/register.php
//
// Body (JSON):
//   - first_name  (string, required)
//   - last_name   (string, required)
//   - email       (string, required)
//   - password    (string, required, min 8 chars)
//   - role        (string, required: 'job_seeker' or 'employer')
// ============================================================

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validation.php';

requireMethod('POST');

$data = getJsonBody();

// --- Validate required fields ---
$errors = validateRequired($data, ['first_name', 'last_name', 'email', 'password', 'role']);
if (!empty($errors)) {
    jsonError('Validation failed.', 422, $errors);
}

$firstName = sanitizeString($data['first_name']);
$lastName  = sanitizeString($data['last_name']);
$email     = strtolower(trim($data['email']));
$password  = $data['password'];
$role      = $data['role'];

// --- Validate email format ---
if (!validateEmail($email)) {
    jsonError('Invalid email address.', 422, ['Invalid email format.']);
}

// --- Validate name length ---
if (!validateLength($firstName, 1, 100) || !validateLength($lastName, 1, 100)) {
    jsonError('Name must be between 1 and 100 characters.', 422);
}

// --- Validate password strength ---
$passwordErrors = validatePassword($password);
if (!empty($passwordErrors)) {
    jsonError('Password does not meet requirements.', 422, $passwordErrors);
}

// --- Validate role ---
if (!validateEnum($role, ['job_seeker', 'employer'])) {
    jsonError('Invalid role. Must be "job_seeker" or "employer".', 422);
}

$db = getDB();

// --- Check if email already exists ---
$stmt = $db->prepare('SELECT id FROM users WHERE email = :email');
$stmt->execute([':email' => $email]);

if ($stmt->fetch()) {
    jsonError('An account with this email already exists.', 409);
}

// --- Hash password ---
$passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// --- Insert user ---
try {
    $db->beginTransaction();

    $stmt = $db->prepare('
        INSERT INTO users (email, password_hash, role, first_name, last_name)
        VALUES (:email, :password_hash, :role, :first_name, :last_name)
    ');
    $stmt->execute([
        ':email'         => $email,
        ':password_hash' => $passwordHash,
        ':role'          => $role,
        ':first_name'    => $firstName,
        ':last_name'     => $lastName,
    ]);

    $userId = (int) $db->lastInsertId();

    // --- Create an empty profile row ---
    $stmt = $db->prepare('INSERT INTO profiles (user_id) VALUES (:user_id)');
    $stmt->execute([':user_id' => $userId]);

    $db->commit();

    // --- Set session ---
    $_SESSION['user_id']    = $userId;
    $_SESSION['email']      = $email;
    $_SESSION['role']       = $role;
    $_SESSION['first_name'] = $firstName;
    $_SESSION['last_name']  = $lastName;

    jsonSuccess([
        'user_id'    => $userId,
        'email'      => $email,
        'role'       => $role,
        'first_name' => $firstName,
        'last_name'  => $lastName,
    ], 'Registration successful.', 201);

} catch (PDOException $e) {
    $db->rollBack();
    jsonError('Registration failed: ' . $e->getMessage(), 500);
}

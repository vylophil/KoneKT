<?php
// ============================================================
// KONEKT — Validation Helper
// ============================================================
// Input validation functions for API endpoints.
// ============================================================

/**
 * Validate that required fields are present and non-empty.
 *
 * @param array  $data     Input data
 * @param array  $fields   Required field names
 * @return array           Array of error messages (empty if valid)
 */
function validateRequired(array $data, array $fields): array
{
    $errors = [];
    foreach ($fields as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
            $errors[] = "The field '{$field}' is required.";
        }
    }
    return $errors;
}

/**
 * Validate email format.
 *
 * @param string $email
 * @return bool
 */
function validateEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate string length within a range.
 *
 * @param string $value
 * @param int    $min   Minimum length
 * @param int    $max   Maximum length
 * @return bool
 */
function validateLength(string $value, int $min, int $max): bool
{
    $len = mb_strlen($value, 'UTF-8');
    return $len >= $min && $len <= $max;
}

/**
 * Validate password strength.
 * Requires at least 8 characters, one uppercase, one lowercase, one digit.
 *
 * @param string $password
 * @return array  Array of error messages (empty if valid)
 */
function validatePassword(string $password): array
{
    $errors = [];

    if (mb_strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one digit.';
    }

    return $errors;
}

/**
 * Validate that a value is in an allowed set.
 *
 * @param string $value
 * @param array  $allowed
 * @return bool
 */
function validateEnum(string $value, array $allowed): bool
{
    return in_array($value, $allowed, true);
}

/**
 * Validate a date string (Y-m-d format).
 *
 * @param string $date
 * @return bool
 */
function validateDate(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * Validate that a value is a positive integer.
 *
 * @param mixed $value
 * @return bool
 */
function validatePositiveInt($value): bool
{
    return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false;
}

/**
 * Sanitize a string by trimming whitespace and stripping tags.
 *
 * @param string $value
 * @return string
 */
function sanitizeString(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate file upload.
 *
 * @param array $file         The $_FILES entry
 * @param array $allowedTypes MIME types allowed (e.g., ['application/pdf'])
 * @param int   $maxSizeMB    Max file size in megabytes
 * @return array              Array of error messages (empty if valid)
 */
function validateFileUpload(array $file, array $allowedTypes, int $maxSizeMB = 5): array
{
    $errors = [];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload failed with error code: ' . $file['error'];
        return $errors;
    }

    if (!in_array($file['type'], $allowedTypes, true)) {
        $errors[] = 'Invalid file type. Allowed: ' . implode(', ', $allowedTypes);
    }

    $maxBytes = $maxSizeMB * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        $errors[] = "File size exceeds the {$maxSizeMB}MB limit.";
    }

    return $errors;
}

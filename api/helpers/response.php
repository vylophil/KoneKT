<?php
// K
// Provides consistent JSON API responses with CORS support.

// Send a JSON success response.
function jsonSuccess($data = null, string $message = 'Success', int $code = 200): void
{
    setCorsHeaders();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');

    $response = [
        'success' => true,
        'message' => $message,
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// Send a JSON error response.
function jsonError(string $message = 'An error occurred', int $code = 400, array $errors = []): void
{
    setCorsHeaders();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');

    $response = [
        'success' => false,
        'message' => $message,
    ];

    if (!empty($errors)) {
        $response['errors'] = $errors;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// Set CORS headers for cross-origin requests.
function setCorsHeaders(): void
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// Require specific HTTP method(s), return 405 if not matched.
function requireMethod($methods): void
{
    if (is_string($methods)) {
        $methods = [$methods];
    }

    // Always allow OPTIONS for CORS preflight
    $methods[] = 'OPTIONS';

    if (!in_array($_SERVER['REQUEST_METHOD'], $methods, true)) {
        jsonError('Method not allowed. Allowed: ' . implode(', ', $methods), 405);
    }
}

// Get the JSON body from the request.
function getJsonBody(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        jsonError('Invalid JSON in request body', 400);
    }

    return $data ?? [];
}

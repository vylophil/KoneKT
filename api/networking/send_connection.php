<?php
// ============================================================
// KONEKT — Send Connection Request
// ============================================================
// POST /api/networking/send_connection.php
//
// Body (JSON):
//   - receiver_id (int, required)
//   - message     (string, optional — personal note with request)
// ============================================================

require_once __DIR__ . '/../auth/session.php';

requireMethod('POST');
$user = requireAuth();
$data = getJsonBody();

if (!isset($data['receiver_id']) || !validatePositiveInt($data['receiver_id'])) {
    jsonError('Valid receiver_id is required.', 422);
}

$receiverId = (int) $data['receiver_id'];

// --- Can't connect with yourself ---
if ($receiverId === $user['id']) {
    jsonError('You cannot send a connection request to yourself.', 400);
}

$db = getDB();

// --- Verify receiver exists ---
$stmt = $db->prepare('SELECT id FROM users WHERE id = :id AND is_active = 1');
$stmt->execute([':id' => $receiverId]);

if (!$stmt->fetch()) {
    jsonError('User not found.', 404);
}

// --- Check existing connection (in either direction) ---
$stmt = $db->prepare('
    SELECT id, status FROM connections
    WHERE (requester_id = :uid1 AND receiver_id = :uid2)
       OR (requester_id = :uid2b AND receiver_id = :uid1b)
');
$stmt->execute([
    ':uid1'  => $user['id'],
    ':uid2'  => $receiverId,
    ':uid2b' => $receiverId,
    ':uid1b' => $user['id'],
]);
$existing = $stmt->fetch();

if ($existing) {
    switch ($existing['status']) {
        case 'accepted':
            jsonError('You are already connected with this user.', 409);
            break;
        case 'pending':
            jsonError('A connection request is already pending.', 409);
            break;
        case 'blocked':
            jsonError('This connection is blocked.', 403);
            break;
        case 'rejected':
            // Allow re-sending after rejection — update the existing record
            $stmt = $db->prepare('
                UPDATE connections
                SET requester_id = :requester_id, receiver_id = :receiver_id,
                    status = "pending", message = :message, updated_at = NOW()
                WHERE id = :id
            ');
            $stmt->execute([
                ':requester_id' => $user['id'],
                ':receiver_id'  => $receiverId,
                ':message'      => isset($data['message']) ? sanitizeString($data['message']) : null,
                ':id'           => $existing['id'],
            ]);
            jsonSuccess(null, 'Connection request re-sent.', 201);
            break;
    }
}

// --- Create new connection request ---
$stmt = $db->prepare('
    INSERT INTO connections (requester_id, receiver_id, message)
    VALUES (:requester_id, :receiver_id, :message)
');
$stmt->execute([
    ':requester_id' => $user['id'],
    ':receiver_id'  => $receiverId,
    ':message'      => isset($data['message']) ? sanitizeString($data['message']) : null,
]);

$connectionId = (int) $db->lastInsertId();

jsonSuccess(['connection_id' => $connectionId], 'Connection request sent.', 201);

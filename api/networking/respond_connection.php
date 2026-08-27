<?php
// K
// PUT /api/networking/respond_connection.php
// Body (JSON):
//   - connection_id (int, required)
//   - action        (string, required: 'accept' | 'reject')

require_once __DIR__ . '/../auth/session.php';

requireMethod('PUT');
$user = requireAuth();
$data = getJsonBody();

$errors = validateRequired($data, ['connection_id', 'action']);
if (!empty($errors)) {
    jsonError('Validation failed.', 422, $errors);
}

$connectionId = (int) $data['connection_id'];
$action       = $data['action'];

if (!validateEnum($action, ['accept', 'reject'])) {
    jsonError('Invalid action. Must be "accept" or "reject".', 422);
}

$db = getDB();

// Verify the request was sent TO this user and is pending
$stmt = $db->prepare('
    SELECT id, requester_id FROM connections
    WHERE id = :id AND receiver_id = :receiver_id AND status = :status
');
$stmt->execute([':id' => $connectionId, ':receiver_id' => $user['id'], ':status' => 'pending']);
$connection = $stmt->fetch();

if (!$connection) {
    jsonError('Connection request not found or already responded to.', 404);
}

$newStatus = ($action === 'accept') ? 'accepted' : 'rejected';

$stmt = $db->prepare('UPDATE connections SET status = :status WHERE id = :id');
$stmt->execute([':status' => $newStatus, ':id' => $connectionId]);

$message = ($action === 'accept')
    ? 'Connection request accepted.'
    : 'Connection request rejected.';

jsonSuccess(null, $message);

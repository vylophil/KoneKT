<?php
// ============================================================
// KONEKT — Send Message
// ============================================================
// POST /api/networking/send_message.php
//
// Body (JSON):
//   - receiver_id (int, required)
//   - content     (string, required)
//
// Users must be connected (accepted) to send messages.
// ============================================================

require_once __DIR__ . '/../auth/session.php';

requireMethod('POST');
$user = requireAuth();
$data = getJsonBody();

$errors = validateRequired($data, ['receiver_id', 'content']);
if (!empty($errors)) {
    jsonError('Validation failed.', 422, $errors);
}

$receiverId = (int) $data['receiver_id'];
$content    = sanitizeString($data['content']);

if ($receiverId === $user['id']) {
    jsonError('You cannot message yourself.', 400);
}

if (mb_strlen($content) === 0) {
    jsonError('Message content cannot be empty.', 422);
}

$db = getDB();

// --- Verify connection exists and is accepted ---
$stmt = $db->prepare('
    SELECT id FROM connections
    WHERE status = "accepted"
    AND (
        (requester_id = :uid1 AND receiver_id = :uid2)
        OR (requester_id = :uid2b AND receiver_id = :uid1b)
    )
');
$stmt->execute([
    ':uid1'  => $user['id'],
    ':uid2'  => $receiverId,
    ':uid2b' => $receiverId,
    ':uid1b' => $user['id'],
]);

if (!$stmt->fetch()) {
    jsonError('You must be connected with this user to send messages.', 403);
}

// --- Insert message ---
$stmt = $db->prepare('
    INSERT INTO messages (sender_id, receiver_id, content)
    VALUES (:sender_id, :receiver_id, :content)
');
$stmt->execute([
    ':sender_id'   => $user['id'],
    ':receiver_id' => $receiverId,
    ':content'     => $content,
]);

$messageId = (int) $db->lastInsertId();

jsonSuccess([
    'message_id' => $messageId,
    'sent_at'    => date('Y-m-d H:i:s'),
], 'Message sent.', 201);

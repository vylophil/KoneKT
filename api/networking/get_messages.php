<?php
// K
// GET /api/networking/get_messages.php?user_id=<id>
// Returns the message thread between the authenticated user
// and the specified user_id.
// Query params:
//   - user_id (int, required — the other user in conversation)
//   - page    (int, optional, default 1)
//   - limit   (int, optional, default 50)
// Also marks unread messages from the other user as read.

require_once __DIR__ . '/../auth/session.php';

requireMethod('GET');
$user = requireAuth();

$otherUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

if (!$otherUserId) {
    jsonError('user_id is required.', 400);
}

$db    = getDB();
$page  = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
$offset = ($page - 1) * $limit;

// Count total messages in conversation
$stmt = $db->prepare('
    SELECT COUNT(*) FROM messages
    WHERE (sender_id = :uid1 AND receiver_id = :uid2)
       OR (sender_id = :uid2b AND receiver_id = :uid1b)
');
$stmt->execute([
    ':uid1'  => $user['id'],
    ':uid2'  => $otherUserId,
    ':uid2b' => $otherUserId,
    ':uid1b' => $user['id'],
]);
$total = (int) $stmt->fetchColumn();

// Fetch messages
$stmt = $db->prepare('
    SELECT
        m.id, m.sender_id, m.receiver_id, m.content, m.is_read, m.sent_at,
        u.first_name AS sender_first_name, u.last_name AS sender_last_name
    FROM messages m
    JOIN users u ON u.id = m.sender_id
    WHERE (m.sender_id = :uid1 AND m.receiver_id = :uid2)
       OR (m.sender_id = :uid2b AND m.receiver_id = :uid1b)
    ORDER BY m.sent_at DESC
    LIMIT :limit OFFSET :offset
');
$stmt->bindValue(':uid1', $user['id'], PDO::PARAM_INT);
$stmt->bindValue(':uid2', $otherUserId, PDO::PARAM_INT);
$stmt->bindValue(':uid2b', $otherUserId, PDO::PARAM_INT);
$stmt->bindValue(':uid1b', $user['id'], PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$messages = $stmt->fetchAll();

// Mark messages from the other user as read
$stmt = $db->prepare('
    UPDATE messages SET is_read = 1
    WHERE sender_id = :sender_id AND receiver_id = :receiver_id AND is_read = 0
');
$stmt->execute([':sender_id' => $otherUserId, ':receiver_id' => $user['id']]);

// Get other user's info
$stmt = $db->prepare('
    SELECT u.id, u.first_name, u.last_name, p.headline, p.avatar_url
    FROM users u
    LEFT JOIN profiles p ON p.user_id = u.id
    WHERE u.id = :id
');
$stmt->execute([':id' => $otherUserId]);
$otherUser = $stmt->fetch();

jsonSuccess([
    'other_user' => $otherUser,
    'messages'   => $messages,
    'pagination' => [
        'page'        => $page,
        'limit'       => $limit,
        'total'       => $total,
        'total_pages' => ceil($total / $limit),
    ],
], 'Messages retrieved successfully.');

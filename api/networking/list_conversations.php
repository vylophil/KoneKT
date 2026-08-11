<?php
// ============================================================
// KONEKT — List Conversations
// ============================================================
// GET /api/networking/list_conversations.php
//
// Returns a list of all conversations for the authenticated user,
// showing the most recent message and unread count per conversation.
//
// Query params:
//   - page  (int, optional, default 1)
//   - limit (int, optional, default 20)
// ============================================================

require_once __DIR__ . '/../auth/session.php';

requireMethod('GET');
$user = requireAuth();

$db     = getDB();
$page   = max(1, (int) ($_GET['page'] ?? 1));
$limit  = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

// --- Get unique conversation partners with latest message ---
$sql = "
    SELECT
        other_user_id,
        u.first_name, u.last_name,
        p.headline, p.avatar_url,
        latest.content AS last_message,
        latest.sent_at AS last_message_at,
        latest.sender_id AS last_sender_id,
        (
            SELECT COUNT(*) FROM messages m2
            WHERE m2.sender_id = other_user_id
            AND m2.receiver_id = :me_unread
            AND m2.is_read = 0
        ) AS unread_count
    FROM (
        SELECT
            IF(sender_id = :me1, receiver_id, sender_id) AS other_user_id,
            MAX(id) AS last_msg_id
        FROM messages
        WHERE sender_id = :me2 OR receiver_id = :me3
        GROUP BY other_user_id
        ORDER BY last_msg_id DESC
        LIMIT :limit OFFSET :offset
    ) AS convos
    JOIN messages latest ON latest.id = convos.last_msg_id
    JOIN users u ON u.id = convos.other_user_id
    LEFT JOIN profiles p ON p.user_id = convos.other_user_id
    ORDER BY latest.sent_at DESC
";

$stmt = $db->prepare($sql);
$stmt->bindValue(':me1', $user['id'], PDO::PARAM_INT);
$stmt->bindValue(':me2', $user['id'], PDO::PARAM_INT);
$stmt->bindValue(':me3', $user['id'], PDO::PARAM_INT);
$stmt->bindValue(':me_unread', $user['id'], PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$conversations = $stmt->fetchAll();

// --- Total conversation count ---
$stmt = $db->prepare('
    SELECT COUNT(DISTINCT IF(sender_id = :me1, receiver_id, sender_id))
    FROM messages
    WHERE sender_id = :me2 OR receiver_id = :me3
');
$stmt->execute([':me1' => $user['id'], ':me2' => $user['id'], ':me3' => $user['id']]);
$total = (int) $stmt->fetchColumn();

// --- Total unread messages ---
$stmt = $db->prepare('
    SELECT COUNT(*) FROM messages WHERE receiver_id = :me AND is_read = 0
');
$stmt->execute([':me' => $user['id']]);
$totalUnread = (int) $stmt->fetchColumn();

jsonSuccess([
    'conversations' => $conversations,
    'total_unread'  => $totalUnread,
    'pagination'    => [
        'page'        => $page,
        'limit'       => $limit,
        'total'       => $total,
        'total_pages' => ceil($total / $limit),
    ],
], 'Conversations retrieved successfully.');

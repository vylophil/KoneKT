<?php
// ============================================================
// KONEKT — List Connections
// ============================================================
// GET /api/networking/list_connections.php
//
// Query params:
//   - status  (string, optional: pending|accepted — default: accepted)
//   - type    (string, optional: sent|received|all — default: all)
//   - page    (int, optional, default 1)
//   - limit   (int, optional, default 20)
// ============================================================

require_once __DIR__ . '/../auth/session.php';

requireMethod('GET');
$user = requireAuth();

$db     = getDB();
$status = $_GET['status'] ?? 'accepted';
$type   = $_GET['type'] ?? 'all';
$page   = max(1, (int) ($_GET['page'] ?? 1));
$limit  = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$where  = ['c.status = :status'];
$params = [':status' => $status];

if ($type === 'sent') {
    $where[]               = 'c.requester_id = :user_id';
    $params[':user_id']    = $user['id'];
} elseif ($type === 'received') {
    $where[]               = 'c.receiver_id = :user_id';
    $params[':user_id']    = $user['id'];
} else {
    $where[]               = '(c.requester_id = :user_id1 OR c.receiver_id = :user_id2)';
    $params[':user_id1']   = $user['id'];
    $params[':user_id2']   = $user['id'];
}

$whereClause = implode(' AND ', $where);

// --- Count ---
$stmt = $db->prepare("SELECT COUNT(*) FROM connections c WHERE {$whereClause}");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();

// --- Fetch with connected user info ---
$sql = "
    SELECT
        c.id AS connection_id, c.requester_id, c.receiver_id, c.status,
        c.message AS connection_message, c.created_at, c.updated_at,
        u.id AS connected_user_id, u.first_name, u.last_name, u.email, u.role,
        p.headline, p.location, p.avatar_url, p.industry
    FROM connections c
    JOIN users u ON u.id = IF(c.requester_id = :me1, c.receiver_id, c.requester_id)
    LEFT JOIN profiles p ON p.user_id = u.id
    WHERE {$whereClause}
    ORDER BY c.updated_at DESC
    LIMIT :limit OFFSET :offset
";

$allParams = array_merge($params, [':me1' => $user['id']]);

$stmt = $db->prepare($sql);
foreach ($allParams as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$connections = $stmt->fetchAll();

jsonSuccess([
    'connections' => $connections,
    'pagination'  => [
        'page'        => $page,
        'limit'       => $limit,
        'total'       => $total,
        'total_pages' => ceil($total / $limit),
    ],
], 'Connections retrieved successfully.');

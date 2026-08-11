<?php
// ============================================================
// KONEKT — Search People / Discover Profiles
// ============================================================
// GET /api/networking/search_people.php
//
// Query params:
//   - search    (string, optional — search by name, headline)
//   - skill_ids (comma-separated int, optional)
//   - industry  (string, optional)
//   - location  (string, optional)
//   - role      (string, optional: job_seeker|employer)
//   - page      (int, optional, default 1)
//   - limit     (int, optional, default 20)
// ============================================================

require_once __DIR__ . '/../auth/session.php';

requireMethod('GET');
$user = requireAuth();

$db       = getDB();
$search   = isset($_GET['search']) ? trim($_GET['search']) : '';
$skillIds = isset($_GET['skill_ids']) ? $_GET['skill_ids'] : '';
$industry = isset($_GET['industry']) ? trim($_GET['industry']) : '';
$location = isset($_GET['location']) ? trim($_GET['location']) : '';
$role     = $_GET['role'] ?? '';
$page     = max(1, (int) ($_GET['page'] ?? 1));
$limit    = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
$offset   = ($page - 1) * $limit;

$where  = ['u.is_active = 1', 'u.id != :current_user_id'];
$params = [':current_user_id' => $user['id']];
$joins  = 'LEFT JOIN profiles p ON p.user_id = u.id';

if ($search !== '') {
    $where[]            = '(CONCAT(u.first_name, " ", u.last_name) LIKE :search OR p.headline LIKE :search2)';
    $params[':search']  = "%{$search}%";
    $params[':search2'] = "%{$search}%";
}

if ($role !== '') {
    $where[]          = 'u.role = :role';
    $params[':role']  = $role;
}

if ($industry !== '') {
    $where[]               = 'p.industry LIKE :industry';
    $params[':industry']   = "%{$industry}%";
}

if ($location !== '') {
    $where[]               = 'p.location LIKE :location';
    $params[':location']   = "%{$location}%";
}

if ($skillIds !== '') {
    $skillIdArray = array_filter(array_map('intval', explode(',', $skillIds)));
    if (!empty($skillIdArray)) {
        $placeholders = [];
        foreach ($skillIdArray as $i => $sid) {
            $key = ":sk_{$i}";
            $placeholders[] = $key;
            $params[$key] = $sid;
        }
        $joins .= ' INNER JOIN user_skills us_filter ON us_filter.user_id = u.id';
        $where[] = 'us_filter.skill_id IN (' . implode(',', $placeholders) . ')';
    }
}

$whereClause = implode(' AND ', $where);

// --- Count ---
$stmt = $db->prepare("SELECT COUNT(DISTINCT u.id) FROM users u {$joins} WHERE {$whereClause}");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();

// --- Fetch ---
$sql = "
    SELECT DISTINCT
        u.id, u.first_name, u.last_name, u.role, u.created_at,
        p.headline, p.location, p.avatar_url, p.industry,
        p.years_of_experience
    FROM users u
    {$joins}
    WHERE {$whereClause}
    ORDER BY u.created_at DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $db->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$people = $stmt->fetchAll();

// --- Attach connection status and mutual connections count ---
foreach ($people as &$person) {
    // Connection status
    $stmt = $db->prepare('
        SELECT status FROM connections
        WHERE (requester_id = :uid1 AND receiver_id = :uid2)
           OR (requester_id = :uid2b AND receiver_id = :uid1b)
    ');
    $stmt->execute([
        ':uid1'  => $user['id'],
        ':uid2'  => $person['id'],
        ':uid2b' => $person['id'],
        ':uid1b' => $user['id'],
    ]);
    $conn = $stmt->fetch();
    $person['connection_status'] = $conn ? $conn['status'] : 'none';

    // Mutual connections count
    $stmt = $db->prepare('
        SELECT COUNT(*) FROM connections c1
        JOIN connections c2 ON (
            (c2.requester_id = :person_id AND c2.receiver_id = IF(c1.requester_id = :me1, c1.receiver_id, c1.requester_id))
            OR (c2.receiver_id = :person_id2 AND c2.requester_id = IF(c1.requester_id = :me2, c1.receiver_id, c1.requester_id))
        )
        WHERE (c1.requester_id = :me3 OR c1.receiver_id = :me4)
        AND c1.status = "accepted"
        AND c2.status = "accepted"
    ');
    $stmt->execute([
        ':person_id'  => $person['id'],
        ':person_id2' => $person['id'],
        ':me1' => $user['id'],
        ':me2' => $user['id'],
        ':me3' => $user['id'],
        ':me4' => $user['id'],
    ]);
    $person['mutual_connections'] = (int) $stmt->fetchColumn();

    // Top skills
    $stmt = $db->prepare('
        SELECT s.name, us.proficiency_level
        FROM user_skills us
        JOIN skills s ON s.id = us.skill_id
        WHERE us.user_id = :user_id
        ORDER BY us.endorsement_count DESC
        LIMIT 5
    ');
    $stmt->execute([':user_id' => $person['id']]);
    $person['top_skills'] = $stmt->fetchAll();
}

jsonSuccess([
    'people'     => $people,
    'pagination' => [
        'page'        => $page,
        'limit'       => $limit,
        'total'       => $total,
        'total_pages' => ceil($total / $limit),
    ],
], 'People search results retrieved.');

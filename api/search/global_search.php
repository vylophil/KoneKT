<?php
// ============================================================
// KONEKT — Global Search (People + Companies)
// ============================================================
// GET /api/search/global_search.php?q=<query>
//
// Returns up to 5 people and 5 companies matching the query.
// Requires authentication.
// ============================================================

require_once __DIR__ . '/../auth/session.php';

requireMethod('GET');
$user = requireAuth();

$db = getDB();
$q  = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($q === '' || mb_strlen($q) < 2) {
    jsonSuccess(['people' => [], 'companies' => []], 'Provide at least 2 characters.');
}

$searchTerm = "%{$q}%";

// ── Search People ────────────────────────────────────────────
$people = [];
try {
    $stmt = $db->prepare("
        SELECT u.id, u.first_name, u.last_name, u.role,
               p.headline, p.avatar_url, p.location
        FROM users u
        LEFT JOIN profiles p ON p.user_id = u.id
        WHERE u.is_active = 1
          AND u.id != :uid
          AND (CONCAT(u.first_name, ' ', u.last_name) LIKE :search
               OR p.headline LIKE :search2)
        ORDER BY u.first_name ASC
        LIMIT 5
    ");
    $stmt->execute([
        ':uid'     => $user['id'],
        ':search'  => $searchTerm,
        ':search2' => $searchTerm,
    ]);
    $people = $stmt->fetchAll();
} catch (Throwable $e) {}

// ── Search Companies ─────────────────────────────────────────
$companies = [];
try {
    $stmt = $db->prepare("
        SELECT c.id, c.name, c.industry, c.location, c.logo_url,
               u.first_name AS owner_first, u.last_name AS owner_last
        FROM companies c
        JOIN users u ON u.id = c.user_id
        WHERE c.name LIKE :search
           OR c.industry LIKE :search2
        ORDER BY c.name ASC
        LIMIT 5
    ");
    $stmt->execute([
        ':search'  => $searchTerm,
        ':search2' => $searchTerm,
    ]);
    $companies = $stmt->fetchAll();
} catch (Throwable $e) {}

jsonSuccess([
    'people'    => $people,
    'companies' => $companies,
], 'Search results retrieved.');

<?php
// ============================================================
// KONEKT — Demo Data Remover
// ============================================================
// Run via browser: http://localhost/.../remove_demo.php
//
// Removes ALL demo data by deleting users whose email matches
// demo_*@konekt.test. Thanks to ON DELETE CASCADE foreign keys,
// all related data (profiles, companies, jobs, applications,
// connections, messages, etc.) is automatically removed.
//
// Safe to run multiple times.
// ============================================================

require_once __DIR__ . '/api/config/database.php';

// ── Output Helpers ───────────────────────────────────────────
function out(string $msg, string $type = 'info'): void {
    $icons = ['ok' => '✅', 'skip' => '⏭️', 'info' => 'ℹ️', 'err' => '❌', 'warn' => '⚠️'];
    $icon = $icons[$type] ?? '';
    echo "<div style='margin:2px 0;padding:4px 8px;font-family:\"Segoe UI\",sans-serif;font-size:14px;'>{$icon} {$msg}</div>\n";
}

// ── Start ────────────────────────────────────────────────────
echo '<!DOCTYPE html><html><head><title>KoneKT · Remove Demo Data</title>';
echo '<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
echo '<style>body{background:#0f0f23;color:#e0e0e0;padding:24px 32px;max-width:800px;margin:0 auto;}';
echo 'a{color:#e6b800;text-decoration:none;}a:hover{text-decoration:underline;}';
echo '.summary{background:#16213e;border:1px solid #e6b800;border-radius:10px;padding:20px;margin-top:20px;}';
echo '.summary h2{color:#e6b800;margin:0 0 12px;font-size:18px;}';
echo '.summary table{width:100%;border-collapse:collapse;font-size:13px;}';
echo '.summary th,.summary td{padding:6px 10px;border-bottom:1px solid #1a1a3e;text-align:left;}';
echo '.summary th{color:#a0a0c0;font-weight:600;}';
echo '.btn-remove{display:inline-block;background:#dc3545;color:#fff;padding:10px 24px;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;transition:background 0.2s;}';
echo '.btn-remove:hover{background:#bb2d3b;color:#fff;}';
echo '.btn-cancel{display:inline-block;background:#374151;color:#e0e0e0;padding:10px 24px;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;margin-left:8px;transition:background 0.2s;}';
echo '.btn-cancel:hover{background:#4b5563;color:#fff;}';
echo '</style></head><body>';
echo '<h1 style="color:#e6b800;font-family:\'Segoe UI\',sans-serif;">🗑️ KoneKT Demo Remover</h1>';

try {
    $db = getDB();
} catch (Throwable $e) {
    out('Cannot connect to database: ' . htmlspecialchars($e->getMessage()), 'err');
    echo '</body></html>';
    exit;
}

// ── Find demo users ──────────────────────────────────────────
$stmt = $db->prepare("SELECT id, email, first_name, last_name, role FROM users WHERE email LIKE 'demo_%@konekt.test' ORDER BY role, first_name");
$stmt->execute();
$demoUsers = $stmt->fetchAll();

if (empty($demoUsers)) {
    out('No demo data found. Nothing to remove.', 'skip');
    echo '<p style="margin-top:12px;"><a href="seed_demo.php">🎭 Seed Demo Data</a> &nbsp;|&nbsp; <a href="index.php">🏠 Go to Homepage</a></p>';
    echo '</body></html>';
    exit;
}

// ── Confirmation step ────────────────────────────────────────
$confirmed = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

if (!$confirmed) {
    echo '<div class="summary">';
    echo '<h2>⚠️ The following demo accounts will be removed:</h2>';
    echo '<p style="color:#a0a0c0;font-size:13px;">All related data (profiles, companies, job postings, applications, connections, messages) will be cascade-deleted.</p>';
    echo '<table>';
    echo '<tr><th>Name</th><th>Email</th><th>Role</th></tr>';
    foreach ($demoUsers as $u) {
        $role = ucfirst(str_replace('_', ' ', $u['role']));
        echo "<tr><td>{$u['first_name']} {$u['last_name']}</td><td><code>{$u['email']}</code></td><td>{$role}</td></tr>";
    }
    echo '</table>';

    // Count related data
    $userIds = array_column($demoUsers, 'id');
    $ph = implode(',', array_fill(0, count($userIds), '?'));

    $counts = [];
    try {
        $s = $db->prepare("SELECT COUNT(*) FROM profiles WHERE user_id IN ({$ph})"); $s->execute($userIds); $counts['Profiles'] = $s->fetchColumn();
        $s = $db->prepare("SELECT COUNT(*) FROM companies WHERE user_id IN ({$ph})"); $s->execute($userIds); $counts['Companies'] = $s->fetchColumn();
        $s = $db->prepare("SELECT COUNT(*) FROM job_postings WHERE employer_id IN ({$ph})"); $s->execute($userIds); $counts['Job Postings'] = $s->fetchColumn();
        $s = $db->prepare("SELECT COUNT(*) FROM job_applications WHERE user_id IN ({$ph})"); $s->execute($userIds); $counts['Applications'] = $s->fetchColumn();
        $s = $db->prepare("SELECT COUNT(*) FROM connections WHERE requester_id IN ({$ph}) OR receiver_id IN ({$ph})"); $s->execute(array_merge($userIds, $userIds)); $counts['Connections'] = $s->fetchColumn();
        $s = $db->prepare("SELECT COUNT(*) FROM messages WHERE sender_id IN ({$ph}) OR receiver_id IN ({$ph})"); $s->execute(array_merge($userIds, $userIds)); $counts['Messages'] = $s->fetchColumn();
        $s = $db->prepare("SELECT COUNT(*) FROM user_skills WHERE user_id IN ({$ph})"); $s->execute($userIds); $counts['User Skills'] = $s->fetchColumn();
        $s = $db->prepare("SELECT COUNT(*) FROM education WHERE user_id IN ({$ph})"); $s->execute($userIds); $counts['Education'] = $s->fetchColumn();
        $s = $db->prepare("SELECT COUNT(*) FROM experience WHERE user_id IN ({$ph})"); $s->execute($userIds); $counts['Experience'] = $s->fetchColumn();
    } catch (Throwable $e) {}

    if (!empty($counts)) {
        echo '<h3 style="color:#e0e0e0;font-size:14px;margin-top:16px;">Related Data to be Cascade-Deleted:</h3>';
        echo '<table>';
        foreach ($counts as $label => $count) {
            $color = $count > 0 ? '#ff6b6b' : '#a0a0c0';
            echo "<tr><td>{$label}</td><td style='color:{$color};font-weight:600;'>{$count}</td></tr>";
        }
        echo '</table>';
    }

    echo '<div style="margin-top:20px;">';
    echo '<a href="remove_demo.php?confirm=yes" class="btn-remove">🗑️ Yes, Remove All Demo Data</a>';
    echo '<a href="index.php" class="btn-cancel">Cancel</a>';
    echo '</div>';
    echo '</div>';

    echo '</body></html>';
    exit;
}

// ── Execute removal ──────────────────────────────────────────
$startTime = microtime(true);

try {
    $db->beginTransaction();

    $deletedCount = 0;
    foreach ($demoUsers as $u) {
        $stmt = $db->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute([':id' => $u['id']]);
        $deletedCount += $stmt->rowCount();
        $role = ucfirst(str_replace('_', ' ', $u['role']));
        out("Deleted {$u['first_name']} {$u['last_name']} ({$role}) — {$u['email']}", 'ok');
    }

    $db->commit();

    $elapsed = round((microtime(true) - $startTime) * 1000);

    echo '<div class="summary">';
    echo '<h2>🎉 Demo Data Removed Successfully!</h2>';
    echo "<p style='color:#a0a0c0;font-size:13px;'>Deleted {$deletedCount} demo user(s) and all related data in {$elapsed}ms.</p>";
    echo '<p style="margin-top:12px;">';
    echo '<a href="seed_demo.php">🎭 Re-Seed Demo Data</a> &nbsp;|&nbsp; ';
    echo '<a href="index.php">🏠 Go to Homepage</a>';
    echo '</p>';
    echo '</div>';

} catch (Throwable $e) {
    $db->rollBack();
    out('Removal failed: ' . htmlspecialchars($e->getMessage()), 'err');
}

echo '</body></html>';

<?php
session_start();
if (empty($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}
// Redirect employers to their dashboard
if (($_SESSION['role'] ?? '') === 'employer') {
  header('Location: employer_dashboard.php');
  exit;
}

require_once __DIR__ . '/api/config/database.php';

$firstName = $_SESSION['first_name'] ?? 'there';
$lastName = $_SESSION['last_name'] ?? '';
$displayName = trim($firstName . ' ' . $lastName);
if ($displayName === '') {
  $displayName = 'there';
}

$active_page = 'dashboard';

$db = getDB();

// Pull real unread message count from DB
$unread_messages = 0;
try {
  $stmt = $db->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id = :uid AND is_read = 0');
  $stmt->execute([':uid' => $_SESSION['user_id']]);
  $unread_messages = (int) $stmt->fetchColumn();
} catch (Throwable $e) {}

// Get resume status
$resumeUrl = null;
try {
  $stmt = $db->prepare('SELECT resume_url FROM profiles WHERE user_id = :uid');
  $stmt->execute([':uid' => $_SESSION['user_id']]);
  $resumeUrl = $stmt->fetchColumn() ?: null;
} catch (Throwable $e) {}

// Get top 3 matches
$topMatches = [];
try {
  $stmt = $db->prepare('
    SELECT jm.match_score, jp.title, jp.location, c.name AS company_name
    FROM job_matches jm
    JOIN job_postings jp ON jp.id = jm.job_id AND jp.is_active = 1
    JOIN companies c ON c.id = jp.company_id
    WHERE jm.user_id = :uid AND jm.match_score > 0
    ORDER BY jm.match_score DESC LIMIT 3
  ');
  $stmt->execute([':uid' => $_SESSION['user_id']]);
  $topMatches = $stmt->fetchAll();
} catch (Throwable $e) {}

// Count total matched jobs
$totalMatchedJobs = 0;
try {
  $stmt = $db->prepare('SELECT COUNT(*) FROM job_matches jm JOIN job_postings jp ON jp.id = jm.job_id AND jp.is_active = 1 WHERE jm.user_id = :uid AND jm.match_score > 0');
  $stmt->execute([':uid' => $_SESSION['user_id']]);
  $totalMatchedJobs = (int) $stmt->fetchColumn();
} catch (Throwable $e) {}

// Get application counts
$appCount = 0;
$pendingApps = 0;
$offeredApps = 0;
try {
  $stmt = $db->prepare('SELECT status, COUNT(*) as cnt FROM job_applications WHERE user_id = :uid GROUP BY status');
  $stmt->execute([':uid' => $_SESSION['user_id']]);
  foreach ($stmt->fetchAll() as $row) {
    $appCount += $row['cnt'];
    if ($row['status'] === 'pending') $pendingApps = $row['cnt'];
    if ($row['status'] === 'offered') $offeredApps = $row['cnt'];
  }
} catch (Throwable $e) {}

// Connection requests
$connRequests = 0;
try {
  $stmt = $db->prepare("SELECT COUNT(*) FROM connections WHERE receiver_id = :uid AND status = 'pending'");
  $stmt->execute([':uid' => $_SESSION['user_id']]);
  $connRequests = (int) $stmt->fetchColumn();
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard · KoneKT</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/theme.css" rel="stylesheet">
</head>
<body>

  <?php include 'includes/navbar.php'; ?>

  <main class="container py-5">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
      <div>
        <h1 class="h3 mb-1">Welcome back, <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-secondary mb-0">Here's what's new since your last visit.</p>
      </div>
      <a href="job_matches.php" class="btn btn-konekt-primary px-4">View My Matches</a>
    </div>

    <div class="row g-4">
      <?php // Resume matching status ?>
      <div class="col-lg-8">
        <div class="konekt-card p-4 mb-4">
          <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
              <h2 class="h5 mb-1">Resume Status</h2>
              <p class="text-secondary mb-0 small"><?= $resumeUrl ? 'Resume uploaded' : 'No resume uploaded yet' ?></p>
            </div>
            <?php if ($resumeUrl): ?>
              <span class="status-badge accepted"><i class="bi bi-check-circle-fill"></i> Active</span>
            <?php else: ?>
              <span class="status-badge pending"><i class="bi bi-exclamation-circle"></i> Missing</span>
            <?php endif; ?>
          </div>
          <?php if ($totalMatchedJobs > 0): ?>
            <p class="mb-3"><?= htmlspecialchars($displayName) ?>, your resume is being matched against <strong><?= $totalMatchedJobs ?> open roles</strong> based on your current preferences.</p>
          <?php else: ?>
            <p class="mb-3">Upload your resume and set preferences to start matching with jobs across industries.</p>
          <?php endif; ?>
          <a href="upload_resume.php" class="btn btn-konekt-outline btn-sm"><?= $resumeUrl ? 'Re-upload Resume' : 'Upload Resume' ?></a>
        </div>

        <?php // Top matches ?>
        <div class="konekt-card p-4 mb-4">
          <h2 class="h5 mb-3">Top Matches for You</h2>
          <?php if (empty($topMatches)): ?>
            <p class="text-secondary small">No matches yet. <a href="job_preferences.php">Set your preferences</a> to get started.</p>
          <?php else: ?>
            <?php foreach ($topMatches as $i => $match): ?>
            <div class="d-flex justify-content-between align-items-center py-3 <?= $i < count($topMatches) - 1 ? 'border-bottom' : '' ?>">
              <div>
                <p class="fw-semibold mb-1"><?= htmlspecialchars($match['title']) ?></p>
                <p class="text-secondary small mb-0"><?= htmlspecialchars($match['company_name']) ?> &middot; <?= htmlspecialchars($match['location'] ?? 'Remote') ?></p>
              </div>
              <span class="match-chip"><?= round($match['match_score']) ?>% match</span>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <?php // My applications ?>
        <div class="konekt-card p-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0">My Applications</h2>
            <a href="my_applications.php" class="small">View all &rarr;</a>
          </div>
          <div class="d-flex gap-4">
            <div>
              <span class="h4 fw-bold"><?= $appCount ?></span>
              <p class="text-secondary small mb-0">Total</p>
            </div>
            <div>
              <span class="h4 fw-bold text-primary"><?= $pendingApps ?></span>
              <p class="text-secondary small mb-0">Pending</p>
            </div>
            <div>
              <span class="h4 fw-bold" style="color: var(--ember-gold);"><?= $offeredApps ?></span>
              <p class="text-secondary small mb-0">Offered</p>
            </div>
          </div>
        </div>
      </div>

      <?php // Sidebar ?>
      <div class="col-lg-4">
        <div class="konekt-card p-4 mb-4">
          <h2 class="h6 mb-3">Your Preferences</h2>
          <?php
            $userIndustry = '';
            try {
              $stmt = $db->prepare('SELECT industry FROM profiles WHERE user_id = :uid');
              $stmt->execute([':uid' => $_SESSION['user_id']]);
              $userIndustry = $stmt->fetchColumn() ?: '';
            } catch (Throwable $e) {}
            $prefs = array_filter(array_map('trim', explode(',', $userIndustry)));
          ?>
          <?php if (!empty($prefs)): ?>
            <?php foreach ($prefs as $p): ?>
              <span class="badge rounded-pill text-bg-light border me-2 mb-2"><?= htmlspecialchars($p) ?></span>
            <?php endforeach; ?>
          <?php else: ?>
            <p class="text-secondary small mb-0">No preferences set yet.</p>
          <?php endif; ?>
          <a href="job_preferences.php" class="d-block mt-2 small">Edit preferences &rarr;</a>
        </div>

        <div class="konekt-card p-4 mb-4">
          <h2 class="h6 mb-3">Network Activity</h2>
          <p class="small text-secondary mb-2"><strong><?= $connRequests ?></strong> new connection request<?= $connRequests !== 1 ? 's' : '' ?></p>
          <p class="small text-secondary mb-3"><strong><?= $unread_messages ?></strong> unread message<?= $unread_messages !== 1 ? 's' : '' ?></p>
          <a href="network.php" class="btn btn-konekt-outline btn-sm w-100">Go to Network</a>
        </div>

        <div class="konekt-card p-4">
          <h2 class="h6 mb-3">Account</h2>
          <form method="post" action="api/auth/logout.php" class="mb-2">
            <button type="submit" class="btn btn-konekt-outline btn-sm w-100">Logout</button>
          </form>
          <form method="post" action="api/auth/delete_profile.php" onsubmit="return confirm('This will permanently delete your account and related data. Continue?');">
            <button type="submit" class="btn btn-outline-danger btn-sm w-100">Delete my profile</button>
          </form>
        </div>
      </div>
    </div>
  </main>

  <?php include 'includes/footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

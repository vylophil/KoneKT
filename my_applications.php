<?php
session_start();
if (empty($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}
if (($_SESSION['role'] ?? '') === 'employer') {
  header('Location: employer_dashboard.php');
  exit;
}

require_once __DIR__ . '/api/config/database.php';

$active_page = 'dashboard';
$unread_messages = 0;

$db = getDB();

// Get unread messages
try {
  $stmt = $db->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id = :uid AND is_read = 0');
  $stmt->execute([':uid' => $_SESSION['user_id']]);
  $unread_messages = (int) $stmt->fetchColumn();
} catch (Throwable $e) {}

// Fetch all applications for this user
$applications = [];
try {
  $stmt = $db->prepare('
    SELECT ja.id, ja.job_id, ja.status, ja.cover_letter, ja.applied_at, ja.updated_at,
           jp.title AS job_title, jp.location AS job_location, jp.job_type,
           c.name AS company_name
    FROM job_applications ja
    JOIN job_postings jp ON jp.id = ja.job_id
    JOIN companies c ON c.id = jp.company_id
    WHERE ja.user_id = :uid
    ORDER BY ja.applied_at DESC
  ');
  $stmt->execute([':uid' => $_SESSION['user_id']]);
  $applications = $stmt->fetchAll();
} catch (Throwable $e) {}

// Group by status
$statusCounts = ['pending' => 0, 'reviewing' => 0, 'shortlisted' => 0, 'interview' => 0, 'offered' => 0, 'accepted' => 0, 'rejected' => 0, 'withdrawn' => 0];
foreach ($applications as $app) {
  if (isset($statusCounts[$app['status']])) {
    $statusCounts[$app['status']]++;
  }
}
$activeCount = $statusCounts['pending'] + $statusCounts['reviewing'] + $statusCounts['shortlisted'] + $statusCounts['interview'] + $statusCounts['offered'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Applications · KoneKT</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/theme.css" rel="stylesheet">
</head>
<body>

  <?php if (file_exists('includes/navbar.php')) include 'includes/navbar.php'; ?>

  <main class="container py-5">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
      <div>
        <h1 class="h3 mb-1">My Applications</h1>
        <p class="text-secondary mb-0"><strong><?= $activeCount ?></strong> active application<?= $activeCount !== 1 ? 's' : '' ?> · <strong><?= count($applications) ?></strong> total</p>
      </div>
      <a href="find_jobs.php" class="btn btn-konekt-primary px-4">
        <i class="bi bi-search me-1"></i> Find More Jobs
      </a>
    </div>

    <?php if (empty($applications)): ?>
      <div class="konekt-card p-5">
        <div class="empty-state">
          <i class="bi bi-file-earmark-text"></i>
          <h3 class="h5 mb-2">No applications yet</h3>
          <p class="text-secondary mb-4">Start applying to jobs and track your progress here.</p>
          <a href="find_jobs.php" class="btn btn-konekt-primary px-4">Browse Jobs</a>
        </div>
      </div>
    <?php else: ?>
      <?php foreach ($applications as $app): ?>
      <div class="konekt-card p-4 mb-3">
        <div class="d-flex justify-content-between align-items-start">
          <div class="flex-grow-1">
            <h2 class="h5 mb-1"><?= htmlspecialchars($app['job_title']) ?></h2>
            <p class="text-secondary small mb-2"><?= htmlspecialchars($app['company_name']) ?> &middot; <?= htmlspecialchars($app['job_location'] ?? 'Remote') ?></p>
            <p class="text-secondary small mb-0">
              Applied <?= date('M j, Y', strtotime($app['applied_at'])) ?>
              <?php if ($app['updated_at'] !== $app['applied_at']): ?>
                &middot; Updated <?= date('M j, Y', strtotime($app['updated_at'])) ?>
              <?php endif; ?>
            </p>
          </div>
          <div class="text-end ms-3">
            <span class="status-badge <?= $app['status'] ?>"><?= ucfirst($app['status']) ?></span>
            <div class="mt-2 action-btn-group justify-content-end">
              <?php if ($app['status'] === 'offered'): ?>
                <button class="btn btn-konekt-success btn-sm app-action" data-id="<?= $app['id'] ?>" data-status="accepted">
                  <i class="bi bi-check2"></i> Accept
                </button>
              <?php endif; ?>
              <?php if (in_array($app['status'], ['pending', 'reviewing', 'shortlisted'])): ?>
                <button class="btn btn-outline-secondary btn-sm app-action" data-id="<?= $app['id'] ?>" data-status="withdrawn">
                  <i class="bi bi-x"></i> Withdraw
                </button>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </main>

  <?php if (file_exists('includes/footer.php')) include 'includes/footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.querySelectorAll('.app-action').forEach(btn => {
      btn.addEventListener('click', async () => {
        const appId = btn.dataset.id;
        const status = btn.dataset.status;
        if (!confirm(`Are you sure you want to ${status === 'withdrawn' ? 'withdraw this application' : 'accept this offer'}?`)) return;

        btn.disabled = true;
        try {
          const res = await fetch('api/jobs/update_application.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ application_id: parseInt(appId), status: status })
          });
          const data = await res.json();
          if (data.success) {
            location.reload();
          } else {
            alert(data.message || 'Action failed.');
            btn.disabled = false;
          }
        } catch (err) {
          alert('Network error.');
          btn.disabled = false;
        }
      });
    });
  </script>
</body>
</html>

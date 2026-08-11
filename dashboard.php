<?php
session_start();
if (empty($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}

$firstName = $_SESSION['first_name'] ?? 'there';
$lastName = $_SESSION['last_name'] ?? '';
$displayName = trim($firstName . ' ' . $lastName);
if ($displayName === '') {
  $displayName = 'there';
}

$active_page = 'dashboard';
$unread_messages = 3; // demo value — pull real count from DB
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
      <!-- Resume / matching status -->
      <div class="col-lg-8">
        <div class="konekt-card p-4 mb-4">
          <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
              <h2 class="h5 mb-1">Resume Status</h2>
              <p class="text-secondary mb-0 small">Last updated 2 days ago</p>
            </div>
            <span class="match-chip"><i class="bi bi-check-circle-fill"></i> Active</span>
          </div>
          <p class="mb-3"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>, your resume is being matched against <strong>142 open roles</strong> based on your current preferences.</p>
          <a href="upload_resume.php" class="btn btn-konekt-outline btn-sm">Re-upload Resume</a>
        </div>

        <div class="konekt-card p-4">
          <h2 class="h5 mb-3">Top Matches for You</h2>

          <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
            <div>
              <p class="fw-semibold mb-1">IT Support Specialist</p>
              <p class="text-secondary small mb-0">Accenture Philippines &middot; Clark, Pampanga</p>
            </div>
            <span class="match-chip">92% match</span>
          </div>

          <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
            <div>
              <p class="fw-semibold mb-1">Junior Systems Administrator</p>
              <p class="text-secondary small mb-0">Concentrix &middot; San Fernando, Pampanga</p>
            </div>
            <span class="match-chip">85% match</span>
          </div>

          <div class="d-flex justify-content-between align-items-center pt-3">
            <div>
              <p class="fw-semibold mb-1">Database Assistant</p>
              <p class="text-secondary small mb-0">SM Investments &middot; Tarlac City</p>
            </div>
            <span class="match-chip">78% match</span>
          </div>
        </div>
      </div>

      <!-- Sidebar -->
      <div class="col-lg-4">
        <div class="konekt-card p-4 mb-4">
          <h2 class="h6 mb-3">Your Preferences</h2>
          <span class="badge rounded-pill text-bg-light border me-2 mb-2">Career growth</span>
          <span class="badge rounded-pill text-bg-light border me-2 mb-2">Remote friendly</span>
          <span class="badge rounded-pill text-bg-light border me-2 mb-2">Cross-field roles</span>
          <a href="job_preferences.php" class="d-block mt-2 small">Edit preferences &rarr;</a>
        </div>

        <div class="konekt-card p-4 mb-4">
          <h2 class="h6 mb-3">Network Activity</h2>
          <p class="small text-secondary mb-2"><strong>3</strong> new connection requests</p>
          <p class="small text-secondary mb-3"><strong>2</strong> unread messages</p>
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

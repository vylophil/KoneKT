<?php
// Ensure $active_page and $unread_messages are set by the including page
$active_page      = $active_page ?? 'home';
$unread_messages   = $unread_messages ?? 0;
$sessionRole       = $_SESSION['role'] ?? '';
$isEmployer        = ($sessionRole === 'employer');
$isLoggedIn        = !empty($_SESSION['user_id']);
?>
<nav class="navbar navbar-expand-lg konekt-navbar sticky-top">
  <div class="container">
    <?php // Brand Title matching theme tokens ?>
    <a class="navbar-brand" href="index.php">
      <span class="match-pulse me-1"></span>
      <span class="brand-k">Kone</span>KT
    </a>

    <button class="navbar-toggler border-0 text-white" type="button" data-bs-toggle="collapse" data-bs-target="#konektNavbar" aria-controls="konektNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="konektNavbar">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3">
        <li class="nav-item">
          <a class="nav-link <?= ($active_page === 'home') ? 'active' : '' ?>" href="index.php">Home</a>
        </li>

        <?php if ($isEmployer): ?>
          <?php // Employer navigation ?>
          <li class="nav-item">
            <a class="nav-link <?= ($active_page === 'employer_dashboard') ? 'active' : '' ?>" href="employer_dashboard.php">Dashboard</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= ($active_page === 'employer_jobs') ? 'active' : '' ?>" href="employer_jobs.php">My Jobs</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= ($active_page === 'employer_applicants') ? 'active' : '' ?>" href="employer_applicants.php">Applicants</a>
          </li>
        <?php else: ?>
          <?php // Job seeker navigation ?>
          <li class="nav-item">
            <a class="nav-link <?= ($active_page === 'dashboard') ? 'active' : '' ?>" href="dashboard.php">Dashboard</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= ($active_page === 'upload') ? 'active' : '' ?>" href="upload_resume.php">Upload & Preferences</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= ($active_page === 'find_jobs') ? 'active' : '' ?>" href="find_jobs.php">Find Jobs</a>
          </li>
        <?php endif; ?>

        <li class="nav-item">
          <a class="nav-link <?= ($active_page === 'network') ? 'active' : '' ?>" href="network.php">
            Network
            <?php if (!empty($unread_messages)): ?>
              <span class="nav-badge"><?= $unread_messages ?></span>
            <?php endif; ?>
          </a>
        </li>
      </ul>

      <div class="d-flex align-items-center gap-2">
        <?php if ($isLoggedIn): ?>
          <a href="<?= $isEmployer ? 'employer_dashboard.php' : 'dashboard.php' ?>" class="btn btn-konekt-outline text-white border-white-50 btn-sm px-3">
            <?= htmlspecialchars($_SESSION['first_name'] ?? 'My Account', ENT_QUOTES, 'UTF-8') ?>
          </a>
          <a href="logout.php" class="btn btn-outline-light btn-sm px-3 opacity-75">Sign Out</a>
        <?php else: ?>
          <a href="login.php" class="btn btn-konekt-outline text-white border-white-50 btn-sm px-3">Sign In</a>
        <?php endif; ?>
        <?php if (!$isEmployer): ?>
          <a href="find_jobs.php" class="btn btn-konekt-gold btn-sm px-3">Find Jobs</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
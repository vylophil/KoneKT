<nav class="navbar navbar-expand-lg konekt-navbar sticky-top">
  <div class="container">
    <!-- Brand Title matching theme tokens -->
    <a class="navbar-brand" href="index.php">
      <span class="match-pulse me-1"></span>
      <KT class="brand-k">KoneKT
    </a>

    <button class="navbar-toggler border-0 text-white" type="button" data-bs-toggle="collapse" data-bs-target="#konektNavbar" aria-controls="konektNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="konektNavbar">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3">
        <li class="nav-item">
          <a class="nav-link <?= ($active_page === 'home') ? 'active' : '' ?>" href="index.php">Home</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= ($active_page === 'dashboard') ? 'active' : '' ?>" href="dashboard.php">Dashboard</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= ($active_page === 'upload') ? 'active' : '' ?>" href="upload_resume.php">Upload & Preferences</a>
        </li>
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
        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id']): ?>
          <a href="dashboard.php" class="btn btn-konekt-outline text-white border-white-50 btn-sm px-3">
            <?= htmlspecialchars($_SESSION['first_name'] ?? 'My Account', ENT_QUOTES, 'UTF-8') ?>
          </a>
          <form method="post" action="api/auth/logout.php" class="d-inline">
            <button type="submit" class="btn btn-outline-light btn-sm px-3">Logout</button>
          </form>
        <?php else: ?>
          <a href="login.php" class="btn btn-konekt-outline text-white border-white-50 btn-sm px-3">Sign In</a>
        <?php endif; ?>
        <a href="find_jobs.php" class="btn btn-konekt-gold btn-sm px-3">Find Jobs</a>
      </div>
    </div>
  </div>
</nav>
<?php
session_start();
$active_page = 'find_jobs';
$unread_messages = 3;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Find Jobs · KoneKT</title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">

  <!-- Bootstrap 5 & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/theme.css" rel="stylesheet">
</head>
<body>

  <!-- Shared Navbar -->
  <?php if (file_exists('includes/navbar.php')) include 'includes/navbar.php'; ?>

  <main class="container py-5">
    
    <!-- Search Banner -->
    <div class="konekt-card p-4 p-md-5 mb-4 text-white text-center rounded-4" style="background-color: var(--ink-navy);">
      <h1 class="h3 mb-2 text-white">Find Cross-Field Opportunities</h1>
      <p class="text-white-50 mb-4 mx-auto" style="max-width: 550px;">Search roles across industries tailored to your skills and qualifications.</p>
      
      <form action="find_jobs.php" method="GET" class="row g-2 justify-content-center max-w-75 mx-auto">
        <div class="col-md-5">
          <div class="input-group">
            <span class="input-group-text bg-white border-0"><i class="bi bi-search text-secondary"></i></span>
            <input type="text" class="form-control border-0" name="keyword" placeholder="Job title, skill, or degree..." value="IT Specialist">
          </div>
        </div>
        <div class="col-md-4">
          <div class="input-group">
            <span class="input-group-text bg-white border-0"><i class="bi bi-geo-alt text-secondary"></i></span>
            <input type="text" class="form-control border-0" name="location" placeholder="Location..." value="Pampanga">
          </div>
        </div>
        <div class="col-md-3">
          <button type="submit" class="btn btn-konekt-gold w-100 fw-semibold py-2">Search Roles</button>
        </div>
      </form>
    </div>

    <!-- Main Content Layout -->
    <div class="row g-4">
      
      <!-- Filters Sidebar -->
      <div class="col-lg-3">
        <div class="konekt-card p-4">
          <h2 class="h6 mb-3 fw-bold">Filter Industry</h2>
          
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="ind-healthcare" checked>
            <label class="form-check-label small" for="ind-healthcare">Healthcare & Med-Tech</label>
          </div>
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="ind-accounting" checked>
            <label class="form-check-label small" for="ind-accounting">Accounting & Finance</label>
          </div>
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="ind-it" checked>
            <label class="form-check-label small" for="ind-it">IT & Systems Admin</label>
          </div>
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="ind-logistics">
            <label class="form-check-label small" for="ind-logistics">Logistics & Supply Chain</label>
          </div>

          <hr class="my-3">

          <h2 class="h6 mb-3 fw-bold">Match Threshold</h2>
          <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="match_rate" id="m-90" checked>
            <label class="form-check-label small" for="m-90">90%+ Match</label>
          </div>
          <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="match_rate" id="m-75">
            <label class="form-check-label small" for="m-75">75%+ Match</label>
          </div>
          <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="match_rate" id="m-all">
            <label class="form-check-label small" for="m-all">All Roles</label>
          </div>
        </div>
      </div>

      <!-- Job Results Column -->
      <div class="col-lg-9">
        
        <!-- Job Card 1 -->
        <div class="konekt-card p-4 mb-3">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
              <h2 class="h5 mb-1"><a href="job_matches.php" class="text-decoration-none">Healthcare Systems Specialist</a></h2>
              <p class="text-secondary small mb-0">The Medical City &middot; Clark, Pampanga</p>
            </div>
            <span class="match-chip"><i class="bi bi-stars"></i> 95% match</span>
          </div>
          <p class="small text-secondary mb-3">
            Responsible for managing clinical electronic record databases and supporting hospital hardware networks.
          </p>
          <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex gap-2">
              <span class="badge rounded-pill text-bg-light border">Healthcare</span>
              <span class="badge rounded-pill text-bg-light border">SQL</span>
            </div>
            <a href="network.php" class="btn btn-konekt-primary btn-sm px-3">Apply / Connect</a>
          </div>
        </div>

        <!-- Job Card 2 -->
        <div class="konekt-card p-4 mb-3">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
              <h2 class="h5 mb-1"><a href="job_matches.php" class="text-decoration-none">IT Operations Support</a></h2>
              <p class="text-secondary small mb-0">Accenture Philippines &middot; Clark, Pampanga</p>
            </div>
            <span class="match-chip"><i class="bi bi-stars"></i> 92% match</span>
          </div>
          <p class="small text-secondary mb-3">
            Provide system diagnostic support, user account administration, and infrastructure upkeep for enterprise teams.
          </p>
          <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex gap-2">
              <span class="badge rounded-pill text-bg-light border">IT Operations</span>
              <span class="badge rounded-pill text-bg-light border">Networking</span>
            </div>
            <a href="network.php" class="btn btn-konekt-primary btn-sm px-3">Apply / Connect</a>
          </div>
        </div>

        <!-- Job Card 3 -->
        <div class="konekt-card p-4 mb-3">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
              <h2 class="h5 mb-1"><a href="job_matches.php" class="text-decoration-none">Financial Data Analyst</a></h2>
              <p class="text-secondary small mb-0">SG&Co &middot; San Fernando, Pampanga</p>
            </div>
            <span class="match-chip"><i class="bi bi-stars"></i> 84% match</span>
          </div>
          <p class="small text-secondary mb-3">
            Audit automated financial software outputs and analyze transaction data logs for corporate clients.
          </p>
          <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex gap-2">
              <span class="badge rounded-pill text-bg-light border">Fintech</span>
              <span class="badge rounded-pill text-bg-light border">Analytics</span>
            </div>
            <a href="network.php" class="btn btn-konekt-primary btn-sm px-3">Apply / Connect</a>
          </div>
        </div>

      </div>

    </div>
  </main>

  <!-- Shared Footer -->
  <?php if (file_exists('includes/footer.php')) include 'includes/footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
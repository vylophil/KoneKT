<?php
session_start();
if (empty($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}

$active_page = 'upload'; // Highlights active context
$unread_messages = 3;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Job Matches · KoneKT</title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">

  <!-- Bootstrap 5 & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  
  <!-- Updated CSS Path -->
  <link href="assets/css/theme.css" rel="stylesheet">
</head>
<body>

  <!-- Shared Navbar -->
  <?php if (file_exists('includes/navbar.php')) include 'includes/navbar.php'; ?>

  <main class="container py-5">
    
    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
      <div>
        <h1 class="h3 mb-1">Your Cross-Field Job Matches</h1>
        <p class="text-secondary mb-0">Matched against <strong>142 open roles</strong> based on your BSIT profile and target preferences.</p>
      </div>
      <!-- Updated link to job_preferences.php -->
      <a href="job_preferences.php" class="btn btn-konekt-outline btn-sm">
        <i class="bi bi-sliders me-1"></i> Edit Preferences
      </a>
    </div>

    <!-- Active Field Filter Chips -->
    <div class="mb-4 d-flex align-items-center gap-2 flex-wrap">
      <span class="text-secondary small fw-semibold me-2">Active Filters:</span>
      <span class="badge bg-white text-dark border px-3 py-2 rounded-pill"><i class="bi bi-check2 me-1 text-success"></i> Healthcare / Med-Tech</span>
      <span class="badge bg-white text-dark border px-3 py-2 rounded-pill"><i class="bi bi-check2 me-1 text-success"></i> Accounting / Fintech</span>
      <span class="badge bg-white text-dark border px-3 py-2 rounded-pill"><i class="bi bi-check2 me-1 text-success"></i> Systems Admin</span>
    </div>

    <div class="row g-4">
      
      <!-- Match List -->
      <div class="col-lg-8">
        
        <!-- Job Card 1 -->
        <div class="konekt-card p-4 mb-3">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
              <h2 class="h5 mb-1"><a href="find_jobs.php" class="text-decoration-none">Healthcare Systems Analyst</a></h2>
              <p class="text-secondary small mb-0">Medical City Philippines &middot; Clark, Pampanga</p>
            </div>
            <span class="match-chip"><i class="bi bi-stars"></i> 95% match</span>
          </div>
          <p class="small text-secondary mb-3">
            Combine your BSIT degree with clinical data processing. Looking for tech-minded analysts to manage EHR database systems.
          </p>
          <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex gap-2">
              <span class="badge rounded-pill text-bg-light border">Healthcare</span>
              <span class="badge rounded-pill text-bg-light border">SQL / Databases</span>
            </div>
            <a href="network.php" class="btn btn-konekt-primary btn-sm px-3">Connect / Message Recruiter</a>
          </div>
        </div>

        <!-- Job Card 2 -->
        <div class="konekt-card p-4 mb-3">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
              <h2 class="h5 mb-1"><a href="find_jobs.php" class="text-decoration-none">IT Support Specialist</a></h2>
              <p class="text-secondary small mb-0">Accenture Philippines &middot; Clark, Pampanga</p>
            </div>
            <span class="match-chip"><i class="bi bi-stars"></i> 92% match</span>
          </div>
          <p class="small text-secondary mb-3">
            Provide tier-1 hardware and network operational support for enterprise client accounts.
          </p>
          <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex gap-2">
              <span class="badge rounded-pill text-bg-light border">IT Operations</span>
              <span class="badge rounded-pill text-bg-light border">Networking</span>
            </div>
            <a href="network.php" class="btn btn-konekt-primary btn-sm px-3">Connect / Message Recruiter</a>
          </div>
        </div>

        <!-- Job Card 3 -->
        <div class="konekt-card p-4 mb-3">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
              <h2 class="h5 mb-1"><a href="find_jobs.php" class="text-decoration-none">Financial Systems Audit Assistant</a></h2>
              <p class="text-secondary small mb-0">SG&Co Accounting &middot; San Fernando, Pampanga</p>
            </div>
            <span class="match-chip"><i class="bi bi-stars"></i> 84% match</span>
          </div>
          <p class="small text-secondary mb-3">
            Assist our accounting team in auditing financial software platforms and database logging security.
          </p>
          <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex gap-2">
              <span class="badge rounded-pill text-bg-light border">Accounting</span>
              <span class="badge rounded-pill text-bg-light border">System Security</span>
            </div>
            <a href="network.php" class="btn btn-konekt-primary btn-sm px-3">Connect / Message Recruiter</a>
          </div>
        </div>

      </div>

      <!-- Sidebar summary -->
      <div class="col-lg-4">
        <div class="konekt-card p-4">
          <h3 class="h6 mb-3"><i class="bi bi-lightbulb text-warning me-2"></i>Match Insights</h3>
          <p class="small text-secondary mb-3">
            Your strongest cross-field synergy is currently in <strong>Healthcare IT</strong> due to your coursework in database structures and system maintenance.
          </p>
          <hr>
          <!-- Updated link to upload_resume.php -->
          <a href="upload_resume.php" class="btn btn-konekt-gold btn-sm w-100">Update Resume to Refresh</a>
        </div>
      </div>

    </div>
  </main>

  <!-- Shared Footer -->
  <?php if (file_exists('includes/footer.php')) include 'includes/footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
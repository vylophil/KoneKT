<?php
session_start();
if (empty($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}

$active_page = 'network';
$unread_messages = 3;
$currentUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Network & Messages · KoneKT</title>

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

  <main class="container py-4">
    <div class="row g-4">
      
      <!-- Left Panel: Conversations & Pending Connections -->
      <div class="col-lg-4">
        
        <!-- Connection Requests Card -->
        <div class="konekt-card p-3 mb-3">
          <h2 class="h6 mb-3 d-flex justify-content-between align-items-center">
            <span>Pending Requests</span>
            <span class="badge bg-primary rounded-pill">2</span>
          </h2>
          
          <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
            <div class="d-flex align-items-center gap-2">
              <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center fw-bold" style="width:36px; height:36px;">
                MC
              </div>
              <div>
                <p class="fw-semibold mb-0 small">Mark Cruz</p>
                <p class="text-secondary extra-small mb-0">Talent Acquisition &middot; Medical City</p>
              </div>
            </div>
            <div class="d-flex gap-1">
              <button class="btn btn-konekt-gold btn-sm py-0 px-2"><i class="bi bi-check"></i></button>
              <button class="btn btn-outline-secondary btn-sm py-0 px-2"><i class="bi bi-x"></i></button>
            </div>
          </div>
        </div>

        <!-- Direct Messages Sidebar -->
        <div class="konekt-card p-3">
          <h2 class="h6 mb-3">Messages</h2>
          <div class="list-group list-group-flush">
            
            <a href="network.php" data-conversation-id="2" data-name="Sarah Jenkins" data-subtitle="Recruiter · Accenture Philippines" class="list-group-item list-group-item-action border-0 rounded p-2 active bg-light text-dark mb-1">
              <div class="d-flex align-items-center gap-2">
                <div class="position-relative">
                  <div class="rounded-circle text-white d-flex align-items-center justify-content-center fw-bold" style="width:38px; height:38px; background-color: var(--ink-navy);">
                    SJ
                  </div>
                  <span class="position-absolute bottom-0 end-0 p-1 bg-success border border-light rounded-circle"></span>
                </div>
                <div class="flex-grow-1 overflow-hidden">
                  <div class="d-flex justify-content-between align-items-center">
                    <p class="fw-semibold mb-0 small text-truncate">Sarah Jenkins</p>
                    <span class="text-secondary extra-small">10m</span>
                  </div>
                  <p class="text-secondary small mb-0 text-truncate">Are you interested in the IT position?</p>
                </div>
              </div>
            </a>

            <a href="network.php" data-conversation-id="3" data-name="Ramon David" data-subtitle="Hiring Manager · SG&amp;Co" class="list-group-item list-group-item-action border-0 rounded p-2 mb-1">
              <div class="d-flex align-items-center gap-2">
                <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center fw-bold" style="width:38px; height:38px;">
                  RD
                </div>
                <div class="flex-grow-1 overflow-hidden">
                  <div class="d-flex justify-content-between align-items-center">
                    <p class="fw-semibold mb-0 small text-truncate">Ramon David</p>
                    <span class="text-secondary extra-small">2h</span>
                  </div>
                  <p class="text-secondary small mb-0 text-truncate">We reviewed your resume match score.</p>
                </div>
              </div>
            </a>

          </div>
        </div>

      </div>

      <!-- Right Panel: Active Chat Window -->
      <div class="col-lg-8">
        <div class="konekt-card d-flex flex-column" style="min-height: 520px; height: 72vh;">
          
          <!-- Chat Header -->
          <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-white rounded-top">
            <div class="d-flex align-items-center gap-2">
              <div class="rounded-circle text-white d-flex align-items-center justify-content-center fw-bold" style="width:38px; height:38px; background-color: var(--ink-navy);">
                SJ
              </div>
              <div>
                <h3 id="chatHeaderName" class="h6 mb-0">Sarah Jenkins</h3>
                <span id="chatHeaderSubtitle" class="text-secondary extra-small">Recruiter &middot; Accenture Philippines</span>
              </div>
            </div>
            <span class="match-chip"><i class="bi bi-stars"></i> 92% match</span>
          </div>

          <!-- Messages Scrollable Body -->
          <div class="p-3 flex-grow-1 overflow-auto" style="background-color: var(--mist);">
            <div id="chatMessages" class="d-flex flex-column gap-3"></div>
          </div>

          <!-- Chat Input -->
          <div class="p-3 border-top bg-white rounded-bottom">
            <form id="messageForm" class="d-flex gap-2">
              <input id="messageInput" type="text" class="form-control" placeholder="Write a message..." autocomplete="off" required>
              <button type="submit" class="btn btn-konekt-primary"><i class="bi bi-send-fill"></i></button>
            </form>
            <div id="messageStatus" class="form-text mt-2 small text-secondary">Ready to send a message.</div>
          </div>

        </div>
      </div>

    </div>
  </main>

  <!-- Shared Footer -->
  <?php if (file_exists('includes/footer.php')) include 'includes/footer.php'; ?>

  <script>
    window.currentUserId = <?= json_encode($currentUserId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  </script>
  <script src="assets/js/messaging.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
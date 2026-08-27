<?php
session_start();
if (empty($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}

require_once __DIR__ . '/api/config/database.php';

$active_page = 'network';

$db = getDB();

// Pull real unread message count from DB
$unread_messages = 0;
try {
  $stmt = $db->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id = :uid AND is_read = 0');
  $stmt->execute([':uid' => $_SESSION['user_id']]);
  $unread_messages = (int) $stmt->fetchColumn();
} catch (Throwable $e) {}

$currentUserId = (int) $_SESSION['user_id'];

// Get pending connection requests (received by this user)
$pendingRequests = [];
try {
  $stmt = $db->prepare("
    SELECT c.id AS connection_id, c.message, c.created_at,
           u.id AS user_id, u.first_name, u.last_name,
           p.headline, p.avatar_url
    FROM connections c
    JOIN users u ON u.id = c.requester_id
    LEFT JOIN profiles p ON p.user_id = u.id
    WHERE c.receiver_id = :uid AND c.status = 'pending'
    ORDER BY c.created_at DESC
  ");
  $stmt->execute([':uid' => $currentUserId]);
  $pendingRequests = $stmt->fetchAll();
} catch (Throwable $e) {}

// Get accepted connections (My Connections)
$myConnections = [];
try {
  $stmt = $db->prepare("
    SELECT
      u.id AS user_id, u.first_name, u.last_name,
      p.headline, p.avatar_url,
      c.updated_at AS connected_since
    FROM connections c
    JOIN users u ON u.id = IF(c.requester_id = :me1, c.receiver_id, c.requester_id)
    LEFT JOIN profiles p ON p.user_id = u.id
    WHERE (c.requester_id = :me2 OR c.receiver_id = :me3)
      AND c.status = 'accepted'
    ORDER BY c.updated_at DESC
    LIMIT 50
  ");
  $stmt->bindValue(':me1', $currentUserId, PDO::PARAM_INT);
  $stmt->bindValue(':me2', $currentUserId, PDO::PARAM_INT);
  $stmt->bindValue(':me3', $currentUserId, PDO::PARAM_INT);
  $stmt->execute();
  $myConnections = $stmt->fetchAll();
} catch (Throwable $e) {}

// Get conversations (most recent message per partner)
$conversations = [];
try {
  $stmt = $db->prepare("
    SELECT
      other_user_id,
      u.first_name, u.last_name,
      p.headline, p.avatar_url,
      latest.content AS last_message,
      latest.sent_at AS last_message_at,
      latest.sender_id AS last_sender_id,
      (
        SELECT COUNT(*) FROM messages m2
        WHERE m2.sender_id = other_user_id
        AND m2.receiver_id = :me_unread
        AND m2.is_read = 0
      ) AS unread_count
    FROM (
      SELECT
        IF(sender_id = :me1, receiver_id, sender_id) AS other_user_id,
        MAX(id) AS last_msg_id
      FROM messages
      WHERE sender_id = :me2 OR receiver_id = :me3
      GROUP BY other_user_id
      ORDER BY last_msg_id DESC
      LIMIT 20
    ) AS convos
    JOIN messages latest ON latest.id = convos.last_msg_id
    JOIN users u ON u.id = convos.other_user_id
    LEFT JOIN profiles p ON p.user_id = convos.other_user_id
    ORDER BY latest.sent_at DESC
  ");
  $stmt->bindValue(':me1', $currentUserId, PDO::PARAM_INT);
  $stmt->bindValue(':me2', $currentUserId, PDO::PARAM_INT);
  $stmt->bindValue(':me3', $currentUserId, PDO::PARAM_INT);
  $stmt->bindValue(':me_unread', $currentUserId, PDO::PARAM_INT);
  $stmt->execute();
  $conversations = $stmt->fetchAll();
} catch (Throwable $e) {}

// Get the active chat partner (first conversation or from query param)
$activeChatUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if (!$activeChatUserId && !empty($conversations)) {
  $activeChatUserId = (int) $conversations[0]['other_user_id'];
}

// Get active chat partner info
$chatPartner = null;
if ($activeChatUserId) {
  try {
    $stmt = $db->prepare('
      SELECT u.id, u.first_name, u.last_name, p.headline, p.avatar_url
      FROM users u LEFT JOIN profiles p ON p.user_id = u.id WHERE u.id = :id
    ');
    $stmt->execute([':id' => $activeChatUserId]);
    $chatPartner = $stmt->fetch();
  } catch (Throwable $e) {}
}

// Get messages for the active chat
$chatMessages = [];
if ($activeChatUserId) {
  try {
    $stmt = $db->prepare('
      SELECT m.id, m.sender_id, m.receiver_id, m.content, m.is_read, m.sent_at
      FROM messages m
      WHERE (m.sender_id = :me1 AND m.receiver_id = :them1)
         OR (m.sender_id = :them2 AND m.receiver_id = :me2)
      ORDER BY m.sent_at ASC
      LIMIT 100
    ');
    $stmt->execute([':me1' => $currentUserId, ':them1' => $activeChatUserId, ':them2' => $activeChatUserId, ':me2' => $currentUserId]);
    $chatMessages = $stmt->fetchAll();

    // Mark as read
    $stmt = $db->prepare('UPDATE messages SET is_read = 1 WHERE sender_id = :sid AND receiver_id = :rid AND is_read = 0');
    $stmt->execute([':sid' => $activeChatUserId, ':rid' => $currentUserId]);
  } catch (Throwable $e) {}
}

function getInitials($first, $last) {
  return strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1));
}

function timeAgo($datetime) {
  $diff = time() - strtotime($datetime);
  if ($diff < 60) return 'now';
  if ($diff < 3600) return floor($diff / 60) . 'm';
  if ($diff < 86400) return floor($diff / 3600) . 'h';
  if ($diff < 604800) return floor($diff / 86400) . 'd';
  return date('M j', strtotime($datetime));
}

function chatDateLabel($datetime) {
  $ts = strtotime($datetime);
  $today = strtotime('today');
  $yesterday = strtotime('yesterday');
  if ($ts >= $today) return 'Today';
  if ($ts >= $yesterday) return 'Yesterday';
  if ($ts >= strtotime('-6 days')) return date('l', $ts); // e.g. "Monday"
  return date('M j, Y', $ts);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Network & Messages · KoneKT</title>
  <meta name="description" content="Connect with professionals, manage your network, and send direct messages on KoneKT.">

  <?php // Google Fonts ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">

  <?php // Bootstrap 5 & Icons ?>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/theme.css" rel="stylesheet">
</head>
<body>

  <?php // Shared Navbar ?>
  <?php if (file_exists('includes/navbar.php')) include 'includes/navbar.php'; ?>

  <main class="container py-4">

    <?php // Search People & Companies ?>
    <div class="mb-4 position-relative" id="networkSearchWrap">
      <div class="konekt-card p-3">
        <div class="d-flex align-items-center gap-3">
          <div class="flex-grow-1 position-relative">
            <i class="bi bi-search" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--slate-light);font-size:0.9rem;"></i>
            <input type="text"
                   id="networkSearchInput"
                   class="form-control"
                   placeholder="Search people to connect with..."
                   autocomplete="off"
                   spellcheck="false"
                   style="padding-left:2.4rem;border-radius:24px;border-color:var(--line);"
                   aria-label="Search people">
          </div>
        </div>
      </div>

      <?php // Search Results Dropdown ?>
      <div class="konekt-search-dropdown" id="networkSearchDropdown" style="display:none;">
        <div id="networkSearchResults"></div>
        <div id="networkSearchEmpty" style="display:none;" class="konekt-search-empty">
          <i class="bi bi-search"></i>
          <p>No results found</p>
        </div>
        <div id="networkSearchLoading" style="display:none;" class="konekt-search-loading">
          <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
          <span>Searching...</span>
        </div>
      </div>
    </div>

    <div class="row g-4">

      <?php // Left Panel: Conversations & Pending Connections ?>
      <div class="col-lg-4">

        <?php // Connection Requests Card ?>
        <?php if (!empty($pendingRequests)): ?>
        <div class="konekt-card no-hover-lift p-3 mb-3" id="pendingRequestsCard">
          <h2 class="h6 mb-3 d-flex justify-content-between align-items-center">
            <span>Pending Requests</span>
            <span class="badge bg-primary rounded-pill" id="pendingCount"><?= count($pendingRequests) ?></span>
          </h2>

          <?php foreach ($pendingRequests as $req): ?>
          <div class="d-flex align-items-center justify-content-between py-2 border-bottom conn-request-row" id="conn-<?= $req['connection_id'] ?>">
            <div class="d-flex align-items-center gap-2">
              <div class="applicant-avatar" style="width:36px; height:36px; font-size: 0.75rem;">
                <?= getInitials($req['first_name'], $req['last_name']) ?>
              </div>
              <div>
                <p class="fw-semibold mb-0 small"><?= htmlspecialchars($req['first_name'] . ' ' . $req['last_name']) ?></p>
                <p class="text-secondary mb-0" style="font-size: 0.72rem;"><?= htmlspecialchars($req['headline'] ?? 'KoneKT User') ?></p>
              </div>
            </div>
            <div class="d-flex gap-1">
              <button class="btn btn-konekt-gold btn-sm py-0 px-2 conn-respond" data-id="<?= $req['connection_id'] ?>" data-action="accept"><i class="bi bi-check"></i></button>
              <button class="btn btn-outline-secondary btn-sm py-0 px-2 conn-respond" data-id="<?= $req['connection_id'] ?>" data-action="reject"><i class="bi bi-x"></i></button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php // Direct Messages Sidebar ?>
        <div class="konekt-card p-3">
          <h2 class="h6 mb-3">Messages</h2>

          <?php if (empty($conversations)): ?>
            <p class="text-secondary small mb-0">No conversations yet. Connect with people to start messaging.</p>
          <?php else: ?>
          <div class="list-group list-group-flush">
            <?php foreach ($conversations as $conv): ?>
            <a href="network.php?user_id=<?= $conv['other_user_id'] ?>"
               class="list-group-item list-group-item-action border-0 rounded p-2 mb-1 <?= $activeChatUserId == $conv['other_user_id'] ? 'active bg-light text-dark' : '' ?>"
               data-user-id="<?= $conv['other_user_id'] ?>"
               data-name="<?= htmlspecialchars($conv['first_name'] . ' ' . $conv['last_name']) ?>"
               data-subtitle="<?= htmlspecialchars($conv['headline'] ?? 'KoneKT User') ?>">
              <div class="d-flex align-items-center gap-2">
                <div class="position-relative">
                  <div class="applicant-avatar" style="width:38px; height:38px; font-size: 0.8rem;">
                    <?= getInitials($conv['first_name'], $conv['last_name']) ?>
                  </div>
                </div>
                <div class="flex-grow-1 overflow-hidden">
                  <div class="d-flex justify-content-between align-items-center">
                    <p class="fw-semibold mb-0 small text-truncate"><?= htmlspecialchars($conv['first_name'] . ' ' . $conv['last_name']) ?></p>
                    <span class="text-secondary" style="font-size: 0.7rem;"><?= timeAgo($conv['last_message_at']) ?></span>
                  </div>
                  <p class="text-secondary small mb-0 text-truncate">
                    <?php if ($conv['unread_count'] > 0): ?><strong><?php endif; ?>
                    <?= htmlspecialchars(mb_strimwidth($conv['last_message'], 0, 45, '...')) ?>
                    <?php if ($conv['unread_count'] > 0): ?></strong><?php endif; ?>
                  </p>
                </div>
                <?php if ($conv['unread_count'] > 0): ?>
                  <span class="badge bg-primary rounded-pill" style="font-size: 0.65rem;"><?= $conv['unread_count'] ?></span>
                <?php endif; ?>
              </div>
            </a>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

      </div>

      <?php // Right Panel: Active Chat Window ?>
      <div class="col-lg-8">
        <div class="konekt-card d-flex flex-column" style="min-height: 520px; height: 72vh;">

          <?php if ($chatPartner): ?>
          <?php // Chat Header ?>
          <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-white rounded-top">
            <div class="d-flex align-items-center gap-2">
              <div class="applicant-avatar" style="width:38px; height:38px; font-size: 0.8rem;">
                <?= getInitials($chatPartner['first_name'], $chatPartner['last_name']) ?>
              </div>
              <div>
                <h3 id="chatHeaderName" class="h6 mb-0"><?= htmlspecialchars($chatPartner['first_name'] . ' ' . $chatPartner['last_name']) ?></h3>
                <span id="chatHeaderSubtitle" class="text-secondary" style="font-size: 0.75rem;"><?= htmlspecialchars($chatPartner['headline'] ?? 'KoneKT User') ?></span>
              </div>
            </div>
            <button class="mobile-back-btn" id="mobileBackBtn" type="button" aria-label="Back to conversations">
              <i class="bi bi-arrow-left"></i> Back
            </button>
          </div>

          <?php // Messages Scrollable Body ?>
          <div class="p-3 flex-grow-1 overflow-auto" style="background-color: var(--mist);" id="chatBody">
            <div id="chatMessages" class="d-flex flex-column gap-3">
              <?php
                $lastDate = '';
                foreach ($chatMessages as $msg):
                  $msgDate = date('Y-m-d', strtotime($msg['sent_at']));
                  if ($msgDate !== $lastDate):
                    $lastDate = $msgDate;
              ?>
              <div class="chat-date-separator">
                <span><?= chatDateLabel($msg['sent_at']) ?></span>
              </div>
              <?php endif; ?>
              <div class="d-flex align-items-start gap-2 <?= $msg['sender_id'] == $currentUserId ? 'align-self-end' : '' ?> chat-msg-enter" style="max-width: 75%;" data-msg-id="<?= $msg['id'] ?>">
                <div class="p-3 rounded-3 shadow-sm <?= $msg['sender_id'] == $currentUserId ? 'text-white' : 'bg-white border' ?>"
                     style="<?= $msg['sender_id'] == $currentUserId ? 'background-color: var(--signal-blue);' : '' ?>">
                  <p class="mb-0 small"><?= htmlspecialchars($msg['content']) ?></p>
                  <span class="<?= $msg['sender_id'] == $currentUserId ? 'text-white-50' : 'text-secondary' ?> mt-1 d-block" style="font-size: 0.68rem;">
                    <?= date('g:i A', strtotime($msg['sent_at'])) ?>
                    <?php if ($msg['sender_id'] == $currentUserId): ?>
                      <span class="msg-read-receipt <?= $msg['is_read'] ? 'read' : 'sent' ?>">
                        <i class="bi bi-check2-all"></i>
                      </span>
                    <?php endif; ?>
                  </span>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <?php // Chat Input ?>
          <div class="p-3 border-top bg-white rounded-bottom">
            <form id="messageForm" class="d-flex gap-2">
              <input type="hidden" id="receiverId" value="<?= $activeChatUserId ?>">
              <input id="messageInput" type="text" class="form-control" placeholder="Write a message..." autocomplete="off" required>
              <button type="submit" class="btn btn-konekt-primary"><i class="bi bi-send-fill"></i></button>
            </form>
            <div id="messageStatus" class="form-text mt-2 small text-secondary"></div>
          </div>

          <?php else: ?>
          <?php // No chat selected ?>
          <div class="flex-grow-1 d-flex align-items-center justify-content-center">
            <div class="empty-state-network">
              <div class="empty-icon-wrap">
                <i class="bi bi-chat-dots"></i>
              </div>
              <h3 class="h5 mb-2">Select a conversation</h3>
              <p class="text-secondary mb-3">Choose a conversation from the left panel, or connect with someone to start messaging.</p>
              <button class="mobile-back-btn mx-auto" id="mobileBackBtnEmpty" type="button" style="display:none;">
                <i class="bi bi-arrow-left"></i> View Conversations
              </button>
            </div>
          </div>
          <?php endif; ?>

        </div>
      </div>

    </div>
  </main>

  <?php // Shared Footer ?>
  <?php if (file_exists('includes/footer.php')) include 'includes/footer.php'; ?>

  <script>
    window.currentUserId = <?= json_encode($currentUserId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  </script>
  <script src="assets/js/messaging.js"></script>
  <script src="assets/js/global-search.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Network Tab Switching & Mobile Toggle -->
  <script>
  (function() {
    'use strict';

    // --- Tab switching ---
    const tabs = document.querySelectorAll('.network-tab');
    const tabContents = {
      messages: document.getElementById('tabContentMessages'),
      connections: document.getElementById('tabContentConnections')
    };

    tabs.forEach(tab => {
      tab.addEventListener('click', () => {
        tabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        Object.values(tabContents).forEach(c => { if (c) c.classList.remove('active'); });
        const target = tabContents[tab.dataset.tab];
        if (target) target.classList.add('active');
      });
    });

    // --- Mobile back button ---
    const sidebar = document.getElementById('networkSidebar');
    const chatPanel = document.getElementById('networkChatPanel');
    const backBtn = document.getElementById('mobileBackBtn');
    const backBtnEmpty = document.getElementById('mobileBackBtnEmpty');

    function showSidebar() {
      if (sidebar) sidebar.classList.remove('mobile-hidden');
      if (chatPanel) chatPanel.classList.remove('mobile-active');
    }

    if (backBtn) backBtn.addEventListener('click', showSidebar);
    if (backBtnEmpty) {
      backBtnEmpty.style.display = '';
      backBtnEmpty.addEventListener('click', showSidebar);
    }

    // On mobile, clicking a conversation hides sidebar & shows chat
    if (window.innerWidth < 992) {
      if (sidebar && chatPanel && chatPanel.classList.contains('mobile-active')) {
        sidebar.classList.add('mobile-hidden');
      }
    }
  })();
  </script>
</body>
</html>
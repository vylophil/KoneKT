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

$active_page = 'upload';
$unread_messages = 0;
$saveMsg = '';
$saveOk  = false;

$db = getDB();

// Load existing preferences from profile
$profile = null;
try {
  $stmt = $db->prepare('SELECT location, industry FROM profiles WHERE user_id = :uid');
  $stmt->execute([':uid' => $_SESSION['user_id']]);
  $profile = $stmt->fetch();
} catch (Throwable $e) {}

// Handle POST — save preferences & trigger matching
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $degree       = trim($_POST['degree'] ?? '');
  $primarySkill = trim($_POST['primary_skill'] ?? '');
  $fields       = $_POST['fields'] ?? [];
  $location     = trim($_POST['location'] ?? '');
  $workSetup    = trim($_POST['work_setup'] ?? '');

  $industry = is_array($fields) ? implode(', ', $fields) : '';

  try {
    // Update profile with preferences
    $stmt = $db->prepare('UPDATE profiles SET industry = :industry, location = :location WHERE user_id = :uid');
    $stmt->execute([
      ':industry' => $industry,
      ':location' => $location,
      ':uid'      => $_SESSION['user_id'],
    ]);

    // Trigger matchmaking for this user
    // We call compute_matches logic inline rather than via HTTP
    $userIds = [$_SESSION['user_id']];
    $jobStmt = $db->query('SELECT id FROM job_postings WHERE is_active = 1');
    $jobIds  = $jobStmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($jobIds)) {
      $proficiencyWeights = ['beginner' => 0.25, 'intermediate' => 0.50, 'advanced' => 0.75, 'expert' => 1.00];
      $importanceWeights  = ['required' => 1.00, 'preferred' => 0.60, 'nice_to_have' => 0.30];
      $educationLevels    = ['none' => 0, 'high_school' => 1, 'associate' => 2, 'bachelors' => 3, 'masters' => 4, 'doctorate' => 5, 'certification' => 2, 'other' => 1];

      $upsertStmt = $db->prepare('
        INSERT INTO job_matches (user_id, job_id, match_score, skill_score, experience_score, education_score, computed_at)
        VALUES (:user_id, :job_id, :match_score, :skill_score, :experience_score, :education_score, NOW())
        ON DUPLICATE KEY UPDATE
          match_score = VALUES(match_score), skill_score = VALUES(skill_score),
          experience_score = VALUES(experience_score), education_score = VALUES(education_score), computed_at = NOW()
      ');

      foreach ($jobIds as $jid) {
        // Simplified scoring inline
        $uid = $_SESSION['user_id'];

        // Skill score
        $sStmt = $db->prepare('SELECT js.skill_id, js.importance FROM job_skills js WHERE js.job_id = :jid');
        $sStmt->execute([':jid' => $jid]);
        $jobSkills = $sStmt->fetchAll();

        $uStmt = $db->prepare('SELECT us.skill_id, us.proficiency_level, us.endorsement_count FROM user_skills us WHERE us.user_id = :uid');
        $uStmt->execute([':uid' => $uid]);
        $userSkillsMap = [];
        foreach ($uStmt->fetchAll() as $us) { $userSkillsMap[$us['skill_id']] = $us; }

        $skillScore = 100.0;
        if (!empty($jobSkills)) {
          $totalW = 0; $matchW = 0;
          foreach ($jobSkills as $js) {
            $imp = $importanceWeights[$js['importance']] ?? 1.0;
            $totalW += $imp;
            if (isset($userSkillsMap[$js['skill_id']])) {
              $prof = $proficiencyWeights[$userSkillsMap[$js['skill_id']]['proficiency_level']] ?? 0.25;
              $bonus = min(0.20, ($userSkillsMap[$js['skill_id']]['endorsement_count'] ?? 0) * 0.02);
              $matchW += $prof * $imp * (1 + $bonus);
            }
          }
          $skillScore = $totalW > 0 ? ($matchW / $totalW) * 100 : 0;
        }

        // Experience score
        $eStmt = $db->prepare('SELECT min_experience_years FROM job_postings WHERE id = :jid');
        $eStmt->execute([':jid' => $jid]);
        $reqYrs = (int)($eStmt->fetchColumn() ?: 0);
        $pStmt = $db->prepare('SELECT years_of_experience FROM profiles WHERE user_id = :uid');
        $pStmt->execute([':uid' => $uid]);
        $userYrs = (int)($pStmt->fetchColumn() ?: 0);
        $expScore = $reqYrs === 0 ? 100.0 : min(100.0, ($userYrs / max(1, $reqYrs)) * 100);

        // Education score
        $edStmt = $db->prepare('SELECT education_requirement FROM job_postings WHERE id = :jid');
        $edStmt->execute([':jid' => $jid]);
        $reqEd = $educationLevels[$edStmt->fetchColumn() ?? 'none'] ?? 0;
        $udStmt = $db->prepare("SELECT degree FROM education WHERE user_id = :uid ORDER BY FIELD(degree,'doctorate','masters','bachelors','associate','certification','high_school','other') LIMIT 1");
        $udStmt->execute([':uid' => $uid]);
        $userDeg = $udStmt->fetchColumn();
        $userEd = $educationLevels[$userDeg ?? 'none'] ?? 0;
        $eduScore = $reqEd === 0 ? 100.0 : ($userEd >= $reqEd ? 100.0 : ($userEd / max(1, $reqEd)) * 100);

        $total = round(min(100, $skillScore * 0.50 + $expScore * 0.30 + $eduScore * 0.20), 2);

        $upsertStmt->execute([
          ':user_id' => $uid, ':job_id' => (int)$jid,
          ':match_score' => $total, ':skill_score' => round($skillScore, 2),
          ':experience_score' => round($expScore, 2), ':education_score' => round($eduScore, 2),
        ]);
      }
    }

    $saveOk  = true;
    $saveMsg = 'Preferences saved! Your matches have been recomputed.';

    // Redirect to matches after a moment
    header('Location: job_matches.php?saved=1');
    exit;

  } catch (Throwable $e) {
    $saveMsg = 'Failed to save preferences: ' . $e->getMessage();
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Job Preferences · KoneKT</title>

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
    <div class="row justify-content-center">
      <div class="col-lg-8">

        <!-- Header Section -->
        <div class="text-center mb-4">
          <span class="match-chip mb-2">
            <i class="bi bi-sliders"></i> Step 2 of 2
          </span>
          <h1 class="h3 mb-2">Cross-Field Job Preferences</h1>
          <p class="text-secondary">Tell KoneKT where you'd like to apply your degree beyond traditional career paths.</p>
        </div>

        <?php if (!empty($saveMsg)): ?>
          <div class="alert <?= $saveOk ? 'alert-success' : 'alert-danger' ?> small"><?= htmlspecialchars($saveMsg) ?></div>
        <?php endif; ?>

        <form action="job_preferences.php" method="POST">

          <!-- Card 1: Academic & Professional Profile -->
          <div class="konekt-card p-4 mb-4">
            <h2 class="h6 mb-3 text-uppercase tracking-wider text-secondary">1. Academic Background</h2>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Degree / Field of Study</label>
                <select class="form-select" name="degree">
                  <option selected>BS Information Technology (BSIT)</option>
                  <option>BS Computer Science (BSCS)</option>
                  <option>BS Business Administration (BSBA)</option>
                  <option>BS Nursing / Allied Health</option>
                  <option>BS Civil Engineering</option>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold">Primary Skill Category</label>
                <select class="form-select" name="primary_skill">
                  <option selected>Database Admin & SQL</option>
                  <option>Network & Systems Admin</option>
                  <option>Software Development</option>
                  <option>Data Analysis & Excel</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Card 2: Target Industries Selection -->
          <div class="konekt-card p-4 mb-4">
            <h2 class="h6 mb-3 text-uppercase tracking-wider text-secondary">2. Target Industries</h2>
            <p class="text-secondary small mb-3">Select industries where you want our matchmaking engine to find cross-field opportunities:</p>

            <div class="d-flex flex-wrap gap-2 mb-3">
              <input type="checkbox" class="btn-check" id="field-med" name="fields[]" value="Healthcare" checked>
              <label class="btn btn-konekt-outline btn-sm rounded-pill px-3 py-2" for="field-med">
                <i class="bi bi-hospital me-1"></i> Healthcare / Med-Tech
              </label>

              <input type="checkbox" class="btn-check" id="field-acct" name="fields[]" value="Accounting" checked>
              <label class="btn btn-konekt-outline btn-sm rounded-pill px-3 py-2" for="field-acct">
                <i class="bi bi-calculator me-1"></i> Accounting / Fintech
              </label>

              <input type="checkbox" class="btn-check" id="field-sys" name="fields[]" value="Systems" checked>
              <label class="btn btn-konekt-outline btn-sm rounded-pill px-3 py-2" for="field-sys">
                <i class="bi bi-cpu me-1"></i> Systems Administration
              </label>

              <input type="checkbox" class="btn-check" id="field-edu" name="fields[]" value="Education">
              <label class="btn btn-konekt-outline btn-sm rounded-pill px-3 py-2" for="field-edu">
                <i class="bi bi-journal-bookmark me-1"></i> Education & EdTech
              </label>

              <input type="checkbox" class="btn-check" id="field-logistics" name="fields[]" value="Logistics">
              <label class="btn btn-konekt-outline btn-sm rounded-pill px-3 py-2" for="field-logistics">
                <i class="bi bi-truck me-1"></i> Logistics & Supply Chain
              </label>
            </div>
          </div>

          <!-- Card 3: Preferred Location & Work Style -->
          <div class="konekt-card p-4 mb-4">
            <h2 class="h6 mb-3 text-uppercase tracking-wider text-secondary">3. Location & Availability</h2>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Target Location</label>
                <input type="text" class="form-control" name="location" value="<?= htmlspecialchars($profile['location'] ?? 'Clark, Pampanga') ?>" placeholder="e.g. Clark, Manila, Remote">
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold">Work Setup Preference</label>
                <select class="form-select" name="work_setup">
                  <option>Hybrid</option>
                  <option>On-site</option>
                  <option>Remote</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="d-flex justify-content-between align-items-center">
            <a href="upload_resume.php" class="btn btn-konekt-outline px-4">&larr; Back to Resume Upload</a>
            <button type="submit" class="btn btn-konekt-primary px-4 py-2">
              <i class="bi bi-stars me-1"></i> Save & Generate Matches
            </button>
          </div>

        </form>

      </div>
    </div>
  </main>

  <!-- Shared Footer -->
  <?php if (file_exists('includes/footer.php')) include 'includes/footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
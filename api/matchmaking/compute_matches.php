<?php
// ============================================================
// KONEKT — Compute Match Scores (Matchmaking Engine)
// ============================================================
// POST /api/matchmaking/compute_matches.php
//
// Computes match scores between:
//   - A specific user and all active jobs
//   - A specific job and all active job seekers
//   - All users × all jobs (full recompute)
//
// Body (JSON):
//   - user_id  (int, optional — compute matches for this user)
//   - job_id   (int, optional — compute matches for this job)
//   - If neither is provided, recomputes all matches
//
// Match Score Formula:
//   Total = (Skill Score × 0.50) + (Experience Score × 0.30) + (Education Score × 0.20)
//
// Skill Score:
//   - Counts matching skills between user and job
//   - Weights by skill importance (required=1.0, preferred=0.6, nice_to_have=0.3)
//   - Weights by proficiency (beginner=0.25, intermediate=0.50, advanced=0.75, expert=1.0)
//   - Endorsements add a bonus multiplier
//
// Experience Score:
//   - Compares user's years_of_experience vs job's min_experience_years
//   - Bonus for relevant industry experience
//
// Education Score:
//   - Maps education levels to numeric values
//   - Compares user's highest degree vs job's requirement
// ============================================================

require_once __DIR__ . '/../auth/session.php';

requireMethod('POST');
requireAuth();

$data = getJsonBody();
$db   = getDB();

$targetUserId = isset($data['user_id']) ? (int) $data['user_id'] : null;
$targetJobId  = isset($data['job_id']) ? (int) $data['job_id'] : null;

// --- Proficiency weights ---
$proficiencyWeights = [
    'beginner'     => 0.25,
    'intermediate' => 0.50,
    'advanced'     => 0.75,
    'expert'       => 1.00,
];

// --- Skill importance weights ---
$importanceWeights = [
    'required'     => 1.00,
    'preferred'    => 0.60,
    'nice_to_have' => 0.30,
];

// --- Education level mapping (higher = better) ---
$educationLevels = [
    'none'          => 0,
    'high_school'   => 1,
    'associate'     => 2,
    'bachelors'     => 3,
    'masters'       => 4,
    'doctorate'     => 5,
    'certification' => 2, // equivalent to associate for scoring
    'other'         => 1,
];

// ============================================================
// HELPER: Compute skill score between a user and a job
// ============================================================
function computeSkillScore(PDO $db, int $userId, int $jobId, array $profWeights, array $impWeights): float
{
    // Get job's required skills
    $stmt = $db->prepare('
        SELECT js.skill_id, js.importance
        FROM job_skills js
        WHERE js.job_id = :job_id
    ');
    $stmt->execute([':job_id' => $jobId]);
    $jobSkills = $stmt->fetchAll();

    if (empty($jobSkills)) {
        return 100.0; // No skills required = perfect match
    }

    // Get user's skills
    $stmt = $db->prepare('
        SELECT us.skill_id, us.proficiency_level, us.endorsement_count
        FROM user_skills us
        WHERE us.user_id = :user_id
    ');
    $stmt->execute([':user_id' => $userId]);
    $userSkillsRaw = $stmt->fetchAll();

    $userSkills = [];
    foreach ($userSkillsRaw as $us) {
        $userSkills[$us['skill_id']] = $us;
    }

    $totalWeight   = 0;
    $matchedWeight = 0;

    foreach ($jobSkills as $js) {
        $importance = $impWeights[$js['importance']] ?? 1.0;
        $totalWeight += $importance;

        if (isset($userSkills[$js['skill_id']])) {
            $us = $userSkills[$js['skill_id']];
            $proficiency = $profWeights[$us['proficiency_level']] ?? 0.25;

            // Endorsement bonus (capped at 20% extra)
            $endorsementBonus = min(0.20, ($us['endorsement_count'] ?? 0) * 0.02);

            $skillMatch = $proficiency * $importance * (1 + $endorsementBonus);
            $matchedWeight += $skillMatch;
        }
    }

    return $totalWeight > 0 ? ($matchedWeight / $totalWeight) * 100 : 0;
}

// ============================================================
// HELPER: Compute experience score
// ============================================================
function computeExperienceScore(PDO $db, int $userId, int $jobId): float
{
    // Get job's experience requirements
    $stmt = $db->prepare('
        SELECT min_experience_years, experience_level
        FROM job_postings
        WHERE id = :job_id
    ');
    $stmt->execute([':job_id' => $jobId]);
    $job = $stmt->fetch();

    $requiredYears = (int) ($job['min_experience_years'] ?? 0);

    // Get user's years of experience from profile
    $stmt = $db->prepare('SELECT years_of_experience FROM profiles WHERE user_id = :user_id');
    $stmt->execute([':user_id' => $userId]);
    $profile = $stmt->fetch();

    $userYears = (int) ($profile['years_of_experience'] ?? 0);

    if ($requiredYears === 0) {
        return 100.0; // No experience required
    }

    // Calculate experience ratio (capped at 100%)
    $ratio = min(1.0, $userYears / $requiredYears);

    // Bonus for exceeding requirements (up to 10% extra for double the required years)
    if ($userYears > $requiredYears) {
        $excessRatio = min(1.0, ($userYears - $requiredYears) / $requiredYears);
        $ratio = min(1.0, $ratio + ($excessRatio * 0.10));
    }

    // Count relevant experience entries for recency bonus
    $stmt = $db->prepare('
        SELECT COUNT(*) as recent_count
        FROM experience
        WHERE user_id = :user_id
        AND (is_current = 1 OR end_date > DATE_SUB(NOW(), INTERVAL 3 YEAR))
    ');
    $stmt->execute([':user_id' => $userId]);
    $recentCount = (int) $stmt->fetchColumn();

    // Recency bonus (up to 10% for having recent experience)
    $recencyBonus = min(0.10, $recentCount * 0.03);

    return min(100.0, ($ratio + $recencyBonus) * 100);
}

// ============================================================
// HELPER: Compute education score
// ============================================================
function computeEducationScore(PDO $db, int $userId, int $jobId, array $eduLevels): float
{
    // Get job's education requirement
    $stmt = $db->prepare('SELECT education_requirement FROM job_postings WHERE id = :job_id');
    $stmt->execute([':job_id' => $jobId]);
    $job = $stmt->fetch();

    $requiredLevel = $eduLevels[$job['education_requirement'] ?? 'none'] ?? 0;

    if ($requiredLevel === 0) {
        return 100.0; // No education required
    }

    // Get user's highest education
    $stmt = $db->prepare('
        SELECT degree FROM education
        WHERE user_id = :user_id
        ORDER BY FIELD(degree, "doctorate", "masters", "bachelors", "associate", "certification", "high_school", "other") ASC
        LIMIT 1
    ');
    $stmt->execute([':user_id' => $userId]);
    $edu = $stmt->fetch();

    if (!$edu) {
        return 0.0; // No education entries
    }

    $userLevel = $eduLevels[$edu['degree']] ?? 0;

    if ($userLevel >= $requiredLevel) {
        return 100.0; // Meets or exceeds requirement
    }

    // Partial score for being close
    return ($userLevel / $requiredLevel) * 100;
}

// ============================================================
// MAIN: Compute matches
// ============================================================

// Determine which users and jobs to process
if ($targetUserId) {
    $userIds = [$targetUserId];
} else {
    $stmt = $db->query("SELECT id FROM users WHERE role = 'job_seeker' AND is_active = 1");
    $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

if ($targetJobId) {
    $jobIds = [$targetJobId];
} else {
    $stmt = $db->query('SELECT id FROM job_postings WHERE is_active = 1');
    $jobIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$matchCount = 0;

// Weight configuration
$weightSkill      = 0.50;
$weightExperience = 0.30;
$weightEducation  = 0.20;

foreach ($userIds as $uid) {
    foreach ($jobIds as $jid) {
        $skillScore      = computeSkillScore($db, (int)$uid, (int)$jid, $proficiencyWeights, $importanceWeights);
        $experienceScore = computeExperienceScore($db, (int)$uid, (int)$jid);
        $educationScore  = computeEducationScore($db, (int)$uid, (int)$jid, $educationLevels);

        $totalScore = ($skillScore * $weightSkill)
                    + ($experienceScore * $weightExperience)
                    + ($educationScore * $weightEducation);

        $totalScore = round(min(100, $totalScore), 2);

        // Upsert match record
        $stmt = $db->prepare('
            INSERT INTO job_matches (user_id, job_id, match_score, skill_score, experience_score, education_score, computed_at)
            VALUES (:user_id, :job_id, :match_score, :skill_score, :experience_score, :education_score, NOW())
            ON DUPLICATE KEY UPDATE
                match_score      = VALUES(match_score),
                skill_score      = VALUES(skill_score),
                experience_score = VALUES(experience_score),
                education_score  = VALUES(education_score),
                computed_at      = NOW()
        ');
        $stmt->execute([
            ':user_id'          => (int) $uid,
            ':job_id'           => (int) $jid,
            ':match_score'      => $totalScore,
            ':skill_score'      => round($skillScore, 2),
            ':experience_score' => round($experienceScore, 2),
            ':education_score'  => round($educationScore, 2),
        ]);

        $matchCount++;
    }
}

jsonSuccess([
    'matches_computed' => $matchCount,
    'users_processed'  => count($userIds),
    'jobs_processed'   => count($jobIds),
], 'Match scores computed successfully.');

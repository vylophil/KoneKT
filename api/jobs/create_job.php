<?php
// K
// POST /api/jobs/create_job.php
// Body (JSON):
//   - company_id            (int, required)
//   - title                 (string, required)
//   - description           (string, required)
//   - requirements          (string, optional)
//   - responsibilities      (string, optional)
//   - location              (string, optional)
//   - job_type              (string, required: full_time|part_time|contract|internship|freelance)
//   - work_arrangement      (string, optional: on_site|remote|hybrid)
//   - salary_min            (number, optional)
//   - salary_max            (number, optional)
//   - salary_currency       (string, optional, default 'PHP')
//   - experience_level      (string, optional: entry|mid|senior|executive)
//   - min_experience_years  (int, optional)
//   - education_requirement (string, optional: none|high_school|associate|bachelors|masters|doctorate)
//   - deadline              (string, optional, Y-m-d)
//   - skills                (array, optional: [{skill_id, importance}])

require_once __DIR__ . '/../auth/session.php';

requireMethod('POST');
$user = requireRole('employer');
$data = getJsonBody();

// Validate required fields
$errors = validateRequired($data, ['company_id', 'title', 'description', 'job_type']);
if (!empty($errors)) {
    jsonError('Validation failed.', 422, $errors);
}

$db = getDB();

// Verify company ownership
$stmt = $db->prepare('SELECT id FROM companies WHERE id = :id AND user_id = :user_id');
$stmt->execute([':id' => (int) $data['company_id'], ':user_id' => $user['id']]);

if (!$stmt->fetch()) {
    jsonError('Company not found or access denied.', 404);
}

// Validate enums
$validJobTypes = ['full_time', 'part_time', 'contract', 'internship', 'freelance'];
if (!validateEnum($data['job_type'], $validJobTypes)) {
    jsonError('Invalid job_type.', 422);
}

if (isset($data['work_arrangement']) && !validateEnum($data['work_arrangement'], ['on_site', 'remote', 'hybrid'])) {
    jsonError('Invalid work_arrangement.', 422);
}

if (isset($data['experience_level']) && !validateEnum($data['experience_level'], ['entry', 'mid', 'senior', 'executive'])) {
    jsonError('Invalid experience_level.', 422);
}

if (isset($data['education_requirement']) && !validateEnum($data['education_requirement'], ['none', 'high_school', 'associate', 'bachelors', 'masters', 'doctorate'])) {
    jsonError('Invalid education_requirement.', 422);
}

if (isset($data['deadline']) && !validateDate($data['deadline'])) {
    jsonError('Invalid deadline format. Use Y-m-d.', 422);
}

try {
    $db->beginTransaction();

    // Insert job posting
    $stmt = $db->prepare('
        INSERT INTO job_postings (
            company_id, employer_id, title, description, requirements,
            responsibilities, location, job_type, work_arrangement,
            salary_min, salary_max, salary_currency,
            experience_level, min_experience_years, education_requirement, deadline
        ) VALUES (
            :company_id, :employer_id, :title, :description, :requirements,
            :responsibilities, :location, :job_type, :work_arrangement,
            :salary_min, :salary_max, :salary_currency,
            :experience_level, :min_experience_years, :education_requirement, :deadline
        )
    ');
    $stmt->execute([
        ':company_id'            => (int) $data['company_id'],
        ':employer_id'           => $user['id'],
        ':title'                 => sanitizeString($data['title']),
        ':description'           => sanitizeString($data['description']),
        ':requirements'          => isset($data['requirements']) ? sanitizeString($data['requirements']) : null,
        ':responsibilities'      => isset($data['responsibilities']) ? sanitizeString($data['responsibilities']) : null,
        ':location'              => isset($data['location']) ? sanitizeString($data['location']) : null,
        ':job_type'              => $data['job_type'],
        ':work_arrangement'      => $data['work_arrangement'] ?? 'on_site',
        ':salary_min'            => isset($data['salary_min']) ? (float) $data['salary_min'] : null,
        ':salary_max'            => isset($data['salary_max']) ? (float) $data['salary_max'] : null,
        ':salary_currency'       => $data['salary_currency'] ?? 'PHP',
        ':experience_level'      => $data['experience_level'] ?? 'entry',
        ':min_experience_years'  => isset($data['min_experience_years']) ? (int) $data['min_experience_years'] : 0,
        ':education_requirement' => $data['education_requirement'] ?? 'none',
        ':deadline'              => $data['deadline'] ?? null,
    ]);

    $jobId = (int) $db->lastInsertId();

    // Attach skills if provided
    if (isset($data['skills']) && is_array($data['skills'])) {
        $skillStmt = $db->prepare('
            INSERT INTO job_skills (job_id, skill_id, importance)
            VALUES (:job_id, :skill_id, :importance)
        ');
        foreach ($data['skills'] as $skill) {
            $importance = $skill['importance'] ?? 'required';
            if (!validateEnum($importance, ['required', 'preferred', 'nice_to_have'])) {
                $importance = 'required';
            }
            $skillStmt->execute([
                ':job_id'     => $jobId,
                ':skill_id'   => (int) $skill['skill_id'],
                ':importance' => $importance,
            ]);
        }
    }

    $db->commit();

    jsonSuccess(['job_id' => $jobId], 'Job posting created successfully.', 201);

} catch (PDOException $e) {
    $db->rollBack();
    jsonError('Failed to create job posting: ' . $e->getMessage(), 500);
}

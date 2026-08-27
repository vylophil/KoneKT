<?php
// K
// PUT /api/jobs/update_job.php
// Body (JSON):
//   - job_id  (int, required)
//   - ...     (any fields from create_job, all optional)
//   - skills  (array, optional — replaces all job skills)

require_once __DIR__ . '/../auth/session.php';

requireMethod('PUT');
$user = requireRole('employer');
$data = getJsonBody();

if (!isset($data['job_id']) || !validatePositiveInt($data['job_id'])) {
    jsonError('Valid job_id is required.', 422);
}

$jobId = (int) $data['job_id'];
$db = getDB();

// Verify ownership
$stmt = $db->prepare('SELECT id FROM job_postings WHERE id = :id AND employer_id = :employer_id');
$stmt->execute([':id' => $jobId, ':employer_id' => $user['id']]);

if (!$stmt->fetch()) {
    jsonError('Job posting not found or access denied.', 404);
}

// Build dynamic update
$allowedFields = [
    'title', 'description', 'requirements', 'responsibilities',
    'location', 'job_type', 'work_arrangement',
    'salary_min', 'salary_max', 'salary_currency',
    'experience_level', 'min_experience_years', 'education_requirement',
    'is_active', 'deadline',
];

$setClauses = [];
$params     = [':id' => $jobId];

foreach ($allowedFields as $field) {
    if (isset($data[$field])) {
        $setClauses[]        = "{$field} = :{$field}";
        if (in_array($field, ['salary_min', 'salary_max'])) {
            $params[":{$field}"] = (float) $data[$field];
        } elseif (in_array($field, ['min_experience_years', 'is_active'])) {
            $params[":{$field}"] = (int) $data[$field];
        } else {
            $params[":{$field}"] = sanitizeString((string) $data[$field]);
        }
    }
}

try {
    $db->beginTransaction();

    if (!empty($setClauses)) {
        $sql = 'UPDATE job_postings SET ' . implode(', ', $setClauses) . ' WHERE id = :id';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    }

    // Replace skills if provided
    if (isset($data['skills']) && is_array($data['skills'])) {
        $db->prepare('DELETE FROM job_skills WHERE job_id = :job_id')
           ->execute([':job_id' => $jobId]);

        $skillStmt = $db->prepare('
            INSERT INTO job_skills (job_id, skill_id, importance)
            VALUES (:job_id, :skill_id, :importance)
        ');
        foreach ($data['skills'] as $skill) {
            $importance = $skill['importance'] ?? 'required';
            $skillStmt->execute([
                ':job_id'     => $jobId,
                ':skill_id'   => (int) $skill['skill_id'],
                ':importance' => $importance,
            ]);
        }
    }

    $db->commit();
    jsonSuccess(null, 'Job posting updated successfully.');

} catch (PDOException $e) {
    $db->rollBack();
    jsonError('Failed to update job posting: ' . $e->getMessage(), 500);
}

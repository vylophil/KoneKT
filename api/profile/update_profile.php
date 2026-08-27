<?php
// K
// PUT /api/profile/update_profile.php
// Body (JSON):
//   - headline           (string, optional)
//   - bio                (string, optional)
//   - location           (string, optional)
//   - phone              (string, optional)
//   - website            (string, optional)
//   - linkedin_url       (string, optional)
//   - github_url         (string, optional)
//   - industry           (string, optional)
//   - years_of_experience (int, optional)
// For employers, additional fields:
//   - company_name       (string, optional)
//   - company_description(string, optional)
//   - company_industry   (string, optional)
//   - company_website    (string, optional)
//   - company_location   (string, optional)
//   - company_size       (string, optional)
//   - founded_year       (int, optional)

require_once __DIR__ . '/../auth/session.php';

requireMethod('PUT');
$user = requireAuth();
$data = getJsonBody();

$db = getDB();

// Update profile fields
$allowedFields = [
    'headline', 'bio', 'location', 'phone', 'website',
    'linkedin_url', 'github_url', 'industry', 'years_of_experience',
];

$setClauses = [];
$params     = [':user_id' => $user['id']];

foreach ($allowedFields as $field) {
    if (isset($data[$field])) {
        $setClauses[]         = "{$field} = :{$field}";
        $params[":{$field}"]  = ($field === 'years_of_experience')
            ? (int) $data[$field]
            : sanitizeString($data[$field]);
    }
}

if (!empty($setClauses)) {
    $sql = 'UPDATE profiles SET ' . implode(', ', $setClauses) . ' WHERE user_id = :user_id';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
}

// Update first_name / last_name on users table
$userUpdates = [];
$userParams  = [':id' => $user['id']];

if (isset($data['first_name']) && trim($data['first_name']) !== '') {
    $userUpdates[]              = 'first_name = :first_name';
    $userParams[':first_name']  = sanitizeString($data['first_name']);
    $_SESSION['first_name']     = $userParams[':first_name'];
}
if (isset($data['last_name']) && trim($data['last_name']) !== '') {
    $userUpdates[]             = 'last_name = :last_name';
    $userParams[':last_name']  = sanitizeString($data['last_name']);
    $_SESSION['last_name']     = $userParams[':last_name'];
}

if (!empty($userUpdates)) {
    $sql = 'UPDATE users SET ' . implode(', ', $userUpdates) . ' WHERE id = :id';
    $stmt = $db->prepare($sql);
    $stmt->execute($userParams);
}

// Update company info (employer only)
if ($user['role'] === 'employer') {
    $companyFields = [
        'company_name'        => 'name',
        'company_description' => 'description',
        'company_industry'    => 'industry',
        'company_website'     => 'website',
        'company_location'    => 'location',
        'company_size'        => 'company_size',
        'founded_year'        => 'founded_year',
    ];

    // Check if company exists
    $stmt = $db->prepare('SELECT id FROM companies WHERE user_id = :user_id');
    $stmt->execute([':user_id' => $user['id']]);
    $existingCompany = $stmt->fetch();

    $companyData = [];
    foreach ($companyFields as $inputKey => $dbColumn) {
        if (isset($data[$inputKey])) {
            $companyData[$dbColumn] = ($dbColumn === 'founded_year')
                ? (int) $data[$inputKey]
                : sanitizeString($data[$inputKey]);
        }
    }

    if (!empty($companyData)) {
        if ($existingCompany) {
            // Update
            $setClauses = [];
            $params     = [':user_id' => $user['id']];
            foreach ($companyData as $col => $val) {
                $setClauses[]       = "{$col} = :{$col}";
                $params[":{$col}"]  = $val;
            }
            $sql = 'UPDATE companies SET ' . implode(', ', $setClauses) . ' WHERE user_id = :user_id';
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        } else {
            // Insert
            $companyData['user_id'] = $user['id'];
            if (!isset($companyData['name'])) {
                $companyData['name'] = $user['first_name'] . ' ' . $user['last_name'] . "'s Company";
            }
            $cols = implode(', ', array_keys($companyData));
            $placeholders = ':' . implode(', :', array_keys($companyData));
            $sql = "INSERT INTO companies ({$cols}) VALUES ({$placeholders})";
            $stmt = $db->prepare($sql);
            $params = [];
            foreach ($companyData as $col => $val) {
                $params[":{$col}"] = $val;
            }
            $stmt->execute($params);
        }
    }
}

jsonSuccess(null, 'Profile updated successfully.');

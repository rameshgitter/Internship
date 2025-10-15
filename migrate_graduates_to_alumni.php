<?php
// migrate_graduates_to_alumni.php
// Script to migrate graduated students to alumni_info and update their role
require_once 'db_config.php';

try {
    // 1. Find all graduated students not yet in alumni_info
    $stmt = $pdo->query("SELECT * FROM student_info WHERE status = 'Graduated' AND student_id NOT IN (SELECT alumni_id FROM alumni_info)");
    $graduates = $stmt->fetchAll();

    foreach ($graduates as $student) {
        // 2. Insert into alumni_info
        $insert = $pdo->prepare("INSERT INTO alumni_info (alumni_id, first_name, last_name, email, mobile_no, batch, graduation_year, degree, department_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $batch = ($student['enrolment_date'] ? date('Y', strtotime($student['enrolment_date'])) : '') . '-' . ($student['graduate_date'] ? date('Y', strtotime($student['graduate_date'])) : '');
        $insert->execute([
            $student['student_id'],
            $student['first_name'],
            $student['last_name'],
            $student['email'],
            $student['mobile_no'],
            $batch,
            $student['graduate_date'] ? date('Y', strtotime($student['graduate_date'])) : null,
            $student['student_type'],
            $student['department_id']
        ]);

        // 3. Update role in index table
        $update = $pdo->prepare("UPDATE `index` SET role = 'alumni' WHERE user_id = ?");
        $update->execute([$student['student_id']]);
    }
    echo "Migration complete. " . count($graduates) . " students migrated to alumni.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

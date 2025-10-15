<?php
// alumni_admin_approval.php - Admin page to approve/reject alumni registrations
session_start();
require_once 'db_config.php';

// Simple admin check (replace with real admin auth in production)
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    die('Access denied.');
}

// Approve or reject logic
if (isset($_GET['action'], $_GET['id'])) {
    $id = intval($_GET['id']);
    if ($_GET['action'] === 'approve') {
        // Move alumni from alumni_pending to alumni_info
        $stmt = $pdo->prepare("SELECT * FROM alumni_pending WHERE id = ?");
        $stmt->execute([$id]);
        $pending = $stmt->fetch();
        if ($pending) {
            $insert = $pdo->prepare("INSERT INTO alumni_info (alumni_id, first_name, last_name, email, graduation_year, degree, department_id, mobile_no, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insert->execute([
                $pending['alumni_id'], $pending['first_name'], $pending['last_name'], $pending['email'],
                $pending['graduation_year'], $pending['degree'], $pending['department_id'], $pending['mobile_no'], $pending['proof_document']
            ]);
            // Set password in alumni_info
            $pdo->prepare("UPDATE alumni_info SET password=? WHERE alumni_id=?")
                ->execute([$pending['password'], $pending['alumni_id']]);
            // Remove from pending
            $pdo->prepare("DELETE FROM alumni_pending WHERE id=?")->execute([$id]);
        }
    } elseif ($_GET['action'] === 'reject') {
        $pdo->prepare("UPDATE alumni_pending SET status='Rejected' WHERE id=?")->execute([$id]);
    }
    header('Location: alumni_admin_approval.php');
    exit;
}

// List all pending alumni
$stmt = $pdo->query("SELECT * FROM alumni_pending WHERE status='Pending'");
$pending_list = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Alumni Admin Approval</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container mt-5">
        <h2>Pending Alumni Registrations</h2>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th><th>Alumni ID</th><th>Name</th><th>Email</th><th>Graduation Year</th><th>Degree</th><th>Department</th><th>Proof</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pending_list as $row): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['alumni_id']) ?></td>
                    <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td><?= htmlspecialchars($row['graduation_year']) ?></td>
                    <td><?= htmlspecialchars($row['degree']) ?></td>
                    <td><?= htmlspecialchars($row['department_id']) ?></td>
                    <td><a href="<?= htmlspecialchars($row['proof_document']) ?>" target="_blank">View</a></td>
                    <td>
                        <a href="?action=approve&id=<?= $row['id'] ?>" class="btn btn-success btn-sm">Approve</a>
                        <a href="?action=reject&id=<?= $row['id'] ?>" class="btn btn-danger btn-sm">Reject</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

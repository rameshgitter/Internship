<?php
// hod_assign_staff.php - HOD assigns staff for verification tasks
session_start();
require_once 'db_config.php';

// Check if user is logged in and is HOD
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'hod') {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';
$hod_id = $_SESSION['user_id'];

// Get HOD info
$hod_info = $_SESSION['hod_info'] ?? null;
if (!$hod_info) {
    $stmt = $pdo->prepare("SELECT * FROM hod WHERE hod_id = ?");
    $stmt->execute([$hod_id]);
    $hod_info = $stmt->fetch();
    $_SESSION['hod_info'] = $hod_info;
}

// Handle staff assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_staff'])) {
        $staff_id = $_POST['staff_id'];
        $assignment_type = $_POST['assignment_type'];
        $department_id = $hod_info['department_id'] ?? '';
        
        try {
            // Check if assignment already exists
            $stmt = $pdo->prepare("
                SELECT * FROM staff_assignments 
                WHERE staff_id = ? AND assignment_type = ? AND is_active = 1
            ");
            $stmt->execute([$staff_id, $assignment_type]);
            
            if ($stmt->fetch()) {
                $error = "This staff member is already assigned to this task.";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO staff_assignments (staff_id, assigned_by, department_id, assignment_type)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$staff_id, $hod_id, $department_id, $assignment_type]);
                $success = "Staff assigned successfully!";
            }
        } catch (Exception $e) {
            $error = "Error assigning staff: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['remove_assignment'])) {
        $assignment_id = $_POST['assignment_id'];
        
        try {
            $stmt = $pdo->prepare("
                UPDATE staff_assignments 
                SET is_active = 0 
                WHERE assignment_id = ? AND assigned_by = ?
            ");
            $stmt->execute([$assignment_id, $hod_id]);
            $success = "Assignment removed successfully!";
        } catch (Exception $e) {
            $error = "Error removing assignment: " . $e->getMessage();
        }
    }
}

// Get available staff members
$available_staff = [];
try {
    $stmt = $pdo->prepare("
        SELECT uc.user_id, ei.first_name, ei.last_name, ei.email
        FROM user_credentials uc
        JOIN employee_info ei ON uc.user_id = ei.emp_id
        WHERE uc.role = 'staff' AND ei.emp_type = 'Staff'
        ORDER BY ei.first_name, ei.last_name
    ");
    $stmt->execute();
    $available_staff = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching staff: " . $e->getMessage());
}

// Get current assignments
$current_assignments = [];
try {
    $stmt = $pdo->prepare("
        SELECT sa.*, ei.first_name, ei.last_name, ei.email
        FROM staff_assignments sa
        JOIN employee_info ei ON sa.staff_id = ei.emp_id
        WHERE sa.assigned_by = ? AND sa.is_active = 1
        ORDER BY sa.assigned_date DESC
    ");
    $stmt->execute([$hod_id]);
    $current_assignments = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching assignments: " . $e->getMessage());
}

// Get verification statistics
$verification_stats = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            sa.staff_id,
            ei.first_name,
            ei.last_name,
            sa.assignment_type,
            COUNT(v.id) as total_verifications,
            COUNT(CASE WHEN DATE(v.created_at) = CURDATE() THEN 1 END) as today_verifications
        FROM staff_assignments sa
        JOIN employee_info ei ON sa.staff_id = ei.emp_id
        LEFT JOIN verification v ON sa.staff_id = v.verified_by
        WHERE sa.assigned_by = ? AND sa.is_active = 1
        GROUP BY sa.staff_id, sa.assignment_type
        ORDER BY total_verifications DESC
    ");
    $stmt->execute([$hod_id]);
    $verification_stats = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching verification stats: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Staff - HOD Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar {
            min-height: 100vh;
            background: #2c3e50;
            color: #fff;
            padding: 20px 0;
        }
        .sidebar .nav-link {
            color: #ccc;
            margin: 8px 0;
            padding: 10px 20px;
            border-radius: 4px;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: #34495e;
            color: #fff;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 sidebar">
                <h3 class="text-center mb-4">HOD Portal</h3>
                <div class="nav flex-column">
                    <a class="nav-link" href="hod_dashboard.php">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a class="nav-link active" href="hod_assign_staff.php">
                        <i class="fas fa-users"></i> Assign Staff
                    </a>
                    <a class="nav-link" href="hod_reports.php">
                        <i class="fas fa-chart-bar"></i> Reports
                    </a>
                    <a class="nav-link" href="profile.php">
                        <i class="fas fa-user"></i> Profile
                    </a>
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 col-lg-10 p-4">
                <h2>Staff Assignment Management</h2>
                <p class="text-muted">Assign staff members to verification tasks</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <!-- Assign New Staff -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-plus-circle"></i> Assign New Staff</h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="assign_staff" value="1">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="staff_id" class="form-label">Select Staff Member</label>
                                        <select name="staff_id" id="staff_id" class="form-select" required>
                                            <option value="">Choose Staff Member</option>
                                            <?php foreach ($available_staff as $staff): ?>
                                                <option value="<?= $staff['user_id'] ?>">
                                                    <?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) ?>
                                                    (<?= htmlspecialchars($staff['email']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="assignment_type" class="form-label">Assignment Type</label>
                                        <select name="assignment_type" id="assignment_type" class="form-select" required>
                                            <option value="">Choose Assignment Type</option>
                                            <option value="registration_verification">Registration Verification</option>
                                            <option value="marks_verification">Marks Verification</option>
                                            <option value="general">General Tasks</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Assign Staff
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Current Assignments -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list"></i> Current Staff Assignments</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($current_assignments)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No staff assignments yet. Assign staff members to verification tasks.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Staff Member</th>
                                            <th>Email</th>
                                            <th>Assignment Type</th>
                                            <th>Assigned Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($current_assignments as $assignment): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']) ?></td>
                                                <td><?= htmlspecialchars($assignment['email']) ?></td>
                                                <td>
                                                    <span class="badge bg-primary">
                                                        <?= ucwords(str_replace('_', ' ', $assignment['assignment_type'])) ?>
                                                    </span>
                                                </td>
                                                <td><?= date('M d, Y', strtotime($assignment['assigned_date'])) ?></td>
                                                <td>
                                                    <form method="post" class="d-inline" 
                                                          onsubmit="return confirm('Remove this assignment?')">
                                                        <input type="hidden" name="remove_assignment" value="1">
                                                        <input type="hidden" name="assignment_id" value="<?= $assignment['assignment_id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="fas fa-trash"></i> Remove
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Verification Statistics -->
                <?php if (!empty($verification_stats)): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Staff Performance Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Staff Member</th>
                                        <th>Assignment Type</th>
                                        <th>Total Verifications</th>
                                        <th>Today's Verifications</th>
                                        <th>Performance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($verification_stats as $stat): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($stat['first_name'] . ' ' . $stat['last_name']) ?></td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?= ucwords(str_replace('_', ' ', $stat['assignment_type'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success"><?= $stat['total_verifications'] ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?= $stat['today_verifications'] ?></span>
                                            </td>
                                            <td>
                                                <?php
                                                $performance = $stat['total_verifications'];
                                                if ($performance >= 50) {
                                                    echo '<span class="badge bg-success">Excellent</span>';
                                                } elseif ($performance >= 20) {
                                                    echo '<span class="badge bg-warning">Good</span>';
                                                } elseif ($performance >= 5) {
                                                    echo '<span class="badge bg-info">Average</span>';
                                                } else {
                                                    echo '<span class="badge bg-secondary">New</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
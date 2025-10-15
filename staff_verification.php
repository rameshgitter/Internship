<?php
// staff_verification.php - Staff verification of student registrations
session_start();
require_once 'db_config.php';

// Check if user is logged in and is employee
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'employee') {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';
$employee_id = $_SESSION['user_id'];

// Handle verification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_registration'])) {
        $registration_id = $_POST['registration_id'];
        $action = $_POST['action']; // 'approve' or 'reject'
        $notes = trim($_POST['notes'] ?? '');
        
        try {
            $pdo->beginTransaction();
            
            $status = ($action === 'approve') ? 'approved' : 'rejected';
            
            // Update registration status
            $stmt = $pdo->prepare("
                UPDATE semester_registration 
                SET status = ?, assigned_staff = ?, verification_notes = ?, verified_date = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$status, $employee_id, $notes, $registration_id]);
            
            // Log verification
            $stmt = $pdo->prepare("
                INSERT INTO verification (registration_id, verification_type, verified_by, verification_notes, status)
                VALUES (?, 'registration', ?, ?, ?)
            ");
            $stmt->execute([$registration_id, $employee_id, $notes, $status]);
            
            $pdo->commit();
            $success = "Registration " . ($action === 'approve' ? 'approved' : 'rejected') . " successfully!";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error updating registration: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['verify_marks'])) {
        $mark_id = $_POST['mark_id'];
        $action = $_POST['action'];
        $notes = trim($_POST['notes'] ?? '');
        
        try {
            $stmt = $pdo->prepare("
                UPDATE marks 
                SET verified_by = ?, verification_date = NOW()
                WHERE mark_id = ?
            ");
            $stmt->execute([$employee_id, $mark_id]);
            
            $success = "Marks verified successfully!";
            
        } catch (Exception $e) {
            $error = "Error verifying marks: " . $e->getMessage();
        }
    }
}

// Get pending registrations assigned to this employee or unassigned
$pending_registrations = [];
try {
    $stmt = $pdo->prepare("
        SELECT sr.*, si.first_name, si.last_name, si.student_id,
               s.semester_name, s.semester_number, s.academic_year
        FROM semester_registration sr
        JOIN student_info si ON sr.student_id = si.student_id
        LEFT JOIN semesters s ON sr.semester = s.semester_number
        WHERE sr.status = 'pending' 
        AND (sr.assigned_staff = ? OR sr.assigned_staff IS NULL)
        ORDER BY sr.sem_reg_date ASC
    ");
    $stmt->execute([$employee_id]);
    $pending_registrations = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching pending registrations: " . $e->getMessage());
}

// Get pending marks for verification
$pending_marks = [];
try {
    $stmt = $pdo->prepare("
        SELECT m.*, si.first_name, si.last_name, si.student_id,
               s.semester_name, s.academic_year
        FROM marks m
        JOIN student_info si ON m.student_id = si.student_id
        LEFT JOIN semesters s ON m.semester_id = s.semester_id
        WHERE m.verified_by IS NULL AND m.marksheet_pdf_path IS NOT NULL
        ORDER BY m.upload_date ASC
    ");
    $stmt->execute();
    $pending_marks = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching pending marks: " . $e->getMessage());
}

// Get verification statistics
$stats = [
    'pending_registrations' => count($pending_registrations),
    'pending_marks' => count($pending_marks),
    'verified_today' => 0,
    'total_verified' => 0
];

try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM verification 
        WHERE verified_by = ? AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$employee_id]);
    $stats['verified_today'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM verification 
        WHERE verified_by = ?
    ");
    $stmt->execute([$employee_id]);
    $stats['total_verified'] = $stmt->fetchColumn();
} catch (Exception $e) {
    error_log("Error fetching stats: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Verification Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar {
            min-height: 100vh;
            background: #343a40;
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
            background: #495057;
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
        .stat-card h4 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
        }
        .stat-card.pending {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .stat-card.verified {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 sidebar">
                <h3>Employee Portal</h3>
                <div class="nav flex-column">
                    <a class="nav-link active" href="staff_dashboard.php">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a class="nav-link" href="staff_verification.php">
                        <i class="fas fa-check-circle"></i> Verification
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
                <h2>Verification Portal</h2>
                <p class="text-muted">Review and verify student registrations and marksheets</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card pending">
                            <h4><?= $stats['pending_registrations'] ?></h4>
                            <p>Pending Registrations</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card pending">
                            <h4><?= $stats['pending_marks'] ?></h4>
                            <p>Pending Marksheets</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card verified">
                            <h4><?= $stats['verified_today'] ?></h4>
                            <p>Verified Today</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <h4><?= $stats['total_verified'] ?></h4>
                            <p>Total Verified</p>
                        </div>
                    </div>
                </div>

                <!-- Pending Registrations -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-clipboard-check"></i> Pending Registration Verifications</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pending_registrations)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <p class="text-muted">No pending registrations to verify!</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                            <th>Student ID</th>
                                            <th>Semester</th>
                                            <th>Registration Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pending_registrations as $reg): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($reg['first_name'] . ' ' . $reg['last_name']) ?></td>
                                                <td><?= htmlspecialchars($reg['student_id']) ?></td>
                                                <td>Semester <?= htmlspecialchars($reg['semester']) ?></td>
                                                <td><?= date('M d, Y', strtotime($reg['sem_reg_date'])) ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-success" 
                                                            onclick="showVerificationModal(<?= $reg['id'] ?>, 'approve', 'registration')">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" 
                                                            onclick="showVerificationModal(<?= $reg['id'] ?>, 'reject', 'registration')">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pending Marksheets -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-file-alt"></i> Pending Marksheet Verifications</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pending_marks)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <p class="text-muted">No pending marksheets to verify!</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                            <th>Student ID</th>
                                            <th>Semester</th>
                                            <th>Upload Date</th>
                                            <th>Marksheet</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pending_marks as $mark): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($mark['first_name'] . ' ' . $mark['last_name']) ?></td>
                                                <td><?= htmlspecialchars($mark['student_id']) ?></td>
                                                <td><?= htmlspecialchars($mark['semester_name'] ?? 'Semester ' . $mark['semester_id']) ?></td>
                                                <td><?= date('M d, Y', strtotime($mark['upload_date'])) ?></td>
                                                <td>
                                                    <a href="<?= htmlspecialchars($mark['marksheet_pdf_path']) ?>" 
                                                       class="btn btn-sm btn-outline-primary" target="_blank">
                                                        <i class="fas fa-file-pdf"></i> View PDF
                                                    </a>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-success" 
                                                            onclick="showVerificationModal(<?= $mark['mark_id'] ?>, 'verify', 'marks')">
                                                        <i class="fas fa-check"></i> Verify
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Verification Modal -->
    <div class="modal fade" id="verificationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Verify Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" id="verificationForm">
                    <div class="modal-body">
                        <input type="hidden" id="itemId" name="registration_id">
                        <input type="hidden" id="action" name="action">
                        <input type="hidden" id="verificationType" name="verification_type">
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="Add any verification notes..."></textarea>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            <span id="actionText">This action will be recorded in the system.</span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="confirmBtn">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showVerificationModal(itemId, action, type) {
            const modal = new bootstrap.Modal(document.getElementById('verificationModal'));
            const form = document.getElementById('verificationForm');
            
            // Set form action based on type
            if (type === 'registration') {
                form.innerHTML = form.innerHTML.replace('name="registration_id"', 'name="registration_id"');
                form.innerHTML += '<input type="hidden" name="verify_registration" value="1">';
            } else if (type === 'marks') {
                form.innerHTML = form.innerHTML.replace('name="registration_id"', 'name="mark_id"');
                form.innerHTML += '<input type="hidden" name="verify_marks" value="1">';
            }
            
            document.getElementById('itemId').value = itemId;
            document.getElementById('action').value = action;
            document.getElementById('verificationType').value = type;
            
            // Update modal content based on action
            const modalTitle = document.getElementById('modalTitle');
            const actionText = document.getElementById('actionText');
            const confirmBtn = document.getElementById('confirmBtn');
            
            if (action === 'approve') {
                modalTitle.textContent = 'Approve Registration';
                actionText.textContent = 'This registration will be approved and the student will be notified.';
                confirmBtn.textContent = 'Approve';
                confirmBtn.className = 'btn btn-success';
            } else if (action === 'reject') {
                modalTitle.textContent = 'Reject Registration';
                actionText.textContent = 'This registration will be rejected and the student will be notified.';
                confirmBtn.textContent = 'Reject';
                confirmBtn.className = 'btn btn-danger';
            } else if (action === 'verify') {
                modalTitle.textContent = 'Verify Marksheet';
                actionText.textContent = 'This marksheet will be marked as verified.';
                confirmBtn.textContent = 'Verify';
                confirmBtn.className = 'btn btn-success';
            }
            
            modal.show();
        }
    </script>
</body>
</html>
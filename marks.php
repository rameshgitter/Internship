<?php
session_start();
require_once 'db_config.php';

// Enable error display for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';

// Get student information from the correct table
$stmt = $pdo->prepare("SELECT * FROM student_info WHERE student_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();

if (!$student) {
    $error = "Student information not found.";
}

// Get student's marks from student_marks table (uploaded marksheets)
$stmt = $pdo->prepare("
    SELECT 
        sm.*,
        COALESCE(sp.subject_name, sm.subject_name) AS subject_name,
        COALESCE(sp.credit, sm.credit) AS credit,
        COALESCE(sp.subject_code, sm.subject_code) AS subject_code,
        sm.semester
    FROM student_marks sm
    LEFT JOIN subjects_pool sp ON sm.subject_code = sp.subject_code
    WHERE sm.student_id = ?
    ORDER BY sm.semester ASC, subject_name ASC
");
$stmt->execute([$_SESSION['user_id']]);
$marks = $stmt->fetchAll();

// Calculate CGPA and SGPA from student_marks
$total_credits = 0;
$total_gp = 0;
$sgpa = [];
$sem_credits = [];
$sem_gp = [];

foreach ($marks as $mark) {
    $credit = isset($mark['credit']) ? (int)$mark['credit'] : 0;
    $gp = isset($mark['total_grade_point']) ? (int)$mark['total_grade_point'] : 0;
    $sem = isset($mark['semester']) ? (int)$mark['semester'] : 0;

    if ($credit > 0) {
        $total_credits += $credit;
        $total_gp += $gp;

        if (!isset($sem_credits[$sem])) { $sem_credits[$sem] = 0; }
        if (!isset($sem_gp[$sem])) { $sem_gp[$sem] = 0; }
        $sem_credits[$sem] += $credit;
        $sem_gp[$sem] += $gp;
    }
}

$cgpa = $total_credits > 0 ? round($total_gp / $total_credits, 2) : 0.00;
foreach ($sem_credits as $s => $c) {
    if ($c > 0) {
        $sgpa[$s] = round($sem_gp[$s] / $c, 2);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marks - Student Portal</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome for Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Font: Roboto -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 0;
            color: #444;
        }

        /* Sidebar */
        .sidebar {
            min-height: 100vh;
            background: #343a40;
            color: #fff;
            padding: 20px 0;
        }

        .sidebar h3 {
            color: #fff;
            font-weight: 500;
            text-align: center;
            margin-bottom: 30px;
        }

        .sidebar .nav-link {
            color: #ccc;
            margin: 8px 0;
            padding: 10px 20px;
            border-radius: 4px;
            transition: background 0.3s, color 0.3s;
        }

        .sidebar .nav-link i {
            margin-right: 10px;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: #495057;
            color: #fff;
        }

        /* Main Content */
        .main-content {
            padding: 30px 20px;
        }

        .main-content h2 {
            font-weight: 500;
            color: #343a40;
        }

        /* Card Styling */
        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }

        .card-header {
            background-color: #e9ecef;
            border-bottom: none;
            padding: 15px 20px;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }

        .card-title {
            margin: 0;
            font-weight: 500;
            color: #495057;
        }

        /* Statistics Cards */
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .stat-card h4 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
        }

        .stat-card p {
            margin: 5px 0 0 0;
            opacity: 0.9;
        }

        /* Table Styling */
        .table-responsive {
            margin-top: 15px;
        }

        th {
            background-color: #f1f3f5;
            font-weight: 500;
        }

        td, th {
            vertical-align: middle;
            padding: 12px;
        }

        /* Grade badges */
        .grade-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .grade-a { background-color: #d4edda; color: #155724; }
        .grade-b { background-color: #d1ecf1; color: #0c5460; }
        .grade-c { background-color: #fff3cd; color: #856404; }
        .grade-d { background-color: #f8d7da; color: #721c24; }

        /* Responsive */
        @media (max-width: 767.98px) {
            .sidebar {
                text-align: center;
            }
            .main-content {
                padding: 15px 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 sidebar">
                <h3>Student Portal</h3>
                <div class="nav flex-column">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a class="nav-link" href="semester_registration.php">
                        <i class="fas fa-calendar-plus"></i> Register Semester
                    </a>
                    <a class="nav-link active" href="marks.php">
                        <i class="fas fa-file-alt"></i> Marks
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
            <main class="col-md-9 col-lg-10 main-content">
                <!-- Header -->
                <div class="row mb-4">
                    <div class="col">
                        <h2>Academic Performance</h2>
                        <p class="text-muted">
                            Student ID: <?= htmlspecialchars($student['student_id'] ?? '') ?>
                        </p>
                    </div>
                    <div class="col-auto d-flex align-items-center">
                        <a href="upload_marksheet.php" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Upload Marksheet
                        </a>
                    </div>
                </div>

                <!-- Alerts -->
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <!-- CGPA/SGPA Statistics Row -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                            <h4><?= $cgpa ?></h4>
                            <p>CGPA</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <h4><?= $total_credits ?></h4>
                            <p>Total Credits</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <h4>
                                <?php
                                if (!empty($sgpa)) {
                                    foreach ($sgpa as $sem => $val) {
                                        echo "Sem $sem: $val ";
                                    }
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </h4>
                            <p>SGPA (per semester)</p>
                        </div>
                    </div>
                </div>

                <!-- Marks Table -->
                <div class="row">
                    <div class="col">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Course-wise Marks</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($marks)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No marks available yet.</p>
                                        <p class="text-muted">Your marks will appear here once you upload your marksheet.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Semester</th>
                                                    <th>Subject Code</th>
                                                    <th>Subject Name</th>
                                                    <th>Credit</th>
                                                    <th>Letter Grade</th>
                                                    <th>Total Grade Point</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($marks as $mark): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($mark['semester'] ?? 'N/A') ?></td>
                                                        <td><?= htmlspecialchars($mark['subject_code'] ?? 'N/A') ?></td>
                                                        <td><?= htmlspecialchars($mark['subject_name'] ?? 'N/A') ?></td>
                                                        <td><?= htmlspecialchars($mark['credit'] ?? 'N/A') ?></td>
                                                        <td><?= htmlspecialchars($mark['letter_grade'] ?? 'N/A') ?></td>
                                                        <td><?= htmlspecialchars($mark['total_grade_point'] ?? 'N/A') ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
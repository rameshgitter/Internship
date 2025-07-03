<?php
session_start();
require_once 'db_config.php';

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

// Get student's marks from student_marks table
$stmt = $pdo->prepare("
    SELECT 
        sm.*,
        sp.subject_name,
        sp.credit,
        sp.subject_code
    FROM student_marks sm
    LEFT JOIN subjects_pool sp ON sm.subject_code = sp.subject_code
    WHERE sm.student_id = ?
    ORDER BY sm.semester ASC, sp.subject_name ASC
");
$stmt->execute([$_SESSION['user_id']]);
$marks = $stmt->fetchAll();

// Calculate overall statistics
$total_credits = 0;
$weighted_marks = 0;
$total_courses = count($marks);
$total_marks_sum = 0;
$courses_with_marks = 0;

foreach ($marks as $mark) {
    if ($mark['total_marks'] !== null && $mark['total_marks'] > 0) {
        $total_marks_sum += $mark['total_marks'];
        $courses_with_marks++;
        if ($mark['credit']) {
            $total_credits += $mark['credit'];
            $weighted_marks += $mark['total_marks'] * $mark['credit'];
        }
    }
}

$overall_percentage = $courses_with_marks > 0 ? $total_marks_sum / $courses_with_marks : 0;
$weighted_percentage = $total_credits > 0 ? $weighted_marks / $total_credits : 0;
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
                </div>

                <!-- Alerts -->
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <!-- Statistics Row -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <h4><?= number_format($overall_percentage, 1) ?>%</h4>
                            <p>Average Percentage</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <h4><?= $total_courses ?></h4>
                            <p>Total Courses</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <h4><?= $total_credits ?></h4>
                            <p>Total Credits</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                            <h4><?php
                                if ($overall_percentage >= 90) echo 'A+';
                                elseif ($overall_percentage >= 80) echo 'A';
                                elseif ($overall_percentage >= 70) echo 'B';
                                elseif ($overall_percentage >= 60) echo 'C';
                                elseif ($overall_percentage >= 50) echo 'D';
                                else echo 'F';
                            ?></h4>
                            <p>Overall Grade</p>
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
                                        <p class="text-muted">Your marks will appear here once they are uploaded by professors.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Subject Code</th>
                                                    <th>Subject Name</th>
                                                    <th>Semester</th>
                                                    <th>Academic Year</th>
                                                    <th>Internal Marks</th>
                                                    <th>External Marks</th>
                                                    <th>Total Marks</th>
                                                    <th>Grade</th>
                                                    <th>Credits</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($marks as $mark): ?>
                                                    <?php
                                                        $total = $mark['total_marks'] ?? 0;
                                                        $grade = $mark['grade'] ?? 'N/A';
                                                        $gradeClass = '';
                                                        
                                                        if ($total >= 90) {
                                                            $gradeClass = 'grade-a';
                                                        } elseif ($total >= 80) {
                                                            $gradeClass = 'grade-a';
                                                        } elseif ($total >= 70) {
                                                            $gradeClass = 'grade-b';
                                                        } elseif ($total >= 60) {
                                                            $gradeClass = 'grade-c';
                                                        } elseif ($total >= 50) {
                                                            $gradeClass = 'grade-c';
                                                        } else {
                                                            $gradeClass = 'grade-d';
                                                        }
                                                    ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($mark['subject_code'] ?? 'N/A') ?></td>
                                                        <td><?= htmlspecialchars($mark['subject_name'] ?? 'N/A') ?></td>
                                                        <td><?= htmlspecialchars($mark['semester'] ?? 'N/A') ?></td>
                                                        <td><?= htmlspecialchars($mark['academic_year'] ?? 'N/A') ?></td>
                                                        <td><?= htmlspecialchars($mark['internal_marks'] ?? 'N/A') ?></td>
                                                        <td><?= htmlspecialchars($mark['external_marks'] ?? 'N/A') ?></td>
                                                        <td><?= htmlspecialchars($mark['total_marks'] ?? 'N/A') ?></td>
                                                        <td>
                                                            <?php if ($grade !== 'N/A'): ?>
                                                                <span class="grade-badge <?= $gradeClass ?>"><?= htmlspecialchars($grade) ?></span>
                                                            <?php else: ?>
                                                                N/A
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?= htmlspecialchars($mark['credit'] ?? 'N/A') ?></td>
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
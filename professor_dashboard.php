<?php
session_start();
require_once 'db_config.php';

// Check if user is logged in and is a professor
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header('Location: login.php');
    exit();
}

$professor_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get professor information
$stmt = $pdo->prepare("SELECT * FROM staff_info WHERE staff_id = ? AND staff_type = 'Professor'");
$stmt->execute([$professor_id]);
$professor = $stmt->fetch();

// Get all subjects from subjects_pool (created by HOD)
$subjects = $pdo->query("SELECT * FROM subjects_pool ORDER BY subject_semester, subject_code")->fetchAll();

// Get all students for reference
$students = $pdo->query("SELECT student_id, first_name, last_name, department_id FROM student_info ORDER BY student_id")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professor Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 0;
            color: #444;
        }
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
        .main-content {
            padding: 30px 20px;
        }
        .main-content h2 {
            font-weight: 500;
            color: #343a40;
        }
        .main-content p.text-muted {
            font-size: 0.9rem;
        }
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
        .table-responsive {
            max-height: 400px;
            overflow-y: auto;
        }
        @media (max-width: 767.98px) {
            .sidebar {
                text-align: center;
            }
            .sidebar .nav-link {
                margin: 5px auto;
                width: 90%;
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
                <h3>Professor Portal</h3>
                <div class="nav flex-column">
                    <a class="nav-link active" href="#dashboard" onclick="showSection('dashboard')">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a class="nav-link" href="#subjects" onclick="showSection('subjects')">
                        <i class="fas fa-book"></i> View Subjects
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
                <!-- Welcome Header -->
                <div class="row mb-4">
                    <div class="col">
                        <h2>Welcome, Professor <?= htmlspecialchars($professor['first_name'] . ' ' . $professor['last_name']) ?></h2>
                        <p class="text-muted">Staff ID: <?= htmlspecialchars($professor['staff_id']) ?></p>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Dashboard Overview -->
                <div id="dashboard" class="section">
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3 class="text-primary"><?= count($subjects) ?></h3>
                                    <p>Available Subjects</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3 class="text-success"><?= count($students) ?></h3>
                                    <p>Total Students</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3 class="text-info"><?= count(array_unique(array_column($subjects, 'subject_semester'))) ?></h3>
                                    <p>Semesters</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Professor Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Name:</strong> <?= htmlspecialchars($professor['first_name'] . ' ' . $professor['last_name']) ?></p>
                                    <p><strong>Staff ID:</strong> <?= htmlspecialchars($professor['staff_id']) ?></p>
                                    <p><strong>Department:</strong> <?= htmlspecialchars($professor['department_id']) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Designation:</strong> <?= htmlspecialchars($professor['designation']) ?></p>
                                    <p><strong>Email:</strong> <?= htmlspecialchars($professor['email']) ?></p>
                                    <p><strong>Mobile:</strong> <?= htmlspecialchars($professor['mobile_no']) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Subjects Section -->
                <div id="subjects" class="section" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">All Subjects (Created by HOD)</h5>
                            <small class="text-muted">Note: Subjects are created and managed by HOD</small>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Subject Code</th>
                                            <th>Subject Name</th>
                                            <th>Type</th>
                                            <th>Semester</th>
                                            <th>Credits</th>
                                            <th>Lecture Hours</th>
                                            <th>Tutorial Hours</th>
                                            <th>Practical Hours</th>
                                            <th>Taught In</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($subjects as $subject): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($subject['subject_code']) ?></td>
                                            <td><?= htmlspecialchars($subject['subject_name']) ?></td>
                                            <td><?= htmlspecialchars($subject['subject_type']) ?></td>
                                            <td><?= htmlspecialchars($subject['subject_semester']) ?></td>
                                            <td><?= htmlspecialchars($subject['credit']) ?></td>
                                            <td><?= htmlspecialchars($subject['lecture_hours']) ?></td>
                                            <td><?= htmlspecialchars($subject['tutorial_hours']) ?></td>
                                            <td><?= htmlspecialchars($subject['practical_hours']) ?></td>
                                            <td><?= htmlspecialchars($subject['taught_in']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showSection(sectionId) {
            // Hide all sections
            const sections = document.querySelectorAll('.section');
            sections.forEach(section => {
                section.style.display = 'none';
            });
            
            // Show selected section
            document.getElementById(sectionId).style.display = 'block';
            
            // Update active nav link
            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                link.classList.remove('active');
            });
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
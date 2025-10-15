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

// Get all students for marks upload
$students = $pdo->query("SELECT student_id, first_name, last_name, department_id FROM student_info ORDER BY student_id")->fetchAll();

// Handle marks upload (placeholder for now as requested to skip)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_marks'])) {
    // This will be implemented later as requested to skip for now
    $success = "Marks upload feature will be implemented later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professor Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f4f6f9;
        }
        .sidebar {
            min-height: 100vh;
            background: #343a40;
            color: #fff;
            padding: 20px 0;
        }
        .sidebar h3 {
            color: #fff;
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
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: #495057;
            color: #fff;
        }
        .main-content {
            padding: 30px 20px;
        }
        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }
        .table-responsive {
            max-height: 400px;
            overflow-y: auto;
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
                    <a class="nav-link" href="#marks" onclick="showSection('marks')">
                        <i class="fas fa-chart-line"></i> Marks Upload
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
                                    <h3 class="text-info">0</h3>
                                    <p>Marks Uploaded</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5>Professor Information</h5>
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
                            <h5>All Subjects (Created by HOD)</h5>
                            <small class="text-muted">Note: Subjects are now created and managed by HOD</small>
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

                <!-- Marks Section -->
                <div id="marks" class="section" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <h5>Marks Upload</h5>
                            <small class="text-muted">Feature to be implemented later</small>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                The marks upload feature will be implemented in the next phase of development.
                                This will include:
                                <ul class="mt-2 mb-0">
                                    <li>Upload marks for students by subject</li>
                                    <li>Internal and external marks entry</li>
                                    <li>Grade calculation</li>
                                    <li>Bulk upload via CSV/Excel</li>
                                </ul>
                            </div>
                            
                            <form method="POST" class="mt-4">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Select Subject</label>
                                            <select name="subject_code" class="form-select" disabled>
                                                <option value="">Select Subject</option>
                                                <?php foreach ($subjects as $subject): ?>
                                                <option value="<?= $subject['subject_code'] ?>"><?= $subject['subject_code'] ?> - <?= $subject['subject_name'] ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Select Student</label>
                                            <select name="student_id" class="form-select" disabled>
                                                <option value="">Select Student</option>
                                                <?php foreach ($students as $student): ?>
                                                <option value="<?= $student['student_id'] ?>"><?= $student['student_id'] ?> - <?= $student['first_name'] . ' ' . $student['last_name'] ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Internal Marks</label>
                                            <input type="number" name="internal_marks" class="form-control" min="0" max="100" disabled>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">External Marks</label>
                                            <input type="number" name="external_marks" class="form-control" min="0" max="100" disabled>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Grade</label>
                                            <select name="grade" class="form-select" disabled>
                                                <option value="">Auto Calculate</option>
                                                <option value="A+">A+</option>
                                                <option value="A">A</option>
                                                <option value="B+">B+</option>
                                                <option value="B">B</option>
                                                <option value="C+">C+</option>
                                                <option value="C">C</option>
                                                <option value="D">D</option>
                                                <option value="F">F</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" name="upload_marks" class="btn btn-primary" disabled>
                                    <i class="fas fa-upload"></i> Upload Marks (Coming Soon)
                                </button>
                            </form>
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
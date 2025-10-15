<?php
session_start();
require_once 'db_config.php';

// Check if user is logged in and is HOD
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'hod') {
    header('Location: login.php');
    exit();
}

$hod_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get HOD information
$stmt = $pdo->prepare("SELECT * FROM hod WHERE hod_id = ?");
$stmt->execute([$hod_id]);
$hod = $stmt->fetch();

// Handle semester and subject creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['bulk_add_sem6'])) {
        try {
            $pdo->beginTransaction();
            $defs = [
                ['UG','Core','CS3210','2020-01-01','Operating Systems',6,4,3,1,0],
                ['UG','Core','CS3220','2020-01-01','Data Communication and Computer Networks',6,4,3,1,0],
                ['UG','Core','CS3230','2020-01-01','Information Security and Cryptography',6,3,3,0,0],
                ['UG','Core','CS3240','2020-01-01','Software Engineering',6,3,3,0,0],
                ['UG','Core','CS3261','2020-01-01','Operating Systems Laboratory',6,2,0,0,2],
                ['UG','Core','CS3262','2020-01-01','Networks Laboratory',6,2,0,0,2],
            ];
            $stmt = $pdo->prepare("INSERT INTO subjects_pool (taught_in, subject_type, subject_code, year_of_introduction, subject_name, subject_semester, credit, lecture_hours, tutorial_hours, practical_hours)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                   ON DUPLICATE KEY UPDATE subject_name=VALUES(subject_name), subject_semester=VALUES(subject_semester), credit=VALUES(credit), lecture_hours=VALUES(lecture_hours), tutorial_hours=VALUES(tutorial_hours), practical_hours=VALUES(practical_hours), subject_type=VALUES(subject_type), taught_in=VALUES(taught_in)");
            $count = 0;
            foreach ($defs as $d) { $stmt->execute($d); $count++; }
            $pdo->commit();
            $success = "Semester 6 subjects added/updated: $count.";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Bulk add failed: " . $e->getMessage();
        }
    } elseif (isset($_POST['create_subject'])) {
        $taught_in = $_POST['taught_in'] ?? 'UG';
        $subject_type = trim($_POST['subject_type'] ?? '');
        $subject_code = strtoupper(trim($_POST['subject_code'] ?? ''));
        $subject_name = trim($_POST['subject_name'] ?? '');
        $subject_semester = (int)($_POST['subject_semester'] ?? 0);
        $credit = ($_POST['credit'] === '' || !isset($_POST['credit'])) ? 0 : (int)$_POST['credit'];
        $lecture_hours = ($_POST['lecture_hours'] === '' || !isset($_POST['lecture_hours'])) ? 0 : (int)$_POST['lecture_hours'];
        $tutorial_hours = ($_POST['tutorial_hours'] === '' || !isset($_POST['tutorial_hours'])) ? 0 : (int)$_POST['tutorial_hours'];
        $practical_hours = ($_POST['practical_hours'] === '' || !isset($_POST['practical_hours'])) ? 0 : (int)$_POST['practical_hours'];
        $year_of_introduction = $_POST['year_of_introduction'] ?? '';
        if (empty($year_of_introduction)) {
            $error = 'Year of Introduction is required.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO subjects_pool (taught_in, subject_type, subject_code, year_of_introduction, subject_name, subject_semester, credit, lecture_hours, tutorial_hours, practical_hours)
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$taught_in, $subject_type, $subject_code, $year_of_introduction, $subject_name, $subject_semester, $credit, $lecture_hours, $tutorial_hours, $practical_hours]);
                $success = "Subject created successfully!";
            } catch (PDOException $e) {
                $error = "Error creating subject: " . $e->getMessage();
            }
        }
    }
}

// Get all database entries for dashboard
$students = $pdo->query("SELECT s.*, i.change_passcode FROM student_info s JOIN `index` i ON s.student_id = i.user_id")->fetchAll();
$employees = $pdo->query("SELECT s.*, i.change_passcode FROM staff_info s JOIN `index` i ON s.staff_id = i.user_id")->fetchAll();
$subjects = $pdo->query("SELECT * FROM subjects_pool ORDER BY subject_semester, subject_code")->fetchAll();
$registrations = $pdo->query("SELECT sr.*, si.first_name, si.last_name FROM semester_registration sr JOIN student_info si ON sr.student_id = si.student_id ORDER BY sr.sem_reg_date DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HOD Dashboard</title>
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
        .badge-status {
            font-size: 0.85rem;
            padding: 0.4em 0.75em;
            border-radius: 12px;
        }
        .status-ongoing { background-color: #d4edda; color: #155724; }
        .status-graduated { background-color: #cce5ff; color: #004085; }
        .status-pending { background-color: #ffeeba; color: #856404; }
        .status-verified { background-color: #d4edda; color: #155724; }
        .status-rejected { background-color: #f8d7da; color: #721c24; }
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
                <h3>HOD Portal</h3>
                <div class="nav flex-column">
                    <a class="nav-link active" href="#dashboard" onclick="showSection('dashboard')">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a class="nav-link" href="#students" onclick="showSection('students')">
                        <i class="fas fa-user-graduate"></i> Students
                    </a>
                    <a class="nav-link" href="#staff" onclick="showSection('staff')">
                        <i class="fas fa-users"></i> Staff
                    </a>
                    <a class="nav-link" href="#subjects" onclick="showSection('subjects')">
                        <i class="fas fa-book"></i> Subjects
                    </a>
                    <a class="nav-link" href="#registrations" onclick="showSection('registrations')">
                        <i class="fas fa-clipboard-list"></i> Registrations
                    </a>
                    <a class="nav-link" href="hod_assign_staff.php">
                        <i class="fas fa-user-plus"></i> Assign Employee
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
                        <h2>Welcome, <?= htmlspecialchars($hod['first_name'] . ' ' . $hod['last_name']) ?></h2>
                        <p class="text-muted">HOD ID: <?= htmlspecialchars($hod['hod_id']) ?></p>
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
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3 class="text-primary"><?= count($students) ?></h3>
                                    <p>Total Students</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3 class="text-success"><?= count($employees) ?></h3>
                                    <p>Total Employees</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3 class="text-info"><?= count($subjects) ?></h3>
                                    <p>Total Subjects</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3 class="text-warning"><?= count($registrations) ?></h3>
                                    <p>Registrations</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Students Section -->
                <div id="students" class="section" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">All Students</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Student ID</th>
                                            <th>Name</th>
                                            <th>Type</th>
                                            <th>Department</th>
                                            <th>Email</th>
                                            <th>Status</th>
                                            <th>Password Changed</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($student['student_id']) ?></td>
                                            <td><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                                            <td><?= htmlspecialchars($student['student_type']) ?></td>
                                            <td><?= htmlspecialchars($student['department_id']) ?></td>
                                            <td><?= htmlspecialchars($student['email']) ?></td>
                                            <td><span class="badge-status status-<?= strtolower($student['status']) ?>"><?= htmlspecialchars($student['status']) ?></span></td>
                                            <td><?= $student['change_passcode'] ? 'Yes' : 'No' ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Staff Section -->
                <div id="staff" class="section" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">All Employees</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Employee ID</th>
                                            <th>Name</th>
                                            <th>Type</th>
                                            <th>Department</th>
                                            <th>Email</th>
                                            <th>Designation</th>
                                            <th>Password Changed</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($employees as $s): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($s['staff_id']) ?></td>
                                            <td><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></td>
                                            <td><?= htmlspecialchars($s['staff_type'] === 'Employee' ? 'Employee' : $s['staff_type']) ?></td>
                                            <td><?= htmlspecialchars($s['department_id']) ?></td>
                                            <td><?= htmlspecialchars($s['email']) ?></td>
                                            <td><?= htmlspecialchars($s['designation']) ?></td>
                                            <td><?= $s['change_passcode'] ? 'Yes' : 'No' ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Subjects Section -->
                <div id="subjects" class="section" style="display: none;">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">Bulk Actions</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" onsubmit="return confirm('Add/Update default Semester 6 subjects?');">
                                        <input type="hidden" name="bulk_add_sem6" value="1">
                                        <p class="text-muted mb-2">Insert default Semester 6 core and laboratory subjects into the subjects pool.</p>
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-plus-circle"></i> Add Semester 6 Subjects
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">Create New Subject</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="mb-3">
                                            <label class="form-label">Taught In</label>
                                            <select name="taught_in" class="form-select" required>
                                                <option value="UG">UG</option>
                                                <option value="PG">PG</option>
                                                <option value="PhD">PhD</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Subject Type</label>
                                            <input type="text" name="subject_type" class="form-control" placeholder="e.g., Core, Elective" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Subject Code</label>
                                            <input type="text" name="subject_code" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Subject Name</label>
                                            <input type="text" name="subject_name" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Semester</label>
                                            <select name="subject_semester" class="form-select" required>
                                                <?php for($i = 1; $i <= 10; $i++): ?>
                                                <option value="<?= $i ?>"><?= $i ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Credits</label>
                                                    <input type="number" name="credit" class="form-control" min="0" max="10">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Lecture Hours</label>
                                                    <input type="number" name="lecture_hours" class="form-control" min="0">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Tutorial Hours</label>
                                                    <input type="number" name="tutorial_hours" class="form-control" min="0">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Practical Hours</label>
                                                    <input type="number" name="practical_hours" class="form-control" min="0">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="year_of_introduction" class="form-label">Year of Introduction</label>
                                            <input type="date" class="form-control" id="year_of_introduction" name="year_of_introduction" required>
                                        </div>
                                        <button type="submit" name="create_subject" class="btn btn-primary">Create Subject</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">All Subjects</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Code</th>
                                                    <th>Name</th>
                                                    <th>Sem</th>
                                                    <th>Credits</th>
                                                    <th>L-T-P</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($subjects as $subject): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($subject['subject_code']) ?></td>
                                                    <td><?= htmlspecialchars($subject['subject_name']) ?></td>
                                                    <td><?= htmlspecialchars($subject['subject_semester']) ?></td>
                                                    <td><?= htmlspecialchars($subject['credit']) ?></td>
                                                    <td><?= $subject['lecture_hours'] . '-' . $subject['tutorial_hours'] . '-' . $subject['practical_hours'] ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Registrations Section -->
                <div id="registrations" class="section" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Student Registrations</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Student ID</th>
                                            <th>Student Name</th>
                                            <th>Semester</th>
                                            <th>Registration Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($registrations as $reg): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($reg['student_id']) ?></td>
                                            <td><?= htmlspecialchars($reg['first_name'] . ' ' . $reg['last_name']) ?></td>
                                            <td><?= htmlspecialchars($reg['semester']) ?></td>
                                            <td><?= htmlspecialchars($reg['sem_reg_date']) ?></td>
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
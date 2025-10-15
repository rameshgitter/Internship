<?php
// dashboard.php
// Set session timeout to 2 hours (7200 seconds) BEFORE starting session
ini_set('session.gc_maxlifetime', 7200);
// Set session cookie lifetime to 2 hours BEFORE starting session
session_set_cookie_params(7200);

session_start();
// The db_config.php file should contain your PDO database connection setup.
// Example: $pdo = new PDO("mysql:host=localhost;dbname=college_management", 'your_user', 'your_password');
require_once 'db_config.php';


// Regenerate session ID to prevent session fixation
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

// Update last activity time
$_SESSION['last_activity'] = time();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$error      = '';
$success    = '';

// Get student information from session (already loaded during login)
$student = $_SESSION['student_info'] ?? null;

// If student data isn't in session, get it from database
if (!$student) {
    $stmt = $pdo->prepare("SELECT * FROM student_info WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    if (!$student) {
        // This could happen if a student record was deleted but the session persists
        session_destroy();
        header('Location: login.php?error=notfound');
        exit();
    }
    // Store in session for future use
    $_SESSION['student_info'] = $student;
}

// Fallbacks for name display
$studentFirstName = $student['first_name'] ?? '';
$studentLastName  = $student['last_name']  ?? '';

// Handle Delete Registration with the new schema
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_registration'])) {
    $registration_student_id = $_POST['delete_registration'];

    try {
        $pdo->beginTransaction();

        // Delete the semester registration record (using student_id as the identifier)
        $stmt_reg = $pdo->prepare("DELETE FROM semester_registration WHERE student_id = ?");
        $stmt_reg->execute([$registration_student_id]);

        if ($stmt_reg->rowCount() > 0) {
            $pdo->commit();
            $success = "Registration has been deleted successfully.";
        } else {
            $pdo->rollBack();
            $error = "Registration not found or you do not have permission to delete it.";
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to delete registration: " . $e->getMessage();
    }
}


// Get available semesters based on subjects in the student's department
// This query finds semesters that have subjects defined for the student's department.
$stmt = $pdo->prepare("
    SELECT s.subject_semester, COUNT(*) as subject_count
    FROM subjects_pool s
    WHERE s.subject_semester IS NOT NULL
    GROUP BY s.subject_semester
    ORDER BY s.subject_semester ASC
");
$stmt->execute();
$available_semesters = $stmt->fetchAll();


// Get student's semester registration history from the correct table
// This query no longer needs a JOIN as the semester number is directly in the table.
$stmt = $pdo->prepare("
    SELECT * 
    FROM semester_registration
    WHERE student_id = ?
    ORDER BY semester DESC
");
$stmt->execute([$student_id]);
$registrations = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Student Dashboard</title>

  <!-- Bootstrap CSS -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
  />

  <!-- Font Awesome for Icons -->
  <link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"
    rel="stylesheet"
  />

  <!-- Google Font: Roboto -->
  <link
    href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap"
    rel="stylesheet"
  />

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

    .main-content p.text-muted {
      font-size: 0.9rem;
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

    /* List Group in Available Semesters */
    .list-group-item {
      border: none;
      border-radius: 6px;
      margin-bottom: 10px;
      padding: 15px 20px;
      transition: background 0.3s, box-shadow 0.3s;
    }

    .list-group-item:hover {
      background: #f8f9fa;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    }

    /* Buttons */
    .btn-primary {
      background-color: #007bff;
      border-color: #007bff;
      font-weight: 500;
    }

    .btn-primary:hover {
      background-color: #0069d9;
      border-color: #0062cc;
    }

    .btn-info {
      background-color: #17a2b8;
      border-color: #17a2b8;
    }

    .btn-info:hover {
      background-color: #138496;
      border-color: #117a8b;
    }

    .btn-danger {
      background-color: #dc3545;
      border-color: #dc3545;
    }

    .btn-danger:hover {
      background-color: #c82333;
      border-color: #bd2130;
    }

    /* Status Badges */
    .badge-status {
      font-size: 0.85rem;
      padding: 0.4em 0.75em;
      border-radius: 12px;
      font-weight: 500;
    }

    .status-registered {
      background-color: #ffeeba;
      color: #856404;
    }

    .status-completed {
      background-color: #d4edda;
      color: #155724;
    }

    .status-failed {
      background-color: #f8d7da;
      color: #721c24;
    }

    /* Table Styling */
    .table-responsive {
      margin-top: 15px;
    }

    th {
      background-color: #f1f3f5;
    }

    td,
    th {
      vertical-align: middle;
    }

    /* Responsive Tweaks */
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
        <h3>Student Portal</h3>
        <div class="nav flex-column">
          <a class="nav-link active" href="dashboard.php">
            <i class="fas fa-home"></i> Dashboard
          </a>
          <a class="nav-link" href="semester_registration.php">
            <i class="fas fa-calendar-plus"></i> Register Semester
          </a>
          <a class="nav-link" href="marks.php">
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
        <!-- Welcome Header -->
        <div class="row mb-4">
          <div class="col">
            <h2>
              Welcome,
              <?= htmlspecialchars(trim($studentFirstName . ' ' . $studentLastName)) ?>
            </h2>
            <p class="text-muted">
              Student ID: <?= htmlspecialchars($student['student_id'] ?? '') ?>
            </p>
            <?php if ($success): ?>
              <div class="alert alert-success" role="alert">
                <?= htmlspecialchars($success) ?>
              </div>
            <?php elseif ($error): ?>
              <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($error) ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Available Semesters -->
        <div class="row mb-4">
          <div class="col">
            <div class="card">
              <div class="card-header">
                <h5 class="card-title mb-0">Available Semesters for Registration</h5>
              </div>
              <div class="card-body">
                <?php if (empty($available_semesters)): ?>
                  <p class="text-muted mb-0">
                    No semesters with available subjects found for your department.
                  </p>
                <?php else: ?>
                  <div class="list-group">
                    <?php foreach ($available_semesters as $semester): ?>
                      <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                          <h6 class="mb-1">
                            Semester <?= htmlspecialchars($semester['subject_semester']) ?>
                          </h6>
                          <small class="text-info">
                            <?= $semester['subject_count'] ?> subject(s) available
                          </small>
                        </div>
                        <a
                          href="semester_registration.php?semester_num=<?= $semester['subject_semester'] ?>"
                          class="btn btn-primary btn-sm"
                        >
                          Register
                        </a>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Registration History -->
        <div class="row">
          <div class="col">
            <div class="card">
              <div class="card-header">
                <h5 class="card-title mb-0">Registration History</h5>
              </div>
              <div class="card-body">
                <?php if (empty($registrations)): ?>
                  <p class="text-muted mb-0">No registration history found.</p>
                <?php else: ?>
                  <div class="table-responsive">
                    <table class="table align-middle">
                      <thead>
                        <tr>
                          <th>Semester</th>
                          <th>Registration Date</th>
                          <th>Status</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($registrations as $reg): ?>
                          <tr>
                            <td>
                              Semester <?= htmlspecialchars($reg['semester']) ?>
                            </td>
                            <td>
                              <?= date('M d, Y', strtotime($reg['sem_reg_date'])) ?>
                            </td>
                            <td>
                              <span class="badge-status status-registered">
                                Registered
                              </span>
                            </td>
                            <td>
                              <form
                                method="post"
                                action="dashboard.php"
                                class="d-inline"
                                onsubmit="return confirm('Are you sure you want to delete this registration?');"
                              >
                                <input
                                  type="hidden"
                                  name="delete_registration"
                                  value="<?= $reg['student_id'] ?>"
                                />
                                <button type="submit" class="btn btn-danger btn-sm">
                                  <i class="fas fa-trash"></i> Delete
                                </button>
                              </form>
                              <?php
                                $pdf_filename = $student_id . '_semester_' . $reg['semester'] . '_' . date('Y-m-d', strtotime($reg['sem_reg_date'])) . '.pdf';
                                $pdf_path = 'uploads/registrations/' . $pdf_filename;
                                if (file_exists(__DIR__ . '/' . $pdf_path)):
                              ?>
                                <a href="<?= $pdf_path ?>" target="_blank" class="btn btn-success btn-sm ms-2">
                                  <i class="fas fa-file-pdf"></i> View PDF
                                </a>
                              <?php endif; ?>
                            </td>
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

  <!-- Bootstrap JS Bundle (incl. Popper) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
session_start();
require_once 'db_config.php';

// Check if TCPDF is available
$tcpdf_error = null;
if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
} else {
    $tcpdf_error = "TCPDF library is not installed. Please run 'composer install' to install dependencies.";
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$_SESSION['last_activity'] = time();
$error = '';
$success = '';

// Get semester number from URL
$semester_num = $_GET['semester_num'] ?? null;

if (!$semester_num) {
    header('Location: dashboard.php');
    exit();
}

// Get available subjects for this semester
$stmt = $pdo->prepare("
    SELECT * FROM subjects_pool 
    WHERE subject_semester = ? 
    ORDER BY subject_code
");
$stmt->execute([$semester_num]);
$subjects = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['last_activity'] = time();
    
    $selected_subjects = $_POST['subjects'] ?? [];
    
    if (empty($selected_subjects)) {
        $error = "Please select at least one subject.";
    } elseif (isset($tcpdf_error)) {
        $error = $tcpdf_error;
    } else {
        try {
            $pdo->beginTransaction();

            // Check if student already registered for this semester
            $stmt = $pdo->prepare("SELECT * FROM semester_registration WHERE student_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $existing_registration = $stmt->fetch();

            if ($existing_registration) {
                // Update existing registration
                $stmt = $pdo->prepare("UPDATE semester_registration SET semester = ?, sem_reg_date = CURDATE() WHERE student_id = ?");
                $stmt->execute([$semester_num, $_SESSION['user_id']]);
            } else {
                // Create new semester registration
                $stmt = $pdo->prepare("INSERT INTO semester_registration (student_id, semester, sem_reg_date) VALUES (?, ?, CURDATE())");
                $stmt->execute([$_SESSION['user_id'], $semester_num]);
            }

            // Generate PDF if TCPDF is available
            if (!isset($tcpdf_error)) {
                $_SESSION['last_activity'] = time();
                
                $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
                $pdf->SetCreator(PDF_CREATOR);
                $pdf->SetTitle('Semester Registration');
                $pdf->AddPage();

                $margins = $pdf->getMargins();
                $usableWidth = $pdf->getPageWidth() - $margins['left'] - $margins['right'];

                // Get student info
                $stmt = $pdo->prepare("SELECT * FROM student_info WHERE student_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $student = $stmt->fetch();

                // Header
                $pdf->SetFont('helvetica', '', 9);
                $pdf->Cell(0, 5, 'Office of The Dean Academic', 0, 1, 'C');
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->Cell(0, 5, 'Indian Institute of Engineering Science and Technology, Shibpur', 0, 1, 'C');
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->Cell(0, 4, 'Semester Registration Form for Undergraduate/ Postgraduate Courses', 0, 1, 'C');
                $pdf->Ln(8);

                // Student Information
                $pdf->SetFont('helvetica', '', 9);
                $pdf->Cell(0, 5, '1. Student Name: ' . htmlspecialchars($student['first_name'] . ' ' . $student['last_name']), 0, 1);
                $pdf->Ln(5);

                $halfWidth = $usableWidth * 0.50;
                $pdf->Cell($halfWidth, 5, '2. Department: ' . htmlspecialchars($student['department_id']), 0, 0);
                $pdf->Cell($halfWidth, 5, '3. Programme: ' . htmlspecialchars($student['student_type']), 0, 1);
                $pdf->Ln(5);

                $pdf->Cell($halfWidth, 5, '4. Registration for: Semester ' . htmlspecialchars($semester_num), 0, 0);
                $pdf->Cell($halfWidth, 5, '5. Registration No: ' . htmlspecialchars($student['registration_no']), 0, 1);
                $pdf->Ln(5);

                $pdf->Cell($halfWidth, 5, '6. Email: ' . htmlspecialchars($student['email']), 0, 0);
                $pdf->Cell($halfWidth, 5, 'Mobile No: ' . htmlspecialchars($student['mobile_no']), 0, 1);
                $pdf->Ln(8);

                // Fee Payment Section
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->Cell(0, 5, '7. Details of Institute fee payment:', 0, 1);
                $pdf->SetFont('helvetica', '', 9);
                $pdf->Cell(0, 5, 'Amount paid: Rs. ___________     Date: ______/______/______     Transaction ID: _____________________', 0, 1);
                $pdf->Cell(0, 5, '(Attached self-attested copy of Payment receipt)', 0, 1);
                $pdf->Ln(8);

                // Subject Details Table
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->Cell(0, 5, '8. Subject details (Including practical or Laboratory Subject):', 0, 1);
                $pdf->Ln(2);

                // Table Header
                $pdf->SetFont('helvetica', 'B', 8);
                $col1 = 10; $col2 = 25; $col3 = 75; $col4 = 30; $col5 = 20; $col6 = 20;

                $pdf->Cell($col1, 6, 'Sl. No', 1, 0, 'C');
                $pdf->Cell($col2, 6, 'Subject Code', 1, 0, 'C');
                $pdf->Cell($col3, 6, 'Name of the Subject', 1, 0, 'C');
                $pdf->Cell($col4, 6, 'Core/Elective', 1, 0, 'C');
                $pdf->Cell($col5, 6, 'Credit', 1, 0, 'C');
                $pdf->Cell($col6, 6, 'Remarks', 1, 1, 'C');

                // Table Rows
                $pdf->SetFont('helvetica', '', 8);
                $sl_no = 1;
                foreach ($selected_subjects as $subject_code) {
                    $stmt = $pdo->prepare("SELECT * FROM subjects_pool WHERE subject_code = ?");
                    $stmt->execute([$subject_code]);
                    $subject = $stmt->fetch();

                    if ($subject) {
                        $pdf->Cell($col1, 6, $sl_no++, 1, 0, 'C');
                        $pdf->Cell($col2, 6, htmlspecialchars($subject['subject_code']), 1, 0, '');
                        $pdf->Cell($col3, 6, htmlspecialchars($subject['subject_name']), 1, 0, '');
                        $pdf->Cell($col4, 6, htmlspecialchars($subject['subject_type']), 1, 0, 'C');
                        $pdf->Cell($col5, 6, htmlspecialchars($subject['credit']), 1, 0, 'C');
                        $pdf->Cell($col6, 6, '', 1, 1, 'C');
                    }
                }

                // Add empty rows
                $empty_rows_to_add = max(0, 5 - count($selected_subjects));
                for ($i = 0; $i < $empty_rows_to_add; $i++) {
                    $pdf->Cell($col1, 6, '', 1, 0, 'C');
                    $pdf->Cell($col2, 6, '', 1, 0, '');
                    $pdf->Cell($col3, 6, '', 1, 0, '');
                    $pdf->Cell($col4, 6, '', 1, 0, 'C');
                    $pdf->Cell($col5, 6, '', 1, 0, 'C');
                    $pdf->Cell($col6, 6, '', 1, 1, 'C');
                }

                $pdf->Cell(0, 4, '# may add more rows if necessary', 0, 1, 'L');
                $pdf->Ln(6);

                // Signature Section
                $pdf->SetFont('helvetica', '', 9);
                $pdf->Cell(0, 5, 'Date: ___________', 0, 1, '');
                $pdf->Ln(4);
                $pdf->Cell(0, 5, 'Checked by the Department/School/Center/any other authority', 0, 1, '');
                $pdf->Ln(10);

                $sigCell1 = $usableWidth * 0.40;
                $sigSpacer = $usableWidth * 0.20;
                $sigCell2 = $usableWidth * 0.40;

                $pdf->Cell($sigCell1, 5, '(Signature)', 'T', 0, 'C');
                $pdf->Cell($sigSpacer, 5, '', 0, 0);
                $pdf->Cell($sigCell2, 5, htmlspecialchars($student['first_name'] . ' ' . $student['last_name']), 'T', 1, 'C');

                $pdf->Cell($sigCell1, 4, '', 0, 0);
                $pdf->Cell($sigSpacer, 4, '', 0, 0);
                $pdf->Cell($sigCell2, 4, 'Signature of the Student', 0, 1, 'C');
                $pdf->Ln(8);

                $pdf->Cell($sigCell1, 5, '_________________________________________', 0, 0, 'C');
                $pdf->Cell($sigSpacer, 5, '', 0, 0);
                $pdf->Cell($sigCell2, 5, 'Recommended/Not recommended', 0, 1, 'C');

                $pdf->Cell($sigCell1, 4, 'Head of the Department/School/Center', 0, 0, 'C');
                $pdf->Cell($sigSpacer, 4, '', 0, 0);
                $pdf->Cell($sigCell2, 4, '', 0, 1, '');
                $pdf->Ln(8);

                $pdf->Cell($sigCell1, 5, 'Scrutinized', 0, 0, 'C');
                $pdf->Cell($sigSpacer, 5, '', 0, 0);
                $pdf->Cell($sigCell2, 5, 'Approved/Not approved', 0, 1, 'C');

                $pdf->Cell($sigCell1, 4, 'PIC (Examination)/ AR (Academic)', 0, 0, 'C');
                $pdf->Cell($sigSpacer, 4, '', 0, 0);
                $pdf->Cell($sigCell2, 4, 'Associate Dean (A/C)/Dean (AC)', 0, 1, 'C');

                // Save PDF
                $upload_dir = __DIR__ . '/uploads/registrations/';
                $pdf_filename = $_SESSION['user_id'] . '_semester_' . $semester_num . '_' . date('Y-m-d') . '.pdf';
                $pdf_path_absolute = $upload_dir . $pdf_filename;

                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $pdf->Output($pdf_path_absolute, 'F');
            }

            $pdo->commit();
            $_SESSION['last_activity'] = time();
            
            $success = "Registration successful! You can download your registration form from the dashboard.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['last_activity'] = time();
            $error = "Registration failed. Please try again. (" . $e->getMessage() . ")";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semester Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: #2c3e50;
            color: white;
            padding: 20px;
        }
        .sidebar .nav-link {
            color: white;
            margin: 10px 0;
        }
        .sidebar .nav-link:hover {
            background: #34495e;
        }
        .main-content {
            padding: 20px;
        }
        .subject-card {
            margin-bottom: 15px;
            cursor: pointer;
        }
        .subject-card.selected {
            border-color: #28a745;
            background-color: #f8fff8;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <h3 class="mb-4">Student Portal</h3>
                <div class="nav flex-column">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a class="nav-link active" href="semester_registration.php">
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
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="row mb-4">
                    <div class="col">
                        <h2>Semester Registration</h2>
                        <p class="text-muted">Registering for: Semester <?= htmlspecialchars($semester_num) ?></p>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?= $success ?>
                        <a href="dashboard.php" class="btn btn-primary ms-3">Go to Dashboard</a>
                    </div>
                <?php else: ?>
                    <form method="post" id="registrationForm">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Available Subjects</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($subjects)): ?>
                                            <div class="alert alert-info">
                                                <h6>No subjects available</h6>
                                                <p class="mb-0">No subjects have been created for this semester yet. Please contact your HOD or check back later.</p>
                                            </div>
                                        <?php else: ?>
                                        <?php foreach ($subjects as $subject): ?>
                                            <div class="card subject-card" onclick="toggleSubject(this, '<?= $subject['subject_code'] ?>')">
                                                <div class="card-body">
                                                    <div class="form-check">
                                                        <input type="checkbox" class="form-check-input" 
                                                               name="subjects[]" value="<?= $subject['subject_code'] ?>"
                                                               id="subject_<?= $subject['subject_code'] ?>">
                                                        <label class="form-check-label" for="subject_<?= $subject['subject_code'] ?>">
                                                        <h6 class="mb-1"><?= htmlspecialchars($subject['subject_name']) ?></h6>
                                                        <p class="mb-1 text-muted">
                                                        Subject Code: <?= htmlspecialchars($subject['subject_code']) ?> | 
                                                        Credits: <?= $subject['credit'] ?> |
                                                        Type: <?= htmlspecialchars($subject['subject_type']) ?>
                                                        </p>
                                                        <small class="text-muted">
                                                        L-T-P: <?= $subject['lecture_hours'] ?>-<?= $subject['tutorial_hours'] ?>-<?= $subject['practical_hours'] ?> |
                                                        Taught in: <?= htmlspecialchars($subject['taught_in']) ?>
                                                        </small>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Registration Summary</h5>
                                    </div>
                                    <div class="card-body">
                                        <p>Selected Subjects: <span id="selectedCount">0</span></p>
                                        <p>Total Credits: <span id="totalCredits">0</span></p>
                                        <button type="submit" class="btn btn-primary w-100" <?= empty($subjects) ? 'disabled' : '' ?>>
                                            <?= empty($subjects) ? 'No Subjects Available' : 'Submit Registration' ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSubject(card, subjectCode) {
            const checkbox = document.getElementById('subject_' + subjectCode);
            checkbox.checked = !checkbox.checked;
            card.classList.toggle('selected');
            updateSummary();
        }

        function updateSummary() {
            const checkboxes = document.querySelectorAll('input[name="subjects[]"]:checked');
            document.getElementById('selectedCount').textContent = checkboxes.length;
            
            let totalCredits = 0;
            checkboxes.forEach(checkbox => {
                const card = checkbox.closest('.card');
                const creditsText = card.querySelector('.text-muted').textContent;
                const creditsMatch = creditsText.match(/Credits: (\d+)/);
                if (creditsMatch) {
                    totalCredits += parseInt(creditsMatch[1]);
                }
            });
            document.getElementById('totalCredits').textContent = totalCredits;
        }

        // Initialize summary on page load
        updateSummary();
    </script>
</body>
</html>
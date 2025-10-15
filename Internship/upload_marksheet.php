<?php
// upload_marksheet.php - Enhanced marksheet upload with Python extraction
session_start();
require_once 'db_config.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';
$student_id = $_SESSION['user_id'];

// Get available semesters for the student
$available_semesters = [];
try {
    $stmt = $pdo->prepare("
        SELECT s.* FROM semesters s 
        WHERE s.is_active = 1 
        ORDER BY s.start_date DESC
    ");
    $stmt->execute();
    $available_semesters = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching semesters: " . $e->getMessage());
}

// Handle marksheet upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_marksheet'])) {
    $semester_id = $_POST['semester_id'] ?? '';
    $academic_year = $_POST['academic_year'] ?? '';
    
    if (empty($semester_id) || empty($academic_year)) {
        $error = "Please select semester and enter academic year.";
    } elseif (!isset($_FILES['marksheet_pdf']) || $_FILES['marksheet_pdf']['error'] !== UPLOAD_ERR_OK) {
        $error = "Please select a valid PDF file.";
    } else {
        $uploaded_file = $_FILES['marksheet_pdf'];
        
        // Validate file type
        $file_info = pathinfo($uploaded_file['name']);
        if (strtolower($file_info['extension']) !== 'pdf') {
            $error = "Only PDF files are allowed.";
        } elseif ($uploaded_file['size'] > 10 * 1024 * 1024) { // 10MB limit
            $error = "File size must be less than 10MB.";
        } else {
            try {
                $pdo->beginTransaction();
                
                // Create upload directory
                $upload_dir = __DIR__ . '/uploads/marksheets/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Generate unique filename
                $filename = 'marksheet_' . $student_id . '_' . $semester_id . '_' . time() . '.pdf';
                $file_path = $upload_dir . $filename;
                $relative_path = 'uploads/marksheets/' . $filename;
                
                // Move uploaded file
                if (move_uploaded_file($uploaded_file['tmp_name'], $file_path)) {
                    
                    // Extract data using Python script
                    $python_script = __DIR__ . '/extract_marksheet_data.py';
                    $command = "python3 " . escapeshellarg($python_script) . " " . escapeshellarg($file_path) . " 2>&1";
                    $output = shell_exec($command);
                    
                    $extracted_data = null;
                    if ($output) {
                        $result = json_decode($output, true);
                        if ($result && $result['success']) {
                            $extracted_data = $result['data'];
                        }
                    }
                    
                    // Insert marks data
                    if ($extracted_data && !empty($extracted_data['subjects'])) {
                        foreach ($extracted_data['subjects'] as $subject) {
                            $stmt = $pdo->prepare("
                                INSERT INTO marks (student_id, subject_code, semester_id, academic_year, 
                                                 marks_obtained, total_marks, grade, marksheet_pdf_path, extracted_data)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE
                                marks_obtained = VALUES(marks_obtained),
                                total_marks = VALUES(total_marks),
                                grade = VALUES(grade),
                                marksheet_pdf_path = VALUES(marksheet_pdf_path),
                                extracted_data = VALUES(extracted_data)
                            ");
                            $stmt->execute([
                                $student_id,
                                $subject['subject_code'],
                                $semester_id,
                                $academic_year,
                                $subject['obtained_marks'],
                                $subject['total_marks'],
                                $subject['percentage'] >= 90 ? 'A+' : 
                                ($subject['percentage'] >= 80 ? 'A' : 
                                ($subject['percentage'] >= 70 ? 'B' : 
                                ($subject['percentage'] >= 60 ? 'C' : 
                                ($subject['percentage'] >= 50 ? 'D' : 'F')))),
                                $relative_path,
                                json_encode($extracted_data)
                            ]);
                        }
                        
                        $pdo->commit();
                        $success = "Marksheet uploaded and data extracted successfully! Found " . count($extracted_data['subjects']) . " subjects.";
                    } else {
                        // Manual entry fallback
                        $stmt = $pdo->prepare("
                            INSERT INTO marks (student_id, semester_id, academic_year, marksheet_pdf_path, extracted_data)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $student_id,
                            $semester_id,
                            $academic_year,
                            $relative_path,
                            json_encode(['extraction_failed' => true, 'manual_entry_required' => true])
                        ]);
                        
                        $pdo->commit();
                        $success = "Marksheet uploaded successfully! Data extraction failed - please enter marks manually.";
                    }
                } else {
                    throw new Exception("Failed to upload file.");
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Upload failed: " . $e->getMessage();
            }
        }
    }
}

// Get student's uploaded marksheets
$uploaded_marksheets = [];
try {
    $stmt = $pdo->prepare("
        SELECT m.*, s.semester_name, s.academic_year as sem_academic_year
        FROM marks m
        LEFT JOIN semesters s ON m.semester_id = s.semester_id
        WHERE m.student_id = ? AND m.marksheet_pdf_path IS NOT NULL
        GROUP BY m.semester_id, m.academic_year
        ORDER BY m.upload_date DESC
    ");
    $stmt->execute([$student_id]);
    $uploaded_marksheets = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching marksheets: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Marksheet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .container { max-width: 800px; margin-top: 30px; }
        .card { box-shadow: 0 0 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .upload-area {
            border: 2px dashed #007bff;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            background: #f8f9ff;
            transition: all 0.3s ease;
        }
        .upload-area:hover {
            border-color: #0056b3;
            background: #e6f3ff;
        }
        .upload-area.dragover {
            border-color: #28a745;
            background: #e8f5e9;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Navigation -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="marks.php">Marks</a></li>
                <li class="breadcrumb-item active">Upload Marksheet</li>
            </ol>
        </nav>

        <!-- Upload Form -->
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-upload"></i> Upload Marksheet</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data" id="uploadForm">
                    <input type="hidden" name="upload_marksheet" value="1">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="semester_id" class="form-label">Semester</label>
                            <select name="semester_id" id="semester_id" class="form-select" required>
                                <option value="">Select Semester</option>
                                <?php foreach ($available_semesters as $semester): ?>
                                    <option value="<?= $semester['semester_id'] ?>">
                                        <?= htmlspecialchars($semester['semester_name']) ?> 
                                        (Sem <?= $semester['semester_number'] ?> - <?= htmlspecialchars($semester['academic_year']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="academic_year" class="form-label">Academic Year</label>
                            <input type="text" name="academic_year" id="academic_year" class="form-control" 
                                   placeholder="e.g., 2024-25" required>
                        </div>
                    </div>

                    <div class="upload-area" id="uploadArea">
                        <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                        <h5>Drag & Drop your marksheet PDF here</h5>
                        <p class="text-muted">or click to browse files</p>
                        <input type="file" name="marksheet_pdf" id="marksheet_pdf" class="d-none" 
                               accept=".pdf" required>
                        <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('marksheet_pdf').click()">
                            <i class="fas fa-folder-open"></i> Browse Files
                        </button>
                        <div id="fileInfo" class="mt-3" style="display: none;">
                            <div class="alert alert-info">
                                <i class="fas fa-file-pdf"></i> <span id="fileName"></span>
                                <button type="button" class="btn-close float-end" onclick="clearFile()"></button>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i> 
                            Supported format: PDF only. Maximum file size: 10MB. 
                            The system will automatically extract marks data from your marksheet.
                        </small>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <a href="marks.php" class="btn btn-secondary me-md-2">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Upload Marksheet
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Uploaded Marksheets -->
        <?php if (!empty($uploaded_marksheets)): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-history"></i> Previously Uploaded Marksheets</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Semester</th>
                                <th>Academic Year</th>
                                <th>Upload Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($uploaded_marksheets as $marksheet): ?>
                                <tr>
                                    <td><?= htmlspecialchars($marksheet['semester_name'] ?? 'Semester ' . $marksheet['semester_id']) ?></td>
                                    <td><?= htmlspecialchars($marksheet['academic_year']) ?></td>
                                    <td><?= date('M d, Y', strtotime($marksheet['upload_date'])) ?></td>
                                    <td>
                                        <?php if ($marksheet['verified_by']): ?>
                                            <span class="badge bg-success">Verified</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Pending Verification</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?= htmlspecialchars($marksheet['marksheet_pdf_path']) ?>" 
                                           class="btn btn-sm btn-outline-primary" target="_blank">
                                            <i class="fas fa-file-pdf"></i> View PDF
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // File upload handling
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('marksheet_pdf');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');

        // Drag and drop functionality
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0 && files[0].type === 'application/pdf') {
                fileInput.files = files;
                showFileInfo(files[0]);
            } else {
                alert('Please select a PDF file.');
            }
        });

        // File input change
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                showFileInfo(e.target.files[0]);
            }
        });

        function showFileInfo(file) {
            fileName.textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)';
            fileInfo.style.display = 'block';
        }

        function clearFile() {
            fileInput.value = '';
            fileInfo.style.display = 'none';
        }

        // Click to upload
        uploadArea.addEventListener('click', (e) => {
            if (e.target === uploadArea || e.target.closest('.upload-area') === uploadArea) {
                fileInput.click();
            }
        });
    </script>
</body>
</html>
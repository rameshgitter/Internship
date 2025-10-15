<?php
// alumni_register.php - Alumni self-registration form
session_start();
require_once 'db_config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $alumni_id = trim($_POST['alumni_id'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $graduation_year = trim($_POST['graduation_year'] ?? '');
    $degree = trim($_POST['degree'] ?? '');
    $department_id = trim($_POST['department_id'] ?? '');
    $mobile_no = trim($_POST['mobile_no'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $proof_document = '';

    // Handle file upload
    if (isset($_FILES['proof_document']) && $_FILES['proof_document']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['proof_document']['name'], PATHINFO_EXTENSION);
        $filename = uniqid('alumni_proof_') . '.' . $ext;
        $target = 'uploads/alumni_proofs/' . $filename;
        if (!is_dir('uploads/alumni_proofs')) mkdir('uploads/alumni_proofs', 0777, true);
        if (move_uploaded_file($_FILES['proof_document']['tmp_name'], $target)) {
            $proof_document = $target;
        } else {
            $error = 'Failed to upload proof document.';
        }
    }

    if (!$error) {
        if (empty($alumni_id) || empty($first_name) || empty($last_name) || empty($email) || empty($graduation_year) || empty($degree) || empty($department_id) || empty($password) || empty($confirm_password)) {
            $error = 'All required fields must be filled.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM alumni_pending WHERE alumni_id = ?");
                $stmt->execute([$alumni_id]);
                if ($stmt->fetch()) {
                    $error = 'Alumni ID already registered and pending approval.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO alumni_pending (alumni_id, first_name, last_name, email, graduation_year, degree, department_id, mobile_no, password, proof_document) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$alumni_id, $first_name, $last_name, $email, $graduation_year, $degree, $department_id, $mobile_no, $hash, $proof_document]);
                    $success = 'Thank you for registering! Your request will be reviewed by the admin.';
                }
            } catch (Exception $e) {
                $error = 'Error submitting registration: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Alumni Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Alumni Self-Registration</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>
                    <form method="post" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Alumni ID (Enrollment No)</label>
                            <input type="text" name="alumni_id" class="form-control" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">First Name</label>
                                <input type="text" name="first_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Name</label>
                                <input type="text" name="last_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Graduation Year</label>
                                <input type="number" name="graduation_year" class="form-control" min="1950" max="<?= date('Y') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Degree</label>
                                <select name="degree" class="form-select" required>
                                    <option value="">Select Degree</option>
                                    <option value="B.Tech">B.Tech</option>
                                    <option value="M.Tech">M.Tech</option>
                                    <option value="PhD">PhD</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department</label>
                            <input type="text" name="department_id" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mobile Number</label>
                            <input type="text" name="mobile_no" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Proof of Graduation (PDF/JPG/PNG)</label>
                            <input type="file" name="proof_document" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Submit Registration</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>

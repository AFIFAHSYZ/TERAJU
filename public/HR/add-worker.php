<?php
session_start();
require_once '../../config/conn.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT name FROM users WHERE id = :id");
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC); // <-- ensures $user['name'] exists


$success = $error = "";

// Helper function to sanitize input
function clean_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = strtoupper(clean_input($_POST['name'] ?? ''));
    $email   = clean_input($_POST['email'] ?? '');
    $position = $_POST['position'] ?? 'employee';
    $race = $_POST['race'] ?? '';
    if ($race === 'other') $race = clean_input($_POST['race_other'] ?? 'Other');
    $religion = $_POST['religion'] ?? '';
    if ($religion === 'other') $religion = clean_input($_POST['religion_other'] ?? 'Other');
    $project = strtoupper(clean_input($_POST['project'] ?? ''));
    $contract_raw = $_POST['contract'] ?? null;
    $contract = ($contract_raw === 'yes') ? true : (($contract_raw === 'no') ? false : null);

    if (empty($name) || empty($email) || empty($race) || empty($religion) || empty($project) || $contract === null) {
        $error = "All fields are required!";
    }

    if (!$error) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = "Email already registered!";
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO users
    (name, email, position, race, religion, project, contract)
    VALUES (:name, :email, :position, :race, :religion, :project, :contract)");
$stmt->execute([
    ':name' => $name,
    ':email' => $email,
    ':position' => $position,
    ':race' => $race,
    ':religion' => $religion,
    ':project' => $project,
    ':contract' => $contract
]);

                $success = "✅ Worker added successfully!";
            }
        } catch (PDOException $e) {
            $error = "❌ Failed to add worker: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Worker | Teraju HR System</title>
<link rel="stylesheet" href="../../assets/css/style.css">
<style>
.card {background: #fff; padding: 30px 40px; border-radius: 12px; box-shadow: 0 3px 10px rgba(0,0,0,0.1); max-width: 900px; margin: 0 auto;}
.card h2 {text-align: center; margin-bottom: 20px;}
.form-grid {display: grid; grid-template-columns: 1fr 1fr; gap: 20px 30px;}
.form-group {display: flex; flex-direction: column;}
.form-group label {font-weight: 600; margin-bottom: 6px;}
.form-group input, .form-group select {width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #d1d5db; background: #f9fafb;}
@media (max-width: 768px) {.form-grid {grid-template-columns: 1fr;}}
.btn-full {display: block; width: 100%; padding: 12px; background: #2563eb; color: #fff; border: none; border-radius: 8px; font-size: 1rem; cursor: pointer; transition: background 0.3s;}
.btn-full:hover {background: #1e40af;}
.success-box, .error-box {padding: 10px; border-radius: 6px; margin-bottom: 15px;}
.success-box { background: #dcfce7; color: #166534; }
.error-box { background: #fee2e2; color: #991b1b; }
footer {text-align: center; margin-top: 40px; color: #666; font-size: 0.9rem;}
</style>
</head>
<body>
<div class="layout">
    <?php include "sidebar.php"; ?>

    <header>
        <h1>Teraju HR System</h1>
    </header>

    <div class="main-content">
        <div class="card">
            <h2>Add New Worker</h2>

            <?php if ($error): ?>
                <div class="error-box"><?= htmlentities($error) ?></div>
            <?php elseif ($success): ?>
                <div class="success-box"><?= htmlentities($success) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="name" required value="<?= htmlspecialchars($name ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" required value="<?= htmlspecialchars($email ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Position</label>
                        <select name="position">
                            <option value="employee" <?= isset($position) && $position=='employee'?'selected':'' ?>>Employee</option>
                            <option value="manager" <?= isset($position) && $position=='manager'?'selected':'' ?>>Manager</option>
                            <option value="hr" <?= isset($position) && $position=='hr'?'selected':'' ?>>HR</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Race</label>
                        <select name="race" onchange="toggleOther('race')">
                            <option value="malay" <?= isset($race) && $race=='malay'?'selected':'' ?>>Malay</option>
                            <option value="chinese" <?= isset($race) && $race=='chinese'?'selected':'' ?>>Chinese</option>
                            <option value="indian" <?= isset($race) && $race=='indian'?'selected':'' ?>>Indian</option>
                            <option value="other" <?= isset($race) && !in_array($race,['malay','chinese','indian'])?'selected':'' ?>>Other</option>
                        </select>
                        <input type="text" id="race_other" name="race_other" placeholder="Specify" style="display:none;" value="<?= htmlspecialchars($race ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Religion</label>
                        <select name="religion" onchange="toggleOther('religion')">
                            <option value="islam" <?= isset($religion) && $religion=='islam'?'selected':'' ?>>Islam</option>
                            <option value="buddhism" <?= isset($religion) && $religion=='buddhism'?'selected':'' ?>>Buddhism</option>
                            <option value="christianity" <?= isset($religion) && $religion=='christianity'?'selected':'' ?>>Christianity</option>
                            <option value="hinduism" <?= isset($religion) && $religion=='hinduism'?'selected':'' ?>>Hinduism</option>
                            <option value="other" <?= isset($religion) && !in_array($religion,['islam','buddhism','christianity','hinduism'])?'selected':'' ?>>Other</option>
                        </select>
                        <input type="text" id="religion_other" name="religion_other" placeholder="Specify" style="display:none;" value="<?= htmlspecialchars($religion ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Project</label>
                        <input type="text" name="project" required value="<?= htmlspecialchars($project ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Contract</label>
                        <select name="contract" required>
                            <option value="" disabled selected>-- Select --</option>
                            <option value="yes" <?= isset($contract_raw) && $contract_raw=='yes'?'selected':'' ?>>Yes</option>
                            <option value="no" <?= isset($contract_raw) && $contract_raw=='no'?'selected':'' ?>>No</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn-full" style="margin-top:20px;">Add Worker</button>
            </form>
        </div>

        <footer>
            &copy; <?= date('Y') ?> Teraju HR System
        </footer>
    </div>
</div>

<script>
function toggleOther(field) {
    const sel = document.querySelector(`select[name="${field}"]`);
    const input = document.getElementById(field+'_other');
    input.style.display = sel.value==='other'?'block':'none';
}
window.onload = function() {
    toggleOther('race');
    toggleOther('religion');
};
</script>

<script src="../../assets/js/sidebar.js"></script>
</body>
</html>

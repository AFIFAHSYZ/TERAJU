<?php
session_start();
require_once '../../config/conn.php';

// Verify HR role
$stmt = $pdo->prepare("SELECT name, position FROM users WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if ($user['position'] !== 'hr') {
    header("Location: ../../unauthorized.php");
    exit();
}
/* -----------------------------------------------
   FORM SUBMISSION PROCESSING
-------------------------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $user_id = intval($_POST['user_id']);
    $leave_type_id = intval($_POST['leave_type_id']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $total_days = floatval($_POST['total_days']);
    $reason = trim($_POST['reason']);
    $override_name = trim($_POST['worker_name']);

    // Fetch original worker name
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $worker = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$worker) {
        die("Invalid worker selected.");
    }

    // Append override name inside reason (since no worker_name column exists)
    if ($override_name !== "") {
        $reason .= "\n\n[Worker Name Override: " . $override_name . "]";
    }

    /* -----------------------------------------------
       HANDLE FILE UPLOAD
    -------------------------------------------------*/
    $attachment_path = null;

    if (!empty($_FILES['attachment']['name'])) {

        $uploadDir = "uploads/leave_attachments/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $filename = time() . "_" . basename($_FILES["attachment"]["name"]);
        $targetPath = $uploadDir . $filename;

        if (move_uploaded_file($_FILES["attachment"]["tmp_name"], $targetPath)) {
            $attachment_path = $targetPath;
        }
    }

    /* -----------------------------------------------
       INSERT LEAVE REQUEST
    -------------------------------------------------*/
    $stmt = $pdo->prepare("
        INSERT INTO leave_requests 
        (user_id, leave_type_id, start_date, end_date, reason, total_days, approved_by, verified_by, status)
        VALUES
        (:uid, :lt, :sd, :ed, :reason, :td, NULL, NULL, 'pending')
    ");

    $stmt->execute([
        ':uid'    => $user_id,
        ':lt'     => $leave_type_id,
        ':sd'     => $start_date,
        ':ed'     => $end_date,
        ':reason' => $reason . ($attachment_path ? "\n\n[Attachment: $attachment_path]" : ""),
        ':td'     => $total_days
    ]);

    $success = true;
}

/* -----------------------------------------------
   FETCH WORKERS (PROJECT != HQ)
-------------------------------------------------*/
$workers = $pdo->query("
    SELECT id, name, project, contract
    FROM users
    WHERE project != 'HQ'
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* -----------------------------------------------
   FETCH LEAVE TYPES
-------------------------------------------------*/
$leave_types = $pdo->query("
    SELECT id, name 
    FROM leave_types 
    ORDER BY id ASC
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Worker Leave Request (HR)</title>
<link rel="stylesheet" href="styles.css">
<style>
    body {
        background: #f2f4f7;
        font-family: Arial, sans-serif;
    }
    .container {
        width: 600px;
        margin: 40px auto;
        background: white;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 4px 14px rgba(0,0,0,0.12);
        text-align: left;
    }
    h2 {
        text-align: center;
        margin-bottom: 25px;
    }
    label {
        font-weight: bold;
        margin-bottom: 4px;
        display: block;
    }
    input, select {
        width: 100%;
        padding: 12px;
        margin-bottom: 18px;
        border: 1px solid #ccc;
        border-radius: 8px;
        font-size: 15px;
    }
    button {
        width: 100%;
        padding: 14px;
        background: #0b5ed7;
        border: none;
        border-radius: 8px;
        color: white;
        font-size: 16px;
        cursor: pointer;
        margin-top: 10px;
        transition: 0.2s;
    }
    button:hover {
        background: #0846a8;
    }
    .success {
        background: #d4edda;
        color: #155724;
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 20px;
        border: 1px solid #c3e6cb;
    }
</style>
</head>
<body>

<div class="container">

    <h2>Worker Leave Request</h2>

    <?php if (!empty($success)): ?>
        <div class="success">Leave request submitted successfully.</div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data">

        <!-- Worker Selector -->
        <label>Select Worker</label>
        <select name="user_id" required>
            <option value="">-- Select Worker --</option>
            <?php foreach ($workers as $w): ?>
                <option value="<?= $w['id'] ?>">
                    <?= htmlspecialchars($w['full_name']) ?> 
                    (<?= htmlspecialchars($w['project']) ?>)
                    <?= $w['contract'] ? '[Contract]' : '[Permanent]' ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- Worker Name Override -->
        <label>Worker Name (Optional Override)</label>
        <input type="text" name="worker_name" placeholder="Fill only if actual worker name differs">

        <!-- Leave Type -->
        <label>Leave Type</label>
        <select name="leave_type_id" required>
            <option value="">-- Select Leave Type --</option>
            <?php foreach ($leave_types as $lt): ?>
                <option value="<?= $lt['id'] ?>">
                    <?= htmlspecialchars($lt['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- Dates -->
        <label>Start Date</label>
        <input type="date" name="start_date" required>

        <label>End Date</label>
        <input type="date" name="end_date" required>

        <!-- Total Days -->
        <label>Total Days</label>
        <input type="number" step="0.5" name="total_days" required>

        <!-- Reason -->
        <label>Reason</label>
        <input type="text" name="reason" placeholder="Reason for requesting leave">

        <!-- Attachment -->
        <label>Attachment (Optional)</label>
        <input type="file" name="attachment">

        <button type="submit">Submit Leave Request</button>
    </form>
</div>

</body>
</html>

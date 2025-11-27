<?php
session_start();
require_once '../../config/conn.php';

// Ensure user is logged in and is HR
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}
$stmt = $pdo->prepare("SELECT id, name, position FROM users WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user || ($user['position'] ?? '') !== 'hr') {
    header("Location: ../../unauthorized.php");
    exit();
}

/**
 * Simple sanitizer for incoming values
 */
function clean_input($v): string {
    return trim(htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'));
}

/**
 * Encode an uploaded file for storage in a BYTEA column.
 *
 * @param array $file        The $_FILES['field'] array
 * @param int   $maxBytes    Maximum allowed size in bytes (default 8MB)
 * @param array $allowedMime Optional allowed MIME types (empty = allow all)
 * @return array             ['data' => string|null, 'type' => string|null, 'name' => string|null, 'size' => int|null, 'error' => string|null]
 */
function encode_uploaded_file(array $file, int $maxBytes = 8388608, array $allowedMime = []): array {
    if (!isset($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['data' => null, 'type' => null, 'name' => null, 'size' => null, 'error' => null];
    }

    $err = $file['error'] ?? UPLOAD_ERR_OK;
    if ($err !== UPLOAD_ERR_OK) {
        return ['data' => null, 'type' => null, 'name' => null, 'size' => null, 'error' => "File upload error (code: $err)."];
    }

    $size = (int)($file['size'] ?? 0);
    if ($size > $maxBytes) {
        return ['data' => null, 'type' => null, 'name' => $file['name'] ?? null, 'size' => $size, 'error' => "Attachment too large. Max " . ($maxBytes/1024/1024) . "MB allowed."];
    }

    $tmp = $file['tmp_name'] ?? null;
    if (!$tmp || !is_uploaded_file($tmp)) {
        return ['data' => null, 'type' => null, 'name' => $file['name'] ?? null, 'size' => $size, 'error' => "Invalid uploaded file."];
    }

    $mime = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = finfo_file($finfo, $tmp) ?: null;
            finfo_close($finfo);
        }
    }
    if (!$mime) {
        $mime = $file['type'] ?? 'application/octet-stream';
    }

    if (!empty($allowedMime) && !in_array($mime, $allowedMime, true)) {
        return ['data' => null, 'type' => $mime, 'name' => $file['name'] ?? null, 'size' => $size, 'error' => "File type not allowed: $mime"];
    }

    $data = @file_get_contents($tmp);
    if ($data === false) {
        return ['data' => null, 'type' => $mime, 'name' => $file['name'] ?? null, 'size' => $size, 'error' => "Failed to read uploaded file."];
    }

    return ['data' => $data, 'type' => $mime, 'name' => $file['name'] ?? null, 'size' => $size, 'error' => null];
}

$success = $error = "";
$old = [
    'user_id' => '',
    'worker_name' => '',
    'leave_type_id' => '',
    'start_date' => '',
    'end_date' => '',
    'total_days' => '',
    'reason' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize incoming values
    $old['user_id'] = intval($_POST['user_id'] ?? 0);
    $old['worker_name'] = clean_input($_POST['worker_name'] ?? '');
    $old['leave_type_id'] = intval($_POST['leave_type_id'] ?? 0);
    $old['start_date'] = clean_input($_POST['start_date'] ?? '');
    $old['end_date'] = clean_input($_POST['end_date'] ?? '');
    $old['total_days'] = clean_input($_POST['total_days'] ?? '');
    $old['reason'] = clean_input($_POST['reason'] ?? '');

    // Basic validation
    if ($old['user_id'] <= 0) {
        $error = "Please select a worker.";
    } elseif ($old['leave_type_id'] <= 0) {
        $error = "Please select a leave type.";
    } elseif (empty($old['start_date']) || empty($old['end_date'])) {
        $error = "Please provide start and end dates.";
    } elseif (!is_numeric($old['total_days']) || floatval($old['total_days']) <= 0) {
        $error = "Total days must be a positive number.";
    } elseif (strtotime($old['end_date']) < strtotime($old['start_date'])) {
        $error = "End date cannot be before start date.";
    }

    if (!$error) {
        // verify worker exists
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE id = ?");
        $stmt->execute([$old['user_id']]);
        $worker = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$worker) {
            $error = "Selected worker not found.";
        } else {
            // prepare reason
            $reason_to_store = $old['reason'];
            if ($old['worker_name'] !== '') {
                $reason_to_store .= "\n\n[Worker Name Override: " . $old['worker_name'] . "]";
            }

            // encode uploaded file for BYTEA storage
            $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            $fileRes = encode_uploaded_file($_FILES['attachment'] ?? [], 8 * 1024 * 1024, $allowed);
            if ($fileRes['error']) {
                $error = $fileRes['error'];
            } else {
                // Insert into DB including attachment BYTEA and attachment_type
                try {
                    // Set verified_by to current HR user's id and status to 'verified'
                    $sql = "
                        INSERT INTO leave_requests
                        (user_id, leave_type_id, start_date, end_date, reason, total_days, attachment, attachment_type, approved_by, verified_by, verified_at, status)
                        VALUES
                        (:uid, :lt, :sd, :ed, :reason, :td, :attachment, :attachment_type, NULL, :verified_by, NOW(), 'verified')
                    ";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindValue(':uid', $old['user_id'], PDO::PARAM_INT);
                    $stmt->bindValue(':lt', $old['leave_type_id'], PDO::PARAM_INT);
                    $stmt->bindValue(':sd', $old['start_date'], PDO::PARAM_STR);
                    $stmt->bindValue(':ed', $old['end_date'], PDO::PARAM_STR);
                    $stmt->bindValue(':reason', $reason_to_store, PDO::PARAM_STR);
                    $stmt->bindValue(':td', floatval($old['total_days']), PDO::PARAM_STR);

                    if ($fileRes['data'] !== null) {
                        $stmt->bindValue(':attachment', $fileRes['data'], PDO::PARAM_LOB);
                        $stmt->bindValue(':attachment_type', $fileRes['type'], PDO::PARAM_STR);
                    } else {
                        $stmt->bindValue(':attachment', null, PDO::PARAM_NULL);
                        $stmt->bindValue(':attachment_type', null, PDO::PARAM_NULL);
                    }

                    // verified_by set to the HR user who submitted the request
                    $stmt->bindValue(':verified_by', $user['id'], PDO::PARAM_INT);

                    $stmt->execute();
                    $success = "Leave request submitted and verified by HR.";
                    // reset old values
                    $old = array_fill_keys(array_keys($old), '');
                } catch (PDOException $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        }
    }
}

// Fetch workers and leave types
$workers = $pdo->query("
    SELECT id, name, project, contract
    FROM users
    WHERE project != 'HQ'
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

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
<title>Worker Leave Request | Teraju HR System</title>
<link rel="stylesheet" href="../../assets/css/style.css">
<style>
.card {background: #fff; padding: 30px 40px; border-radius: 12px; box-shadow: 0 3px 10px rgba(0,0,0,0.1); max-width: 900px; margin: 0 auto;}
.card h2 {text-align: center; margin-bottom: 20px;}
.form-grid {display: grid; grid-template-columns: 1fr 1fr; gap: 20px 30px;}
.form-group {display: flex; flex-direction: column;}
.form-group label {font-weight: 600; margin-bottom: 6px;}
.form-group input, .form-group select, .form-group textarea {width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #d1d5db; background: #f9fafb; font-size: 0.95rem;}
.form-group textarea {min-height: 100px; resize: vertical;}
@media (max-width: 768px) {.form-grid {grid-template-columns: 1fr;}}
.success-box, .error-box {padding: 10px; border-radius: 6px; margin-bottom: 15px;}
.success-box { background: #dcfce7; color: #166534; }
.error-box { background: #fee2e2; color: #991b1b; }
.layout {display:flex;}

/* Submit button placement */
.form-actions {display:flex; justify-content:flex-end; align-items:center; margin-top: 18px;}
@media (min-width: 769px) {.form-actions .btn-full {width: 220px;}}
@media (max-width: 768px) {.form-actions {justify-content:stretch;} .form-actions .btn-full {width:100%;}}
</style>
</head>
<body>
<div class="layout">
    <?php include "sidebar.php"; ?>

    <div class="main-content">
        <header>
            <h1>Teraju HR System</h1>
        </header>

        <div class="card">
            <h2>Worker Leave Request</h2>

            <?php if ($error): ?>
                <div class="error-box"><?= htmlentities($error) ?></div>
            <?php elseif ($success): ?>
                <div class="success-box"><?= htmlentities($success) ?></div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data" novalidate>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Select Worker</label>
                        <select name="user_id" required>
                            <option value="">-- Select Worker --</option>
                            <?php foreach ($workers as $w): ?>
                                <option value="<?= (int)$w['id'] ?>" <?= ((int)$old['user_id'] === (int)$w['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($w['name']) ?> (<?= htmlspecialchars($w['project']) ?>) <?= $w['contract'] ? '[Contract]' : '[Permanent]' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Worker Name (Optional Override)</label>
                        <input type="text" name="worker_name" placeholder="Fill only if actual worker name differs" value="<?= htmlspecialchars($old['worker_name']) ?>">
                    </div>

                    <div class="form-group">
                        <label>Leave Type</label>
                        <select name="leave_type_id" required>
                            <option value="">-- Select Leave Type --</option>
                            <?php foreach ($leave_types as $lt): ?>
                                <option value="<?= (int)$lt['id'] ?>" <?= ((int)$old['leave_type_id'] === (int)$lt['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($lt['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Total Days</label>
                        <input type="number" step="0.5" name="total_days" required value="<?= htmlspecialchars($old['total_days']) ?>">
                    </div>

                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" required value="<?= htmlspecialchars($old['start_date']) ?>">
                    </div>

                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" required value="<?= htmlspecialchars($old['end_date']) ?>">
                    </div>

                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Reason</label>
                        <textarea name="reason" placeholder="Reason for requesting leave"><?= htmlspecialchars($old['reason']) ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Attachment (Optional)</label>
                        <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <small style="color:#666; margin-top:6px;">Optional: supporting documents (max 8MB)</small>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-full">Submit Leave Request</button>
                </div>
            </form>
        </div>

        <footer>
            &copy; <?= date('Y') ?> Teraju HR System
        </footer>
    </div>
</div>

<script src="../../assets/js/sidebar.js"></script>
</body>
</html>
<?php
session_start();
require_once '../../config/conn.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = $error = "";

// Fetch user info including saturday_cycle
$stmt = $pdo->prepare("SELECT name, saturday_cycle FROM users WHERE id = :id");
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$saturday_cycle = $user['saturday_cycle'] ?? 'work';

// Fetch leave types
$types = $pdo->query("SELECT id, name FROM leave_types ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Helper: identify emergency leave
function isEmergencyLeave($pdo, $leave_type_id) {
    $s = $pdo->prepare("SELECT name FROM leave_types WHERE id = :id");
    $s->execute([':id' => $leave_type_id]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    if (!$row) return false;
    return stripos(trim($row['name']), 'emergency') !== false;
}

// Calculate total leave days (used server-side for non-annual)
function calculateLeaveDaysPHP($start_date, $end_date, $leave_type_id, $saturday_cycle, $pdo) {
    if (isEmergencyLeave($pdo, $leave_type_id)) {
        return 0.5;
    }

    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $days = 0.0;

    while ($start <= $end) {
        $dow = (int)$start->format('w'); // 0=Sun,6=Sat

        if ($dow === 0) {
            // Sunday off
        } elseif ($dow === 6) {
            // Saturday logic
            if ($saturday_cycle !== 'none') {
                $yearStart = new DateTime($start->format('Y-01-01'));
                $weeksSinceYearStart = floor($start->diff($yearStart)->days / 7);
                $isWorkWeek = ($saturday_cycle === 'work')
                    ? ($weeksSinceYearStart % 2 === 0)
                    : ($weeksSinceYearStart % 2 === 1);
                if ($isWorkWeek) $days += 0.5;
            }
        } else {
            $days += 1.0;
        }

        $start->modify('+1 day');
    }

    return round($days, 2);
}

// Handle leave form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $leave_type = $_POST['leave_type'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    if ($leave_type && $start_date && $end_date) {
        try {
            // Use manual total_days if submitted, else calculate
            $total_days = isset($_POST['total_days']) && is_numeric($_POST['total_days'])
                ? (float) $_POST['total_days']
                : calculateLeaveDaysPHP($start_date, $end_date, $leave_type, $saturday_cycle, $pdo);

            $sql = "INSERT INTO leave_requests 
                    (user_id, leave_type_id, start_date, end_date, reason, total_days, status)
                    VALUES (:user_id, :leave_type, :start_date, :end_date, :reason, :total_days, 'pending')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':user_id' => $user_id,
                ':leave_type' => $leave_type,
                ':start_date' => $start_date,
                ':end_date' => $end_date,
                ':reason' => $reason,
                ':total_days' => $total_days
            ]);

            $success = "✅ Leave request submitted successfully! Total Days: $total_days";
        } catch (PDOException $e) {
            $error = "❌ Failed to submit leave request: " . $e->getMessage();
        }
    } else {
        $error = "⚠️ Please fill in all required fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Apply Leave | Teraju HR System</title>
<link rel="stylesheet" href="../../assets/css/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<style>
.card {background: #fff; padding: 30px 40px; border-radius: 12px;box-shadow: 0 3px 10px rgba(0,0,0,0.1);max-width: 900px;margin: 0 auto;}
.card h2 {text-align: center; margin-bottom: 20px;}
.form-grid {display: grid;grid-template-columns: 1fr 1fr;gap: 20px 30px;}
.form-group {display: flex;flex-direction: column;}
.form-group label {font-weight: 600;margin-bottom: 6px;}
.form-group input, .form-group select, textarea {width: 100%;padding: 10px;border-radius: 8px;border: 1px solid #d1d5db;background: #f9fafb;}
textarea {resize: none;}
@media (max-width: 768px) {.form-grid {grid-template-columns: 1fr;}}
.btn-full {display: block;width: 100%;padding: 12px;background: #2563eb;color: #fff;border: none;border-radius: 8px;font-size: 1rem;cursor: pointer;transition: background 0.3s;}
.btn-full:hover {background: #1e40af;}
.success-box, .error-box {padding: 10px;border-radius: 6px;margin-bottom: 15px;}
.success-box { background: #dcfce7; color: #166534; }
.error-box { background: #fee2e2; color: #991b1b; }
footer {text-align: center; margin-top: 40px;color: #666;font-size: 0.9rem;}
</style>
</head>
<body>
<div class="layout">
  <?php include "emp-sidebar.php"; ?>

  <header>
    <h1>Teraju Leave Management System</h1>
  </header>

  <div class="main-content">
    <div class="card">
      <h2>Apply for Leave</h2>

      <?php if ($error): ?>
        <div class="error-box"><?= htmlentities($error) ?></div>
      <?php elseif ($success): ?>
        <div class="success-box"><?= htmlentities($success) ?></div>
      <?php endif; ?>

      <form method="POST" action="">
        <div class="form-grid">
          <div class="form-group">
            <label for="leave_type">Leave Type <span style="color: red;">*</span></label>
            <select name="leave_type" id="leave_type" required>
              <option value="">-- Select Leave Type --</option>
              <?php foreach ($types as $t): ?>
                <option value="<?= $t['id'] ?>"><?= htmlentities($t['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="reason">Reason (optional)</label>
            <textarea name="reason" id="reason" rows="3"></textarea>
          </div>

          <div class="form-group">
            <label for="start_date">Start Date <span style="color: red;">*</span></label>
            <input type="text" id="start_date" name="start_date" required>
          </div>

          <div class="form-group">
              <label for="end_date">End Date <span style="color:red;">*</span></label>
              <input type="text" id="end_date" name="end_date" readonly>
          </div>

          <div class="form-group">
              <label for="total_days">Total Days <span style="color:red;">*</span></label>
              <input type="number" step="0.5" id="total_days" name="total_days" value="0" required>
              <small style="color: #555;">For Annual Leave: enter total days and end date will be auto-calculated. For others: choose start & end.</small>
          </div>
        </div>

        <button type="submit" class="btn-full" style="margin-top:20px;">Submit Leave Request</button>
      </form>

      <div style="text-align:center; margin-top:15px;">
        <a href="emp-dashboard.php" style="color:#2563eb; text-decoration:none;">← Back to Dashboard</a>
      </div>
    </div>

    <footer>
      &copy; <?= date('Y') ?> Teraju HR System
    </footer>
  </div>
</div>

<script>
/*
  Clean JS:
  - Annual: total_days editable (step 0.5) -> calculate end (readonly)
  - Other: choose start+end -> calculate total_days (readonly)
  - Emergency detected by name includes('emergency') -> total 0.5, end = start
  - Saturday cycle respected (variable from PHP)
  - Flatpickr preserved (Sundays disabled, minDate logic)
*/

const saturdayCycle = '<?= $saturday_cycle ?>';

// Flatpickr helpers and init
function disableSundays(date) { return date.getDay() === 0; }

const startPicker = flatpickr("#start_date", {
  dateFormat: "Y-m-d",
  disable: [disableSundays],
  onChange: function(selectedDates) {
    if (selectedDates && selectedDates[0]) {
      // ensure end cannot be set before start
      endPicker.set('minDate', selectedDates[0]);
    } else {
      endPicker.set('minDate', null);
    }
    onStartChanged();
  }
});

const endPicker = flatpickr("#end_date", {
  dateFormat: "Y-m-d",
  disable: [disableSundays],
  onChange: function(selectedDates) {
    onEndChanged();
  }
});

// DOM elements
const leaveTypeSelect = document.getElementById('leave_type');
const totalDaysInput = document.getElementById('total_days');
const endInput = document.getElementById('end_date');
const startInput = document.getElementById('start_date');

// tiny helpers
function txtOfLeave() {
  return (leaveTypeSelect.options[leaveTypeSelect.selectedIndex]?.text || '').toLowerCase();
}
function isAnnual() { return txtOfLeave().includes('annual'); }
function isEmergency() { return txtOfLeave().includes('emergency'); }

// SATURDAY work-week detection (same logic as server)
function isWorkSaturday(date) {
  if (saturdayCycle === 'none') return false;
  const yearStart = new Date(date.getFullYear(), 0, 1);
  const diffDays = Math.floor((date - yearStart) / (1000*60*60*24));
  const weeksSince = Math.floor(diffDays / 7);
  return (saturdayCycle === 'work') ? (weeksSince % 2 === 0) : (weeksSince % 2 === 1);
}

// Calculate end date from start + totalDays (annual mode)
// Count includes start day (i.e., total_days=1 -> end = start if weekday)
function calculateEndDateForAnnual() {
  const startDate = startPicker.selectedDates[0];
  const total = parseFloat(totalDaysInput.value);

  if (!startDate || isNaN(total) || total <= 0) {
    endPicker.clear();
    endInput.value = '';
    return;
  }

  let remaining = total;
  let current = new Date(startDate);

  // loop until remaining consumed (include start)
  while (true) {
    const dow = current.getDay();
    if (dow === 0) {
      // Sunday skip
    } else if (dow === 6) {
      if (isWorkSaturday(current)) remaining -= 0.5;
    } else {
      remaining -= 1.0;
    }

    if (remaining <= 0) break;

    current.setDate(current.getDate() + 1);
  }

  const iso = current.toISOString().split('T')[0];
  endPicker.setDate(iso, true, 'Y-m-d');
}

// Calculate totalDays from start+end (non-annual). inclusive
function calculateTotalDaysFromRange() {
  const startDate = startPicker.selectedDates[0];
  const endDate = endPicker.selectedDates[0];
  if (!startDate || !endDate || endDate < startDate) {
    totalDaysInput.value = 0;
    return;
  }

  let count = 0.0;
  let current = new Date(startDate);

  while (current <= endDate) {
    const dow = current.getDay();
    if (dow === 0) {
      // skip Sundays
    } else if (dow === 6) {
      if (isWorkSaturday(current)) count += 0.5;
    } else {
      count += 1.0;
    }
    current.setDate(current.getDate() + 1);
  }

  totalDaysInput.value = Math.round(count * 100) / 100;
}

// UI controller: set readonly / behaviors based on leave type
function updateUIForLeaveType() {
  const txt = txtOfLeave();

  if (isEmergency()) {
    // emergency: total = 0.5, end = start, both readonly
    totalDaysInput.value = 0.5;
    totalDaysInput.setAttribute('readonly', true);
    totalDaysInput.step = '0.5';

    const s = startPicker.selectedDates[0];
    if (s) {
      const iso = s.toISOString().split('T')[0];
      endPicker.setDate(iso, true, 'Y-m-d');
    } else {
      endPicker.clear();
    }
    endInput.setAttribute('readonly', true);
    return;
  }

  if (isAnnual()) {
    // annual: user inputs total, end auto calc, end readonly
    totalDaysInput.removeAttribute('readonly');
    totalDaysInput.step = '0.5';
    endInput.setAttribute('readonly', true);

    // If start exists and total has value, recalc end
    if (startPicker.selectedDates[0] && parseFloat(totalDaysInput.value) > 0) {
      calculateEndDateForAnnual();
    } else {
      endPicker.clear();
      endInput.value = '';
    }
    return;
  }

  // normal (non-annual, non-emergency)
  totalDaysInput.setAttribute('readonly', true);
  totalDaysInput.step = '0.5'; // keep step OK but readonly
  endInput.removeAttribute('readonly');

  // If both start+end exist, calculate total
  if (startPicker.selectedDates[0] && endPicker.selectedDates[0]) calculateTotalDaysFromRange();
}

// Handlers triggered by Flatpickr / inputs
function onStartChanged() {
  const txt = txtOfLeave();

  // ensure end minDate is updated (flatpickr already sets)
  // For emergency: set end = start
  if (isEmergency()) {
    const s = startPicker.selectedDates[0];
    if (s) endPicker.setDate(s, true, 'Y-m-d');
    endInput.setAttribute('readonly', true);
  }

  if (isAnnual()) {
    // recalc end from total if total provided
    calculateEndDateForAnnual();
  } else {
    // recalc total if end exists
    if (endPicker.selectedDates[0]) calculateTotalDaysFromRange();
  }
}

function onEndChanged() {
  if (!isAnnual() && !isEmergency()) {
    calculateTotalDaysFromRange();
  }
}

// totalDays input changed (typing or arrows)
function onTotalChanged() {
  if (isAnnual()) {
    // do not allow the system to overwrite user input; just use it
    calculateEndDateForAnnual();
  }
}

// wire events
leaveTypeSelect.addEventListener('change', function() {
  // when changing leave type reset behaviours; keep total value unless switching away from annual
  updateUIForLeaveType();
});

totalDaysInput.addEventListener('input', onTotalChanged);
totalDaysInput.addEventListener('change', onTotalChanged);

// initialize once DOM loaded
document.addEventListener('DOMContentLoaded', function() {
  // set initial read/write states correctly
  updateUIForLeaveType();
});
</script>

</body>
</html>

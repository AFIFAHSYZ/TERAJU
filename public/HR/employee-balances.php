<?php
session_start();
require_once '../../config/conn.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Check HR role
$stmt = $pdo->prepare("SELECT name, position FROM users WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user['position'] !== 'hr') {
    header("Location: ../../unauthorized.php");
    exit();
}

// Get employee ID
$emp_id = $_GET['id'] ?? null;
if (!$emp_id) {
    header("Location: employees.php");
    exit();
}

// Fetch employee info
$stmt = $pdo->prepare("SELECT name, email, position, date_joined FROM users WHERE id = :id");
$stmt->execute([':id' => $emp_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch leave balances
$sql = "
    SELECT 
        lt.id AS leave_type_id,
        lt.name AS leave_type,
        lb.year,
        lb.entitled_days,
        lb.used_days,
        lb.carry_forward,
        lb.total_available
    FROM leave_balances lb
    JOIN leave_types lt ON lb.leave_type_id = lt.id
    WHERE lb.user_id = :emp_id
    ORDER BY lt.id
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':emp_id' => $emp_id]);
$balances = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($employee['name']) ?> | Leave Balances</title>
<link rel="stylesheet" href="../../assets/css/style.css">
<style>
  body { font-family: 'Inter', sans-serif; background: #f9fafb; color: #111827; }
  .card { background: #fff; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); padding: 20px; }
  .card h2 { margin-bottom: 5px; font-size: 1.5rem; color: #1e293b; }
  .employee-info { margin-bottom: 20px; background: #f8fafc; padding: 15px; border-radius: 10px; }
  .employee-info p { margin: 5px 0; }
  table.leave-table { width: 100%; border-collapse: collapse; margin-top: 10px; border-radius: 10px; overflow: hidden; }
  table.leave-table th, table.leave-table td { border: 1px solid #e5e7eb; padding: 12px; text-align: center; }
  table.leave-table th { background: #2563eb; color: #fff; text-transform: uppercase; font-size: 0.9rem; }
  table.leave-table tr:nth-child(even) { background: #f9fafb; }
  .btn-back { background: #64748b; color: #fff; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-weight: 500; transition: 0.2s; }
  .btn-back:hover { background: #475569; }

  /* Carry forward column */
/* Carry Forward Cell */
.carry-cell {
  position: relative;
  text-align: center;
  min-width: 100px;
}

.carry-value {
  display: inline-block;
  text-align: center;
  font-weight: 500;
}

input.carry-input {
  width: 60px;
  text-align: center;
  padding: 4px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
}

.edit-btn,
.save-btn {
  position: absolute;
  right: 6px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  font-size: 1.1rem;
  transition: 0.2s;
}

.edit-btn { color: #2563eb; }
.save-btn { color: #16a34a; display: none; }

.edit-btn:hover { transform: translateY(-50%) scale(1.15); }
.save-btn:hover { transform: translateY(-50%) scale(1.15); }
  .status-msg { position: fixed; top: 20px; right: 20px; background: #16a34a; color: #fff; padding: 10px 15px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); opacity: 0; transition: opacity 0.3s ease; }
  .status-msg.show { opacity: 1; }
</style>
</head>
<body>
<div class="layout">
  <?php include 'sidebar.php'; ?>
<header><h1>Leave Management System</h1></header>

  <main class="main-content"> 
    <div style="margin-top:15px; margin-bottom: 15px;">
        <a href="employees.php" class="btn-back">← Back to Employees</a>
    </div>

    <div class="card">
      <h2>Leave Balances</h2>
      <h2><?= htmlspecialchars($employee['name']) ?></h2>

      <div class="employee-info">
        <p><strong>Email:</strong> <?= htmlspecialchars($employee['email']) ?></p>
        <p><strong>Position:</strong> <?= ucfirst($employee['position']) ?></p>
        <p><strong>Date Joined:</strong> <?= $employee['date_joined'] ?></p>
      </div>

      <table class="leave-table">
        <thead>
          <tr>
            <th>Leave Type</th>
            <th>Year</th>
            <th>Entitled</th>
            <th>Used</th>
            <th>Carry Forward</th>
            <th>Total Available</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($balances as $b): ?>
            <tr>
              <td><?= htmlspecialchars($b['leave_type']) ?></td>
              <td><?= htmlspecialchars($b['year']) ?></td>
              <td><?= htmlspecialchars($b['entitled_days']) ?></td>
              <td><?= htmlspecialchars($b['used_days']) ?></td>
<td>
  <?php if (strtolower($b['leave_type']) === 'annual leave'): ?>
    <div class="carry-cell" data-leave-id="<?= $b['leave_type_id'] ?>" data-user-id="<?= $emp_id ?>">
      <span class="carry-value"><?= htmlspecialchars($b['carry_forward']) ?></span>
      <input type="number" class="carry-input" min="0" max="5"
             value="<?= htmlspecialchars($b['carry_forward']) ?>" style="display:none;">
      <button class="edit-btn" title="Edit">✏️</button>
      <button class="save-btn" title="Save">✅</button>
    </div>
  <?php else: ?>
    <?= htmlspecialchars($b['carry_forward']) ?>
  <?php endif; ?>
</td>
              <td><strong><?= htmlspecialchars($b['total_available']) ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

    </div>
  </main>
</div>

<div id="status" class="status-msg">Carry forward updated ✅</div>

<script src="../../assets/js/sidebar.js"></script>
<script>
// Inline editing logic
document.querySelectorAll('.carry-cell').forEach(cell => {
  const editBtn = cell.querySelector('.edit-btn');
  const saveBtn = cell.querySelector('.save-btn');
  const span = cell.querySelector('.carry-value');
  const input = cell.querySelector('.carry-input');
  const leaveId = cell.dataset.leaveId;
  const userId = cell.dataset.userId;

  editBtn.addEventListener('click', () => {
    span.style.display = 'none';
    editBtn.style.display = 'none';
    input.style.display = 'inline-block';
    saveBtn.style.display = 'inline-block';
    input.focus();
  });

  saveBtn.addEventListener('click', async () => {
    let newValue = parseInt(input.value) || 0;
    if (newValue > 5) newValue = 5;
    if (newValue < 0) newValue = 0;

    const formData = new FormData();
    formData.append('user_id', userId);
    formData.append('leave_id', leaveId);
    formData.append('carry_forward', newValue);

    try {
      const response = await fetch('update_carry_ajax.php', {
        method: 'POST',
        body: formData
      });

      const data = await response.json();
      if (data.success) {
        // Update displayed carry_forward
        span.textContent = data.carry_forward;

        // Update total_available cell in the same row
        const totalCell = cell.parentElement.querySelector('td:last-child strong');
        if (totalCell) totalCell.textContent = data.total_available;

        // Restore display state
        span.style.display = 'inline-block';
        editBtn.style.display = 'inline-block';
        input.style.display = 'none';
        saveBtn.style.display = 'none';

        showStatus(); // ✅ success popup
      } else {
        alert('Update failed. Please try again.');
      }
    } catch (err) {
      console.error(err);
      alert('Error updating carry forward.');
    }
  });
});

function showStatus() {
  const msg = document.getElementById('status');
  msg.classList.add('show');
  setTimeout(() => msg.classList.remove('show'), 2000);
}
</script>
</body>
</html>

<?php
session_start();
require_once '../../config/conn.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// ========= Pagination defaults =========
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$user_id = $_SESSION['user_id'];

// ✅ Fetch manager info
$stmt = $pdo->prepare("SELECT name, position FROM users WHERE id = :id");
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Only allow manager or HR
if (!in_array($user['position'], ['manager', 'hr'])) {
    header("Location: ../../unauthorized.php");
    exit();
}

// 🧾 Handle filters
$statusFilter = $_GET['status'] ?? '';
$typeFilter = $_GET['type'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

$where = [];
$params = [];

if ($statusFilter !== '') {
    $where[] = "lr.status = :status";
    $params[':status'] = $statusFilter;
}
if ($typeFilter !== '') {
    $where[] = "lt.id = :type";
    $params[':type'] = $typeFilter;
}
if ($startDate !== '' && $endDate !== '') {
    // ensure valid dates (basic)
    $where[] = "lr.start_date BETWEEN :start AND :end";
    $params[':start'] = $startDate;
    $params[':end'] = $endDate;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ======= Count total for pagination =======
$countSql = "
SELECT COUNT(*) AS cnt
FROM leave_requests lr
JOIN users u ON lr.user_id = u.id
LEFT JOIN leave_types lt ON lr.leave_type_id = lt.id
{$whereSQL}
";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = $total > 0 ? (int)ceil($total / $limit) : 1;
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}

// 🧮 Fetch leave requests (with LIMIT/OFFSET)
$sql = "
SELECT lr.id, u.name AS employee_name, lt.name AS leave_type,
       lr.start_date, lr.end_date, lr.reason, lr.status, lr.applied_at
FROM leave_requests lr
JOIN users u ON lr.user_id = u.id
LEFT JOIN leave_types lt ON lr.leave_type_id = lt.id
{$whereSQL}
ORDER BY lr.applied_at DESC
LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
// bind filter params
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 🗂 Fetch leave types for filters
$types = $pdo->query("SELECT id, name FROM leave_types ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Helper: build query string of current filters (excluding page)
$filterParams = [];
if ($statusFilter !== '') $filterParams['status'] = $statusFilter;
if ($typeFilter !== '') $filterParams['type'] = $typeFilter;
if ($startDate !== '') $filterParams['start_date'] = $startDate;
if ($endDate !== '') $filterParams['end_date'] = $endDate;
$filterQuery = http_build_query($filterParams);
if ($filterQuery !== '') $filterQuery = '&' . $filterQuery;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Team Leaves | Manager Dashboard</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .filter-form {display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px;align-items: flex-end; background: #fff; padding: 15px;border-radius: 10px; box-shadow: 0 3px 10px rgba(0,0,0,0.05); }
        .filter-form label { display: block; font-size: 0.9rem; color: #334155; margin-bottom: 5px; }
        .filter-form input, .filter-form select { padding: 8px 10px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 0.9rem; width: 150px; }
        .filter-form button { padding: 9px 16px; background: #334155; border: none; border-radius: 8px;  color: #fff; font-weight: 600; cursor: pointer; }
        .filter-form button:hover { background: #283242ff; }
        .btn-review {background: #334155;color: #fff;border: none;padding: 6px 10px;border-radius: 6px;text-decoration: none;font-size: 0.85rem; cursor: pointer;}
        .btn-review:hover {background: #283242ff;}
        .status-approved {color: #1ca74fff; font-weight: 600;}
        .status-rejected {color: #d72828ff; font-weight: 600;}
        .status-pending {color: #e7970dff; font-weight: 600;}
        .status-verified {color: #3c59c2ff; font-weight: 600;}
        .leave-table { width:100%; border-collapse: collapse; background:#fff; border-radius:8px; overflow:hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        .leave-table th, .leave-table td { padding: 10px 12px; text-align:left; border-bottom: 1px solid #eef2f7; font-size:0.95rem; }
        .leave-table thead th { background:#f8fafc; font-weight:700; color:#334155; }
    </style>
</head>
<body>
<div class="layout">

    <!-- Sidebar -->
    <?php include 'm-sidebar.php'; ?>

    <header>
        <h1>Leave Management System</h1>
    </header>

    <!-- Main -->
    <main class="main-content">
        <div class="card">
            <h2>Team Leave Requests</h2>

            <!-- Filters -->
            <form method="GET" class="filter-form">
                <select name="status">
                    <option value="">All Status</option>
                    <option value="pending" <?= $statusFilter==='pending'?'selected':'' ?>>Pending</option>
                    <option value="approved" <?= $statusFilter==='approved'?'selected':'' ?>>Approved</option>
                    <option value="rejected" <?= $statusFilter==='rejected'?'selected':'' ?>>Rejected</option>
                    <option value="verified" <?= $statusFilter==='verified'?'selected':'' ?>>Verified</option>
                </select>

                <select name="type">
                    <option value="">All Leave Types</option>
                    <?php foreach ($types as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= (string)$typeFilter === (string)$t['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
                <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">

                <button type="submit">Filter</button>
            </form>

            <!-- Table -->
            <table class="leave-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Employee</th>
                        <th>Leave Type</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="7" style="text-align:center;">No records found</td></tr>
                    <?php else: ?>
                        <?php foreach ($requests as $i => $r): ?>
                            <tr>
                                <td><?= $offset + $i + 1 ?></td>
                                <td><?= htmlspecialchars($r['employee_name']) ?></td>
                                <td><?= htmlspecialchars($r['leave_type'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($r['start_date']) ?></td>
                                <td><?= htmlspecialchars($r['end_date']) ?></td>
                                <td class="status-<?= strtolower($r['status'] ?? 'pending') ?>">
                                    <?= htmlspecialchars(ucfirst($r['status'] ?? 'Pending')) ?>
                                </td>
                                <td>
                                    <a href="review-request.php?id=<?= (int)$r['id'] ?>" class="btn-review">Review</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="pagination" role="navigation" aria-label="Pagination">
                <?php
                // links preserving filters
                $base = '?';
                if ($filterQuery !== '') {
                    // filterQuery already prefixed with &
                    $filtersForLink = substr($filterQuery, 1); // remove leading &
                } else {
                    $filtersForLink = '';
                }
                // Prev
                if ($page > 1): ?>
                    <a href="<?= '?page=' . ($page - 1) . ($filtersForLink ? '&' . htmlspecialchars($filtersForLink) : '') ?>">‹ Prev</a>
                <?php else: ?>
                    <span class="disabled">‹ Prev</span>
                <?php endif; ?>

                <?php
                // show a limited range of pages to avoid huge lists
                $start = max(1, $page - 3);
                $end = min($totalPages, $page + 3);
                if ($start > 1): ?>
                    <a href="<?= '?page=1' . ($filtersForLink ? '&' . htmlspecialchars($filtersForLink) : '') ?>">1</a>
                    <?php if ($start > 2): ?><span>…</span><?php endif; ?>
                <?php endif; ?>

                <?php for ($p = $start; $p <= $end; $p++): ?>
                    <?php if ($p == $page): ?>
                        <span class="active"><?= $p ?></span>
                    <?php else: ?>
                        <a href="<?= '?page=' . $p . ($filtersForLink ? '&' . htmlspecialchars($filtersForLink) : '') ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($end < $totalPages): ?>
                    <?php if ($end < $totalPages - 1): ?><span>…</span><?php endif; ?>
                    <a href="<?= '?page=' . $totalPages . ($filtersForLink ? '&' . htmlspecialchars($filtersForLink) : '') ?>"><?= $totalPages ?></a>
                <?php endif; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="<?= '?page=' . ($page + 1) . ($filtersForLink ? '&' . htmlspecialchars($filtersForLink) : '') ?>">Next ›</a>
                <?php else: ?>
                    <span class="disabled">Next ›</span>
                <?php endif; ?>
            </div>

        </div>
    </main>
</div>
<script src="../../assets/js/sidebar.js"></script>
</body>
</html>
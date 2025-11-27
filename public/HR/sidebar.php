<aside class="sidebar">
  <div class="user-profile">
    <h2>LEAVE</h2>
    <div class="avatar"><?= strtoupper(substr($user['name'], 0, 1)); ?></div>
    <p class="user-name"><?= htmlspecialchars($user['name']); ?></p>
  </div>

  <nav>
    <ul class="menu">
      <li><a href="hr-dashboard.php">Dashboard</a></li>

      <li class="has-dropdown">
        <a href="javascript:void(0)">Employees <span class="arrow">›</span></a>
        <ul class="dropdown-menu">
          <li><a href="employees.php">Employee List</a></li>
          <li><a href="add-worker.php">Add Worker</a></li>
          <li><a href="worker-leave-request.php">Worker Leave Requests</a></li>
          <li><a href="all-requests.php">All Requests</a></li>
        </ul>
      </li>

      <li class="has-dropdown">
        <a href="javascript:void(0)">My Leave  <span class="arrow">›</span></a>
        <ul class="dropdown-menu">
          <li><a href="hr-request.php">Request Leaves</a></li>
          <li><a href="hr-leaves.php">My Request History</a></li>
        </ul>
      </li>

      <li><a href="holidays.php">Public Holidays</a></li>

      <li><a href="hr-reports.php">Leave Reports</a></li>

      <li><a href="hr-profile.php">Profile</a></li>
      <li><a href="../logout.php" id="logout-link">Logout</a></li>
    </ul>
  </nav>

  <div class="sidebar-footer">&copy; <?= date('Y'); ?> Teraju LMS</div>
</aside>


</script>
<html><style>    
    .sidebar ul li {position: relative;}
    .sidebar ul li a {display: flex; justify-content: space-between; align-items: center; padding: 10px 20px; font-size: 0.9rem; color: #fff; text-decoration: none;}
    .sidebar ul li a .arrow {font-size: 0.8rem; transition: transform 0.3s ease;}
    .sidebar ul li.active > a .arrow {transform: rotate(180deg);}
    .sidebar ul li .dropdown-menu {display: none; flex-direction: column; background: #2a343aff; padding-left: 0;}
    .sidebar ul li.active .dropdown-menu {display: flex !important;flex-direction: column;}
    .sidebar ul li .dropdown-menu li a {padding: 8px 30px; font-size: 0.85rem;}
</style></html>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const logoutLink = document.getElementById('logout-link');
  if (logoutLink) {
    logoutLink.addEventListener('click', function (e) {
      const confirmLogout = confirm('Are you sure you want to log out?');
      if (!confirmLogout) {
        e.preventDefault(); // Cancel logout if user clicks Cancel
      }
    });
  }
});
</script>

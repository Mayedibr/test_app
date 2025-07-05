<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$page = basename($_SERVER['PHP_SELF']);
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<div class="sidebar d-flex flex-column flex-shrink-0 p-3">
    <a href="dashboard.php" class="navbar-brand d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
        <img src="../assets/logo.png" alt="Logo"> Entitlements
    </a>
    <hr>
    <ul class="nav nav-pills flex-column mb-auto">
        <li><a href="dashboard.php" class="nav-link<?php if($page=='dashboard.php') echo ' active';?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
        <li><a href="employees.php" class="nav-link<?php if($page=='employees.php') echo ' active';?>"><i class="bi bi-people me-2"></i>Add Employees</a></li>
        <li><a href="entitlement_types.php" class="nav-link<?php if($page=='entitlement_types.php') echo ' active';?>"><i class="bi bi-list-check me-2"></i>Entitlement Types</a></li>
        <li><a href="assign_entitlement.php" class="nav-link<?php if($page=='assign_entitlement.php') echo ' active';?>"><i class="bi bi-award me-2"></i>Assign Entitlements</a></li>
        <?php if ($_SESSION['role'] === 'admin'): ?>
        <li><a href="users.php" class="nav-link<?php if($page=='users.php') echo ' active';?>"><i class="bi bi-person-gear me-2"></i>User Management</a></li>
        <li><a href="audit_logs.php" class="nav-link<?php if($page=='audit_logs.php') echo ' active';?>"><i class="bi bi-journal-text me-2"></i>Audit Logs</a></li>
        <?php endif; ?>
    </ul>
    <hr>
    <div class="mt-auto">
        <div class="d-flex align-items-center">
            <i class="bi bi-person-circle me-2"></i>
            <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
        </div>
        <a href="logout.php" class="btn btn-outline-warning btn-sm mt-2"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
</div> 
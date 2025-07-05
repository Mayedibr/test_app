<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once '../db/db.php';
// Get stats
$empCount = $pdo->query('SELECT COUNT(*) FROM employees')->fetchColumn();
$entCount = $pdo->query('SELECT COUNT(*) FROM entitlements')->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Employee Entitlements</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">Entitlements</a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link active" href="dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="employees.php">Add Employees</a></li>
                <li class="nav-item"><a class="nav-link" href="entitlement_types.php">Entitlement Types</a></li>
                <li class="nav-item"><a class="nav-link" href="assign_entitlement.php">Assign Entitlements</a></li>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                <li class="nav-item"><a class="nav-link" href="users.php">User Management</a></li>
                <?php endif; ?>
            </ul>
            <span class="navbar-text me-3">Welcome, <?= htmlspecialchars($_SESSION['username']) ?></span>
            <a href="logout.php" class="btn btn-outline-secondary btn-sm">Logout</a>
        </div>
    </div>
</nav>
<div class="main-content">
    <h1 class="mb-4">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card p-3">
                <h5>Employees by Department</h5>
                <canvas id="deptChart"></canvas>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card p-3">
                <h5>Entitlements by Type</h5>
                <canvas id="typeChart"></canvas>
            </div>
        </div>
    </div>
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card p-3">
                <h5>Recent Activity</h5>
                <ul class="list-group">
                <?php
                $recent = $pdo->query('SELECT a.*, u.username FROM audit_logs a JOIN users u ON a.user_id=u.id ORDER BY a.id DESC LIMIT 5')->fetchAll();
                foreach ($recent as $r) {
                    echo '<li class="list-group-item">'.htmlspecialchars($r['username']).' - '.htmlspecialchars($r['action']).' ('.htmlspecialchars($r['created_at']).')</li>';
                }
                ?>
                </ul>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card p-3">
                <h5>Quick Links</h5>
                <a href="employees.php" class="btn btn-primary mb-2"><i class="bi bi-people"></i> Employees</a>
                <a href="assign_entitlement.php" class="btn btn-success mb-2"><i class="bi bi-award"></i> Assign Entitlement</a>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="users.php" class="btn btn-warning mb-2"><i class="bi bi-person-gear"></i> User Management</a>
                <a href="audit_logs.php" class="btn btn-info mb-2"><i class="bi bi-journal-text"></i> Audit Logs</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script>
// Fetch data for charts via PHP
<?php
$depts = $pdo->query('SELECT department, COUNT(*) as cnt FROM employees GROUP BY department')->fetchAll();
$deptLabels = [];
$deptData = [];
foreach ($depts as $d) { $deptLabels[] = $d['department'] ?: 'Unassigned'; $deptData[] = $d['cnt']; }
$types = $pdo->query('SELECT t.name, COUNT(e.id) as cnt FROM entitlement_types t LEFT JOIN entitlements e ON t.id=e.entitlement_type_id GROUP BY t.id')->fetchAll();
$typeLabels = [];
$typeData = [];
foreach ($types as $t) { $typeLabels[] = $t['name']; $typeData[] = $t['cnt']; }
?>
const deptChart = new Chart(document.getElementById('deptChart'), {
    type: 'doughnut',
    data: { labels: <?php echo json_encode($deptLabels); ?>, datasets: [{ data: <?php echo json_encode($deptData); ?>, backgroundColor: ['#232946','#ffd803','#a1a1aa','#b8c1ec','#eebbc3'] }] },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
const typeChart = new Chart(document.getElementById('typeChart'), {
    type: 'bar',
    data: { labels: <?php echo json_encode($typeLabels); ?>, datasets: [{ data: <?php echo json_encode($typeData); ?>, backgroundColor: '#232946' }] },
    options: { responsive: true, plugins: { legend: { display: false } } }
});
</script>
</body>
</html> 
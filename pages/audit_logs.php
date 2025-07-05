<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}
require_once '../db/db.php';
// Search/filter
$search = trim($_GET['search'] ?? '');
$where = $search ? "WHERE a.action LIKE ? OR a.details LIKE ? OR u.username LIKE ?" : '';
$params = $search ? ["%$search%", "%$search%", "%$search%"] : [];
$sql = "SELECT a.*, u.username FROM audit_logs a JOIN users u ON a.user_id = u.id $where ORDER BY a.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();
// Export to CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename=logs.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['#', 'User', 'Action', 'Details', 'Timestamp']);
    foreach ($logs as $log) {
        fputcsv($out, [$log['id'], $log['username'], $log['action'], $log['details'], $log['created_at']]);
    }
    fclose($out);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.6/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.6/js/dataTables.bootstrap5.min.js"></script>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="main-content">
    <h2>Audit Logs</h2>
    <button class="btn btn-outline-success mb-3" onclick="window.location='?export=csv'">Export CSV</button>
    <button class="btn btn-outline-danger mb-3" onclick="window.location='?export=pdf'">Export PDF</button>
    <table id="logsTable" class="table table-hover table-bordered w-100">
        <thead><tr><th>#</th><th>User</th><th>Action</th><th>Details</th><th>Timestamp</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
            <tr>
                <td><?php echo $l['id']; ?></td>
                <td><?php echo htmlspecialchars($l['username']); ?></td>
                <td><?php echo htmlspecialchars($l['action']); ?></td>
                <td><?php echo htmlspecialchars($l['details']); ?></td>
                <td><?php echo htmlspecialchars($l['created_at']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="toast-container position-fixed top-0 end-0 p-3">
      <?php if (!empty($success)): ?><div class="toast align-items-center text-bg-success border-0 show" role="alert"><div class="d-flex"><div class="toast-body"><?php echo $success; ?></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div><?php endif; ?>
      <?php if (!empty($error)): ?><div class="toast align-items-center text-bg-danger border-0 show" role="alert"><div class="d-flex"><div class="toast-body"><?php echo $error; ?></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div><?php endif; ?>
    </div>
</div>
<script>
$(function(){ $('#logsTable').DataTable({responsive:true}); });
</script>
</body>
</html> 
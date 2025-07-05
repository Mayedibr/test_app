<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once '../db/db.php';
$table = $_GET['table'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT h.*, u.username FROM edit_history h JOIN users u ON h.user_id=u.id WHERE h.table_name=? AND h.record_id=? ORDER BY h.id DESC');
$stmt->execute([$table, $id]);
$history = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit History</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.6/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.6/js/dataTables.bootstrap5.min.js"></script>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container">
    <h1 class="mb-4">Edit History (<?= htmlspecialchars($table) ?> #<?= $id ?>)</h1>
    <div class="main-content">
        <h2>Edit History</h2>
        <table id="historyTable" class="table table-hover table-bordered w-100">
            <thead><tr><th>#</th><th>User</th><th>Action</th><th>Old Data</th><th>New Data</th><th>Timestamp</th></tr></thead>
            <tbody>
            <?php foreach ($history as $h): ?>
                <tr>
                    <td><?php echo $h['id']; ?></td>
                    <td><?php echo htmlspecialchars($h['username']); ?></td>
                    <td><?php echo htmlspecialchars($h['action']); ?></td>
                    <td><pre style="white-space:pre-wrap;max-width:300px;"><?php echo htmlspecialchars($h['old_data']); ?></pre></td>
                    <td><pre style="white-space:pre-wrap;max-width:300px;"><?php echo htmlspecialchars($h['new_data']); ?></pre></td>
                    <td><?php echo htmlspecialchars($h['created_at']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="toast-container position-fixed top-0 end-0 p-3">
          <?php if (!empty($success)): ?><div class="toast align-items-center text-bg-success border-0 show" role="alert"><div class="d-flex"><div class="toast-body"><?php echo $success; ?></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div><?php endif; ?>
          <?php if (!empty($error)): ?><div class="toast align-items-center text-bg-danger border-0 show" role="alert"><div class="d-flex"><div class="toast-body"><?php echo $error; ?></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div><?php endif; ?>
        </div>
    </div>
    <a href="javascript:history.back()" class="btn btn-secondary">Back</a>
</div>
<script>
$(function(){ $('#historyTable').DataTable({responsive:true}); });
</script>
</body>
</html> 
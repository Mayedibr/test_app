<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once '../db/db.php';
$error = '';
$success = '';
// Add new type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_type'])) {
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $reqs = trim($_POST['requirements'] ?? '');
    if ($name) {
        $stmt = $pdo->prepare('INSERT INTO entitlement_types (name, description, requirements) VALUES (?, ?, ?)');
        try {
            $stmt->execute([$name, $desc, $reqs]);
            $success = 'Entitlement type added.';
            log_audit($pdo, $_SESSION['user_id'], 'Add Entitlement Type', $name);
        } catch (PDOException $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    } else {
        $error = 'Name is required.';
    }
}
// Handle edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_type'])) {
    $id = (int)$_POST['edit_id'];
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $reqs = trim($_POST['requirements'] ?? '');
    $old = $pdo->query("SELECT * FROM entitlement_types WHERE id=$id")->fetch();
    $stmt = $pdo->prepare('UPDATE entitlement_types SET name=?, description=?, requirements=? WHERE id=?');
    $stmt->execute([$name, $desc, $reqs, $id]);
    log_edit_history($pdo, 'entitlement_types', $id, $_SESSION['user_id'], 'edit', $old, ['name'=>$name,'description'=>$desc,'requirements'=>$reqs]);
    $success = 'Entitlement type updated.';
}
// Handle delete
if (isset($_GET['delete']) && $_SESSION['role'] === 'admin') {
    $id = (int)$_GET['delete'];
    $old = $pdo->query("SELECT * FROM entitlement_types WHERE id=$id")->fetch();
    $stmt = $pdo->prepare('DELETE FROM entitlement_types WHERE id=?');
    $stmt->execute([$id]);
    log_edit_history($pdo, 'entitlement_types', $id, $_SESSION['user_id'], 'delete', $old, null);
    $success = 'Entitlement type deleted.';
}
// Search/filter
$search = trim($_GET['search'] ?? '');
$where = $search ? "WHERE name LIKE ?" : '';
$params = $search ? ["%$search%"] : [];
$types = $pdo->prepare("SELECT * FROM entitlement_types $where ORDER BY id DESC");
$types->execute($params);
$types = $types->fetchAll();
// Advanced filtering
$filter_name = trim($_GET['filter_name'] ?? '');
$filter_reqs = trim($_GET['filter_reqs'] ?? '');
$where = [];
$params = [];
if ($filter_name) { $where[] = 'name LIKE ?'; $params[] = "%$filter_name%"; }
if ($filter_reqs) { $where[] = 'requirements LIKE ?'; $params[] = "%$filter_reqs%"; }
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$sql = "SELECT * FROM entitlement_types $where_sql ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$types = $stmt->fetchAll();
// Export to CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="entitlement_types.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['#', 'Name', 'Description', 'Requirements']);
    foreach ($types as $type) {
        fputcsv($out, [$type['id'], $type['name'], $type['description'], $type['requirements']]);
    }
    fclose($out);
    exit();
}
// PDF export (using TCPDF)
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    require_once '../assets/tcpdf/tcpdf.php';
    $pdf = new TCPDF();
    $pdf->AddPage();
    $html = '<h2>Entitlement Types</h2><table border="1" cellpadding="4"><tr><th>Name</th><th>Description</th><th>Requirements</th></tr>';
    foreach ($types as $t) {
        $html .= '<tr><td>'.htmlspecialchars($t['name']).'</td><td>'.htmlspecialchars($t['description']).'</td><td>'.htmlspecialchars($t['requirements']).'</td></tr>';
    }
    $html .= '</table>';
    $pdf->writeHTML($html);
    $pdf->Output('entitlement_types.pdf', 'D');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entitlement Types</title>
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
    <h2>Entitlement Types</h2>
    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-list-check"></i> Add Type</button>
    <button class="btn btn-outline-success mb-3" onclick="window.location='?export=csv'">Export CSV</button>
    <button class="btn btn-outline-danger mb-3" onclick="window.location='?export=pdf'">Export PDF</button>
    <table id="typesTable" class="table table-hover table-bordered w-100">
        <thead><tr><th>#</th><th>Name</th><th>Description</th><th>Requirements</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($types as $t): ?>
            <tr>
                <td><?php echo $t['id']; ?></td>
                <td><?php echo htmlspecialchars($t['name']); ?></td>
                <td><?php echo htmlspecialchars($t['description']); ?></td>
                <td><?php echo htmlspecialchars($t['requirements']); ?></td>
                <td>
                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $t['id']; ?>"><i class="bi bi-pencil"></i></button>
                    <a href="edit_history.php?table=entitlement_types&id=<?php echo $t['id']; ?>" class="btn btn-sm btn-info"><i class="bi bi-clock-history"></i></a>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="?delete=<?php echo $t['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this type?')"><i class="bi bi-trash"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <!-- Edit Modal -->
            <div class="modal fade" id="editModal<?php echo $t['id']; ?>" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog"><div class="modal-content">
                <form method="post">
                  <div class="modal-header"><h5 class="modal-title">Edit Type</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                  <div class="modal-body">
                    <input type="hidden" name="edit_id" value="<?php echo $t['id']; ?>">
                    <div class="mb-2"><label>Name</label><input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($t['name']); ?>" required></div>
                    <div class="mb-2"><label>Description</label><input type="text" name="description" class="form-control" value="<?php echo htmlspecialchars($t['description']); ?>"></div>
                    <div class="mb-2"><label>Requirements</label><input type="text" name="requirements" class="form-control" value="<?php echo htmlspecialchars($t['requirements']); ?>"></div>
                  </div>
                  <div class="modal-footer"><button type="submit" name="edit_type" class="btn btn-primary">Save</button></div>
                </form>
              </div></div>
            </div>
        <?php endforeach; ?>
        </tbody>
    </table>
    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog"><div class="modal-content">
        <form method="post">
          <div class="modal-header"><h5 class="modal-title">Add Entitlement Type</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <div class="mb-2"><label>Name</label><input type="text" name="name" class="form-control" required></div>
            <div class="mb-2"><label>Description</label><input type="text" name="description" class="form-control"></div>
            <div class="mb-2"><label>Requirements</label><input type="text" name="requirements" class="form-control"></div>
          </div>
          <div class="modal-footer"><button type="submit" name="add_type" class="btn btn-primary">Add</button></div>
        </form>
      </div></div>
    </div>
    <div class="toast-container position-fixed top-0 end-0 p-3">
      <?php if ($success): ?><div class="toast align-items-center text-bg-success border-0 show" role="alert"><div class="d-flex"><div class="toast-body"><?php echo $success; ?></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div><?php endif; ?>
      <?php if ($error): ?><div class="toast align-items-center text-bg-danger border-0 show" role="alert"><div class="d-flex"><div class="toast-body"><?php echo $error; ?></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div><?php endif; ?>
    </div>
</div>
<script>
$(function(){ $('#typesTable').DataTable({responsive:true}); });
</script>
</body>
</html> 
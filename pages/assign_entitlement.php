<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once '../db/db.php';
$error = '';
$success = '';
// Handle assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign'])) {
    $emp_id = (int)($_POST['employee_id'] ?? 0);
    $type_id = (int)($_POST['entitlement_type_id'] ?? 0);
    $req_num = trim($_POST['request_number'] ?? '');
    $issue_date = $_POST['issue_date'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    if ($emp_id && $type_id && $req_num && $issue_date) {
        $stmt = $pdo->prepare('INSERT INTO entitlements (employee_id, entitlement_type_id, request_number, issue_date, notes) VALUES (?, ?, ?, ?, ?)');
        try {
            $stmt->execute([$emp_id, $type_id, $req_num, $issue_date, $notes]);
            $success = 'Entitlement assigned.';
            log_audit($pdo, $_SESSION['user_id'], 'Assign Entitlement', "EmpID: $emp_id, TypeID: $type_id, Req#: $req_num");
        } catch (PDOException $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    } else {
        $error = 'All fields except notes are required.';
    }
}
// Handle edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_entitlement'])) {
    $id = (int)$_POST['edit_id'];
    $emp_id = (int)($_POST['employee_id'] ?? 0);
    $type_id = (int)($_POST['entitlement_type_id'] ?? 0);
    $req_num = trim($_POST['request_number'] ?? '');
    $issue_date = $_POST['issue_date'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    $old = $pdo->query("SELECT * FROM entitlements WHERE id=$id")->fetch();
    $stmt = $pdo->prepare('UPDATE entitlements SET employee_id=?, entitlement_type_id=?, request_number=?, issue_date=?, notes=? WHERE id=?');
    $stmt->execute([$emp_id, $type_id, $req_num, $issue_date, $notes, $id]);
    log_edit_history($pdo, 'entitlements', $id, $_SESSION['user_id'], 'edit', $old, ['employee_id'=>$emp_id,'entitlement_type_id'=>$type_id,'request_number'=>$req_num,'issue_date'=>$issue_date,'notes'=>$notes]);
    $success = 'Entitlement updated.';
}
// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $old = $pdo->query("SELECT * FROM entitlements WHERE id=$id")->fetch();
    $stmt = $pdo->prepare('DELETE FROM entitlements WHERE id=?');
    $stmt->execute([$id]);
    log_edit_history($pdo, 'entitlements', $id, $_SESSION['user_id'], 'delete', $old, null);
    $success = 'Entitlement deleted.';
}
// Search/filter
$search = trim($_GET['search'] ?? '');
$where = $search ? "WHERE emp.name LIKE ? OR t.name LIKE ?" : '';
$params = $search ? ["%$search%", "%$search%"] : [];
$assignments = $pdo->prepare("SELECT e.id, emp.name, t.name AS type, e.request_number, e.issue_date, e.notes FROM entitlements e JOIN employees emp ON e.employee_id=emp.id JOIN entitlement_types t ON e.entitlement_type_id=t.id $where ORDER BY e.id DESC");
$assignments->execute($params);
$assignments = $assignments->fetchAll();
// Advanced filtering
$filter_emp = trim($_GET['filter_emp'] ?? '');
$filter_type = trim($_GET['filter_type'] ?? '');
$filter_date = trim($_GET['filter_date'] ?? '');
$where = [];
$params = [];
if ($filter_emp) { $where[] = 'e.name LIKE ?'; $params[] = "%$filter_emp%"; }
if ($filter_type) { $where[] = 't.name LIKE ?'; $params[] = "%$filter_type%"; }
if ($filter_date) { $where[] = 'en.issue_date = ?'; $params[] = $filter_date; }
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$sql = "SELECT en.*, e.name as emp_name, t.name as type_name FROM entitlements en JOIN employees e ON en.employee_id=e.id JOIN entitlement_types t ON en.entitlement_type_id=t.id $where_sql ORDER BY en.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$assignments = $stmt->fetchAll();
// Export to CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="entitlements.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['#', 'Employee', 'Type', 'Request #', 'Issue Date', 'Notes']);
    foreach ($assignments as $a) {
        fputcsv($out, [$a['id'], $a['name'], $a['type'], $a['request_number'], $a['issue_date'], $a['notes']]);
    }
    fclose($out);
    exit();
}
// PDF export (using TCPDF)
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    require_once '../assets/tcpdf/tcpdf.php';
    $pdf = new TCPDF();
    $pdf->AddPage();
    $html = '<h2>Entitlement Assignments</h2><table border="1" cellpadding="4"><tr><th>Employee</th><th>Type</th><th>Request #</th><th>Issue Date</th><th>Notes</th></tr>';
    foreach ($assignments as $a) {
        $html .= '<tr><td>'.htmlspecialchars($a['emp_name']).'</td><td>'.htmlspecialchars($a['type_name']).'</td><td>'.htmlspecialchars($a['request_number']).'</td><td>'.htmlspecialchars($a['issue_date']).'</td><td>'.htmlspecialchars($a['notes']).'</td></tr>';
    }
    $html .= '</table>';
    $pdf->writeHTML($html);
    $pdf->Output('entitlements.pdf', 'D');
    exit();
}
// Fetch employees and types
$employees = $pdo->query('SELECT * FROM employees ORDER BY name')->fetchAll();
$types = $pdo->query('SELECT * FROM entitlement_types ORDER BY name')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Entitlements</title>
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
    <h1 class="mb-4">Assign Entitlements</h1>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <div class="card mb-4">
        <div class="card-body">
            <h5>Assign New Entitlement</h5>
            <form method="post">
                <input type="hidden" name="assign" value="1">
                <div class="mb-2">
                    <label>Employee</label>
                    <select name="employee_id" class="form-control" required>
                        <option value="">Select...</option>
                        <?php foreach ($employees as $emp): ?>
                        <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label>Entitlement Type</label>
                    <select name="entitlement_type_id" class="form-control" required>
                        <option value="">Select...</option>
                        <?php foreach ($types as $type): ?>
                        <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label>Request Number</label>
                    <input type="text" name="request_number" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label>Issue Date</label>
                    <input type="date" name="issue_date" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label>Notes (optional)</label>
                    <input type="text" name="notes" class="form-control">
                </div>
                <button type="submit" class="btn btn-primary">Assign</button>
            </form>
        </div>
    </div>
    <div class="main-content">
        <h2>Entitlement Assignments</h2>
        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-award"></i> Assign Entitlement</button>
        <button class="btn btn-outline-success mb-3" onclick="window.location='?export=csv'">Export CSV</button>
        <button class="btn btn-outline-danger mb-3" onclick="window.location='?export=pdf'">Export PDF</button>
        <table id="assignTable" class="table table-hover table-bordered w-100">
            <thead><tr><th>#</th><th>Employee</th><th>Type</th><th>Request #</th><th>Issue Date</th><th>Notes</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($assignments as $a): ?>
                <tr>
                    <td><?php echo $a['id']; ?></td>
                    <td><?php echo htmlspecialchars($a['emp_name']); ?></td>
                    <td><?php echo htmlspecialchars($a['type_name']); ?></td>
                    <td><?php echo htmlspecialchars($a['request_number']); ?></td>
                    <td><?php echo htmlspecialchars($a['issue_date']); ?></td>
                    <td><?php echo htmlspecialchars($a['notes']); ?></td>
                    <td>
                        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $a['id']; ?>"><i class="bi bi-pencil"></i></button>
                        <a href="edit_history.php?table=entitlements&id=<?php echo $a['id']; ?>" class="btn btn-sm btn-info"><i class="bi bi-clock-history"></i></a>
                        <a href="?delete=<?php echo $a['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this assignment?')"><i class="bi bi-trash"></i></a>
                    </td>
                </tr>
                <!-- Edit Modal -->
                <div class="modal fade" id="editModal<?php echo $a['id']; ?>" tabindex="-1" aria-hidden="true">
                  <div class="modal-dialog"><div class="modal-content">
                    <form method="post">
                      <div class="modal-header"><h5 class="modal-title">Edit Assignment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                      <div class="modal-body">
                        <input type="hidden" name="edit_id" value="<?php echo $a['id']; ?>">
                        <div class="mb-2"><label>Employee</label><select name="employee_id" class="form-control" required><?php foreach ($employees as $e) { echo '<option value="'.$e['id'].'"'.($a['employee_id']==$e['id']?' selected':'').'>'.htmlspecialchars($e['name']).'</option>'; } ?></select></div>
                        <div class="mb-2"><label>Type</label><select name="entitlement_type_id" class="form-control" required><?php foreach ($types as $t) { echo '<option value="'.$t['id'].'"'.($a['entitlement_type_id']==$t['id']?' selected':'').'>'.htmlspecialchars($t['name']).'</option>'; } ?></select></div>
                        <div class="mb-2"><label>Request #</label><input type="text" name="request_number" class="form-control" value="<?php echo htmlspecialchars($a['request_number']); ?>" required></div>
                        <div class="mb-2"><label>Issue Date</label><input type="date" name="issue_date" class="form-control" value="<?php echo htmlspecialchars($a['issue_date']); ?>" required></div>
                        <div class="mb-2"><label>Notes</label><input type="text" name="notes" class="form-control" value="<?php echo htmlspecialchars($a['notes']); ?>"></div>
                      </div>
                      <div class="modal-footer"><button type="submit" name="edit_entitlement" class="btn btn-primary">Save</button></div>
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
              <div class="modal-header"><h5 class="modal-title">Assign Entitlement</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
              <div class="modal-body">
                <div class="mb-2"><label>Employee</label><select name="employee_id" class="form-control" required><?php foreach ($employees as $e) { echo '<option value="'.$e['id'].'">'.htmlspecialchars($e['name']).'</option>'; } ?></select></div>
                <div class="mb-2"><label>Type</label><select name="entitlement_type_id" class="form-control" required><?php foreach ($types as $t) { echo '<option value="'.$t['id'].'">'.htmlspecialchars($t['name']).'</option>'; } ?></select></div>
                <div class="mb-2"><label>Request #</label><input type="text" name="request_number" class="form-control" required></div>
                <div class="mb-2"><label>Issue Date</label><input type="date" name="issue_date" class="form-control" required></div>
                <div class="mb-2"><label>Notes</label><input type="text" name="notes" class="form-control"></div>
              </div>
              <div class="modal-footer"><button type="submit" name="assign" class="btn btn-primary">Assign</button></div>
            </form>
          </div></div>
        </div>
        <div class="toast-container position-fixed top-0 end-0 p-3">
          <?php if ($success): ?><div class="toast align-items-center text-bg-success border-0 show" role="alert"><div class="d-flex"><div class="toast-body"><?php echo $success; ?></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div><?php endif; ?>
          <?php if ($error): ?><div class="toast align-items-center text-bg-danger border-0 show" role="alert"><div class="d-flex"><div class="toast-body"><?php echo $error; ?></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div><?php endif; ?>
        </div>
    </div>
</div>
<script>
$(function(){ $('#assignTable').DataTable({responsive:true}); });
</script>
</body>
</html> 
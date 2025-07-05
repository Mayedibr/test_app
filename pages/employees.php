<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once '../db/db.php';
$error = '';
$success = '';
// Handle manual add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_manual'])) {
    $emp_num = trim($_POST['employee_number'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $dept = trim($_POST['department'] ?? '');
    $email = trim($_POST['email'] ?? '');
    if ($emp_num && $name) {
        $stmt = $pdo->prepare('INSERT INTO employees (employee_number, name, department, email) VALUES (?, ?, ?, ?)');
        try {
            $stmt->execute([$emp_num, $name, $dept, $email]);
            $success = 'Employee added.';
            log_audit($pdo, $_SESSION['user_id'], 'Add Employee', "Added $emp_num - $name");
        } catch (PDOException $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    } else {
        $error = 'Employee number and name are required.';
    }
}
// Handle Excel upload (CSV for simplicity)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_csv'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $header = fgetcsv($file);
        while ($row = fgetcsv($file)) {
            $emp_num = $row[0] ?? '';
            $name = $row[1] ?? '';
            $dept = $row[2] ?? '';
            $email = $row[3] ?? '';
            if ($emp_num && $name) {
                $stmt = $pdo->prepare('INSERT IGNORE INTO employees (employee_number, name, department, email) VALUES (?, ?, ?, ?)');
                $stmt->execute([$emp_num, $name, $dept, $email]);
            }
        }
        fclose($file);
        log_audit($pdo, $_SESSION['user_id'], 'Bulk Upload Employees', 'Uploaded CSV');
        $success = 'CSV upload complete.';
    } else {
        $error = 'Please upload a valid CSV file.';
    }
}
// Handle edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_employee'])) {
    $id = (int)$_POST['edit_id'];
    $emp_num = trim($_POST['employee_number'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $dept = trim($_POST['department'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $old = $pdo->query("SELECT * FROM employees WHERE id=$id")->fetch();
    $stmt = $pdo->prepare('UPDATE employees SET employee_number=?, name=?, department=?, email=? WHERE id=?');
    $stmt->execute([$emp_num, $name, $dept, $email, $id]);
    log_edit_history($pdo, 'employees', $id, $_SESSION['user_id'], 'edit', $old, ['employee_number'=>$emp_num,'name'=>$name,'department'=>$dept,'email'=>$email]);
    $success = 'Employee updated.';
}
// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $old = $pdo->query("SELECT * FROM employees WHERE id=$id")->fetch();
    $pdo->prepare('DELETE FROM employees WHERE id=?')->execute([$id]);
    log_edit_history($pdo, 'employees', $id, $_SESSION['user_id'], 'delete', $old, null);
    $success = 'Employee deleted.';
}
// Advanced filtering
$search = trim($_GET['search'] ?? '');
$dept_filter = trim($_GET['department'] ?? '');
$email_filter = trim($_GET['email'] ?? '');
$where = [];
$params = [];
if ($search) { $where[] = '(employee_number LIKE ? OR name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($dept_filter) { $where[] = 'department LIKE ?'; $params[] = "%$dept_filter%"; }
if ($email_filter) { $where[] = 'email LIKE ?'; $params[] = "%$email_filter%"; }
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$employees = $pdo->prepare("SELECT * FROM employees $where_sql ORDER BY id DESC");
$employees->execute($params);
$employees = $employees->fetchAll();
// PDF export
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    require_once '../assets/tcpdf/tcpdf.php';
    $pdf = new TCPDF();
    $pdf->AddPage();
    $html = '<h2>Employee List</h2><table border="1" cellpadding="4"><tr><th>#</th><th>Number</th><th>Name</th><th>Department</th><th>Email</th></tr>';
    foreach ($employees as $emp) {
        $html .= '<tr><td>'.$emp['id'].'</td><td>'.$emp['employee_number'].'</td><td>'.$emp['name'].'</td><td>'.$emp['department'].'</td><td>'.$emp['email'].'</td></tr>';
    }
    $html .= '</table>';
    $pdf->writeHTML($html);
    $pdf->Output('employees.pdf', 'D');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Employees</title>
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
    <h2>Employees</h2>
    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-person-plus"></i> Add Employee</button>
    <button class="btn btn-outline-success mb-3" onclick="window.location='?export=csv'">Export CSV</button>
    <button class="btn btn-outline-danger mb-3" onclick="window.location='?export=pdf'">Export PDF</button>
    <table id="empTable" class="table table-hover table-bordered w-100">
        <thead><tr><th>#</th><th>Number</th><th>Name</th><th>Department</th><th>Email</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($employees as $e): ?>
            <tr>
                <td><?php echo $e['id']; ?></td>
                <td><?php echo htmlspecialchars($e['employee_number']); ?></td>
                <td><?php echo htmlspecialchars($e['name']); ?></td>
                <td><?php echo htmlspecialchars($e['department']); ?></td>
                <td><?php echo htmlspecialchars($e['email']); ?></td>
                <td>
                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $e['id']; ?>"><i class="bi bi-pencil"></i></button>
                    <a href="edit_history.php?table=employees&id=<?php echo $e['id']; ?>" class="btn btn-sm btn-info"><i class="bi bi-clock-history"></i></a>
                    <a href="?delete=<?php echo $e['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this employee?')"><i class="bi bi-trash"></i></a>
                </td>
            </tr>
            <!-- Edit Modal -->
            <div class="modal fade" id="editModal<?php echo $e['id']; ?>" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog"><div class="modal-content">
                <form method="post">
                  <div class="modal-header"><h5 class="modal-title">Edit Employee</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                  <div class="modal-body">
                    <input type="hidden" name="edit_id" value="<?php echo $e['id']; ?>">
                    <div class="mb-2"><label>Number</label><input type="text" name="employee_number" class="form-control" value="<?php echo htmlspecialchars($e['employee_number']); ?>" required></div>
                    <div class="mb-2"><label>Name</label><input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($e['name']); ?>" required></div>
                    <div class="mb-2"><label>Department</label><input type="text" name="department" class="form-control" value="<?php echo htmlspecialchars($e['department']); ?>"></div>
                    <div class="mb-2"><label>Email</label><input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($e['email']); ?>"></div>
                  </div>
                  <div class="modal-footer"><button type="submit" name="edit_employee" class="btn btn-primary">Save</button></div>
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
          <div class="modal-header"><h5 class="modal-title">Add Employee</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <div class="mb-2"><label>Number</label><input type="text" name="employee_number" class="form-control" required></div>
            <div class="mb-2"><label>Name</label><input type="text" name="name" class="form-control" required></div>
            <div class="mb-2"><label>Department</label><input type="text" name="department" class="form-control"></div>
            <div class="mb-2"><label>Email</label><input type="email" name="email" class="form-control"></div>
          </div>
          <div class="modal-footer"><button type="submit" name="add_manual" class="btn btn-primary">Add</button></div>
        </form>
      </div></div>
    </div>
    <div class="toast-container position-fixed top-0 end-0 p-3">
      <?php if ($success): ?><div class="toast align-items-center text-bg-success border-0 show" role="alert"><div class="d-flex"><div class="toast-body"><?php echo $success; ?></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div><?php endif; ?>
      <?php if ($error): ?><div class="toast align-items-center text-bg-danger border-0 show" role="alert"><div class="d-flex"><div class="toast-body"><?php echo $error; ?></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div><?php endif; ?>
    </div>
</div>
<script>
$(function(){ $('#empTable').DataTable({responsive:true}); });
</script>
</body>
</html> 
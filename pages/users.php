<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}
require_once '../db/db.php';
$error = '';
$success = '';
// Add user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $role = $_POST['role'] ?? 'user';
    if ($username && $password) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)');
        try {
            $stmt->execute([$username, $hash, $full_name, $role]);
            $success = 'User added.';
            log_audit($pdo, $_SESSION['user_id'], 'Add User', $username);
        } catch (PDOException $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    } else {
        $error = 'Username and password are required.';
    }
}
// Handle edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $id = (int)$_POST['edit_id'];
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $role = $_POST['role'] ?? 'user';
    $old = $pdo->query("SELECT * FROM users WHERE id=$id")->fetch();
    $stmt = $pdo->prepare('UPDATE users SET username=?, full_name=?, role=? WHERE id=?');
    $stmt->execute([$username, $full_name, $role, $id]);
    log_edit_history($pdo, 'users', $id, $_SESSION['user_id'], 'edit', $old, ['username'=>$username,'full_name'=>$full_name,'role'=>$role]);
    $success = 'User updated.';
}
// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id == $_SESSION['user_id']) {
        $error = 'You cannot delete yourself.';
    } else {
        $old = $pdo->query("SELECT * FROM users WHERE id=$id")->fetch();
        $stmt = $pdo->prepare('DELETE FROM users WHERE id=?');
        $stmt->execute([$id]);
        log_edit_history($pdo, 'users', $id, $_SESSION['user_id'], 'delete', $old, null);
        $success = 'User deleted.';
    }
}
// Search/filter
$search = trim($_GET['search'] ?? '');
$where = $search ? "WHERE username LIKE ? OR full_name LIKE ?" : '';
$params = $search ? ["%$search%", "%$search%"] : [];
$users = $pdo->prepare("SELECT * FROM users $where ORDER BY id DESC");
$users->execute($params);
$users = $users->fetchAll();
// Advanced filtering
$filter_username = trim($_GET['filter_username'] ?? '');
$filter_fullname = trim($_GET['filter_fullname'] ?? '');
$filter_role = trim($_GET['filter_role'] ?? '');
$where = [];
$params = [];
if ($filter_username) { $where[] = 'username LIKE ?'; $params[] = "%$filter_username%"; }
if ($filter_fullname) { $where[] = 'full_name LIKE ?'; $params[] = "%$filter_fullname%"; }
if ($filter_role) { $where[] = 'role = ?'; $params[] = $filter_role; }
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$sql = "SELECT * FROM users $where_sql ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
// Export to CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="users.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['#', 'Username', 'Full Name', 'Role']);
    foreach ($users as $user) {
        fputcsv($out, [$user['id'], $user['username'], $user['full_name'], $user['role']]);
    }
    fclose($out);
    exit();
}
// PDF export (using TCPDF)
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    require_once '../assets/tcpdf/tcpdf.php';
    $pdf = new TCPDF();
    $pdf->AddPage();
    $html = '<h2>Users</h2><table border="1" cellpadding="4"><tr><th>Username</th><th>Full Name</th><th>Role</th></tr>';
    foreach ($users as $u) {
        $html .= '<tr><td>'.htmlspecialchars($u['username']).'</td><td>'.htmlspecialchars($u['full_name']).'</td><td>'.htmlspecialchars($u['role']).'</td></tr>';
    }
    $html .= '</table>';
    $pdf->writeHTML($html);
    $pdf->Output('users.pdf', 'D');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
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
    <h2>User Management</h2>
    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-person-plus"></i> Add User</button>
    <button class="btn btn-outline-success mb-3" onclick="window.location='?export=csv'">Export CSV</button>
    <button class="btn btn-outline-danger mb-3" onclick="window.location='?export=pdf'">Export PDF</button>
    <table id="usersTable" class="table table-hover table-bordered w-100">
        <thead><tr><th>#</th><th>Username</th><th>Full Name</th><th>Role</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?php echo $u['id']; ?></td>
                <td><?php echo htmlspecialchars($u['username']); ?></td>
                <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                <td><?php echo htmlspecialchars($u['role']); ?></td>
                <td>
                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $u['id']; ?>"><i class="bi bi-pencil"></i></button>
                    <a href="edit_history.php?table=users&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-info"><i class="bi bi-clock-history"></i></a>
                    <a href="?delete=<?php echo $u['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this user?')"><i class="bi bi-trash"></i></a>
                </td>
            </tr>
            <!-- Edit Modal -->
            <div class="modal fade" id="editModal<?php echo $u['id']; ?>" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog"><div class="modal-content">
                <form method="post">
                  <div class="modal-header"><h5 class="modal-title">Edit User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                  <div class="modal-body">
                    <input type="hidden" name="edit_id" value="<?php echo $u['id']; ?>">
                    <div class="mb-2"><label>Username</label><input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($u['username']); ?>" required></div>
                    <div class="mb-2"><label>Full Name</label><input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($u['full_name']); ?>"></div>
                    <div class="mb-2"><label>Role</label><select name="role" class="form-control"><option value="user"<?php if($u['role']=='user') echo ' selected';?>>User</option><option value="admin"<?php if($u['role']=='admin') echo ' selected';?>>Admin</option></select></div>
                  </div>
                  <div class="modal-footer"><button type="submit" name="edit_user" class="btn btn-primary">Save</button></div>
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
          <div class="modal-header"><h5 class="modal-title">Add User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <div class="mb-2"><label>Username</label><input type="text" name="username" class="form-control" required></div>
            <div class="mb-2"><label>Password</label><input type="password" name="password" class="form-control" required></div>
            <div class="mb-2"><label>Full Name</label><input type="text" name="full_name" class="form-control"></div>
            <div class="mb-2"><label>Role</label><select name="role" class="form-control"><option value="user">User</option><option value="admin">Admin</option></select></div>
          </div>
          <div class="modal-footer"><button type="submit" name="add_user" class="btn btn-primary">Add</button></div>
        </form>
      </div></div>
    </div>
    <div class="toast-container position-fixed top-0 end-0 p-3">
      <?php if ($success): ?><div class="toast align-items-center text-bg-success border-0 show" role="alert"><div class="d-flex"><div class="toast-body"><?php echo $success; ?></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div><?php endif; ?>
      <?php if ($error): ?><div class="toast align-items-center text-bg-danger border-0 show" role="alert"><div class="d-flex"><div class="toast-body"><?php echo $error; ?></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div><?php endif; ?>
    </div>
</div>
<script>
$(function(){ $('#usersTable').DataTable({responsive:true}); });
</script>
</body>
</html> 
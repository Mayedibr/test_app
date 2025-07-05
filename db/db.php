<?php
$host = 'localhost';
$db   = 'employee_entitlements';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = 'mysql:host=db;port=3306;dbname=employee_entitlements;charset=utf8';
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
     throw new PDOException($e->getMessage(), (int)$e->getCode());
}

function log_audit($pdo, $user_id, $action, $details = null) {
    $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)');
    $stmt->execute([$user_id, $action, $details]);
}

function log_edit_history($pdo, $table, $record_id, $user_id, $action, $old_data, $new_data) {
    $stmt = $pdo->prepare('INSERT INTO edit_history (table_name, record_id, user_id, action, old_data, new_data) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$table, $record_id, $user_id, $action, json_encode($old_data), json_encode($new_data)]);
} 

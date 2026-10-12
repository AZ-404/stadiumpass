<?php
require __DIR__ . '/config/db.php';

$users = [
    ['Admin User', 'admin@stadium.com', 'Admin@123', 'admin'],
    ['Fan User',   'fan@stadium.com',   'Fan@123',   'fan'],
    ['Staff User', 'staff@stadium.com', 'Staff@123', 'staff'],
];

$stmt = $pdo->prepare(
    "INSERT IGNORE INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)"
);
foreach ($users as [$name, $email, $password, $role]) {
    $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
}
echo 'Users created. Delete this file now.';
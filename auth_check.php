<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('home_for_role')) {
    function home_for_role(string $role): string {
        $inStaff = (basename(dirname($_SERVER['SCRIPT_NAME'] ?? '')) === 'staff');
        return match ($role) {
            'admin' => $inStaff ? '../matches.php' : 'matches.php',
            'staff' => $inStaff ? 'verify.php' : 'staff/verify.php',
            default => $inStaff ? '../booking.php?action=matches' : 'booking.php?action=matches',
        };
    }
}

if (!function_exists('require_role')) {
    function require_role(string ...$roles): void {
        $inStaff = (basename(dirname($_SERVER['SCRIPT_NAME'] ?? '')) === 'staff');
        $loginUrl = $inStaff ? '../login.php' : 'login.php';

        if (empty($_SESSION['user_id'])) {
            header("Location: $loginUrl");
            exit;
        }

        if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
            http_response_code(403);
            die('Access denied. You do not have permission to view this page.');
        }
    }
}

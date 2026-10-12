<?php
require_once __DIR__ . '/auth_check.php';

if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    header('Location: ' . home_for_role($_SESSION['role']));
    exit;
}

header('Location: booking.php?action=matches');
exit;

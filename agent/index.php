<?php
session_start();
require '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$error = null;
$success = null;

require '../includes/header.php';
?>


<?php require '../includes/footer.php'; ?>

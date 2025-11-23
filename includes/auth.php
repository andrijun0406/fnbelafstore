
<?php
session_start();

function checkRole($role) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
        header("Location: login.php");
        exit();
    }
}
?>

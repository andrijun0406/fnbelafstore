<?php
$host = "localhost";
$user = "u952857351_fnb_user"; // Ganti dengan username database
$pass = "F&BAdmin123"; // Ganti dengan password database
$db   = "u952857351_fnb_store";

$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>
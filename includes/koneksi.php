<?php
$host = "localhost";
$user = "u952857351_fnb_user";
$pass = "F&BAdmin123"; // karakter & aman dalam string PHP
$db   = "u952857351_fnb_store";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // tampilkan error di dev
$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Pastikan UTF-8 penuh
$conn->set_charset('utf8mb4');
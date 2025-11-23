
<?php
// Pastikan file ini hanya digunakan sekali, lalu hapus setelah selesai!
include 'includes/koneksi.php';

// Password baru
$newPassword = 'admin123'; // Ganti sesuai keinginan

// Hash password dengan bcrypt
$hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

// Update password admin
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
$stmt->bind_param("s", $hashedPassword);

if ($stmt->execute()) {
    echo "<h3>Password admin berhasil direset menjadi: <b>$newPassword</b></h3>";
    echo "<p>Segera hapus file ini untuk keamanan!</p>";
} else {
    echo "<h3>Gagal reset password.</h3>";
}
?>

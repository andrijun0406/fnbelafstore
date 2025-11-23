
<?php
include '../includes/auth.php';
checkRole('admin');
include '../includes/koneksi.php';

// Ambil semua user
$result = $conn->query("SELECT id, username, role, created_at FROM users");
?>
<!DOCTYPE html>
<html>
<head><title>Manage Users</title></head>
<body>
<h2>Manage Users</h2>
<table border="1" cellpadding="8">
<tr><th>ID</th><th>Username</th><th>Role</th><th>Created At</th><th>Aksi</th></tr>
<?php while($row = $result->fetch_assoc()) { ?>
<tr>
    <td><?= $row['id'] ?></td>
    <td><?= htmlspecialchars($row['username']) ?></td>
    <td><?= $row['role'] ?></td>
    <td><?= $row['created_at'] ?></td>
    <td>
        edit_user.php?id=<?= $row[">Edit</a> |
        delete_user.php?id=<?= $row[" onclick="return confirm('Hapus user ini?')">Delete</a>
    </td>
</tr>
<?php } ?>
</table>
<p>add_user.phpTambah User</a></p>
</body>
</html>

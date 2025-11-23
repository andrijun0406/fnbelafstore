
<?php
include '../includes/auth.php';
checkRole('admin');
include '../includes/koneksi.php';

// Ambil semua supplier
$query = "
SELECT s.id, s.nama, s.handphone, u.username AS user_account
FROM supplier s
LEFT JOIN users u ON s.user_id = u.id
";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html>
<head><title>Manage Suppliers</title></head>
<body>
<h2>Manage Suppliers</h2>
<table border="1" cellpadding="8">
<tr><th>ID</th><th>Nama</th><th>Handphone</th><th>User Account</th><th>Aksi</th></tr>
<?php while($row = $result->fetch_assoc()) { ?>
<tr>
    <td><?= $row['id'] ?></td>
    <td><?= htmlspecialchars($row['nama']) ?></td>
    <td><?= htmlspecialchars($row['handphone']) ?></td>
    <td><?= $row['user_account'] ?: 'Belum terkait' ?></td>
    <td>
        edit_supplier.php?id=<?= $row[">Edit</a> |
        delete_supplier.php?id=<?= $row[" onclick="return confirm('Hapus supplier ini?')">Delete</a>
    </td>
</tr>
<?php } ?>
</table>
<p>add_supplier.phpTambah Supplier</a></p>
</body>
</html>

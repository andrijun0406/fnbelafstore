
<?php
include 'includes/koneksi.php';

// Ambil produk dengan stok tersedia
$query = "
SELECT p.nama, p.jenis, p.harga_jual, sa.jumlah_sisa
FROM produk p
JOIN stok s ON p.id = s.produk_id
JOIN stok_akhir sa ON s.id = sa.stok_id
WHERE sa.jumlah_sisa > 0
";

$result = $conn->query($query);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Produk Tersedia</title>
    <style>
        table { border-collapse: collapse; width: 80%; margin: 20px auto; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: center; }
        th { background-color: #f4f4f4; }
    </style>
</head>
<body>
<h1 style="text-align:center;">Produk Tersedia</h1>
<table>
    <tr>
        <th>Nama Produk</th>
        <th>Jenis</th>
        <th>Harga Jual</th>
        <th>Stok Tersisa</th>
    </tr>
    <?php while ($row = $result->fetch_assoc()) { ?>
    <tr>
        <td><?= htmlspecialchars($row['nama']) ?></td>
        <td><?= htmlspecialchars($row['jenis']) ?></td>
        <td>Rp <?= number_format($row['harga_jual'], 0, ',', '.') ?></td>
        <td><?= $row['jumlah_sisa'] ?></td>
    </tr>
    <?php } ?>
</table>
<p style="text-align:center;">login.phpLogin Admin/Supplier</a></p>
</body>
</html>

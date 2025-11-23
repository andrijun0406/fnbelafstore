
<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard Utama</title>
    https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container mt-4">
    <h1>Welcome, <?php echo $_SESSION['username']; ?></h1>
   SESSION['role']; ?></p>

    <?php if ($_SESSION['role'] === 'admin'): ?>
        manage_products.phpKelola Produk</a>
        manage_suppliers.phpKelola Supplier</a>
    <?php elseif ($_SESSION['role'] === 'supplier'): ?>
        supplier_dashboard.phpInput Produk</a>
        update_profile.phpUpdate Profil</a>
    <?php endif; ?>

    <br><br>
    logout.phpLogout</a>
</div>
</body>
</html>

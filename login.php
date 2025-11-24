<?php
declare(strict_types=1);

$secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
session_set_cookie_params([
  'lifetime' => 0,
  'path' => '/',
  'domain' => '',
  'secure' => $secure,
  'httponly' => true,
  'samesite' => 'Lax'
]);

session_start();
require_once __DIR__ . '/includes/koneksi.php';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = null;
$username_input = '';
$logged_out = isset($_GET['logged_out']) && $_GET['logged_out'] === '1';

// Proses login (singkat)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $csrf = $_POST['csrf_token'] ?? '';
    $username_input = $username;

    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        $error = "Sesi tidak valid, silakan muat ulang halaman.";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = (int)$user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['role']      = $user['role'];

            // Redirect berdasarkan role
            if ($user['role'] === 'admin') {
                header("Location: /admin/dashboard.php");
            } else {
                header("Location: /supplier/dashboard.php");
            }
            exit();
        } else {
            $error = "Username atau password salah!";
        }
    }
}
?>
<!doctype html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Login - F &amp; B KAF Bekasi</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/brand.css">
    <link rel="icon" type="image/png" href="/assets/images/logo/logo_image_only.png">
    <style>
      body { background-color: #f8f9fa; }
      .login-card { max-width: 420px; width: 100%; }
      .brand { font-weight: 600; }
    </style>
  </head>
  <body>
    <!-- Header kecil publik: tombol Home (Etalase) -->
    <header class="py-3 border-bottom bg-white">
      <div class="container d-flex justify-content-between align-items-center">
         <div class="d-flex align-items-center">
              <img src="/assets/images/logo/logo_fnb_kaf_full.png"
                  alt="F &amp; B KAF Bekasi logo" class="brand-logo-lg brand-on-light">
              <span class="brand-text">Food &amp; Beverage Kuttab Al Fatih Bekasi</span>
            </div>
        <a href="/index.php" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-house-door me-1"></i> Home Etalase
        </a>
      </div>
    </header>

    <div class="container min-vh-100 d-flex align-items-center justify-content-center">
      <div class="card shadow-sm login-card mt-4">
        <div class="card-body p-4">
          <div class="text-center mb-4">
            <div class="brand h5 mb-1">Masuk ke Akun</div>
            <div class="text-muted">Admin dan Supplier</div>
          </div>

          <?php if ($logged_out): ?>
            <div class="alert alert-success" role="alert">
              Anda telah logout. Klik “Home Etalase” di atas untuk kembali melihat etalase publik.
            </div>
          <?php endif; ?>

          <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
              <?= htmlspecialchars($error) ?>
            </div>
          <?php endif; ?>

          <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div class="form-floating mb-3">
              <input type="text" class="form-control" id="username" name="username" placeholder="Username" value="<?= htmlspecialchars($username_input) ?>" required>
              <label for="username">Username</label>
            </div>

            <div class="form-floating mb-3">
              <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
              <label for="password">Password</label>
            </div>

            <button type="submit" class="btn btn-primary w-100">
              <i class="bi bi-box-arrow-in-right me-1"></i> Login
            </button>
          </form>

          <div class="text-center mt-3">
            <small class="text-muted">
              Perlu bantuan akses? Hubungi admin.
            </small>
          </div>
        </div>
      </div>
    </div>

    <footer class="border-top bg-white">
      <div class="container py-3">
        <small class="text-muted">&copy; <?= date('Y'); ?> F &amp; B KAF Bekasi</small>
      </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
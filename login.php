<?php
declare(strict_types=1);

// Konfigurasi cookie yang aman (jalankan sebelum session_start)
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

// Siapkan CSRF token
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = null;
$username_input = ''; // untuk mengisi kembali field username saat error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $csrf = $_POST['csrf_token'] ?? '';
    $username_input = $username;

    // Validasi CSRF
    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        $error = "Sesi tidak valid, silakan muat ulang halaman.";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            // Amankan sesi setelah login
            session_regenerate_id(true);
            $_SESSION['user_id']   = (int)$user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['role']      = $user['role'];

            // Redirect berdasarkan role
            if ($user['role'] === 'admin') {
                header("Location: admin/dashboard.php");
                exit();
            } elseif ($user['role'] === 'supplier') {
                header("Location: supplier/dashboard.php");
                exit();
            } else {
                $error = "Role pengguna tidak dikenali.";
            }
        } else {
            $error = "Username atau password salah!";
        }
        $stmt->close();
    }
}
?>
<!doctype html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Login - FnBelaf Store</title>

    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">

    <!-- Bootstrap Icons (opsional) -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
      body { background-color: #f8f9fa; }
      .login-card { max-width: 420px; width: 100%; }
      .brand { font-weight: 600; }
    </style>
  </head>
  <body>
    <div class="container min-vh-100 d-flex align-items-center justify-content-center">
      <div class="card shadow-sm login-card">
        <div class="card-body p-4">
          <div class="text-center mb-4">
            <div class="brand h5 mb-1">FnBelaf Store</div>
            <div class="text-muted">Masuk ke akun Anda</div>
          </div>

          <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
              <?= htmlspecialchars($error) ?>
            </div>
          <?php endif; ?>

          <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div class="form-floating mb-3">
              <input type="text"
                     class="form-control"
                     id="username"
                     name="username"
                     placeholder="Username"
                     value="<?= htmlspecialchars($username_input) ?>"
                     required>
              <label for="username">Username</label>
            </div>

            <div class="form-floating mb-3">
              <input type="password"
                     class="form-control"
                     id="password"
                     name="password"
                     placeholder="Password"
                     required>
              <label for="password">Password</label>
            </div>

            <div class="d-flex align-items-center justify-content-between mb-3">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" value="" id="remember" disabled>
                <label class="form-check-label text-muted" for="remember">
                  Ingat saya (opsional)
                </label>
              </div>
              <a href="#" class="text-decoration-none text-muted">Lupa password?</a>
            </div>

            <button type="submit" class="btn btn-primary w-100">
              <i class="bi bi-box-arrow-in-right me-1"></i> Login
            </button>
          </form>

          <div class="text-center mt-3">
            <small class="text-muted">
              Belum punya akun? Hubungi admin untuk pembuatan akun.
            </small>
          </div>
        </div>
      </div>
    </div>

    <!-- Bootstrap 5 JS Bundle (termasuk Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
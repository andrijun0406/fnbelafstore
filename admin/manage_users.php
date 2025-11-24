<?php
declare(strict_types=1);

// Debug sementara (MATIKAN di production)
error_reporting(E_ALL);
ini_set('display_errors', '1');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Mulai sesi lebih awal
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

include_once __DIR__ . '/../includes/auth.php';
// Hanya admin
if (function_exists('requireRole')) {
  requireRole('admin');
} else {
  checkRole('admin');
}

require_once __DIR__ . '/../includes/koneksi.php';

// Muat partial navbar admin konsisten
include_once __DIR__ . '/../includes/navbar_admin.php';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function post($key, $default='') {
  return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $csrf = post('csrf_token');
  if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
    $flash = ['type' => 'danger', 'msg' => 'Sesi tidak valid, silakan muat ulang.'];
  } else {
    $action = post('action');
    if ($action === 'create_user') {
      $username = post('username');
      $password = post('password');
      $role     = post('role'); // 'admin' atau 'supplier'
      $supplier_id = post('supplier_id'); // opsional, hanya untuk role supplier
      $new_supplier_name = post('new_supplier_name');
      $new_supplier_hp   = post('new_supplier_hp');

      if ($username === '' || $password === '' || !in_array($role, ['admin','supplier'], true)) {
        $flash = ['type' => 'danger', 'msg' => 'Data tidak lengkap atau role tidak valid.'];
      } else {
        // Buat user
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $hash, $role);
        if ($stmt->execute()) {
          $new_user_id = $stmt->insert_id;
          $stmt->close();

          // Jika supplier: link ke supplier terpilih atau buat supplier baru
          if ($role === 'supplier') {
            if ($supplier_id !== '' && ctype_digit($supplier_id)) {
              // Link ke supplier yang belum punya user
              $stmt2 = $conn->prepare("UPDATE supplier SET user_id = ? WHERE id = ? AND user_id IS NULL");
              $stmt2->bind_param("ii", $new_user_id, $supplier_id);
              $stmt2->execute();
              $stmt2->close();
            } elseif ($new_supplier_name !== '' && $new_supplier_hp !== '') {
              // Buat supplier baru dan link ke user
              $stmt3 = $conn->prepare("INSERT INTO supplier (nama, handphone, user_id) VALUES (?, ?, ?)");
              $stmt3->bind_param("ssi", $new_supplier_name, $new_supplier_hp, $new_user_id);
              $stmt3->execute();
              $stmt3->close();
            }
          }

          $flash = ['type' => 'success', 'msg' => 'User berhasil dibuat.'];
        } else {
          $err = $stmt->error;
          $stmt->close();
          $flash = ['type' => 'danger', 'msg' => 'Gagal membuat user: ' . htmlspecialchars($err)];
        }
      }
    } elseif ($action === 'update_role') {
      $user_id = post('user_id');
      $role    = post('role');
      if (ctype_digit($user_id) && in_array($role, ['admin','supplier'], true)) {
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param("si", $role, $user_id);
        $stmt->execute();
        $stmt->close();
        $flash = ['type' => 'success', 'msg' => 'Role berhasil diperbarui.'];
      } else {
        $flash = ['type' => 'danger', 'msg' => 'Input tidak valid.'];
      }
    } elseif ($action === 'reset_password') {
      $user_id = post('user_id');
      $newpass = post('new_password');
      if (ctype_digit($user_id) && $newpass !== '') {
        $hash = password_hash($newpass, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hash, $user_id);
        $stmt->execute();
        $stmt->close();
        $flash = ['type' => 'success', 'msg' => 'Password berhasil di-reset.'];
      } else {
        $flash = ['type' => 'danger', 'msg' => 'Password baru tidak boleh kosong.'];
      }
    } elseif ($action === 'delete_user') {
      $user_id = post('user_id');
      if (ctype_digit($user_id)) {
        // Unlink supplier terlebih dahulu bila ada
        $stmt0 = $conn->prepare("UPDATE supplier SET user_id = NULL WHERE user_id = ?");
        $stmt0->bind_param("i", $user_id);
        $stmt0->execute();
        $stmt0->close();

        // Hapus user
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
          $flash = ['type' => 'success', 'msg' => 'User dihapus.'];
        } else {
          $flash = ['type' => 'danger', 'msg' => 'Gagal hapus user. Pastikan tidak ada relasi yang menghalangi.'];
        }
        $stmt->close();
      }
    } elseif ($action === 'link_supplier') {
      // Link manual user (supplier) ke supplier yang belum memiliki user
      $user_id = post('user_id');
      $supplier_id = post('supplier_id');
      if (ctype_digit($user_id) && ctype_digit($supplier_id)) {
        $stmt = $conn->prepare("UPDATE supplier SET user_id = ? WHERE id = ? AND user_id IS NULL");
        $stmt->bind_param("ii", $user_id, $supplier_id);
        $stmt->execute();
        $stmt->close();
        $flash = ['type' => 'success', 'msg' => 'Supplier berhasil di-link ke user.'];
      }
    } elseif ($action === 'unlink_supplier') {
      $supplier_id = post('supplier_id');
      if (ctype_digit($supplier_id)) {
        $stmt = $conn->prepare("UPDATE supplier SET user_id = NULL WHERE id = ?");
        $stmt->bind_param("i", $supplier_id);
        $stmt->execute();
        $stmt->close();
        $flash = ['type' => 'success', 'msg' => 'Link supplier ke user dihapus.'];
      }
    }
  }
}

// Ambil data untuk tampilan
$users = $conn->query("SELECT id, username, role, created_at FROM users ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$suppliers_unlinked = $conn->query("SELECT id, nama FROM supplier WHERE user_id IS NULL ORDER BY nama ASC")->fetch_all(MYSQLI_ASSOC);
$suppliers = $conn->query("
  SELECT s.id, s.nama, s.handphone, s.user_id, u.username
  FROM supplier s
  LEFT JOIN users u ON u.id = s.user_id
  ORDER BY s.nama ASC
")->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Users</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="/assets/css/brand.css">
  <link rel="icon" type="image/png" href="/assets/images/logo/logo_image_only.png">
</head>
<body class="bg-light">

<?php render_admin_navbar(); ?>

<main class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Manage Users</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
      <i class="bi bi-person-plus"></i> Tambah User
    </button>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm mb-4">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Username</th>
              <th>Role</th>
              <th>Dibuat</th>
              <th class="text-end">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
              <tr>
                <td><?= $u['id'] ?></td>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td>
                  <form method="post" class="d-flex gap-2">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="update_role">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <select name="role" class="form-select form-select-sm" style="max-width:160px">
                      <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>Admin</option>
                      <option value="supplier" <?= $u['role']==='supplier'?'selected':'' ?>>Supplier</option>
                    </select>
                    <button class="btn btn-outline-secondary btn-sm" type="submit">Simpan</button>
                  </form>
                </td>
                <td><small class="text-muted"><?= htmlspecialchars($u['created_at'] ?? '') ?></small></td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#resetPassModal<?= $u['id'] ?>">Reset PW</button>
                    <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteUserModal<?= $u['id'] ?>">Delete</button>
                  </div>
                </td>
              </tr>

              <!-- Modal reset password -->
              <div class="modal fade" id="resetPassModal<?= $u['id'] ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog"><div class="modal-content">
                  <form method="post">
                    <div class="modal-header">
                      <h5 class="modal-title">Reset Password - <?= htmlspecialchars($u['username']) ?></h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                      <input type="hidden" name="action" value="reset_password">
                      <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                      <div class="form-floating">
                        <input type="password" class="form-control" id="newpass<?= $u['id'] ?>" name="new_password" placeholder="Password baru" required>
                        <label for="newpass<?= $u['id'] ?>">Password baru</label>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                      <button class="btn btn-primary" type="submit">Simpan</button>
                    </div>
                  </form>
                </div></div>
              </div>

              <!-- Modal delete user -->
              <div class="modal fade" id="deleteUserModal<?= $u['id'] ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog"><div class="modal-content">
                  <form method="post">
                    <div class="modal-header">
                      <h5 class="modal-title">Hapus User - <?= htmlspecialchars($u['username']) ?></h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                      <p class="mb-0">Tindakan ini tidak dapat dibatalkan. Jika user tertaut ke supplier, tautan akan dihapus lebih dulu.</p>
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                      <input type="hidden" name="action" value="delete_user">
                      <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    </div>
                    <div class="modal-footer">
                      <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                      <button class="btn btn-danger" type="submit">Hapus</button>
                    </div>
                  </form>
                </div></div>
              </div>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Panel link supplier ke user (opsional cepat) -->
  <div class="card shadow-sm mb-4">
    <div class="card-header">Link Supplier ke User (role supplier)</div>
    <div class="card-body">
      <form method="post" class="row g-3">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="link_supplier">
        <div class="col-md-4">
          <label class="form-label">User ID</label>
          <input type="number" name="user_id" class="form-control" placeholder="ID user supplier" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Supplier (belum punya user)</label>
          <select name="supplier_id" class="form-select" required>
            <option value="">-- Pilih --</option>
            <?php foreach ($suppliers_unlinked as $s): ?>
              <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 d-grid">
          <button class="btn btn-primary" type="submit">Link</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal create user -->
  <div class="modal fade" id="createUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg"><div class="modal-content">
      <form method="post">
        <div class="modal-header">
          <h5 class="modal-title">Tambah User</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
          <input type="hidden" name="action" value="create_user">

          <div class="row g-3">
            <div class="col-md-4">
              <div class="form-floating">
                <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
                <label for="username">Username</label>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-floating">
                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                <label for="password">Password</label>
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Role</label>
              <select name="role" class="form-select" id="roleSelect" required>
                <option value="admin">Admin</option>
                <option value="supplier">Supplier</option>
              </select>
            </div>

            <div class="col-12">
              <div class="alert alert-info mb-2">
                Jika role = Supplier, Anda bisa pilih supplier yang sudah ada atau buat supplier baru sekaligus.
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Pilih Supplier (opsional)</label>
              <select name="supplier_id" class="form-select">
                <option value="">-- Tidak memilih --</option>
                <?php foreach ($suppliers_unlinked as $s): ?>
                  <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nama']) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Hanya supplier tanpa user.</div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Atau Buat Supplier Baru</label>
              <div class="row g-2">
                <div class="col-md-6">
                  <input type="text" name="new_supplier_name" class="form-control" placeholder="Nama supplier">
                </div>
                <div class="col-md-6">
                  <input type="text" name="new_supplier_hp" class="form-control" placeholder="Handphone">
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button class="btn btn-primary" type="submit">Simpan</button>
        </div>
      </form>
    </div></div>
  </div>
</main>

<footer class="border-top mt-4">
  <div class="container py-3">
    <small class="text-muted">&copy; <?= date('Y'); ?> F &amp; B ELAF Store</small>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
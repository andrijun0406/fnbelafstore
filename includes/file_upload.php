<?php
declare(strict_types=1);

/**
 * Helper Upload/Hapus Foto Produk
 * - Menyimpan foto produk ke /assets/images/products
 * - Menghapus foto fisik ketika diminta
 * - Validasi tipe MIME (jpeg/png/webp) dan ukuran (<= 5MB)
 */

// Lokasi direktori fisik dan prefix web
define('PRODUCT_IMAGE_DIR', __DIR__ . '/../assets/images/products');
define('PRODUCT_IMAGE_WEB', '/assets/images/products');

// Pastikan direktori ada
if (!is_dir(PRODUCT_IMAGE_DIR)) {
  @mkdir(PRODUCT_IMAGE_DIR, 0755, true);
}

/**
 * Simpan foto produk ke storage, kembalikan path web (untuk disimpan di DB).
 * @param array $file $_FILES['foto']
 * @param int $ownerId Supplier/Admin ID sebagai bagian penamaan file
 * @return string|null Web path (mis. /assets/images/products/xxxx.jpg) atau null jika tidak ada file
 * @throws RuntimeException bila validasi gagal atau penyimpanan gagal
 */
function save_product_image(array $file, int $ownerId): ?string {
  if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
    return null; // Tidak ada file atau error lain, biarkan caller menangani bila perlu
  }

  // Batasi ukuran: maksimal 5MB
  if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
    throw new RuntimeException('Ukuran foto melebihi 5MB.');
  }

  // Validasi MIME nyata
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime  = $finfo->file($file['tmp_name']);
  $allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
  ];
  if (!isset($allowed[$mime])) {
    throw new RuntimeException('Tipe gambar tidak didukung. Gunakan JPG/PNG/WebP.');
  }

  // Generate nama file unik
  $ext = $allowed[$mime];
  $basename = 'own' . $ownerId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
  $target = PRODUCT_IMAGE_DIR . DIRECTORY_SEPARATOR . $basename;

  // Pindahkan file
  if (!move_uploaded_file($file['tmp_name'], $target)) {
    throw new RuntimeException('Gagal menyimpan file foto.');
  }

  // Kembalikan path web untuk disimpan di DB
  return PRODUCT_IMAGE_WEB . '/' . $basename;
}

/**
 * Hapus foto fisik dari storage berdasarkan path web yang tersimpan di DB.
 * @param string|null $webPath Path web (mis. /assets/images/products/xxx.jpg)
 */
function delete_product_image(?string $webPath): void {
  if (!$webPath) return;
  // Konversi path web ke path fisik
  if (strpos($webPath, PRODUCT_IMAGE_WEB) !== 0) return;
  $rel = substr($webPath, strlen(PRODUCT_IMAGE_WEB));
  $full = PRODUCT_IMAGE_DIR . $rel;
  if (is_file($full)) {
    @unlink($full);
  }
}
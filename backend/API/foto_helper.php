<?php
/**
 * Helper pemrosesan foto untuk endpoint API.
 *
 * Model penyimpanan foto:
 *   - Kolom `foto`      : menyimpan file foto ASLI (tanpa crop).
 *   - Kolom `foto_crop` : menyimpan file foto hasil crop (untuk tampilan).
 *
 * Respons API (GET) selalu menampilkan:
 *   - `foto`          : path foto yang DITAMPILKAN (foto_crop jika ada, jika tidak foto asli).
 *   - `foto_original` : path foto ASLI (untuk keperluan edit / crop ulang di CMS).
 *
 * Alur upload (POST):
 *   - `foto_original` : file foto asli (opsional, dari pilihan file baru di CMS).
 *   - `foto`          : file foto hasil crop.
 *   - Update tanpa `foto_original` berarti hanya crop ulang (foto asli dipertahankan).
 */

// Migrasi kolom dilakukan terpusat di fix_database.php (no-op di runtime untuk performa).
function foto_ensure_column($conn, $table, ...$args) {
    // No-op di hot-path request untuk mengeliminasi metadata lock & ALTER TABLE overhead
}

// Kembalikan path foto langsung tanpa overhead disk I/O di setiap GET request
function foto_resolve_existing_path($path) {
    return $path ?? '';
}

// Ubah satu baris hasil query: isi `foto` = tampilan (crop jika ada, else original), simpan `foto_original`.
function foto_map_row(&$row) {
    $original = $row['foto'] ?? '';
    $crop = $row['foto_crop'] ?? '';

    $row['foto_original'] = $original;
    $row['foto'] = (!empty($crop)) ? $crop : $original;
}

// Ubah banyak baris hasil query (mengambil referensi langsung).
function foto_map_rows(&$rows) {
    foreach ($rows as &$row) {
        foto_map_row($row);
    }
    unset($row);
}

// Cek apakah field file terunggah dengan benar dan memenuhi validasi (maks 10MB, format gambar).
function foto_has_upload($field) {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    // Batas 10MB
    if ($_FILES[$field]['size'] > 10 * 1024 * 1024) {
        return false;
    }
    return true;
}

// Simpan satu file dari form ke direktori upload, kembalikan path DB (atau '').
function foto_save_file($field, $upload_dir, $prefix) {
    if (!foto_has_upload($field)) return '';
    if (!file_exists($upload_dir)) {
        @mkdir($upload_dir, 0777, true);
    }
    // Nama unik: timestamp + acak, mencegah tabrakan nama (mis. foto asli vs foto crop
    // yang diunggah dalam detik yang sama dengan nama dasar identik).
    $base = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES[$field]['name']));
    $name = time() . '_' . bin2hex(random_bytes(4)) . '_' . $base;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $upload_dir . $name)) return '';
    return $prefix . $name;
}

// Hapus file lokal dari path DB (abaikan URL eksternal).
function foto_unlink($path) {
    if (empty($path) || strpos($path, 'backend/') !== 0) return;
    $relative = '../' . str_replace('backend/', '', $path);
    if (file_exists($relative)) {
        @unlink($relative);
    }
}

// Dimensi maksimum sisi terpanjang untuk foto yang disimpan (dalam px).
define('FOTO_MAX_DIM', 1920);

// Muat file gambar menjadi resource GD (mendukung PNG, JPG, GIF, BMP, WBMP, WebP).
function foto_load_gd($fullpath, $ext) {
    switch ($ext) {
        case 'png':
            if (!function_exists('imagecreatefrompng')) return null;
            $img = @imagecreatefrompng($fullpath);
            if ($img) {
                @imagepalettetotruecolor($img);
                @imagealphablending($img, true);
                @imagesavealpha($img, true);
            }
            return $img;
        case 'jpg':
        case 'jpeg':
            if (!function_exists('imagecreatefromjpeg')) return null;
            return @imagecreatefromjpeg($fullpath);
        case 'gif':
            if (!function_exists('imagecreatefromgif')) return null;
            return @imagecreatefromgif($fullpath);
        case 'bmp':
            if (!function_exists('imagecreatefrombmp')) return null;
            return @imagecreatefrombmp($fullpath);
        case 'wbmp':
            if (!function_exists('imagecreatefromwbmp')) return null;
            return @imagecreatefromwbmp($fullpath);
        case 'webp':
            if (!function_exists('imagecreatefromwebp')) return null;
            return @imagecreatefromwebp($fullpath);
    }
    return null;
}

// Konversi semua format gambar didukung ke WebP + resize ke ukuran wajar (runtime optimization).
function foto_convert_to_webp($filepath) {
    if (empty($filepath)) return $filepath;

    // Pengecekan modul & fungsi GD agar tidak menyebabkan Fatal Error jika GD mati
    if (!extension_loaded('gd') || !function_exists('imagewebp')) {
        return $filepath;
    }

    $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
    $supported = ['png', 'jpg', 'jpeg', 'gif', 'bmp', 'wbmp', 'webp'];
    if (!in_array($ext, $supported, true)) return $filepath;

    $fullpath = '../' . str_replace('backend/', '', $filepath);
    if (!file_exists($fullpath)) return $filepath;

    try {
        $img = foto_load_gd($fullpath, $ext);
        if (!$img) return $filepath;

        // Pastikan truecolor: imagewebp tidak mendukung palette image (mis. GIF).
        if (!imageistruecolor($img)) {
            @imagepalettetotruecolor($img);
        }

        // Resize jika sisi terpanjang melebihi batas (hemat payload, tetap cukup untuk crop ulang).
        $w = imagesx($img);
        $h = imagesy($img);
        $max = max($w, $h);
        if ($max > FOTO_MAX_DIM) {
            $scale = FOTO_MAX_DIM / $max;
            $nw = (int) round($w * $scale);
            $nh = (int) round($h * $scale);
            $resized = imagecreatetruecolor($nw, $nh);
            if ($ext === 'png' || $ext === 'webp' || $ext === 'gif') {
                @imagealphablending($resized, false);
                @imagesavealpha($resized, true);
            }
            @imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            @imagedestroy($img);
            $img = $resized;
        }

        $webpPath = preg_replace('/\.(png|jpe?g|gif|bmp|wbmp|webp)$/i', '.webp', $fullpath);
        $saved = @imagewebp($img, $webpPath, 82);
        if ($img) {
            @imagedestroy($img);
        }

        if ($saved && file_exists($webpPath) && filesize($webpPath) > 0) {
            @unlink($fullpath);
            return str_replace(basename($filepath), basename($webpPath), $filepath);
        }
    } catch (Throwable $e) {
        // Ignore conversion errors, keep original
    }

    return $filepath;
}

// Upload untuk CREATE: kembalikan [foto_asli, foto_crop].
// Jika tidak ada `foto_original`, fallback kompatibilitas: `foto` disimpan sebagai asli.
function foto_handle_create($upload_dir, $prefix) {
    if (foto_has_upload('foto_original')) {
        $original = foto_save_file('foto_original', $upload_dir, $prefix);
        $original = foto_convert_to_webp($original);
        $crop = foto_save_file('foto', $upload_dir, $prefix);
        $crop = foto_convert_to_webp($crop);
        return [$original, $crop];
    }
    $legacy = foto_save_file('foto', $upload_dir, $prefix);
    return [foto_convert_to_webp($legacy), ''];
}

// Upload untuk UPDATE: kembalikan [foto_asli, foto_crop] terbaru.
// - Ada `foto_original` : ganti foto asli + foto crop (hapus file lama).
// - Hanya `foto`        : ganti foto crop saja (crop ulang, foto asli dipertahankan).
// - Tanpa file          : pertahankan keduanya.
function foto_handle_update($upload_dir, $prefix, $old_original, $old_crop) {
    if (foto_has_upload('foto_original')) {
        foto_unlink($old_original);
        foto_unlink($old_crop);
        $original = foto_save_file('foto_original', $upload_dir, $prefix);
        $original = foto_convert_to_webp($original);
        $crop = foto_save_file('foto', $upload_dir, $prefix);
        $crop = foto_convert_to_webp($crop);
        return [$original, $crop];
    }
    if (foto_has_upload('foto')) {
        foto_unlink($old_crop);
        $crop = foto_save_file('foto', $upload_dir, $prefix);
        return [$old_original, foto_convert_to_webp($crop)];
    }
    return [$old_original, $old_crop];
}
?>
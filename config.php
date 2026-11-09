<?php
// PENGATURAN DATABASE (sesuaikan jika perlu)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'pustaka');

// ATURAN PERPUSTAKAAN
define('DENDA_PER_HARI', 1000);
define('MAKS_PINJAM', 3);
define('LAMA_PINJAM', 7); // hari

date_default_timezone_set('Asia/Jakarta');
session_start();

// alat bantu
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function redirect($url) { header('Location: ' . $url); exit; }
function user() { return isset($_SESSION['user']) ? $_SESSION['user'] : null; }
function is_staff() { $u = user(); return $u && in_array($u['role'], array('admin', 'petugas')); }

function flash($type = null, $msg = null) {
    if ($type !== null) { $_SESSION['flash'] = array($type, $msg); return null; }
    $f = isset($_SESSION['flash']) ? $_SESSION['flash'] : null;
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token() {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check() {
    $t = isset($_POST['csrf']) ? $_POST['csrf'] : '';
    if (!hash_equals(csrf_token(), $t)) { http_response_code(419); exit('Token tidak valid. Muat ulang halaman lalu coba lagi.'); }
}

function require_login($roles = array(), $R = '') {
    $u = user();
    if (!$u) { redirect($R . 'login.php'); }
    if ($roles && !in_array($u['role'], $roles)) { http_response_code(403); exit('Akses ditolak.'); }
}

function tgl($d) { return $d ? date('d/m/Y', strtotime($d)) : '-'; }
function rupiah($n) { return 'Rp' . number_format((int)$n, 0, ',', '.'); }

function hari_terlambat($l) {
    if (empty($l['jatuh_tempo']) || !in_array($l['status'], array('dipinjam', 'dikembalikan'))) { return 0; }
    $akhir = !empty($l['tgl_kembali']) ? $l['tgl_kembali'] : date('Y-m-d');
    return max(0, (int)floor((strtotime($akhir) - strtotime($l['jatuh_tempo'])) / 86400));
}
function denda_loan($l) {
    return $l['status'] === 'dipinjam' ? hari_terlambat($l) * DENDA_PER_HARI : (int)$l['denda'];
}
function status_info($l) {
    if ($l['status'] === 'dipinjam' && hari_terlambat($l) > 0) { return array('Terlambat', 'er'); }
    $m = array(
        'menunggu' => array('Menunggu', 'wa'),
        'dipinjam' => array('Dipinjam', 'ok'),
        'dikembalikan' => array('Dikembalikan', 'nt'),
        'ditolak' => array('Ditolak', 'er'),
    );
    return $m[$l['status']];
}
function badge($l) { $s = status_info($l); return '<span class="b ' . $s[1] . '">' . e($s[0]) . '</span>'; }

function cover($id) {
    $p = array(
        array('#5A3418', '#F6EEDB'), array('#2F4A3A', '#F6EEDB'), array('#27384F', '#F6EEDB'),
        array('#E0CBA0', '#2A1B10'), array('#7A2E22', '#F6EEDB'), array('#8A5A25', '#FFF8E7'),
        array('#3B2618', '#F6EEDB'), array('#C98A3D', '#2A1B10'), array('#6B4E2E', '#F6EEDB'),
        array('#1F3B34', '#F6EEDB'),
    );
    return $p[$id % count($p)];
}

function book_card($b) {
    $c = cover((int)$b['id']);
    $h = '<div><div class="cv" style="background:' . $c[0] . ';color:' . $c[1] . '"><span>' . e($b['judul']) . '</span><small>' . e($b['penulis']) . '</small></div>';
    $h .= '<div class="bt">' . e($b['judul']) . '</div><div class="mut">' . e($b['penulis']) . '</div><div class="mut">' . e($b['kategori']) . '</div>';
    $h .= '<div class="row" style="justify-content:space-between;margin-top:6px">';
    $h .= '<span class="b ' . ($b['stok'] > 0 ? 'ok' : 'er') . '">' . ($b['stok'] > 0 ? 'Stok ' . (int)$b['stok'] : 'Habis') . '</span>';
    if ($b['stok'] > 0) {
        $h .= '<form method="post" action="pinjam.php">' . csrf_field() . '<input type="hidden" name="book_id" value="' . (int)$b['id'] . '"><button class="btn sm">Pinjam</button></form>';
    }
    return $h . '</div></div>';
}

// Koneksi database
if (!defined('NO_DB')) {
    try {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ));
    } catch (PDOException $ex) {
        exit('Koneksi database gagal. Pastikan MySQL sudah menyala, lalu buka <a href="install.php">install.php</a> terlebih dahulu.<br><small>' . e($ex->getMessage()) . '</small>');
    }
}

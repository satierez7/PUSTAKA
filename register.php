<?php
require __DIR__ . '/config.php';
if (user()) { redirect('index.php'); }
$errors = array();
$v = array('name' => '', 'email' => '', 'nis' => '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($v as $k => $_) { $v[$k] = trim(isset($_POST[$k]) ? $_POST[$k] : ''); }
    $pw = isset($_POST['password']) ? $_POST['password'] : '';
    if ($v['name'] === '') { $errors[] = 'Nama wajib diisi.'; }
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) { $errors[] = 'Email tidak valid.'; }
    if (strlen($pw) < 6) { $errors[] = 'Kata sandi minimal 6 karakter.'; }
    if ($pw !== (isset($_POST['password2']) ? $_POST['password2'] : '')) { $errors[] = 'Konfirmasi kata sandi tidak sama.'; }
    if (!$errors) {
        $c = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $c->execute(array($v['email']));
        if ($c->fetchColumn() > 0) { $errors[] = 'Email sudah terdaftar.'; }
    }
    if (!$errors) {
        $pdo->prepare('INSERT INTO users (name,email,password,role,nis) VALUES (?,?,?,?,?)')
            ->execute(array($v['name'], $v['email'], password_hash($pw, PASSWORD_DEFAULT), 'anggota', $v['nis'] ?: null));
        $id = (int)$pdo->lastInsertId();
        session_regenerate_id(true);
        $_SESSION['user'] = array('id' => $id, 'name' => $v['name'], 'role' => 'anggota');
        redirect('index.php');
    }
}
$title = 'Daftar'; $heading = 'Buat Akun'; $sub = 'Daftar sebagai anggota perpustakaan';
$altlink = 'login.php'; $alttext = 'Masuk';
include __DIR__ . '/partials/auth_header.php';
?>
<form method="post">
    <?= csrf_field() ?>
    <label for="name">Nama lengkap</label>
    <input class="in" id="name" name="name" value="<?= e($v['name']) ?>" required>
    <div class="fg">
        <div><label for="email">Email</label><input class="in" id="email" name="email" type="email" value="<?= e($v['email']) ?>" required></div>
        <div><label for="nis">NIS (opsional)</label><input class="in" id="nis" name="nis" value="<?= e($v['nis']) ?>"></div>
        <div><label for="password">Kata sandi</label><input class="in" id="password" name="password" type="password" required></div>
        <div><label for="password2">Ulangi sandi</label><input class="in" id="password2" name="password2" type="password" required></div>
    </div>
    <button class="btn" style="width:100%">Daftar</button>
</form>
<?php include __DIR__ . '/partials/auth_footer.php'; ?>

<?php
require __DIR__ . '/../config.php';
require_login(array('admin', 'petugas'), '../');
$R = '../'; $active = 'members.php';
$id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
$m = array('name' => '', 'email' => '', 'nis' => '', 'no_hp' => '', 'alamat' => '');
if ($id) {
    $st = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'anggota'"); $st->execute(array($id));
    $m = $st->fetch();
    if (!$m) { flash('error', 'Anggota tidak ditemukan.'); redirect('members.php'); }
}
$errors = array();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (array('name', 'email', 'nis', 'no_hp', 'alamat') as $k) { $m[$k] = trim(isset($_POST[$k]) ? $_POST[$k] : ''); }
    $pw = isset($_POST['password']) ? $_POST['password'] : '';
    if ($m['name'] === '') { $errors[] = 'Nama wajib diisi.'; }
    if (!filter_var($m['email'], FILTER_VALIDATE_EMAIL)) { $errors[] = 'Email tidak valid.'; }
    if (!$id && strlen($pw) < 6) { $errors[] = 'Kata sandi minimal 6 karakter.'; }
    if ($id && $pw !== '' && strlen($pw) < 6) { $errors[] = 'Kata sandi baru minimal 6 karakter.'; }
    if (!$errors) {
        $c = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?'); $c->execute(array($m['email'], $id));
        if ($c->fetchColumn() > 0) { $errors[] = 'Email sudah dipakai.'; }
    }
    if (!$errors) {
        if ($id) {
            $pdo->prepare('UPDATE users SET name=?, email=?, nis=?, no_hp=?, alamat=? WHERE id=?')->execute(array($m['name'], $m['email'], $m['nis'] ?: null, $m['no_hp'] ?: null, $m['alamat'] ?: null, $id));
            if ($pw !== '') { $pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute(array(password_hash($pw, PASSWORD_DEFAULT), $id)); }
            flash('success', 'Data anggota diperbarui.');
        } else {
            $pdo->prepare("INSERT INTO users (name,email,password,role,nis,no_hp,alamat) VALUES (?,?,?,'anggota',?,?,?)")->execute(array($m['name'], $m['email'], password_hash($pw, PASSWORD_DEFAULT), $m['nis'] ?: null, $m['no_hp'] ?: null, $m['alamat'] ?: null));
            flash('success', 'Anggota ditambahkan.');
        }
        redirect('members.php');
    }
}
$title = $id ? 'Edit Anggota' : 'Tambah Anggota';
include __DIR__ . '/../partials/header.php'; ?>
<h1><?= e($title) ?></h1>
<p class="sub">Lengkapi data anggota</p>
<?php if ($errors): ?><div class="alert er"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>
<div class="card" style="max-width:680px">
<form method="post">
    <?= csrf_field() ?>
    <div class="fg">
        <div><label for="name">Nama lengkap</label><input class="in" id="name" name="name" value="<?= e($m['name']) ?>" required></div>
        <div><label for="nis">NIS</label><input class="in" id="nis" name="nis" value="<?= e($m['nis']) ?>"></div>
        <div><label for="email">Email</label><input class="in" id="email" name="email" type="email" value="<?= e($m['email']) ?>" required></div>
        <div><label for="no_hp">No. HP</label><input class="in" id="no_hp" name="no_hp" value="<?= e($m['no_hp']) ?>"></div>
        <div><label for="password">Kata sandi <?= $id ? '(kosongkan jika tidak diubah)' : '' ?></label><input class="in" id="password" name="password" type="password" <?= $id ? '' : 'required' ?>></div>
        <div><label for="alamat">Alamat</label><input class="in" id="alamat" name="alamat" value="<?= e($m['alamat']) ?>"></div>
    </div>
    <div class="row"><button class="btn">Simpan</button><a class="btn2" href="members.php">Batal</a></div>
</form></div>
<?php include __DIR__ . '/../partials/footer.php'; ?>

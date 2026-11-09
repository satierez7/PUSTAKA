<?php $f = flash(); ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> - Pustaka</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<div class="frame" style="align-items:center;justify-content:center;background:#D9C7A0;padding:24px">
    <div class="authbox">
        <div class="art">
            <?php foreach (array(array(30,60,230,52,'#8A5A25',-14), array(60,120,250,48,'#C98A3D',8), array(20,180,240,56,'#5A3418',-6), array(70,246,260,50,'#E0CBA0',12), array(40,310,230,54,'#6B4E2E',-10), array(90,370,240,48,'#A9743A',5)) as $b): ?>
                <div style="position:absolute;left:<?= $b[0] ?>px;top:<?= $b[1] ?>px;width:<?= $b[2] ?>px;height:<?= $b[3] ?>px;background:<?= $b[4] ?>;transform:rotate(<?= $b[5] ?>deg);border-radius:3px;box-shadow:0 4px 10px rgba(0,0,0,.4)"></div>
            <?php endforeach; ?>
            <div class="tag"><div class="logo">Pustaka</div><div>Ribuan buku, satu tempat.</div></div>
        </div>
        <div class="formside">
            <a class="toplink" href="<?= e($altlink) ?>"><?= e($alttext) ?></a>
            <div class="fcenter">
                <h1><?= e($heading) ?></h1>
                <p class="sub"><?= e($sub) ?></p>
                <?php if ($f): ?><div class="alert <?= $f[0] === 'success' ? 'ok' : 'er' ?>"><?= e($f[1]) ?></div><?php endif; ?>
                <?php if (!empty($errors)): ?><div class="alert er"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

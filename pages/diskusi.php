<?php
require_once __DIR__ . '/../config.php';
require 'ModelDiskusi.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_PATH . "/login");
    exit;
}

$db = (new Database())->getConnection();
$diskusi = new Diskusi($db);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $diskusi->user_id = $_SESSION['user_id'];
    $diskusi->judul = $_POST['judul'];
    $diskusi->pertanyaan = $_POST['pertanyaan'];

    $file = $_FILES['image'] ?? null;
    if (!empty($file['tmp_name'])) {
        $filename = time() . "_" . basename($file['name']);
        move_uploaded_file($file['tmp_name'], __DIR__ . "/uploads/" . $filename);
        $diskusi->image_path = "uploads/" . $filename;
    } else {
        $diskusi->image_path = "";
    }

    $diskusi->create();
    header("Location: " . BASE_PATH . "/diskusi");
    exit;
}

$pertanyaans = $diskusi->getAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Diskusi</title>
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/style.css">
</head>
<body>
    <h2>Forum Diskusi</h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="text" name="judul" required placeholder="Judul"><br>
        <textarea name="pertanyaan" required placeholder="Pertanyaan"></textarea><br>
        <input type="file" name="image" accept="image/*"><br>
        <button type="submit">Kirim</button>
    </form>
    <hr>
    <?php foreach ($pertanyaans as $row): ?>
        <div>
            <strong><?php echo htmlspecialchars($row['judul']); ?> (<?php echo htmlspecialchars($row['name']); ?>)</strong><br>
            <?php echo nl2br(htmlspecialchars($row['pertanyaan'])); ?><br>
            <?php if ($row['image_path']): ?>
                <img src="<?php echo BASE_PATH . '/' . htmlspecialchars($row['image_path']); ?>" width="150"><br>
            <?php endif; ?>
            <small><?php echo htmlspecialchars($row['created_at']); ?></small><hr>
        </div>
    <?php endforeach; ?>
    <a href="<?php echo BASE_PATH; ?>/dashboard">Kembali</a>
</body>
</html>

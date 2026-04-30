<?php
// Настройки лимитов для загрузки больших файлов
@ini_set('upload_max_filesize', '10G');
@ini_set('post_max_size', '10G');
@ini_set('max_execution_time', '300');

$base_dir = '/var/www/html/cloud/';
$categories = [
    'multimedia' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mp3', 'mov'],
    'documents'  => ['pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx', 'ppt', 'rtf'],
    'system'     => ['exe', 'sh', 'deb', 'bin', 'iso'],
    'archives'   => ['zip', 'rar', '7z', 'tar', 'gz'],
    'undefined'  => []
];

function formatSize($bytes) {
    if ($bytes >= 1073741824) $bytes = number_format($bytes / 1073741824, 2) . ' GB';
    elseif ($bytes >= 1048576) $bytes = number_format($bytes / 1048576, 2) . ' MB';
    elseif ($bytes >= 1024) $bytes = number_format($bytes / 1024, 2) . ' KB';
    else $bytes = $bytes . ' B';
    return $bytes;
}

function getFolderSize($path) {
    $size = 0;
    $path = rtrim($path, '/');
    if (is_dir($path)) {
        foreach (glob($path . '/*') as $each) {
            $size += is_file($each) ? filesize($each) : 0;
        }
    }
    return $size;
}

$total_cloud_size = 0;
foreach (array_keys($categories) as $cat) { $total_cloud_size += getFolderSize($base_dir . $cat); }

if (isset($_FILES['file'])) {
    $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
    $target_cat = 'undefined';
    foreach ($categories as $cat => $exts) { if (in_array($ext, $exts)) { $target_cat = $cat; break; } }
    move_uploaded_file($_FILES['file']['tmp_name'], $base_dir . $target_cat . '/' . $_FILES['file']['name']);
    header("Location: index.php?cat=" . $target_cat); exit;
}

if (isset($_GET['delete'])) {
    $f_path = $base_dir . $_GET['fcat'] . '/' . $_GET['delete'];
    if (file_exists($f_path)) unlink($f_path);
    header("Location: index.php?cat=" . $_GET['cat']); exit;
}

$current_cat = $_GET['cat'] ?? 'all';
$display_files = [];
if ($current_cat == 'all') {
    foreach (array_keys($categories) as $cat) {
        $path = $base_dir . $cat . '/';
        if (is_dir($path)) {
            $files = array_diff(scandir($path), ['.', '..']);
            foreach ($files as $f) { $display_files[] = ['name' => $f, 'cat' => $cat, 'path' => $path . $f]; }
        }
    }
} else {
    $path = $base_dir . $current_cat . '/';
    if (is_dir($path)) {
        $files = array_diff(scandir($path), ['.', '..']);
        foreach ($files as $f) { $display_files[] = ['name' => $f, 'cat' => $current_cat, 'path' => $path . $f]; }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cloud Storage</title>
    <style>
        :root { --primary: #ff8c00; --blue: #4a69bd; --red: #d63031; --bg: #f0f2f5; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: var(--bg); margin: 0; display: flex; height: 100vh; color: #333; }
        
        .sidebar { width: 340px; padding: 20px; display: flex; flex-direction: column; gap: 20px; box-sizing: border-box; overflow-y: auto; }
        .card { background: white; padding: 20px; border-radius: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .header-title { font-size: 18px; font-weight: bold; border-left: 4px solid var(--primary); padding-left: 12px; margin-bottom: 20px; }
        
        .cat-btn { display: flex; justify-content: space-between; align-items: center; width: 100%; padding: 12px 15px; margin-bottom: 8px; border: none; border-radius: 10px; 
                   background: var(--primary); color: white; text-decoration: none; font-weight: bold; font-size: 13px; transition: 0.2s; box-sizing: border-box; }
        .cat-btn:hover { filter: brightness(1.1); transform: translateX(5px); }
        .cat-btn.active { background: #333; }
        .cat-size { font-size: 10px; background: rgba(0,0,0,0.2); padding: 2px 8px; border-radius: 10px; }
        
        .upload-btn { background: var(--blue); margin-top: 10px; justify-content: center; cursor: pointer; }
        .stat-box { color: white; padding: 12px; border-radius: 8px; font-size: 13px; margin-top: 8px; font-weight: 500; }

        .main-content { flex: 1; padding: 20px; overflow-y: auto; }
        .file-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); grid-gap: 15px; }
        .file-card { background: white; border-radius: 12px; padding: 15px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.04); transition: 0.3s; border: 1px solid transparent; }
        .file-card:hover { border-color: var(--primary); transform: translateY(-3px); }
        
        .btn-sm { padding: 8px 12px; border-radius: 6px; font-size: 11px; text-decoration: none; color: white; font-weight: bold; display: inline-block; cursor: pointer; border:none;}
        
        @media (max-width: 768px) {
            body { flex-direction: column; height: auto; }
            .sidebar { width: 100%; padding: 10px; }
            .file-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="card">
        <div class="header-title">Файлы</div>
        <a href="?cat=all" class="cat-btn <?= $current_cat=='all'?'active':'' ?>" style="background: #555; margin-bottom: 15px;">
            <span>📁 Все файлы</span>
            <span class="cat-size"><?= formatSize($total_cloud_size) ?></span>
        </a>
        <?php
        $names = ['multimedia'=>'🎬 Мультимедиа', 'documents'=>'📄 Документы', 'system'=>'⚙️ Системные файлы', 'archives'=>'📦 Архивы', 'undefined'=>'❓ Неопределенные'];
        foreach ($categories as $cat => $exts) {
            $size = formatSize(getFolderSize($base_dir . $cat));
            $act = ($current_cat == $cat) ? 'active' : '';
            echo "<a href='?cat=$cat' class='cat-btn $act'><span>{$names[$cat]}</span><span class='cat-size'>$size</span></a>";
        }
        ?>
        <form action="index.php" method="post" enctype="multipart/form-data" id="uploadForm">
            <label class="cat-btn upload-btn">
                📂 Загрузить файл
                <input type="file" name="file" style="display:none;" onchange="document.getElementById('uploadForm').submit()">
            </label>
        </form>
    </div>

    <div class="card">
        <div class="header-title" style="border-color: #27ae60;">Память</div>
        <div class="stat-box" style="background: #27ae60;">В облаке: <?= formatSize($total_cloud_size) ?></div>
        <div class="stat-box" style="background: #219150;">Свободно: <?= formatSize(disk_free_space("/")) ?></div>
    </div>
</div>

<div class="main-content">
    <div class="file-grid">
        <?php foreach ($display_files as $f): ?>
            <div class="file-card">
                <div style="height: 100px; display: flex; align-items: center; justify-content: center; margin-bottom: 10px; background: #f9f9f9; border-radius: 8px; overflow: hidden;">
                    <?php 
                        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                            echo '<img src="'.$f['cat'].'/'.$f['name'].'" style="width: 100%; height: 100%; object-fit: cover;">';
                        } else {
                            echo '<div style="font-size: 40px;">📄</div>';
                        }
                    ?>
                </div>
                <div style="font-size: 13px; font-weight: 600; height: 36px; overflow: hidden; margin-bottom: 5px;"><?= $f['name'] ?></div>
                <div style="font-size: 11px; color: #999; margin-bottom: 10px;"><?= formatSize(filesize($f['path'])) ?></div>
                <div style="display: flex; justify-content: center; gap: 5px;">
                    <a href="<?= $f['cat'] ?>/<?= $f['name'] ?>" download class="btn-sm" style="background: var(--blue); flex: 1; text-align: center;">Скачать</a>
                    <a href="?cat=<?= $current_cat ?>&fcat=<?= $f['cat'] ?>&delete=<?= $f['name'] ?>" class="btn-sm" style="background: var(--red);" onclick="return confirm('Удалить?')">🗑️</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

</body>
</html>

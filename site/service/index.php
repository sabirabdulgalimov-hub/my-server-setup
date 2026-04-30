<?php
// --- 1. НАСТРОЙКИ ПУТЕЙ ---
$temp_csv = '/var/www/html/service/temp_data.csv';
$log_file = '/var/www/html/service/blockcheck_res.txt';
$strategies_file = '/var/www/html/service/my_strategies.txt';
$config_file = '/opt/zapret/config'; // этот путь не меняем

// 2. СБОР ТЕМПЕРАТУРЫ (Исправленный)
$temp_raw = shell_exec("cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null");
$cpu_temp = $temp_raw ? round((float)$temp_raw / 1000, 1) : 0;

if ($cpu_temp > 0) {
    // Читаем текущие данные, убирая пустые строки
    $lines = file_exists($temp_csv) ? file($temp_csv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    
    // Добавляем новую точку в конец
    $lines[] = time() . "," . $cpu_temp;
    
    // Оставляем только последние 72 записи (за 24 часа, если замер каждые 20 мин)
    if (count($lines) > 1440) {
        $lines = array_slice($lines, -1440);
    }
    
    // Перезаписываем файл целиком ОДНИМ махом (атомарно через LOCK_EX)
    file_put_contents($temp_csv, implode("\n", $lines) . "\n", LOCK_EX);
}

// 3. ПОДГОТОВКА JSON ДЛЯ ГРАФИКА
$chart_points = [];
if (file_exists($temp_csv)) {
    // Читаем файл, игнорируя пустые строки
    $lines = file($temp_csv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    // Берем последние 100 точек (хватит для Core 2 Duo, чтобы не тормозило)
    $lines = array_slice($lines, -100);
    
    foreach ($lines as $line) {
        $c = explode(',', trim($line));
        if (count($c) >= 2) {
            $time = (int)$c[0] * 1000; // время в мс
            $temp = (float)$c[1];      // температура
            $chart_points[] = ['x' => $time, 'y' => $temp];
        }
    }
}
$json_data = json_encode($chart_points);

// 4. СТАТУСЫ СЕРВИСОВ
function get_status($service) {
    $res = trim(shell_exec("systemctl is-active $service 2>/dev/null"));
    return ($res == 'active') ? '🟢' : '🔴';
}
$is_running = !empty(trim(shell_exec("ps aux | grep '[b]lockcheck.sh'")));

// 5. ОБРАБОТКА ДЕЙСТВИЙ (КНОПКИ)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action == 'zapret') shell_exec('sudo systemctl restart zapret');
    if ($action == 'tgproxy') shell_exec('sudo systemctl restart tgproxy');
    if ($action == 'tailscale') shell_exec('sudo systemctl restart tailscaled');
    if ($action == 'reboot') shell_exec('sudo reboot');
    
if ($action == 'run_blockcheck' && !$is_running) {
    $domain = escapeshellcmd($_POST['domain'] ?: 'youtube.com');
    $mode = isset($_POST['mode']) ? (int)$_POST['mode'] : 1;
    
    // ОДНА строка с правильной последовательностью ответов:
    // Домен -> Enter (пауза) -> 4 (IPv4) -> Enter (пауза) -> 1 (Standard сканирование) -> $mode (скорость) -> куча Enter до конца
    $input_string = $domain . "\\n\\n\\n4\\n\\n1\\n" . $mode . "\\n\\n\\n\\n\\n\\n\\n\\n\\n\\n";

    shell_exec("sudo systemctl stop zapret && > /var/www/html/service/blockcheck_res.txt");

    $cmd = "cd /home/sabir/zapret-72.12/ && printf \"$input_string\" | sudo /usr/bin/stdbuf -oL -eL /bin/bash ./blockcheck.sh > /var/www/html/service/blockcheck_res.txt 2>&1 &";
    shell_exec($cmd);
    
    header("Location: index.php"); exit;
}
    
    if ($action == 'stop_blockcheck') {
        shell_exec("sudo pkill -9 -f blockcheck && sudo systemctl start zapret");
    }

    // КРАСИВОЕ СОХРАНЕНИЕ СТРАТЕГИЙ
    if ($action == 'extract_strategies') {
        $log_content = file_get_contents($log_file);
        
        if (strpos($log_content, 'SUMMARY') !== false) {
            $date = date('Y-m-d H:i');
            
            // 1. Сначала пробуем взять из поля ввода на сайте
            $domain_name = !empty($_POST['domain']) ? trim(escapeshellcmd($_POST['domain'])) : "";
            
            // 2. Если поле пустое, ищем в логе более хитрым способом
            if (empty($domain_name)) {
                $detected = shell_exec("grep -E 'testing|domain\(s\)' $log_file | head -n 1 | awk -F': ' '{print $2}'");
                $domain_name = trim($detected) ?: "youtube.com"; // Ютуб как запасной вариант
            }

            $header = "\n" . str_repeat("=", 35) . "\n";
            $header .= "[ $date ] FINAL SUMMARY REPORT: $domain_name\n";
            $header .= str_repeat("=", 35) . "\n";
            
            file_put_contents($strategies_file, $header, FILE_APPEND);
            shell_exec("grep -A 10000 \"SUMMARY\" $log_file >> $strategies_file");
            file_put_contents($strategies_file, str_repeat("=", 35) . "\n", FILE_APPEND);
        }
        header("Location: index.php"); exit;
    }
    // ОЧИСТКА БАЗЫ
    if ($action == 'clear_strategies') {
        file_put_contents($strategies_file, "");
    }

    header("Location: index.php"); exit;
}

if (isset($_POST['save_config'])) {
    file_put_contents($config_file, $_POST['config_content']);
    shell_exec('sudo systemctl restart zapret');
    header("Location: index.php"); exit;
}

$ram = shell_exec("free -m | grep Mem | awk '{print $3\"Mb / \"$2\"Mb\"}'") ?? "н/д";
$disk = shell_exec("df -h / | grep / | awk '{print $4}'") ?? "н/д";
$config_content = file_exists($config_file) ? file_get_contents($config_file) : "";
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Dashboard v2.2</title>
    <style>
        :root { --bg: #f0f2f5; --card: #fff; --primary: #ff8c00; }
        body { background: var(--bg); font-family: sans-serif; margin: 0; padding: 10px; display: flex; justify-content: center; }
        .main-container { display: flex; flex-direction: column; gap: 15px; width: 100%; max-width: 1400px; }
        @media (min-width: 1100px) { .main-container { display: grid; grid-template-columns: 350px 1fr; gap: 20px; padding: 20px; } }
        .card { background: var(--card); border-radius: 12px; padding: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .monitor-box { background: #1a1a1a; color: #fff; border-radius: 12px; padding: 20px; height: 350px; position: relative; }
        h3 { margin: 0 0 15px 0; font-size: 18px; border-left: 4px solid var(--primary); padding-left: 10px; }
        button { border: none; border-radius: 8px; color: #fff; font-weight: bold; cursor: pointer; padding: 12px; width: 100%; margin-bottom: 8px; }
        .btn-orange { background: var(--primary); } .btn-red { background: #d32f2f; } .btn-green { background: #2e7d32; } .btn-blue { background: #3f51b5; } .btn-gray { background: #666; }
        textarea { width: 100%; height: 300px; font-family: monospace; padding: 10px; border-radius: 8px; border: 1px solid #ddd; box-sizing: border-box; }
        input { width: 100%; padding: 10px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; }
        #logModal { display: none; position: fixed; z-index: 100; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); }
        .modal-content { background: #000; color: #0f0; margin: 5% auto; padding: 20px; width: 90%; max-width: 900px; border-radius: 12px; }
        #logText { height: 60vh; overflow-y: auto; white-space: pre-wrap; font-family: monospace; font-size: 12px; }
    </style>
</head>
<body>
<div class="main-container">
    <div class="left-col" style="display:flex; flex-direction:column; gap:15px;">
        <div class="card">
            <div style="display:flex; justify-content:space-between; font-size:14px; margin-bottom:8px;"><span>RAM:</span> <b><?=$ram?></b></div>
            <div style="display:flex; justify-content:space-between; font-size:14px;"><span>Disk:</span> <b><?=$disk?> free</b></div>
        </div>
        <div class="card">
            <h3>Сервисы</h3>
            <form method="post">
                <button name="action" value="zapret" class="btn-orange"><?=get_status('zapret')?> Restart Zapret</button>
                <button name="action" value="tgproxy" class="btn-orange"><?=get_status('tgproxy')?> Restart TG Proxy</button>
                <button name="action" value="tailscale" class="btn-orange"><?=get_status('tailscaled')?> Restart Tailscale</button>
                <button name="action" value="reboot" class="btn-red">Перезагрузить сервер</button>
            </form>
        </div>
<div class="card">
    <h3>Zapret Helper</h3>
    <form method="post">
        <!-- Поле для ввода домена -->
        <input type="text" name="domain" placeholder="youtube.com" value="youtube.com" required>
        
        <!-- Выбор режима поиска -->
        <select name="mode" style="width:100%; padding:10px; margin-bottom:10px; border-radius:8px; border:1px solid #ddd;">
            <option value="1">Quick Scan (Быстро)</option>
            <option value="2">Standard (Обычный)</option>
            <option value="3">Force (Глубокий)</option>
        </select>
        
        <?php if ($is_running): ?>
            <div style="text-align:center; padding:10px; color:orange;">🔍 Анализ запущен...</div>
            <button name="action" value="stop_blockcheck" class="btn-red">Остановить поиск</button>
        <?php else: ?>
            <button name="action" value="run_blockcheck" class="btn-gray">🚀 Начать анализ</button>
        <?php endif; ?>
    </form>
    
    <button onclick="openLogs()" class="btn-blue">📄 Показать логи</button>
    
    <div style="display:flex; gap:10px; margin-top:10px;">
        <a href="my_strategies.txt" target="_blank" style="flex:2;"><button class="btn-green">📂 База</button></a>
        <form method="post" style="flex:1;"><button name="action" value="extract_strategies" class="btn-green">📥</button></form>
        <form method="post" style="flex:1;"><button name="action" value="clear_strategies" class="btn-red">🗑️</button></form>
    </div>
</div>
    </div>

    <div class="right-col" style="display:flex; flex-direction:column; gap:15px;">
<div class="monitor-box">
    <div style="text-align:center; color:#ff5252; font-weight:bold; margin-bottom:10px;">CPU TEMP: <?=$cpu_temp?>°C</div>
    
    <!-- Новый SVG График -->
<!-- Контейнер графика -->
<div id="graph-container" style="background: #000; border-radius: 5px; border: 1px solid #333; height: 300px; position: relative; padding: 15px 10px 10px 10px; margin-bottom: 20px;">
    
    <!-- Всплывающая подсказка (Tooltip) -->
    <div id="svg-tooltip" style="position: absolute; display: none; background: rgba(30, 30, 30, 0.9); color: #4caf50; padding: 5px 10px; border-radius: 4px; font-size: 12px; font-weight: bold; pointer-events: none; z-index: 1000; border: 1px solid #4caf50; white-space: nowrap; box-shadow: 0 2px 10px rgba(0,0,0,0.5);"></div>

    <!-- Боковая шкала -->
    <div style="position: absolute; left: 10px; top: 15px; height: 300px; display: flex; flex-direction: column; justify-content: space-between; color: #888; font-family: Arial; font-size: 11px; z-index: 10; pointer-events: none;">
        <span>70°C</span>
        <span>55°C</span>
        <span>40°C</span>
    </div>

    <svg viewBox="0 0 500 150" preserveAspectRatio="none" style="width: 100%; height: 300px; display: block; padding-left: 35px; box-sizing: border-box;">
        <!-- Сетка -->
        <line x1="0" y1="0" x2="500" y2="0" stroke="#222" stroke-width="1" />
        <line x1="0" y1="75" x2="500" y2="75" stroke="#222" stroke-width="1" />
        <line x1="0" y1="150" x2="500" y2="150" stroke="#222" stroke-width="1" />
        
        <?php
        if (!empty($chart_points) && count($chart_points) > 1) {
            $min_y = 40; $max_y = 70; $width = 500; $height = 150;
            $points = []; $circles = "";
            $x_step = $width / (count($chart_points) - 1);
            $i = 0;
            
            foreach ($chart_points as $p) {
                $x = $i * $x_step;
                $temp = max($min_y, min($max_y, $p['y']));
                $y = $height - (($temp - $min_y) / ($max_y - $min_y) * $height);
                $points[] = round($x, 1) . "," . round($y, 1);
                
                $time_str = date("H:i", $p['x'] / 1000);
                $t_val = $p['y'];

                // Рисуем видимые точки-узлы
                $circles .= "<circle cx='$x' cy='$y' r='1.5' fill='#4caf50' fill-opacity='0.5' 
                             onmouseover='showT(this, \"$t_val°C [$time_str]\")' 
                             onmouseout='hideT()' style='cursor:pointer;' />";
                $i++;
            }
            $points_str = implode(" ", $points);
            echo '<polyline points="0,'.$height.' '.$points_str.' '.$width.','.$height.'" fill="rgba(76,175,80,0.15)" stroke="none" />';
            echo '<polyline points="'.$points_str.'" fill="none" stroke="#4caf50" stroke-width="1" stroke-linejoin="round" />';
            echo $circles; 
        }
        ?>
    </svg>
</div>


</div>
        <div class="card">
            <h3>Редактор конфига</h3>
            <form method="post">
                <textarea name="config_content"><?=htmlspecialchars($config_content)?></textarea>
                <button name="save_config" class="btn-green" style="margin-top:10px;">💾 Сохранить и применить</button>
            </form>
        </div>
    </div>
</div>

<!-- Окно логов -->
<div id="logModal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.85);">
    <div style="background:#1a1a1a; margin:5% auto; padding:20px; width:90%; max-width:850px; border-radius:10px; border: 1px solid #444; box-shadow: 0 0 20px rgba(0,0,0,0.5);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom: 1px solid #333; padding-bottom: 10px;">
            <h3 style="color:#f29961; margin:0; font-family: sans-serif;">| Логи анализа (Zapret)</h3>
	    <button onclick="closeLogs()" style="background:#d63031; border:none; color:white; padding:5px 15px; cursor:pointer; border-radius:4px; font-weight:bold; width: 40px; height: 30px; flex-shrink: 0;">X</button>
        </div>
        
        <!-- Поле для текста логов -->
        <pre id="logContent" style="background:#000; color:#2ecc71; padding:15px; height:450px; overflow-y:auto; border:1px solid #333; font-family:'Courier New', monospace; white-space:pre-wrap; margin:0; font-size:13px; line-height:1.4;"></pre>
    </div>
</div>

<script>

// --- ФУНКЦИИ ГРАФИКА ---
const tooltip = document.getElementById('svg-tooltip');

function showT(element, text) {
    const rect = element.getBoundingClientRect();
    tooltip.innerHTML = text;
    tooltip.style.display = "block";
    
    // Точное позиционирование без «вылетов» за экран
    const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    
    tooltip.style.left = (rect.left + scrollLeft - (tooltip.offsetWidth / 2) + (rect.width / 2)) + 'px';
    tooltip.style.top = (rect.top + scrollTop - tooltip.offsetHeight - 10) + 'px';
}

function hideT() {
    tooltip.style.display = "none";
}

// --- ФУНКЦИИ ЛОГОВ (Оставляем как было) ---

function openLogs() { 
    // Проверь, что ID окна в HTML совпадает с этим (logModal)
    const modal = document.getElementById('logModal');
    if (modal) {
        modal.style.display = "block"; 
        fetchLogs(); 
        clearInterval(logInt);
        logInt = setInterval(fetchLogs, 2000); 
    } else {
        console.error("Окно logModal не найдено!");
    }
}

function closeLogs() { 
    document.getElementById('logModal').style.display = "none"; 
    clearInterval(logInt); 
}

function fetchLogs() { 
    fetch('/service/blockcheck_res.txt?v=' + Date.now())
        .then(r => r.ok ? r.text() : "Загрузка...")
        .then(d => { 
            const el = document.getElementById('logContent');
            if (el) { el.textContent = d; el.scrollTop = el.scrollHeight; }
        });
}

window.addEventListener('load', () => { if (isRunning) openLogs(); });
    // 1. Статус из PHP
let logInt = null;
const isRunning = <?=json_encode($is_running)?>;

// 1. Функция ОТКРЫТИЯ окна логов
function openLogs() { 
    const modal = document.getElementById('logModal');
    if (modal) {
        modal.style.display = "block"; 
        fetchLogs(); 
        if (logInt) clearInterval(logInt);
        logInt = setInterval(fetchLogs, 2000); 
    } else {
        alert("Ошибка: Окно logModal не найдено в коде!");
    }
}

// 2. Функция ЗАКРЫТИЯ окна логов
function closeLogs() { 
    const modal = document.getElementById('logModal');
    if (modal) modal.style.display = "none"; 
    if (logInt) clearInterval(logInt); 
}

// 3. Функция ПОЛУЧЕНИЯ текста логов
function fetchLogs() { 
    fetch('/service/blockcheck_res.txt?v=' + Date.now())
        .then(r => r.ok ? r.text() : "Файл логов еще не создан...")
        .then(d => { 
            const el = document.getElementById('logContent');
            if (el) { 
                el.textContent = d; 
                el.scrollTop = el.scrollHeight; 
            }
        })
        .catch(err => console.error("Ошибка загрузки:", err));
}

    // 5. Автозапуск при загрузке страницы, если анализ идет
    window.addEventListener('load', function() {
        if (isRunning) {
            openLogs();
        }
    });

</script>
</body>
</html>

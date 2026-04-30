<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель управления</title>
    <style>
        body {
            background-color: #ebedef;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .hub-card {
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            width: 90%;
            max-width: 450px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .header {
            color: #333;
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 10px;
            padding-left: 5px;
            border-left: 4px solid #f29961;
        }
        .item {
            padding: 15px;
            text-align: center;
            text-decoration: none;
            color: white;
            font-size: 16px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
            border: none;
        }
        .btn-orange {
            background-color: #ff8c00;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
        }
        .btn-blue {
            background-color: #4a69bd;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
        }
        /* Новый стиль для кнопки Терминала */
        .btn-dark {
            background-color: #2d3436;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
        }
        .item:hover {
            filter: brightness(1.1);
            transform: translateY(-1px);
        }
        .item:active { transform: translateY(0); }
    </style>
</head>
<body>

<div class="hub-card">
    <div class="header">Управление сервером</div>

    <a href="/service/" class="item btn-orange">⚙️ ПАНЕЛЬ МОНИТОРИНГА</a>

    <a href="/cloud/" class="item btn-blue">📂 ОБЛАЧНОЕ ХРАНИЛИЩЕ</a>

    <!-- Новая кнопка терминала -->
    <a href="http://100.76.248.5:9090" target="_blank" class="item btn-dark">🐚 ТЕРМИНАЛ (COCKPIT)</a>
</div>

</body>
</html>

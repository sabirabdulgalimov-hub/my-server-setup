#!/bin/bash
echo "🧹 Начинаю полную очистку системы..."

# 1. Останавливаем и удаляем службы
sudo systemctl stop tailscaled zapret tgproxy 2>/dev/null
sudo systemctl disable tailscaled zapret tgproxy 2>/dev/null

# 2. Удаляем софт
sudo apt purge -y tailscale nginx php-fpm
sudo rm -rf /opt/zapret
sudo rm -rf /opt/tgproxy
sudo rm -rf /var/lib/tailscale

# 3. Очищаем веб-папку
sudo rm -rf /var/www/html/*

# 4. Очищаем автозагрузку (Cron)
crontab -r

echo "✅ Система очищена! (Кроме базовых зависимостей)"

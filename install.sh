#!/bin/bash

echo "🚀 Запуск полной установки сервера..."

# 1. Обновление и установка софта
sudo apt update
sudo apt install -y nginx cockpit php-fpm curl git

# 2. Установка Tailscale и Zapret
curl -fsSL https://tailscale.com | sh
git clone https://github.com /opt/zapret
# Настройка zapret (используем твой конфиг)
sudo cp configs/zapret.conf /opt/zapret/config

# 3. Восстановление сайта (панель, облако и прочее)
sudo rm -rf /var/www/html/*
sudo cp -r site/* /var/www/html/
sudo chown -R www-data:www-data /var/www/html/

# 4. Восстановление конфига Nginx
sudo cp configs/nginx_default /etc/nginx/sites-available/default
sudo systemctl restart nginx

# 5. Включение Cockpit
sudo systemctl enable --now cockpit.socket

# 6. Права Sudo для кнопок
if [ -f configs/sudo_rules ]; then
    cat configs/sudo_rules | sudo tee -a /etc/sudoers
fi

# 7. DNS Fix (чтобы Tailscale не отваливался)
echo "nameserver 8.8.8.8" | sudo tee /etc/resolv.conf

echo "✅ Всё готово! Проверь Tailscale командой: tailscale status"

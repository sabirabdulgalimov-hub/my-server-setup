#!/bin/bash
# Установка базовых утилит
sudo apt update && sudo apt install -y curl wget git

# Установка Tailscale (официальный скрипт)
curl -fsSL https://tailscale.com/install.sh | sh

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

echo "Скачиваю и устанавливаю Zapret v72.12..."
cd /opt
sudo wget https://github.com/bol-van/zapret/releases/download/v72.12/zapret-v72.12.tar.gz
sudo tar -xvf zapret-v72.12.tar.gz
sudo mv zapret-v72.12 zapret
sudo rm zapret-v72.12.tar.gz

# Подкидываем твой сохраненный конфиг из репозитория
sudo cp ~/myserver_v2/configs/zapret.conf /opt/zapret/config

# Запуск родного инсталлера
cd /opt/zapret
sudo ./install_bin.sh
# ВАЖНО: install_easy.sh обычно требует ввода данных от пользователя (интерактивный)
sudo ./install_easy.sh

# --- Установка и запуск TG-WS-Proxy ---
echo "📦 Устанавливаю TG-WS-Proxy от Flowseal..."

# 1. Скачиваем прокси в папку /opt (чтобы путь был всегда одинаковый)
sudo git clone https://github.com/Flowseal/tg-ws-proxy/releases/download/v1.2.1/TgWsProxy_linux_amd64.deb /opt/tgproxy

# 2. Настраиваем автозапуск (Cron)
(crontab -l 2>/dev/null; echo "@reboot cd /opt/tgproxy/proxy && nohup python3 tg_ws_proxy.py --host 0.0.0.0 --port 1080 > proxy.log 2>&1 &") | crontab -

# 3. Запускаем немедленно
cd /opt/tgproxy/proxy
nohup python3 tg_ws_proxy.py --host 0.0.0.0 --port 1080 > proxy.log 2>&1 &

echo "✅ Прокси установлен в /opt/tgproxy и запущен на 0.0.0.0:1080"

rm TgWsProxy_linux_amd64.deb


# 7. DNS Fix (чтобы Tailscale не отваливался)
echo "nameserver 8.8.8.8" | sudo tee /etc/resolv.conf

echo "✅ Всё готово! Проверь Tailscale командой: tailscale status"
# --- Настройка планировщика (Cron) ---


# --- Настройка сети для Exit Node ---
echo "⚙️ Настраиваю систему для работы Exit Node..."
echo 'net.ipv4.ip_forward = 1' | sudo tee -a /etc/sysctl.conf
echo 'net.ipv6.conf.all.forwarding = 1' | sudo tee -a /etc/sysctl.conf
sudo sysctl -p > /dev/null

# --- Авторизация в Tailscale ---
echo "🔑 Сейчас появится ссылка для входа в Tailscale."
echo "После того как вы войдете в аккаунт, скрипт автоматически настроит Exit Node."
echo "---------------------------------------------------------"
sudo tailscale up

# Скрипт подождет, пока пользователь залогинится и нажмет Enter
echo "---------------------------------------------------------"
read -p "Нажмите [Enter] ПОСЛЕ того, как успешно войдете в аккаунт в браузере..."

# --- Финальная настройка Exit Node ---
echo "🚀 Активирую режим Exit Node..."
sudo tailscale up --advertise-exit-node

echo "✅ Всё готово! Не забудьте поставить галочку 'Exit Node' в панели на сайте Tailscale."

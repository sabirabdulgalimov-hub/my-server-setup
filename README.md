# My Server Setup

Готовая сборка для развертывания персонального сервера.

### Что внутри:
*   **Tailscale** — защищенная сеть между устройствами.
*   **Zapret** — обход блокировок (настроен через конфиг).
*   **Cockpit** — панель управления сервером (порт 9090).
*   **Web Panel** — мониторинг и управление через браузер.
*   **Cloud** — персональное облако для файлов.

### Как развернуть на новом сервере:
1. `git clone https://github.com/sabirabdulgalimov-hub/my-server-setup`
2. `cd my-server-setup`
3. `sudo ./install.sh`

### 🛠 Использованные инструменты:
*   [zapret](https://github.com/bol-van/zapret) от **bol-van** — для обхода блокировок.
*   [tg-ws-proxy](https://github.com/Flowseal/tg-ws-proxy) от **Flowseal** — для работы прокси. «используется версия 1.2 с поддержкой SOCKS5»

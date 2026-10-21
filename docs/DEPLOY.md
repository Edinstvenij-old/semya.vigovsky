# Развёртывание Family Hub на сервере

Итог: сайт `https://ваш-домен` с бесплатным HTTPS (Let's Encrypt через Caddy), PostgreSQL, приватным
хранилищем файлов (MinIO), очередью для пуш-уведомлений и планировщиком (напоминания, бэкапы).
Обновления — автоматически после `git push` в `main` через GitHub Actions.

## 1. Что понадобится

| Что | Рекомендация |
|---|---|
| VPS | Ubuntu 24.04, **2+ ГБ RAM**, 1–2 vCPU, 20+ ГБ диска. Рекомендуется дата-центр в ЕС (Германия/Финляндия) — не зависит от отключений света в Украине: **Hetzner** (≈ 4–6 € в месяц, оплата украинской Visa/Mastercard), DigitalOcean, Vultr |
| Домен | например `vigovsky.od.ua` (регистраторы imena.ua, hostiq.ua) или `.com.ua` / `.com`; сайт — на поддомене `semya.vigovsky.od.ua` |
| DNS | A-запись `semya` → IP сервера (и AAAA, если есть IPv6) |
| Почта (по желанию) | SMTP для писем «Забыли пароль?»: Gmail (пароль приложения), ukr.net, Mailgun, Brevo и т. п. |

Порты 80 и 443 на сервере должны быть открыты — через них Caddy получает сертификат.

## 2. Подготовка сервера (один раз)

```bash
ssh root@IP_СЕРВЕРА
timedatectl set-timezone Europe/Kyiv
apt update && apt -y upgrade
curl -fsSL https://get.docker.com | sh          # Docker + docker compose
adduser --disabled-password --gecos "" deploy && usermod -aG docker deploy
ufw allow OpenSSH && ufw allow 80,443/tcp && ufw allow 443/udp && ufw --force enable
```

## 3. Доступ сервера к репозиторию (deploy key)

На сервере под пользователем `deploy`:

```bash
su - deploy
ssh-keygen -t ed25519 -N "" -f ~/.ssh/id_ed25519 -C "family-hub-server"
cat ~/.ssh/id_ed25519.pub
```

GitHub → репозиторий → **Settings → Deploy keys → Add deploy key** → вставить ключ (только чтение).

```bash
git clone git@github.com:ВАШ_ЛОГИН/family-hub.git ~/family-hub
cd ~/family-hub
```

## 4. Первый запуск

```bash
./deploy.sh init semya.vigovsky.od.ua nikvigovskij@gmail.com
```

Скрипт сам:
- создаёт `.env` со случайными паролями БД и хранилища, ключом приложения и ключами Web Push;
- собирает образы, запускает всё и применяет миграции;
- спрашивает название семьи, имя, email и пароль первого администратора.

Через минуту откройте `https://semya.vigovsky.od.ua`, войдите и раздайте приглашения в разделе «Семья».

> `.env` на сервере — единственное место с секретами. Он не попадает в Git. Сохраните копию в менеджер паролей.

## 5. Почта для «Забыли пароль?»

В `~/family-hub/.env`:

```env
# Пример для Gmail: в аккаунте Google включите 2FA и создайте «пароль приложения»
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=ваш.адрес@gmail.com
MAIL_PASSWORD=пароль-приложения
MAIL_FROM_ADDRESS=ваш.адрес@gmail.com
MAIL_FROM_NAME="Семья Виговских"
```

и `./deploy.sh` (применит конфигурацию). Без почты сброс работает через ссылку администратора.

## 6. Автодеплой из GitHub Actions

GitHub → репозиторий → **Settings**:

- **Secrets and variables → Actions → Variables**: `DEPLOY_ENABLED` = `true`
- **Secrets**:
  - `DEPLOY_HOST` — IP сервера
  - `DEPLOY_USER` — `deploy`
  - `DEPLOY_PATH` — `/home/deploy/family-hub`
  - `DEPLOY_SSH_KEY` — *приватный* ключ, которым Actions заходит на сервер. Создайте отдельную пару
    на своём компьютере (`ssh-keygen -t ed25519 -f gha_deploy`), публичную половину допишите на сервере
    в `/home/deploy/.ssh/authorized_keys`, приватную — в секрет.
- **Environments → New environment** `production` (можно включить ручное подтверждение деплоя).

После этого каждый push в `main`: сборка → проверка стиля → тесты на SQLite и PostgreSQL → `./deploy.sh` на сервере.

## 7. Дополнительные сервисы (по желанию)

Всё необязательно: без ключей соответствующая функция просто скрыта. После правки `.env` — `./deploy.sh`.

**Telegram-бот** (срочные уведомления в Telegram):
1. В Telegram откройте @BotFather → `/newbot` → получите токен и имя бота.
2. В `.env`: `TELEGRAM_BOT_TOKEN=…`, `TELEGRAM_BOT_USERNAME=имя_бота` (без @),
   `TELEGRAM_WEBHOOK_SECRET=` любая длинная случайная строка (`openssl rand -hex 24`).
3. `./deploy.sh && docker compose exec app php artisan telegram:webhook`.
4. Каждый подключает себя сам: «Настройки» → «Подключить Telegram».

**Воздушная тревога на главной:** запросите бесплатный токен на alerts.in.ua (раздел API) →
`ALERTS_IN_UA_TOKEN=…`. Области выбирает администратор в разделе «Семья».

**Статус рейса:** зарегистрируйтесь на rapidapi.com, подпишитесь на AeroDataBox (бесплатный план) →
`FLIGHT_STATUS_API_KEY=…`.

**Обновления в реальном времени** настраиваются сами (`./deploy.sh init` создаёт ключи Reverb, Caddy
проксирует веб-сокеты на `/app`).

## 8. Восстановление из резервной копии

Восстановление всегда создаёт **новую** семью рядом со старой — ничего не перезаписывается:

```bash
docker compose exec app php artisan family:restore backups/family-1/backup-2026-10-21_033000.zip --name="Виговские"
```

Люди сопоставляются по email; если кого-то нет в базе, аккаунт создаётся заново, и он входит через
«Забыли пароль?» или ссылку от администратора. Архив, скачанный к себе на компьютер, сначала положите
на сервер (`scp`) и укажите путь к файлу.

## 9. Повседневное

```bash
./deploy.sh            # вручную обновить до последней версии из Git
./deploy.sh backup     # резервная копия всех семей прямо сейчас
./deploy.sh logs       # логи приложения
docker compose exec app php artisan family:invite 1 --role=member   # ссылка-приглашение из консоли
```

**Резервные копии** создаются по расписанию (раздел «Семья») и лежат в MinIO на том же сервере.
Раз в месяц скачивайте свежий архив из раздела «Семья» к себе — копия на том же диске не спасёт от
поломки сервера. Дополнительно у многих хостеров есть снапшоты VPS (≈ 20 % к цене) — включите.

**Консоль MinIO** (просмотр файлов) открыта только на сервере: `ssh -L 9001:127.0.0.1:9001 deploy@IP`,
затем `http://localhost:9001`, логин `familyhub`, пароль — `AWS_SECRET_ACCESS_KEY` из `.env`.

**Дополнительный замок** на весь сайт (браузер спросит логин ещё до приложения): задайте
`FAMILYHUB_BASIC_AUTH_USER` и `FAMILYHUB_BASIC_AUTH_PASSWORD` в `.env` и выполните `./deploy.sh`.

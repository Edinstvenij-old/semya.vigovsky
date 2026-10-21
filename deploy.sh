#!/usr/bin/env sh
# Family Hub deploy helper for a Linux server with Docker.
#
#   ./deploy.sh init <domain> <email>   first install: writes .env with fresh secrets,
#                                       starts the stack, migrates, creates the family admin
#   ./deploy.sh                         update: git pull, rebuild, migrate (also used by CI)
#   ./deploy.sh backup                  back up every family now
#   ./deploy.sh logs                    follow application logs
set -eu
cd "$(dirname "$0")"

dc() { docker compose "$@"; }
rand() { openssl rand -hex "${1:-24}"; }

set_env() { # set_env KEY VALUE — replace or append a line in .env
    if grep -q "^$1=" .env; then
        sed -i "s|^$1=.*|$1=$2|" .env
    else
        printf '%s=%s\n' "$1" "$2" >> .env
    fi
}

case "${1:-update}" in
init)
    domain="${2:?usage: ./deploy.sh init <domain> <email>}"
    email="${3:?usage: ./deploy.sh init <domain> <email>}"
    [ -f .env ] && { echo ".env already exists — refusing to overwrite secrets. Remove it first if you really mean it."; exit 1; }

    cp .env.example .env
    set_env APP_ENV production
    set_env APP_DEBUG false
    set_env LOG_LEVEL warning
    set_env APP_URL "https://$domain"
    set_env APP_DOMAIN "$domain"
    set_env ACME_EMAIL "$email"
    set_env APP_KEY "base64:$(openssl rand -base64 32)"
    set_env SESSION_SECURE_COOKIE true
    set_env SANCTUM_STATEFUL_DOMAINS "$domain"
    set_env DB_PASSWORD "$(rand)"
    set_env AWS_ACCESS_KEY_ID "familyhub"
    set_env AWS_SECRET_ACCESS_KEY "$(rand)"
    set_env AWS_BUCKET familyhub
    set_env VAPID_SUBJECT "mailto:$email"
    set_env BROADCAST_CONNECTION reverb
    set_env REVERB_APP_ID "$(od -An -N4 -tu4 /dev/urandom | tr -d ' ')"
    set_env REVERB_APP_KEY "$(rand 16)"
    set_env REVERB_APP_SECRET "$(rand 32)"
    set_env REVERB_PUBLIC_HOST "$domain"
    set_env REVERB_PUBLIC_PORT 443
    set_env REVERB_PUBLIC_SCHEME https
    chmod 600 .env

    dc build
    keys="$(dc run --rm --no-deps -T app php artisan push:vapid)"
    set_env VAPID_PUBLIC_KEY "$(echo "$keys" | sed -n 's/^VAPID_PUBLIC_KEY=//p')"
    set_env VAPID_PRIVATE_KEY "$(echo "$keys" | sed -n 's/^VAPID_PRIVATE_KEY=//p')"

    dc up -d
    dc exec -T app php artisan migrate --force
    dc exec -T app php artisan optimize
    echo
    echo "Создаём семью и первого администратора:"
    dc exec app php artisan family:create
    echo
    echo "Готово: https://$domain"
    echo "Почта для «Забыли пароль?»: заполните MAIL_* в .env и выполните ./deploy.sh"
    ;;
update)
    git pull --ff-only
    dc up -d --build --remove-orphans
    dc exec -T app php artisan migrate --force
    dc exec -T app php artisan optimize
    docker image prune -f >/dev/null
    echo "Updated."
    ;;
backup)
    dc exec -T app php artisan family:backup --all
    ;;
logs)
    dc logs -f --tail=100 app queue scheduler reverb caddy
    ;;
*)
    echo "usage: ./deploy.sh [init <domain> <email> | update | backup | logs]"
    exit 1
    ;;
esac

#!/usr/bin/env bash
# Atualiza o NEXO Chat em produção: baixa o código, reconstrói as imagens,
# aplica as migrations e reinicia os serviços. Pode ser rodado sempre.
set -euo pipefail
cd "$(dirname "$0")/.."

dc() { docker compose -f compose.prod.yaml "$@"; }

echo "==> Baixando código"
git pull --ff-only

echo "==> Construindo imagens"
dc build

echo "==> Banco e Redis"
dc up -d postgres redis

echo "==> Migrations"
dc run --rm app php artisan migrate --force

echo "==> Subindo serviços"
dc up -d --remove-orphans

echo "==> Avisando a fila para recarregar o código"
dc exec -T app php artisan queue:restart || true

docker image prune -f >/dev/null
echo "==> Pronto"
dc ps

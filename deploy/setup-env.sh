#!/usr/bin/env bash
# Cria o .env de produção a partir de .env.production.example, gerando
# APP_KEY, senha do banco e chaves do Reverb. Não sobrescreve um .env existente.
set -euo pipefail
cd "$(dirname "$0")/.."

if [ -f .env ]; then
    echo ".env já existe — nada foi alterado." >&2
    exit 1
fi

rand() { openssl rand -hex "$1"; }

sed -e "s|^APP_KEY=__GERAR__|APP_KEY=base64:$(openssl rand -base64 32)|" \
    -e "s|^DB_PASSWORD=__GERAR__|DB_PASSWORD=$(rand 24)|" \
    -e "s|^REVERB_APP_ID=__GERAR__|REVERB_APP_ID=$(shuf -i 100000-999999 -n 1)|" \
    -e "s|^REVERB_APP_KEY=__GERAR__|REVERB_APP_KEY=$(rand 16)|" \
    -e "s|^REVERB_APP_SECRET=__GERAR__|REVERB_APP_SECRET=$(rand 24)|" \
    .env.production.example > .env
chmod 600 .env
echo ".env criado. Confira APP_URL e VITE_REVERB_HOST antes do primeiro deploy."

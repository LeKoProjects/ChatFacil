# Deploy na AWS — chat.nexoti.com.br

Guia para colocar o NEXO Chat na **mesma EC2 que já roda o GLPI com Apache**, sem mexer no GLPI.

## Como fica

```
Internet ──443──► Apache da EC2 (já existente)
                   ├─ glpi.…              → GLPI (como hoje)
                   └─ chat.nexoti.com.br  → 127.0.0.1:8090 (Docker)
                                              │
                    ┌─────────────── Docker (compose.prod.yaml) ───────────────┐
                    │ web (Nginx) ─┬─ páginas/API ─► app (PHP-FPM / Laravel)   │
                    │              └─ /app (WebSocket) ─► reverb               │
                    │ queue (envia eventos ao Reverb)   postgres   redis       │
                    └───────────────────────────────────────────────────────────┘
```

- O Apache continua sendo o único na porta 80/443 e cuida do HTTPS (Certbot). Ele só encaminha o domínio do chat para `127.0.0.1:8090`.
- Tudo do chat roda em contêineres. Postgres e Redis **não** ficam expostos: só os contêineres se enxergam.
- Nenhuma porta nova precisa ser aberta no Security Group: 80 e 443 já estão abertas para o GLPI.

## 1. Conferir a EC2

Conecte por SSH e veja memória, disco e sistema:

```bash
free -h && df -h / && cat /etc/os-release | head -2 && (apache2 -v 2>/dev/null || httpd -v)
```

- **Memória:** o chat usa cerca de 600–900 MB em uso normal. Com GLPI na mesma máquina, o ideal é 4 GB no total (t3.medium). Com 2 GB funciona para testes, mas crie um swap de 2 GB para não travar o build:

  ```bash
  sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile && sudo mkswap /swapfile && sudo swapon /swapfile
  echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
  ```

- **Disco:** deixe pelo menos 5 GB livres (imagens Docker e banco).
- **IP fixo:** a instância precisa de um **Elastic IP**. Sem ele, o IP muda ao reiniciar e o DNS para de apontar. Confira em EC2 → Elastic IPs.

## 2. Apontar o DNS

No provedor de DNS de `nexoti.com.br`, crie o registro:

| Tipo | Nome | Valor | TTL |
|---|---|---|---|
| A | `chat` | Elastic IP da EC2 (o mesmo do GLPI) | 300 |

Confira (pode levar de minutos a algumas horas):

```bash
nslookup chat.nexoti.com.br
```

## 3. Instalar o Docker

**Ubuntu / Debian**

```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER   # saia e entre de novo no SSH
```

**Amazon Linux 2023**

```bash
sudo dnf install -y docker git
sudo systemctl enable --now docker
sudo usermod -aG docker $USER   # saia e entre de novo no SSH
sudo mkdir -p /usr/local/lib/docker/cli-plugins
sudo curl -SL https://github.com/docker/compose/releases/latest/download/docker-compose-linux-$(uname -m) -o /usr/local/lib/docker/cli-plugins/docker-compose
sudo chmod +x /usr/local/lib/docker/cli-plugins/docker-compose
```

Confira: `docker compose version`.

## 4. Baixar o projeto

```bash
sudo mkdir -p /opt/nexo-chat && sudo chown $USER: /opt/nexo-chat
git clone https://github.com/LeKoProjects/ChatFacil.git /opt/nexo-chat
cd /opt/nexo-chat
```

> Se o repositório for privado, crie uma *deploy key* (GitHub → Settings → Deploy keys) com uma chave SSH gerada na EC2 e clone com `git@github.com:LeKoProjects/ChatFacil.git`.

## 5. Criar o `.env` de produção

```bash
./deploy/setup-env.sh
```

O script copia `.env.production.example` para `.env` e gera `APP_KEY`, a senha do banco e as chaves do Reverb. Confira se estão certos:

- `APP_URL=https://chat.nexoti.com.br`
- `VITE_REVERB_HOST=chat.nexoti.com.br`
- `APP_PORT=8090`. Se a 8090 já estiver em uso no servidor, troque aqui e no vhost do Apache.

O `.env` fica só no servidor (permissão 600) e nunca vai para o Git. **Guarde uma cópia em local seguro**: sem o `APP_KEY`, as sessões e dados criptografados não podem ser lidos.

## 6. Primeiro deploy

```bash
./deploy/deploy.sh
```

Ele constrói as imagens (a primeira vez leva alguns minutos), sobe o banco, roda as migrations e inicia tudo. No fim, todos os serviços devem aparecer como `running`.

Teste localmente na EC2: `curl -I http://127.0.0.1:8090/up` deve responder `200`.

Crie o administrador da NEXO (pede a senha sem mostrar, mínimo 12 caracteres):

```bash
docker compose -f compose.prod.yaml exec app php artisan nexo:admin seu-email@nexoti.com.br --name="Seu Nome"
```

> Não rode `db:seed` em produção. Ele cria usuários de demonstração com a senha `password` e, por isso, se recusa a rodar quando `APP_ENV=production`.

## 7. Configurar o Apache

**Ubuntu / Debian**

```bash
sudo a2enmod proxy proxy_http proxy_wstunnel rewrite headers ssl
sudo cp deploy/apache-chat.nexoti.com.br.conf /etc/apache2/sites-available/chat.nexoti.com.br.conf
sudo a2ensite chat.nexoti.com.br
sudo apache2ctl configtest && sudo systemctl reload apache2
```

**Amazon Linux / RHEL**

```bash
sudo cp deploy/apache-chat.nexoti.com.br.conf /etc/httpd/conf.d/chat.nexoti.com.br.conf
sudo sed -i 's|${APACHE_LOG_DIR}|logs|g' /etc/httpd/conf.d/chat.nexoti.com.br.conf
httpd -M | grep -E 'proxy_module|proxy_http|proxy_wstunnel|rewrite|headers'   # todos devem aparecer
sudo apachectl configtest && sudo systemctl reload httpd
```

O vhost novo só responde a `chat.nexoti.com.br` (`ServerName`), então o GLPI continua igual. Rode `configtest` antes de recarregar: se der erro, o Apache não recarrega e o GLPI não é afetado.

## 8. HTTPS (Let's Encrypt)

Se o GLPI já usa Certbot, ele já está instalado. Senão: `sudo apt install certbot python3-certbot-apache` (Ubuntu) ou `sudo dnf install certbot python3-certbot-apache` (Amazon Linux).

```bash
sudo certbot --apache -d chat.nexoti.com.br
```

Escolha redirecionar HTTP → HTTPS. O Certbot cria o vhost 443 com as mesmas regras de proxy e agenda a renovação automática.

## 9. Testar

1. Abra https://chat.nexoti.com.br e entre com o administrador NEXO.
2. Em **Empresas → Nova empresa**, crie a empresa e o administrador dela.
3. Entre como esse administrador, vá em **Sites**, cadastre o site e os domínios autorizados (com e sem `www`) e copie o script do widget.
4. Cole o script no site (WordPress: veja o README) e abra a central de atendimento em outra aba. O rodapé da central deve mostrar **"Tempo real conectado"**.
5. Mande uma mensagem pelo widget: ela deve aparecer na central na hora, e a resposta deve voltar no widget sem recarregar.

## 10. Backup do banco

```bash
sudo mkdir -p /var/backups/nexo-chat && sudo chown $USER: /var/backups/nexo-chat
./deploy/backup.sh            # teste manual
crontab -e                    # adicione a linha abaixo
0 3 * * * /opt/nexo-chat/deploy/backup.sh >> /var/log/nexo-chat-backup.log 2>&1
```

Guarda 14 dias em `/var/backups/nexo-chat`. Para ter cópia fora da EC2 (recomendado), crie um bucket S3, dê à instância uma IAM Role com permissão de escrita nele e defina `BACKUP_S3=s3://seu-bucket/nexo-chat` na linha do cron.

Restaurar:

```bash
gunzip -c /var/backups/nexo-chat/ARQUIVO.sql.gz | docker compose -f compose.prod.yaml exec -T postgres psql -U nexo -d nexo_chat
```

## 11. Atualizar

Depois de enviar mudanças para o GitHub:

```bash
cd /opt/nexo-chat && ./deploy/deploy.sh
```

## Problemas comuns

| Sintoma | Onde olhar |
|---|---|
| **502/503** no domínio | Os contêineres caíram: `docker compose -f compose.prod.yaml ps` e `... logs --tail=100 app web` |
| Página abre, mas sem **"Tempo real conectado"** | WebSocket bloqueado no Apache: confira o módulo `proxy_wstunnel` e se o vhost **443** (criado pelo Certbot) tem as linhas `RewriteCond %{HTTP:Upgrade}`. Logs: `... logs --tail=100 reverb` |
| Mensagens só aparecem ao recarregar | Fila parada: `... logs --tail=100 queue` |
| **Erro 500** | `docker compose -f compose.prod.yaml exec app tail -n 50 storage/logs/laravel-$(date +%F).log` |
| Widget não aparece no site | Domínio não autorizado (cadastre com e sem `www`) ou plugin de cache do site juntando o `widget.js` |
| Servidor lento, GLPI travando | Memória: `free -h` e `docker stats`. Aumente a instância ou crie swap |

Comandos úteis (rodar em `/opt/nexo-chat`):

```bash
docker compose -f compose.prod.yaml ps                 # situação
docker compose -f compose.prod.yaml logs -f --tail=50  # logs ao vivo
docker compose -f compose.prod.yaml restart            # reiniciar tudo
docker stats --no-stream                               # memória/CPU por contêiner
```

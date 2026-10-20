# Droplet Migration Plan (DOKS → Single Droplet)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrate blastr.pro від DOKS (4 ноди + LB = ~$84/mo overhead) до одного s-2vcpu-4gb дроплету ($24/mo). Managed MySQL + Managed Valkey залишаються без змін. Економія ~$60/mo.

**Architecture:** Single s-2vcpu-4gb FRA1 droplet runs web+workers+scheduler via docker-compose. Connects to existing DO Managed MySQL + Valkey via VPC private endpoints (same VPC default-fra1). CF DNS A-record changes from LB IP `138.68.113.45` → droplet IP.

**Tech Stack:** Docker Compose, FrankenPHP, DO Managed MySQL 8.0.45, DO Managed Valkey 8.1.4, Cloudflare DNS

## Global Constraints

- Region: **FRA1** (mandatory — droplet must be in same VPC as managed DBs for private endpoint access)
- VPC: `default-fra1` (UUID `0582fa37-a24e-456a-8a71-4fa0f32d7038`) — select when creating droplet
- Droplet size: `s-2vcpu-4gb` ($24/mo, Ubuntu 24.04 LTS)
- Managed MySQL private endpoint: `private-blastr-mysql-fra1-do-user-35597821-0.j.db.ondigitalocean.com:25060`
- Managed Valkey private endpoint: `private-blastr-valkey-fra1-do-user-35597821-0.j.db.ondigitalocean.com:25061`
- SSL for MySQL: VERIFY_CA with DO CA cert at `storage/ssl/do-ca.crt`
- No database migration — prod data stays in managed MySQL untouched
- CF proxy (orange cloud) stays ON for DDoS protection + IP masking

---

## Task 1: Update docker-compose.prod.yml for droplet

**Files:**
- Modify: `docker-compose.prod.yml`

**Context:** The current file has `image: blastr-app:latest` (local build tag). On the droplet we pull from the registry. Also need all 3 worker variants (default, sync, ai) since there's no KEDA to orchestrate them. No mysql/redis services — those stay managed.

- [ ] **Step 1: Rewrite docker-compose.prod.yml**

```yaml
# =============================================================================
# Production Docker Compose — single droplet.
#
# Services:
#   app          → FrankenPHP web (ports 80/443)
#   worker       → supervisord: default + compile + discord queues
#   worker-sync  → supervisord: bnet + rio + wcl queues
#   worker-ai    → supervisord: ai queue
#   scheduler    → php artisan schedule:work
#
# MySQL + Redis/Valkey are DO Managed — accessed via VPC private endpoints.
# Droplet must be in VPC default-fra1 (FRA1) to reach private hostnames.
# =============================================================================

name: blastr

x-app-defaults: &app-defaults
  image: registry.digitalocean.com/blastr/app:latest
  restart: unless-stopped
  env_file: /var/www/html/.env
  networks:
    - app_network

services:

  app:
    <<: *app-defaults
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - /var/www/html/nginx/ssl:/etc/frankenphp/ssl:ro
      - /var/www/html/storage:/var/www/html/storage
    healthcheck:
      test: ["CMD-SHELL", "curl -fsS http://localhost/up || exit 1"]
      interval: 30s
      timeout: 5s
      retries: 3
      start_period: 30s

  worker:
    <<: *app-defaults
    command: ["worker"]

  worker-sync:
    <<: *app-defaults
    command: ["worker-sync"]

  worker-ai:
    <<: *app-defaults
    command: ["worker-ai"]

  scheduler:
    <<: *app-defaults
    command: ["scheduler"]

networks:
  app_network:
    driver: bridge
```

- [ ] **Step 2: Commit**

```bash
git add docker-compose.prod.yml
git commit -m "chore(infra): update prod compose for single-droplet deploy (pull from registry, all worker types)"
git push origin main
```

---

## Task 2: Create droplet + add to managed DB trusted sources

**This task requires DO panel access — done by user or via doctl.**

- [ ] **Step 1: Create droplet in DO panel**

Settings:
- **Region**: Frankfurt (FRA1)
- **Image**: Ubuntu 24.04 LTS x64
- **Size**: s-2vcpu-4gb ($24/mo — "Basic, Regular SSD")
- **VPC Network**: `default-fra1` ← critical, must match managed DB VPC
- **Authentication**: SSH key (add existing key)
- **Hostname**: `blastr-prod`

Note the droplet's **IP address** and its **VPC internal IP** (shown in Networking tab, typically `10.114.x.x`).

- [ ] **Step 2: Add droplet to MySQL trusted sources**

DO panel → Databases → `blastr-mysql-fra1` → Settings → Trusted Sources → Add Source → select new droplet `blastr-prod`.

- [ ] **Step 3: Add droplet to Valkey trusted sources**

DO panel → Databases → `blastr-valkey-fra1` → Settings → Trusted Sources → Add Source → select new droplet `blastr-prod`.

- [ ] **Step 4: Verify VPC connectivity from droplet**

SSH into droplet, test managed DB reachability:
```bash
# MySQL
nc -zv private-blastr-mysql-fra1-do-user-35597821-0.j.db.ondigitalocean.com 25060
# Expected: Connection to ... 25060 port [tcp/*] succeeded!

# Valkey
nc -zv private-blastr-valkey-fra1-do-user-35597821-0.j.db.ondigitalocean.com 25061
# Expected: Connection to ... 25061 port [tcp/*] succeeded!
```

If either fails: check Trusted Sources config — it may take 1-2 minutes to apply.

---

## Task 3: Bootstrap droplet

**SSH into the droplet as root.**

- [ ] **Step 1: Install Docker + Compose plugin**

```bash
apt-get update && apt-get install -y ca-certificates curl
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] \
  https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
  > /etc/apt/sources.list.d/docker.list
apt-get update && apt-get install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
```

Verify: `docker --version` → `Docker version 27+`

- [ ] **Step 2: Authenticate to DO Container Registry**

```bash
export DO_TOKEN="<value from keys/do_token in repo>"
echo "$DO_TOKEN" | docker login registry.digitalocean.com --username ignored --password-stdin
```

Expected: `Login Succeeded`

- [ ] **Step 3: Clone repo**

```bash
mkdir -p /var/www/html
git clone https://github.com/<org>/blastr.git /var/www/html
cd /var/www/html
git checkout main
```

- [ ] **Step 4: Copy .env from k8s pod**

On LOCAL machine (has kubectl):
```bash
kubectl exec -n blastr deploy/blastr-web -c web -- env \
  | grep -vE '^(HOME|PATH|HOSTNAME|PWD|SHLVL|_)=' \
  | sort > /tmp/prod.env
```

Transfer to droplet:
```bash
scp /tmp/prod.env root@<DROPLET_IP>:/var/www/html/.env
```

On droplet — update APP_URL to production domain:
```bash
sed -i "s|^APP_URL=.*|APP_URL=https://blastr.pro|" /var/www/html/.env
```

- [ ] **Step 5: Restore DO CA cert for MySQL SSL**

The cert is at `storage/ssl/do-ca.crt` in the repo (gitignored but present in k8s secret). Copy from pod:

```bash
# On LOCAL machine
kubectl exec -n blastr deploy/blastr-web -c web -- cat /var/www/html/storage/ssl/do-ca.crt \
  > /tmp/do-ca.crt

# Transfer to droplet
scp /tmp/do-ca.crt root@<DROPLET_IP>:/var/www/html/storage/ssl/do-ca.crt
```

- [ ] **Step 6: Copy SSL wildcard cert (for HTTPS)**

The CF Origin wildcard cert. Copy from k8s secret or existing backup:

```bash
# On LOCAL machine — extract from k8s tls secret
kubectl get secret -n blastr blastr-tls -o jsonpath='{.data.tls\.crt}' | base64 -d > /tmp/blastr-tls.crt
kubectl get secret -n blastr blastr-tls -o jsonpath='{.data.tls\.key}' | base64 -d > /tmp/blastr-tls.key

# Transfer to droplet
scp /tmp/blastr-tls.crt root@<DROPLET_IP>:/var/www/html/nginx/ssl/blastr.pro.crt
scp /tmp/blastr-tls.key root@<DROPLET_IP>:/var/www/html/nginx/ssl/blastr.pro.key
```

Check that the Caddyfile references the right cert filenames:
```bash
grep -i ssl /var/www/html/docker/app/Caddyfile
```

Adjust filenames in `nginx/ssl/` to match what Caddyfile expects.

- [ ] **Step 7: Pull image and start stack**

```bash
cd /var/www/html
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d
```

- [ ] **Step 8: Run pending migrations**

```bash
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
```

Expected: `Nothing to migrate` (all migrations already ran on managed DB).

---

## Task 4: Smoke test before DNS cutover

- [ ] **Step 1: Test HTTP locally on droplet**

```bash
curl -sk https://localhost/up
```

Expected: `{"status":"ok"}` or `1`

- [ ] **Step 2: Test via direct IP (bypass CF)**

From local machine:
```bash
curl -sk https://<DROPLET_IP>/up -H "Host: blastr.pro"
```

Expected: 200 response. If SSL cert error — that's expected for IP access with domain cert; check with `--insecure`.

- [ ] **Step 3: Check all containers running**

```bash
docker compose -f docker-compose.prod.yml ps
```

Expected: app/worker/worker-sync/worker-ai/scheduler all `Up` or `(healthy)`.

- [ ] **Step 4: Check DB connectivity**

```bash
docker compose -f docker-compose.prod.yml exec app php artisan db:show
```

Expected: shows managed MySQL connection info, no errors.

- [ ] **Step 5: Check Redis/Valkey connectivity**

```bash
docker compose -f docker-compose.prod.yml exec app php artisan tinker --execute="echo Cache::get('test') ?? 'ok';"
```

Expected: `ok` (no exceptions).

---

## Task 5: DNS cutover

- [ ] **Step 1: Lower CF TTL to 1 minute**

CF panel → DNS → `blastr.pro` A-record → Edit → TTL: 1 minute. Save. Wait 5 minutes.

- [ ] **Step 2: Switch A-record to droplet IP**

Change `blastr.pro` A-record from `138.68.113.45` → `<DROPLET_IP>`.

Keep **orange cloud (proxy) ON**.

- [ ] **Step 3: Verify propagation**

```bash
# Within ~1 minute CF routes new requests to new droplet
curl -s https://blastr.pro/up
```

Expected: `{"status":"ok"}` or `1`.

- [ ] **Step 4: Full browser test**

Open `https://blastr.pro` → login → dashboard → check no errors in console.

- [ ] **Step 5: Monitor logs for 10 minutes**

```bash
ssh root@<DROPLET_IP> "docker compose -f /var/www/html/docker-compose.prod.yml logs -f --tail=50"
```

Watch for PHP errors, DB connection errors, queue failures.

---

## Task 6: Decommission DOKS (after 24h stable)

**Only after confirming 24h of stable production on the droplet.**

- [ ] **Step 1: Delete DOKS cluster**

```bash
export DO_TOKEN=$(cat /var/www/html/keys/do_token)
DIGITALOCEAN_ACCESS_TOKEN="$DO_TOKEN" ~/.local/bin/doctl kubernetes cluster delete blastr-prod --dangerous
```

Deletes: 4 node pools ($72/mo) + DO LB ($12/mo). Confirm when prompted.

- [ ] **Step 2: Verify LB deleted**

```bash
DIGITALOCEAN_ACCESS_TOKEN="$DO_TOKEN" ~/.local/bin/doctl compute load-balancer list
```

Expected: empty list.

- [ ] **Step 3: Remove DOKS from managed DB trusted sources**

DO panel → Databases → `blastr-mysql-fra1` → Settings → Trusted Sources → remove the DOKS cluster entry (keep new droplet).

Same for `blastr-valkey-fra1`.

- [ ] **Step 4: Downgrade Container Registry to Starter (optional)**

If the latest image fits under 500MB: DO panel → Container Registry → Change Plan → Starter ($0). Otherwise keep Basic ($5).

---

## Task 7: Droplet deploy workflow

**Context:** Old deploy was `helm upgrade` to DOKS. New deploy: build image locally → push to registry → SSH pull+restart on droplet.

- [ ] **Step 1: Create deploy script**

Create `deploy/droplet-deploy.sh`:

```bash
#!/bin/bash
# Usage: DROPLET_HOST=root@<IP> ./deploy/droplet-deploy.sh v1.2.3
set -e

TAG="${1:?Usage: $0 <image-tag>}"
DROPLET_HOST="${DROPLET_HOST:?Set DROPLET_HOST=root@<IP>}"
REGISTRY="registry.digitalocean.com/blastr/app"
COMPOSE="/var/www/html/docker-compose.prod.yml"

echo "→ Deploying $REGISTRY:$TAG to $DROPLET_HOST"

ssh "$DROPLET_HOST" bash -s << EOF
set -e
cd /var/www/html
git pull origin main
# Update image tag in compose file
sed -i "s|image: $REGISTRY:.*|image: $REGISTRY:$TAG|" $COMPOSE
docker compose -f $COMPOSE pull app worker worker-sync worker-ai scheduler
docker compose -f $COMPOSE up -d --no-deps app worker worker-sync worker-ai scheduler
docker compose -f $COMPOSE exec -T app php artisan migrate --force
docker compose -f $COMPOSE ps
echo "✓ Deploy $TAG done"
EOF
```

```bash
chmod +x deploy/droplet-deploy.sh
git add deploy/droplet-deploy.sh
git commit -m "chore(infra): add single-droplet deploy script"
git push origin main
```

---

## Expected final costs

| Item | $/mo |
|---|---|
| Droplet s-2vcpu-4gb (FRA1) | $24 |
| DO Managed MySQL | $21 |
| DO Managed Valkey | $15 |
| Container Registry | $5 |
| DO Spaces | $5 |
| **Total** | **$70** |

vs previous ~$130/mo. Saving **~$60/mo**.

# Phase 6 — Service droplet (GlitchTip + Loki + Grafana)

Goal: окремий $24 дроплет з обсервабіліті стеком який живе незалежно
від main k8s кластеру. Збирає помилки з blastr-pods (через GlitchTip)
і логи (через Loki + Promtail).

> **Робити після Phase 5 cutover.** Поки k8s не working — Sentry хмарний
> покриває моніторинг, GlitchTip непотрібний.

## Prerequisites

- Phase 5 завершено (DOKS обслуговує prod, blastr.pro → DO LB)
- Старий $24 дроплет ще живий (insurance, ще не вимикали)
- CF Origin wildcard cert файли .pem (з project_cf_wildcard_cert.md в memory)

## Step 1 — Create NEW service droplet ($24)

```bash
# 1vcpu/1gb достатньо для обсервабіліті стеку (Grafana + GlitchTip + Loki)
doctl compute droplet create blastr-services \
  --region fra1 \
  --image debian-12-x64 \
  --size s-1vcpu-1gb \
  --vpc-uuid "$VPC_UUID" \
  --ssh-keys $(doctl compute ssh-key list --format ID --no-header | tr '\n' ',' | sed 's/,$//') \
  --wait

# Запам'ятай public + private IPs
doctl compute droplet list blastr-services
export SERVICE_DROPLET_PUBLIC_IP=<...>
export SERVICE_DROPLET_VPC_IP=<10.x.x.x>
```

## Step 2 — Attach Block Storage volumes ($10/міс — 2×50GB)

```bash
# Postgres data для GlitchTip
doctl compute volume create glitchtip-pg \
  --region fra1 --size 50GiB --fs-type ext4
doctl compute volume-action attach glitchtip-pg <service-droplet-id>

# Loki chunks + index
doctl compute volume create loki-data \
  --region fra1 --size 50GiB --fs-type ext4
doctl compute volume-action attach loki-data <service-droplet-id>
```

## Step 3 — Provision droplet

SSH в дроплет:

```bash
ssh root@$SERVICE_DROPLET_PUBLIC_IP

# Mount volumes (повторити після кожного reboot — або додати в /etc/fstab)
mkdir -p /mnt/glitchtip-pg /mnt/loki
mount -o discard,defaults,noatime /dev/disk/by-id/scsi-0DO_Volume_glitchtip-pg /mnt/glitchtip-pg
mount -o discard,defaults,noatime /dev/disk/by-id/scsi-0DO_Volume_loki-data /mnt/loki

# Permanent mount у /etc/fstab
cat >> /etc/fstab <<EOF
/dev/disk/by-id/scsi-0DO_Volume_glitchtip-pg /mnt/glitchtip-pg ext4 discard,defaults,noatime 0 2
/dev/disk/by-id/scsi-0DO_Volume_loki-data /mnt/loki ext4 discard,defaults,noatime 0 2
EOF

# Postgres own data dir
chown -R 999:999 /mnt/glitchtip-pg   # docker postgres user ID

# Docker
apt update && apt install -y docker.io docker-compose-v2
systemctl enable --now docker

# CF Origin cert
mkdir -p /etc/ssl/cloudflare
# scp файли з твого ноута (взяти з project_cf_wildcard_cert.md в memory):
#   scp /path/to/cloudflare-origin.fullchain.pem root@$SERVICE_DROPLET_PUBLIC_IP:/etc/ssl/cloudflare/
#   scp /path/to/cloudflare-origin.privkey.pem root@$SERVICE_DROPLET_PUBLIC_IP:/etc/ssl/cloudflare/
chmod 600 /etc/ssl/cloudflare/*.pem
```

## Step 4 — Deploy stack

З локального ноута:

```bash
# Скопіюй конфіги на дроплет
scp -r deploy/services root@$SERVICE_DROPLET_PUBLIC_IP:/opt/blastr-services

# SSH і запусти
ssh root@$SERVICE_DROPLET_PUBLIC_IP
cd /opt/blastr-services

# Скопіюй .env.example у .env і заповни
cp .env.example .env
# Згенеруй POSTGRES_PASSWORD, GLITCHTIP_SECRET_KEY, GRAFANA_ADMIN_PASSWORD:
echo "POSTGRES_PASSWORD=$(openssl rand -hex 24)" >> .env
echo "GLITCHTIP_SECRET_KEY=$(openssl rand -hex 64)" >> .env
echo "GRAFANA_ADMIN_PASSWORD=$(openssl rand -hex 12)" >> .env
# Збережи паролі у Bitwarden одразу.

# VPC interface IP — для Loki binding
ip -4 addr show eth1 | grep -oP '(?<=inet\s)\d+(\.\d+){3}'
echo "VPC_INTERFACE_IP=<paste-вище>" >> .env

# Підняти стек
docker compose up -d
docker compose ps   # всі повинні бути healthy
```

## Step 5 — Configure DNS

CF панель → DNS → нова A-record:
- Type: A
- Name: obs
- Content: `$SERVICE_DROPLET_PUBLIC_IP`
- Proxy status: Proxied (orange cloud)
- TTL: Auto

Перевір з браузера:
- https://obs.blastr.pro → GlitchTip welcome
- https://obs.blastr.pro/grafana/ → Grafana login

## Step 6 — Initial GlitchTip setup

1. Відкрий https://obs.blastr.pro
2. Зареєструй adminа (перший юзер автоматично admin):
   - Email: arty.zavrik@gmail.com
   - Password: <save в Bitwarden>
3. Створи Organization: `blastr`
4. Створи Project: `blastr-laravel` (platform: Python — Laravel використовує Sentry SDK ок з Python platform для подібності)
5. Скопіюй DSN з Project Settings → Client Keys (DSN)
   Формат: `https://<key>@obs.blastr.pro/<project-id>`

## Step 7 — Point k8s pods at GlitchTip

```bash
# Локально на ноуті — оновити sealed Secret з новим SENTRY_LARAVEL_DSN
# 1. Відредагуй ~/.env.blastr-prod і встав GlitchTip DSN
nano ~/.env.blastr-prod

# 2. Re-seal
kubectl create secret generic blastr-env \
  --namespace blastr \
  --from-env-file ~/.env.blastr-prod \
  --dry-run=client -o yaml \
  | kubeseal \
      --cert ~/blastr-cluster-pubkey.pem \
      --format yaml \
      > deploy/helm/blastr/sealed/blastr-env.sealed.yaml

# 3. Apply
kubectl apply -f deploy/helm/blastr/sealed/blastr-env.sealed.yaml

# 4. Force pod restart щоб підхопити новий Secret
kubectl rollout restart -n blastr deployment/blastr-web
kubectl rollout restart -n blastr deployment/blastr-worker-default
kubectl rollout restart -n blastr deployment/blastr-worker-sync
# (Pods отримають Secret з cluster — він декодований з sealed-secrets controller)

# 5. Спровокуй тестову помилку
kubectl run sentry-test --rm -i --restart=Never --quiet \
  --image=curlimages/curl:8.10.1 \
  --namespace=blastr \
  --command -- curl -s http://blastr-web/non-existent-route-please-404

# 6. Перевір що подія прийшла у GlitchTip UI
```

## Step 8 — Install Promtail у DOKS

```bash
# Заміни <SERVICE_DROPLET_VPC_IP> у promtail-daemonset.yaml на твій VPC IP
sed -i "s|<SERVICE_DROPLET_VPC_IP>|$SERVICE_DROPLET_VPC_IP|g" deploy/services/promtail-daemonset.yaml

kubectl apply -f deploy/services/promtail-daemonset.yaml

# Перевір що пускає
kubectl get pods -n observability
kubectl logs -n observability -l app=promtail --tail=20

# Має бути "scrape" повідомлення без errors
```

## Step 9 — Add Loki to Grafana + first dashboard

1. https://obs.blastr.pro/grafana/ → Login (admin / $GRAFANA_ADMIN_PASSWORD)
2. Data Sources → Loki вже preconfigured (через grafana-datasources.yml)
3. Explore → Loki:
   - Query: `{namespace="blastr"}`
   - Time: Last 15 min
   - Має показати свіжі логи всіх pods
4. Тестовий dashboard:
   - Logs by component: `{namespace="blastr"} | json | __error__=""`
   - Group by `component` label

## Verify end-to-end

```bash
# Спровокуй помилку у Laravel → має з'явитися:
# 1. У kubectl logs (web pod stderr)
# 2. У GlitchTip (Issues tab)
# 3. У Grafana Loki (filter component=web, level=ERROR)

# Спровокуй sync статіку → має з'явитися:
# 1. У scheduler логах "Dispatched bnet sync..."
# 2. У worker-sync логах (через Loki/component=worker-sync)
# 3. Жодних помилок у GlitchTip
```

## Maintenance

- **Backup Postgres volume** раз на тиждень через DO snapshot:
  ```bash
  doctl compute volume snapshot create glitchtip-pg --snapshot-name "glitchtip-pg-$(date +%Y%m%d)"
  ```
- **Loki volume забивається?** Manually очисти старі chunks:
  ```bash
  ssh root@$SERVICE_DROPLET_PUBLIC_IP "df -h /mnt/loki; du -sh /mnt/loki/chunks/* | sort -h | tail -20"
  ```
- **GlitchTip update**:
  ```bash
  ssh root@$SERVICE_DROPLET_PUBLIC_IP
  cd /opt/blastr-services
  docker compose pull
  docker compose up -d
  ```

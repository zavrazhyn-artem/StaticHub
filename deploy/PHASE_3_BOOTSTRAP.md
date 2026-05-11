# Phase 3 — DOKS Cluster Bootstrap

Runbook for the first-time bring-up of the blastr-prod DOKS cluster + cluster
operators + sealed secrets + first `helm install`. Run from your local machine.

> **Старий $24 дроплет продовжує жити весь Phase 3.** Він — наша страховка
> до Phase 5 (cutover). Нічого з нього не вимикаємо.

## Prerequisites

Install once on your laptop:

```bash
# Linux/WSL
curl -sL https://github.com/digitalocean/doctl/releases/download/v1.117.0/doctl-1.117.0-linux-amd64.tar.gz | tar -xzv -C /tmp
sudo mv /tmp/doctl /usr/local/bin/

curl -fsSL https://get.helm.sh/helm-v3.16.0-linux-amd64.tar.gz | tar -xz -C /tmp
sudo mv /tmp/linux-amd64/helm /usr/local/bin/

curl -fsSL https://github.com/bitnami-labs/sealed-secrets/releases/download/v0.27.1/kubeseal-0.27.1-linux-amd64.tar.gz | tar -xz -C /tmp
sudo mv /tmp/kubeseal /usr/local/bin/

# kubectl зазвичай встановлюється разом з Docker Desktop. Якщо немає:
curl -LO "https://dl.k8s.io/release/$(curl -L -s https://dl.k8s.io/release/stable.txt)/bin/linux/amd64/kubectl"
sudo install -o root -g root -m 0755 kubectl /usr/local/bin/kubectl

# Перевірка
doctl version
helm version
kubeseal --version
kubectl version --client
```

## Step 1 — DO authentication

```bash
# Створи API token: https://cloud.digitalocean.com/account/api/tokens (Read+Write scope)
doctl auth init
# вставити token коли запитає
doctl account get   # перевірка авторизації
```

## Step 2 — Discover Managed DB VPC

К8с має жити в **тому самому VPC** що Managed MySQL + Valkey, інакше app
не достукається до них.

```bash
doctl databases list
# знайди blastr MySQL — колонка "VPC UUID" або "Private Network UUID"
# скопіюй UUID, ми використаємо його при створенні кластера

# Або через VPC list:
doctl vpcs list
# візьми UUID того VPC що в регіоні FRA1
```

Запиши UUID:
```bash
export VPC_UUID="<paste-uuid-here>"
echo $VPC_UUID
```

## Step 3 — Create DOKS cluster (⚠️ PAID, ~$72/міс idle)

```bash
doctl kubernetes cluster create blastr-prod \
  --region fra1 \
  --version 1.31.1-do.0 \
  --node-pool "name=web;size=s-2vcpu-2gb;count=1;auto-scale=true;min-nodes=1;max-nodes=3;label=pool=web" \
  --node-pool "name=core;size=s-2vcpu-2gb;count=1;label=pool=core" \
  --node-pool "name=sync;size=s-2vcpu-2gb;count=1;auto-scale=true;min-nodes=1;max-nodes=2;label=pool=sync" \
  --node-pool "name=ai;size=s-2vcpu-2gb;count=0;auto-scale=true;min-nodes=0;max-nodes=2;label=pool=ai" \
  --vpc-uuid "$VPC_UUID" \
  --wait
```

> Чекає ~5-7 хвилин. `--wait` блокує до ready.
>
> **Біллінг стартує одразу** після цього. 3 завжди-живі ноди = ~$54/міс +
> webp$18 (один із core/sync/web може бути зайвим в idle, autoscaler
> прибере). Реальний idle ~$54.

```bash
# Kubeconfig автоматично додається до ~/.kube/config як контекст do-fra1-blastr-prod
kubectl config current-context
kubectl get nodes -o wide   # повинно бути 3 ноди (ai pool count=0)
```

## Step 4 — Create Container Registry (⚠️ PAID, $5/міс)

```bash
doctl registry create blastr --subscription-tier starter --region fra1

# Bind registry credentials to the cluster so pods можуть pull без auth
doctl registry kubernetes-manifest | kubectl apply -f -

# Це створює Secret `registry-blastr` у default namespace. Ми посилаємось
# на нього як на blastr-registry-creds через values.yaml.
# Можна перейменувати або скопіювати в потрібний namespace після `helm install`.
```

## Step 5 — Install cluster operators

```bash
# 1) ingress-nginx — створить DO Managed LB ($12/міс)
helm repo add ingress-nginx https://kubernetes.github.io/ingress-nginx
helm repo update
helm install ingress-nginx ingress-nginx/ingress-nginx \
  -n ingress-nginx --create-namespace \
  --set controller.service.annotations."service\.beta\.kubernetes\.io/do-loadbalancer-protocol"=tcp \
  --set controller.config.use-forwarded-headers=true \
  --wait

# Перевір що DO LB отримав публічний IP (потрібен кілька хвилин)
kubectl get svc -n ingress-nginx ingress-nginx-controller -w
# Чекаємо поки EXTERNAL-IP стане не <pending>. Запиши цей IP.
export LB_IP="<paste-LB-IP>"
echo $LB_IP

# 2) sealed-secrets controller
helm repo add sealed-secrets https://bitnami-labs.github.io/sealed-secrets
helm install sealed-secrets sealed-secrets/sealed-secrets \
  -n kube-system \
  --wait

# Експортуй публічний ключ кластера — потрібен для seal-операцій локально
kubeseal --fetch-cert \
  --controller-namespace kube-system \
  --controller-name sealed-secrets > ~/blastr-cluster-pubkey.pem

# 3) KEDA — autoscaling на queue depth
helm repo add kedacore https://kedacore.github.io/charts
helm install keda kedacore/keda \
  -n keda --create-namespace \
  --wait

# Sanity check — всі контролери Running
kubectl get pods -A | grep -E 'ingress-nginx|sealed-secrets|keda'
```

## Step 6 — Build + push first image

З репи (`/var/www/html` на твоєму ноуті):

```bash
# Логін у DO registry — токен на 24h
doctl registry login --expiry-seconds 86400

# Білд + push (image multistage, ~5-8 хв)
cd /path/to/StaticHub   # WSL шлях до твоєї робочої папки
docker build -t registry.digitalocean.com/blastr/app:v0.1.0 -f docker/app/Dockerfile --target app .
docker push registry.digitalocean.com/blastr/app:v0.1.0

# Перевір що image у registry
doctl registry repository list-tags app
```

## Step 7 — Prepare .env.production locally

> **НЕ комітити цей файл.** Він живе тільки на твоєму ноуті — джерело
> правди для kubeseal.

Створи `~/.env.blastr-prod` (поза репою) з усіма 30 sensitive ключами.
Список ключів у `deploy/helm/blastr/values.yaml` під `secret.keys`. Зразок:

```bash
cat > ~/.env.blastr-prod <<'EOF'
APP_KEY=base64:......
DB_HOST=private-blastr-mysql-do-user-XXXXX-0.fra.private.db.ondigitalocean.com
DB_PORT=25060
DB_DATABASE=defaultdb
DB_USERNAME=doadmin
DB_PASSWORD=...
REDIS_HOST=private-blastr-valkey-do-user-XXXXX-0.fra.private.db.ondigitalocean.com
REDIS_PORT=25061
REDIS_PASSWORD=...
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
BATTLE_NET_CLIENT_ID=...
BATTLE_NET_CLIENT_SECRET=...
DISCORD_PUBLIC_KEY=...
DISCORD_BOT_TOKEN=...
DISCORD_CLIENT_ID=...
DISCORD_CLIENT_SECRET=...
DISCORD_WEBHOOK_URL=...
WCL_CLIENT_ID=...
WCL_CLIENT_SECRET=...
GEMINI_API_KEY=...
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_HOST=...
MAIL_PORT=587
ADMIN_ACCESS_KEY=...
ADMIN_DOMAIN=admin.blastr.pro
GHOST_USER_ID=1
SENTRY_LARAVEL_DSN=https://...@sentry.io/...
VITE_SENTRY_DSN=https://...@sentry.io/...
EOF

chmod 600 ~/.env.blastr-prod
```

> **DB_HOST/REDIS_HOST повинні бути _private_ ендпоінти** (бо ми в одному VPC).
> Бери їх з `doctl databases connection` або з DO Control Panel.

## Step 8 — Seal env Secret

```bash
mkdir -p /path/to/StaticHub/deploy/helm/blastr/sealed

# Створи Secret manifest у пам'яті + одразу seal
kubectl create secret generic blastr-env \
  --namespace blastr \
  --from-env-file ~/.env.blastr-prod \
  --dry-run=client -o yaml \
  | kubeseal \
      --cert ~/blastr-cluster-pubkey.pem \
      --format yaml \
      > /path/to/StaticHub/deploy/helm/blastr/sealed/blastr-env.sealed.yaml

# Перевір що файл зашифрований
head -20 /path/to/StaticHub/deploy/helm/blastr/sealed/blastr-env.sealed.yaml
# повинно бути type: SealedSecret + encryptedData з base64-блоками
```

## Step 9 — Seal TLS Secret (CF wildcard cert)

```bash
# Знайди шляхи до CF Origin cert + key (project_cf_wildcard_cert.md в memory)
# Якщо вони на старому droplet — scp їх сюди.
export CF_CERT=/path/to/cloudflare-origin.fullchain.pem
export CF_KEY=/path/to/cloudflare-origin.privkey.pem

kubectl create secret tls blastr-tls \
  --namespace blastr \
  --cert "$CF_CERT" \
  --key "$CF_KEY" \
  --dry-run=client -o yaml \
  | kubeseal \
      --cert ~/blastr-cluster-pubkey.pem \
      --format yaml \
      > /path/to/StaticHub/deploy/helm/blastr/sealed/blastr-tls.sealed.yaml

# Перевір що зашифрований
head -20 /path/to/StaticHub/deploy/helm/blastr/sealed/blastr-tls.sealed.yaml
```

## Step 10 — Apply sealed secrets

```bash
# Створи namespace blastr
kubectl create namespace blastr

# Перенеси registry creds у blastr namespace
kubectl get secret registry-blastr -n default -o yaml \
  | sed 's/namespace: default/namespace: blastr/' \
  | kubectl apply -f -

# Перейменуй щоб збігалося з values.yaml (image.pullSecretName: blastr-registry-creds)
kubectl get secret registry-blastr -n blastr -o yaml \
  | sed 's/name: registry-blastr/name: blastr-registry-creds/' \
  | kubectl apply -f -

# Застосуй sealed secrets
kubectl apply -f /path/to/StaticHub/deploy/helm/blastr/sealed/

# Перевір що sealed-secrets controller розшифрував у звичайні Secrets
kubectl get secrets -n blastr
# повинні бути: blastr-env, blastr-tls, blastr-registry-creds
```

## Step 11 — Helm install

```bash
cd /path/to/StaticHub

helm install blastr ./deploy/helm/blastr \
  --namespace blastr \
  --set image.tag=v0.1.0 \
  --wait \
  --timeout 10m
```

> `--wait` блокує до того поки всі Deployments/StatefulSets матимуть
> готовий стан. Якщо щось не запустилось — `--wait` таймаутне і покаже
> який саме pod проблемний.

```bash
# Що відбулося:
kubectl get all -n blastr
kubectl get pods -n blastr -o wide   # подивитися на яких нодах
kubectl describe job -n blastr blastr-migrate-1   # pre-upgrade migrate
```

## Step 12 — Verify before DNS swap

```bash
# Запит на blastr.pro через --resolve підставляє LB IP замість CF anycast
curl -kv --resolve blastr.pro:443:$LB_IP https://blastr.pro/healthz
# повинно повернути 200 {"ok":true,"checks":{"db":true,"redis":true}}

curl -k --resolve blastr.pro:443:$LB_IP https://blastr.pro/up
# OK

# Перевір що migrate Job завершився ОК
kubectl logs -n blastr -l app.kubernetes.io/component=migrate

# Web pod логи
kubectl logs -n blastr -l app.kubernetes.io/component=web --tail=50

# Scheduler — повинен бачити `Schedule running` повідомлення
kubectl logs -n blastr -l app.kubernetes.io/component=scheduler --tail=20

# Workers
kubectl logs -n blastr -l app.kubernetes.io/component=worker-default --tail=20
kubectl logs -n blastr -l app.kubernetes.io/component=worker-sync --tail=20
# AI worker запускається лише якщо queue має jobs (KEDA scale-to-zero)
```

## What's next

Якщо все зелене:

- **Phase 4** — GitHub Actions: `.github/workflows/deploy.yml` що автоматизує
  push tag → docker build → push → `helm upgrade`
- **Phase 5** — Cutover: swap CF DNS A-record blastr.pro з IP старого droplet
  на `$LB_IP` (instant через orange cloud). Старий droplet залишається 1
  тиждень як rollback.
- **Phase 6** — створення нового сервісного дроплета з GlitchTip + Loki
  (відбувається **після** Phase 5, бо k8s вже worsk)

## Rollback / cleanup (якщо щось пішло не так)

```bash
# Видалити helm release але залишити namespace
helm uninstall blastr -n blastr

# Видалити namespace зі всім вмістом
kubectl delete namespace blastr

# Видалити cluster повністю (⚠️ зупинить біллінг)
doctl kubernetes cluster delete blastr-prod

# Видалити registry (⚠️ всі images зникнуть)
doctl registry delete blastr
```

DO Managed MySQL + Valkey НЕ чіпаємо — вони і так використовуються старим
дроплетом і ми не хочемо втратити дані.

## Save outputs in memory after Phase 3 success

Запиши в memory:
- LB IP (з step 5)
- Cluster UUID (з `doctl kubernetes cluster get blastr-prod`)
- Дату першого успішного `helm install`

# Phase 5 — Production Cutover (CF DNS swap)

Goal: traffic переходить зі старого $24 дроплета на DOKS LB. Старий
дроплет залишається 1 тиждень як rollback insurance — НЕ вимикаємо!

> Pre-req: Phase 3 успішно завершено, k8s pods зелені, `curl --resolve`
> на `$LB_IP` повертає 200.

## Зробити НЕ під години пік

- Кращий час: будній день, ~14:00-16:00 за Києвом (мало хто грає).
- Гірший час: вечір середи/четверга (рейди).
- Уникай: weekly:reset вікон (середа 04:01 EU UTC, вівторок 15:01 US UTC).

## Pre-flight (за день до)

1. **Перевір що migrate Job працює** — зроби manual `helm upgrade` з
   тим самим тегом щоб переконатися що pre-upgrade hook firing'ить
   `php artisan migrate --force` без помилок:

   ```bash
   helm upgrade blastr ./deploy/helm/blastr \
     --namespace blastr \
     --reuse-values \
     --atomic --timeout 5m
   kubectl logs -n blastr -l app.kubernetes.io/component=migrate --tail=50
   ```

2. **Перевір що Sentry/GlitchTip (поки що Sentry, GlitchTip буде Phase 6)
   приймає помилки з k8s pods** — спровокуй тестову помилку у /up чи
   подивися чи Sentry пише з нових IP-адрес.

3. **Знизи CF DNS TTL для `blastr.pro` A-record до 60s** (через CF
   панель) — не обов'язково для orange-cloud, але дозволяє швидко
   rollback'ати без ще одного TTL очікування. Робиш за день.

4. **Knock the rust off rollback procedure** — без жартів, прочитай
   секцію Rollback нижче ДО того як зробив swap, не після.

## Cutover

### Step 1 — Final sanity (Т-5 хв)

```bash
# Перевір ще раз що все живе
kubectl get pods -n blastr
kubectl get nodes -o wide

# /healthz з зовні (через --resolve)
curl --resolve blastr.pro:443:$LB_IP https://blastr.pro/healthz
# {"ok":true,"checks":{"db":true,"redis":true}}

# Перевір що queue:work воркери активні
kubectl logs -n blastr -l app.kubernetes.io/component=worker-default --tail=10
```

### Step 2 — Swap CF DNS (Т=0)

В Cloudflare панелі → DNS → blastr.pro:

1. Знайди A-record для `blastr.pro` (root domain).
2. **Запиши старий IP** (старого дроплета) у нотатник — потрібен для rollback.
3. Зміни Content на `$LB_IP` (DO LB IP).
4. Proxy status = Proxied (orange cloud), TTL = Auto.
5. Save.

Те саме для `admin.blastr.pro` A-record.

> CF orange cloud означає що browser резолвить blastr.pro на CF anycast IPs.
> CF потім роутить на origin IP який ми щойно змінили. **Зміна mить — secs,
> не minutes.** Жодного DNS propagation очікування для зовнішніх клієнтів.

### Step 3 — Watch (Т+1 хв до Т+30 хв)

Відкрий 4 термінали і дивись паралельно:

```bash
# T1: web pod logs
kubectl logs -n blastr -l app.kubernetes.io/component=web --tail=20 -f

# T2: worker logs (всі типи)
kubectl logs -n blastr -l app.kubernetes.io/component=worker-default --tail=10 -f &
kubectl logs -n blastr -l app.kubernetes.io/component=worker-sync --tail=10 -f &

# T3: Sentry events (CF dashboard відкрий також)
# https://sentry.io/organizations/blastr/issues/

# T4: pod health
watch -n 5 'kubectl get pods -n blastr -o wide; echo; kubectl top pods -n blastr'
```

Що дивитися:
- ✅ Web pod CPU зростає (трафік пішов).
- ✅ Discord interactions у worker-discord логах (юзери рейди вкидають RSVP).
- ✅ Bnet/Rio sync працює — `Dispatched bnet sync for X character(s)` у scheduler логах кожну хв.
- ❌ Sentry — нові помилки з тегом `runtime.k8s_pod`? Якщо так — швидко
  rollback (Step Rollback).
- ❌ 5xx на ingress-nginx? `kubectl logs -n ingress-nginx -l app.kubernetes.io/name=ingress-nginx`

### Step 4 — Verify business flows (Т+30 хв)

З браузера (як юзер):

- [ ] https://blastr.pro/dashboard відкривається після Bnet OAuth
- [ ] Створення raid event працює
- [ ] Discord RSVP → message в каналі з reaction emoji
- [ ] /gear шторінка показує characters
- [ ] /logs показує WCL аналізи
- [ ] /loot шторінка
- [ ] Admin: https://admin.blastr.pro/login (ADMIN_ACCESS_KEY)
- [ ] Ghost mode як адмін

### Step 5 — Mark cutover successful

```bash
# Збережи дату/IP/git tag у memory
git tag k8s-cutover-$(date +%Y-%m-%d)
git push --tags
```

## Rollback (якщо щось горить)

Швидкий rollback не торкається DOKS — просто повертаємо CF DNS на старий
дроплет:

1. CF панель → DNS → blastr.pro A-record → Content = **<старий IP дроплета>** → Save.
2. Те саме для `admin.blastr.pro`.
3. CF почне роутити на старий дроплет за секунди.

**Чому це безпечно:**
- Старий дроплет продовжує жити (нічого не вимикали).
- DO Managed MySQL — спільна (k8s і дроплет читали/писали ту саму БД).
- Redis — спільна (k8s pods і droplet worker'и підключалися до того ж Valkey).
- Worker queue jobs можуть бути в "reserved" стані з k8s — старий droplet
  worker їх не підхопить (різні `queue` назви немає, але reserved jobs
  будуть retry'нути по `REDIS_QUEUE_RETRY_AFTER=1500s`).
- Сесії в DB → юзери залишаються залогінені.

Розслідуй проблему, фіксуй, deploy наново.

### Деструктивний rollback (якщо k8s повністю не fixable)

```bash
helm uninstall blastr -n blastr
# DOKS cluster залишається запущений (можна досліджувати помилки)
# Біллінг продовжує іти. Видали cluster коли впевнений що повертатись не будеш:
doctl kubernetes cluster delete blastr-prod
```

## Cleanup після тижня green metrics

Тільки після того як:
- ✅ 7 повних днів без 5xx сплесків
- ✅ Sentry baseline стабільний
- ✅ Phase 6 (service droplet з GlitchTip) запущений і працює
- ✅ Cloudflare Health Checks налаштовані (Phase 8)

Тоді:

```bash
# Старий droplet
doctl compute droplet list
doctl compute droplet delete <old-droplet-id>

# Floating IP (якщо є)
doctl compute floating-ip list
# Звільни/перенеси якщо треба

# CF DNS TTL — повертай до 1h (більше не треба швидкого rollback)
```

Збережи snapshot старого droplet до видалення на випадок "що там в crontab
було" — `doctl compute snapshot list`.

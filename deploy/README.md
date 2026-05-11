# Deploy — BlastR on DOKS

K8s migration plan from a single $24 droplet to a 4-pool DOKS cluster +
service droplet for observability. Old droplet stays as rollback insurance.

## Phase overview

| # | What | Doc |
|---|---|---|
| 1 | Code blockers (s3 storage, /healthz, OpCache preload, orchestrator-as-job, drop laravel-backup) | committed in `1033ad6` |
| 2 | Helm chart (`deploy/helm/blastr/`) + supervisord split per worker pool | committed in `ecba5cd` |
| 3 | Cluster bootstrap — DOKS, registry, operators, first helm install | `PHASE_3_BOOTSTRAP.md` |
| 4 | GitHub Actions CI/CD | `.github/workflows/deploy.yml` |
| 5 | Cutover — swap CF DNS to DO LB | `PHASE_5_CUTOVER.md` |
| 6 | Service droplet — GlitchTip + Loki + Grafana | `PHASE_6_SERVICES.md` |
| 7 | Decommission old droplet (after 1 week green) | last section of `PHASE_5_CUTOVER.md` |

## Cluster shape

```
DOKS cluster (FRA1, in MySQL/Valkey VPC)
│
├── Pool web (s-2vcpu-2gb, autoscale 1-3)
│   └── Deployment: blastr-web (FrankenPHP Octane, HPA on CPU)
│
├── Pool core (s-2vcpu-2gb, fixed 1)
│   ├── Deployment: blastr-scheduler (replicas=1)
│   ├── Deployment: blastr-worker-default (default+compile+discord queues)
│   └── 11× CronJob (daily + weekly tasks)
│
├── Pool sync (s-2vcpu-2gb, autoscale 1-2)
│   └── Deployment: blastr-worker-sync (bnet×4 + rio×2 + wcl×1 queues)
│       HPA via KEDA on Redis queue depth
│
└── Pool ai (s-2vcpu-2gb, autoscale 0-2)
    └── Deployment: blastr-worker-ai (ai queue)
        KEDA scale 0-2 with terminationGracePeriod=3600s + PDB maxUnavailable=0
```

Plus: ingress-nginx → DO LB ($12/mo), MySQL Managed, Valkey Managed,
DO Container Registry ($5/mo), DO Spaces (250GB, $5/mo).

## Costs

| | Idle | Peak |
|---|---|---|
| DOKS nodes (3-7) | $54 | $144 |
| DO LB | $12 | $12 |
| MySQL Managed | $21 | $21 |
| Valkey Managed | $15 | $15 |
| Container Registry | $5 | $5 |
| Spaces | $5 | $5 |
| Service droplet + 2× Block Storage | $34 | $34 |
| **Total** | **$146** | **$236** |

vs current $60/mo single droplet → +$86 baseline for full k8s readiness.

## Quick commands (Makefile)

```bash
make build TAG=v0.1.0          # docker build + push to DO registry
make deploy TAG=v0.1.0         # helm upgrade with explicit tag
make rollback                  # helm rollback to previous revision
make logs COMPONENT=web        # tail logs from a component (web/scheduler/worker-default/worker-sync/worker-ai)
make seal-env                  # rebuild sealed-secret from ~/.env.blastr-prod
make verify                    # post-deploy smoke check (rollout status + curl /up)
```

See `Makefile` for full target list.

## File tree

```
deploy/
├── README.md                          ← this file
├── Makefile                           ← shortcuts for build/deploy/logs
├── PHASE_3_BOOTSTRAP.md               ← cluster + operators + first install
├── PHASE_5_CUTOVER.md                 ← CF DNS swap procedure
├── PHASE_6_SERVICES.md                ← service droplet setup
│
├── helm/blastr/
│   ├── Chart.yaml
│   ├── values.yaml                    ← all tunable knobs
│   ├── sealed/                        ← (gitignored or committed encrypted)
│   │   ├── blastr-env.sealed.yaml
│   │   └── blastr-tls.sealed.yaml
│   └── templates/                     ← 20 templates, 25 resources rendered
│       ├── _helpers.tpl
│       ├── configmap.yaml + secret.yaml + tls-cert.yaml
│       ├── deployment-web.yaml + service.yaml + ingress.yaml
│       ├── deployment-scheduler.yaml
│       ├── deployment-worker-{default,sync,ai}.yaml
│       ├── hpa-web.yaml + hpa-worker-sync.yaml
│       ├── keda-scaledobject-ai.yaml
│       ├── pdb-{web,ai}.yaml
│       ├── job-migrate.yaml           ← Helm pre-upgrade hook
│       └── cronjobs.yaml              ← 11 CronJobs via range loop
│
└── services/                          ← service droplet (Phase 6)
    ├── docker-compose.yml             ← GlitchTip + Loki + Grafana + Caddy
    ├── Caddyfile
    ├── loki-config.yml
    ├── grafana-datasources.yml
    ├── .env.example
    └── promtail-daemonset.yaml        ← applied to DOKS, ships logs to Loki
```

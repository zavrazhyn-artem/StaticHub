# =============================================================================
# Convenience targets for the DOKS deploy workflow. Most just wrap doctl +
# docker + helm + kubectl with our project's defaults baked in.
#
# Override defaults via make var=value, e.g. `make deploy TAG=v0.2.0`.
# =============================================================================

REGISTRY    ?= registry.digitalocean.com
IMAGE       ?= blastr/app
NAMESPACE   ?= blastr
RELEASE     ?= blastr
CLUSTER     ?= blastr-prod
ENV_FILE    ?= $(HOME)/.env.blastr-prod
PUBKEY      ?= $(HOME)/blastr-cluster-pubkey.pem
CHART       ?= ./helm/blastr
COMPONENT   ?= web

# `make help` lists all targets with their first-line comment.
.PHONY: help
help:
	@awk 'BEGIN { FS=":.*##" } /^[a-zA-Z_-]+:.*?##/ { printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

# -----------------------------------------------------------------------------
# Image build + push
# -----------------------------------------------------------------------------
.PHONY: registry-login
registry-login: ## Login to DO Container Registry (1h token)
	doctl registry login --expiry-seconds 3600

.PHONY: build
build: registry-login ## Build + push image. Usage: make build TAG=v0.1.0
	@if [ -z "$(TAG)" ]; then echo "ERROR: TAG required, e.g. make build TAG=v0.1.0"; exit 1; fi
	docker build -t $(REGISTRY)/$(IMAGE):$(TAG) -f ../docker/app/Dockerfile --target app ..
	docker push $(REGISTRY)/$(IMAGE):$(TAG)

# -----------------------------------------------------------------------------
# Cluster ops
# -----------------------------------------------------------------------------
.PHONY: kubeconfig
kubeconfig: ## Fetch kubeconfig for the DOKS cluster
	doctl kubernetes cluster kubeconfig save $(CLUSTER)

.PHONY: nodes
nodes: ## List nodes by pool
	kubectl get nodes -L pool

# -----------------------------------------------------------------------------
# Secret sealing
# -----------------------------------------------------------------------------
.PHONY: seal-env
seal-env: ## Re-seal blastr-env from ~/.env.blastr-prod
	@if [ ! -f $(ENV_FILE) ]; then echo "ERROR: $(ENV_FILE) missing"; exit 1; fi
	@if [ ! -f $(PUBKEY) ]; then echo "ERROR: $(PUBKEY) missing (run 'make pubkey')"; exit 1; fi
	kubectl create secret generic blastr-env \
		--namespace $(NAMESPACE) \
		--from-env-file $(ENV_FILE) \
		--dry-run=client -o yaml \
		| kubeseal --cert $(PUBKEY) --format yaml > $(CHART)/sealed/blastr-env.sealed.yaml
	@echo "Wrote $(CHART)/sealed/blastr-env.sealed.yaml"

.PHONY: seal-tls
seal-tls: ## Re-seal TLS Secret. Usage: make seal-tls CERT=path.pem KEY=path.pem
	@if [ -z "$(CERT)" ] || [ -z "$(KEY)" ]; then echo "ERROR: CERT and KEY required"; exit 1; fi
	kubectl create secret tls blastr-tls \
		--namespace $(NAMESPACE) \
		--cert $(CERT) --key $(KEY) \
		--dry-run=client -o yaml \
		| kubeseal --cert $(PUBKEY) --format yaml > $(CHART)/sealed/blastr-tls.sealed.yaml

.PHONY: pubkey
pubkey: ## Export cluster public key for kubeseal (run once after operators install)
	kubeseal --fetch-cert \
		--controller-namespace kube-system \
		--controller-name sealed-secrets > $(PUBKEY)
	@echo "Saved cluster pubkey to $(PUBKEY)"

.PHONY: apply-sealed
apply-sealed: ## Apply sealed secrets to the cluster
	kubectl apply -f $(CHART)/sealed/

# -----------------------------------------------------------------------------
# Helm
# -----------------------------------------------------------------------------
.PHONY: lint
lint: ## helm lint + helm template
	helm lint $(CHART)
	helm template $(CHART) > /dev/null && echo "Templates render OK"

.PHONY: deploy
deploy: ## helm upgrade --install. Usage: make deploy TAG=v0.1.0
	@if [ -z "$(TAG)" ]; then echo "ERROR: TAG required"; exit 1; fi
	helm upgrade --install $(RELEASE) $(CHART) \
		--namespace $(NAMESPACE) --create-namespace \
		--set image.tag=$(TAG) \
		--atomic --timeout 10m --history-max 10

.PHONY: status
status: ## Show release status + recent history
	helm status $(RELEASE) -n $(NAMESPACE)
	@echo
	helm history $(RELEASE) -n $(NAMESPACE) --max 5

.PHONY: rollback
rollback: ## Rollback to previous revision
	helm rollback $(RELEASE) -n $(NAMESPACE)

.PHONY: uninstall
uninstall: ## ⚠️ Delete release (keeps namespace, secrets, PVCs)
	helm uninstall $(RELEASE) -n $(NAMESPACE)

# -----------------------------------------------------------------------------
# Observability
# -----------------------------------------------------------------------------
.PHONY: pods
pods: ## Show all pods with component labels
	kubectl get pods -n $(NAMESPACE) -L app.kubernetes.io/component -o wide

.PHONY: logs
logs: ## Tail logs by component. Usage: make logs COMPONENT=worker-sync
	kubectl logs -n $(NAMESPACE) -l app.kubernetes.io/component=$(COMPONENT) --tail=100 -f

.PHONY: describe
describe: ## kubectl describe for a component. Usage: make describe COMPONENT=web
	kubectl describe deployment -n $(NAMESPACE) $(RELEASE)-$(COMPONENT)

.PHONY: events
events: ## Recent warning events
	kubectl get events -n $(NAMESPACE) --sort-by='.lastTimestamp' --field-selector type=Warning

.PHONY: shell
shell: ## Drop into a one-shot debug pod
	kubectl run shell-$$RANDOM --rm -it --restart=Never \
		--namespace $(NAMESPACE) \
		--image=$(REGISTRY)/$(IMAGE):latest \
		--overrides='{"spec":{"nodeSelector":{"pool":"core"},"imagePullSecrets":[{"name":"blastr-registry-creds"}]}}' \
		--command -- /bin/bash

# -----------------------------------------------------------------------------
# Verify
# -----------------------------------------------------------------------------
.PHONY: verify
verify: ## Post-deploy smoke checks
	kubectl rollout status -n $(NAMESPACE) deployment/$(RELEASE)-web --timeout=5m
	kubectl rollout status -n $(NAMESPACE) deployment/$(RELEASE)-scheduler --timeout=2m
	kubectl rollout status -n $(NAMESPACE) deployment/$(RELEASE)-worker-default --timeout=2m
	kubectl rollout status -n $(NAMESPACE) deployment/$(RELEASE)-worker-sync --timeout=2m
	@echo "All rollouts healthy. Run 'curl --resolve blastr.pro:443:<LB_IP> https://blastr.pro/healthz' for end-to-end check."

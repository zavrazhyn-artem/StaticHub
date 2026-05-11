{{/*
Expand the name of the chart.
*/}}
{{- define "blastr.name" -}}
{{- default .Chart.Name .Values.nameOverride | trunc 63 | trimSuffix "-" -}}
{{- end -}}

{{/*
Fully qualified app name. Truncated at 63 chars for k8s DNS limits.
*/}}
{{- define "blastr.fullname" -}}
{{- if .Values.fullnameOverride -}}
{{- .Values.fullnameOverride | trunc 63 | trimSuffix "-" -}}
{{- else -}}
{{- $name := default .Chart.Name .Values.nameOverride -}}
{{- if contains $name .Release.Name -}}
{{- .Release.Name | trunc 63 | trimSuffix "-" -}}
{{- else -}}
{{- printf "%s-%s" .Release.Name $name | trunc 63 | trimSuffix "-" -}}
{{- end -}}
{{- end -}}
{{- end -}}

{{/*
Chart name + version label. Stable across releases.
*/}}
{{- define "blastr.chart" -}}
{{- printf "%s-%s" .Chart.Name .Chart.Version | replace "+" "_" | trunc 63 | trimSuffix "-" -}}
{{- end -}}

{{/*
Common labels applied to every resource.
*/}}
{{- define "blastr.labels" -}}
helm.sh/chart: {{ include "blastr.chart" . }}
{{ include "blastr.selectorLabels" . }}
app.kubernetes.io/version: {{ .Chart.AppVersion | quote }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
{{- end -}}

{{/*
Selector labels — used by Deployment/Service .spec.selector. MUST be stable
across upgrades or k8s rejects the change.
*/}}
{{- define "blastr.selectorLabels" -}}
app.kubernetes.io/name: {{ include "blastr.name" . }}
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end -}}

{{/*
Image reference. Falls back to Chart.AppVersion if values.image.tag unset.
*/}}
{{- define "blastr.image" -}}
{{- $tag := .Values.image.tag | default .Chart.AppVersion -}}
{{- printf "%s:%s" .Values.image.repository $tag -}}
{{- end -}}

{{/*
Common pod spec fragments. Each deployment uses these so changes propagate.
*/}}
{{- define "blastr.imagePullSecrets" -}}
{{- if .Values.image.pullSecretName }}
imagePullSecrets:
  - name: {{ .Values.image.pullSecretName }}
{{- end }}
{{- end -}}

{{/*
envFrom block — every pod loads non-sensitive ConfigMap + sensitive Secret.
Components add their own env: overrides on top if they need pod-specific values
(e.g. OCTANE_WORKERS on web).
*/}}
{{- define "blastr.envFrom" -}}
envFrom:
  - configMapRef:
      name: {{ include "blastr.fullname" . }}-env
  - secretRef:
      name: {{ .Values.secret.name }}
{{- end -}}

#!/usr/bin/env bash
#
# Test de non-régression et de fuite mémoire FrankenPHP (Makefile : `make smoke-worker`).
#
# Contrat (règle 3 du AGENTS.md — Worker Mode & persistance mémoire) :
#   - Envoie SMOKE_REQUESTS requêtes HTTP consécutives à l'application.
#   - Échec si : une réponse >= 500 ou hors délai, un redémarrage du conteneur,
#     une erreur fatale dans les logs, ou une dérive RSS du worker au-delà du seuil.
#
# Variables d'environnement (optionnelles) :
#   SMOKE_BASE_URL           URL cible (défaut : http://127.0.0.1:${HTTP_PORT:-8080})
#   SMOKE_REQUESTS           nombre de requêtes (défaut : 50)
#   SMOKE_TIMEOUT            timeout curl par requête en secondes (défaut : 10)
#   SMOKE_MAX_RSS_GROWTH_MB  dérive RSS maximale du worker en Mo (défaut : 64)
set -euo pipefail

SMOKE_BASE_URL="${SMOKE_BASE_URL:-http://127.0.0.1:${HTTP_PORT:-8080}}"
SMOKE_REQUESTS="${SMOKE_REQUESTS:-50}"
SMOKE_TIMEOUT="${SMOKE_TIMEOUT:-10}"
SMOKE_MAX_RSS_GROWTH_MB="${SMOKE_MAX_RSS_GROWTH_MB:-64}"

# --- Garde-fou : le conteneur applicatif doit être démarré ---
if ! docker compose exec -T app true > /dev/null 2>&1; then
    echo "❌ [Smoke-Worker] Le conteneur 'app' n'est pas joignable. Démarrez l'environnement : docker compose up -d --build" >&2
    exit 1
fi

get_worker_rss_kb() {
    # RSS du processus principal (PID 1 = FrankenPHP/Caddy) dans le conteneur, en kB
    docker compose exec -T app grep VmRSS /proc/1/status | awk '{print $2}'
}

# --- Baseline : redémarrages, mémoire du worker, horodatage des logs ---
RESTARTS_BEFORE=$(docker inspect -f '{{.RestartCount}}' "$(docker compose ps -q app)")
RSS_BEFORE=$(get_worker_rss_kb)
LOG_SINCE=$(date -u +%Y-%m-%dT%H:%M:%SZ)

# Requête d'échauffement : absorbe le coût du cold start (page non encore compilée/mise en cache)
curl -s -o /dev/null --max-time "$SMOKE_TIMEOUT" "$SMOKE_BASE_URL/" || true
sleep 1

# Baseline mémoire après échauffement (le cold start alloue légitimement de la mémoire)
RSS_BASELINE=$(get_worker_rss_kb)

# --- Charge : SMOKE_REQUESTS requêtes consécutives ---
FAILURES=0
for i in $(seq 1 "$SMOKE_REQUESTS"); do
    CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time "$SMOKE_TIMEOUT" "$SMOKE_BASE_URL/" || echo 000)
    if [[ "$CODE" -lt 200 || "$CODE" -ge 500 ]]; then
        echo "❌ [Smoke-Worker] Requête $i/$SMOKE_REQUESTS : code HTTP $CODE" >&2
        FAILURES=$((FAILURES + 1))
    fi
done
if [[ "$FAILURES" -gt 0 ]]; then
    echo "❌ [Smoke-Worker] Échec : $FAILURES/$SMOKE_REQUESTS requêtes en erreur." >&2
    exit 1
fi

# --- Intégrité du conteneur : aucun redémarrage pendant la charge ---
RESTARTS_AFTER=$(docker inspect -f '{{.RestartCount}}' "$(docker compose ps -q app)")
if [[ "$RESTARTS_AFTER" != "$RESTARTS_BEFORE" ]]; then
    echo "❌ [Smoke-Worker] Le conteneur a redémarré pendant le test ($RESTARTS_BEFORE → $RESTARTS_AFTER) : crash worker." >&2
    exit 1
fi

# --- Logs : aucune erreur fatale depuis le début du test ---
FATAL_LINES=$(docker compose logs app --since "$LOG_SINCE" 2>&1 | grep -ciE "fatal|panic|segmentation fault" || true)
if [[ "$FATAL_LINES" -gt 0 ]]; then
    echo "❌ [Smoke-Worker] $FATAL_LINES erreur(s) fatale(s) détectée(s) dans les logs depuis le début du test :" >&2
    docker compose logs app --since "$LOG_SINCE" 2>&1 | grep -iE "fatal|panic|segmentation fault" | head -5 >&2
    exit 1
fi

# --- Fuite mémoire : dérive RSS du worker au-delà du seuil ---
RSS_AFTER=$(get_worker_rss_kb)
RSS_GROWTH_KB=$((RSS_AFTER - RSS_BASELINE))
RSS_GROWTH_MB=$((RSS_GROWTH_KB / 1024))
if (( RSS_GROWTH_KB > SMOKE_MAX_RSS_GROWTH_MB * 1024 )); then
    echo "❌ [Smoke-Worker] Fuite mémoire probable : RSS worker ${RSS_GROWTH_MB} Mo de dérive (seuil : ${SMOKE_MAX_RSS_GROWTH_MB} Mo, ${RSS_BASELINE} kB → ${RSS_AFTER} kB)." >&2
    echo "   Vérifiez le contrat ResetInterface (règle 3 du AGENTS.md) des services à état mutable." >&2
    exit 1
fi

echo "✅ [Smoke-Worker] $SMOKE_REQUESTS/$SMOKE_REQUESTS requêtes OK — 0 redémarrage, 0 erreur fatale, dérive RSS ${RSS_GROWTH_MB} Mo (seuil ${SMOKE_MAX_RSS_GROWTH_MB} Mo)."

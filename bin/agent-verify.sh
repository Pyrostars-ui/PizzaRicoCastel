#!/usr/bin/env bash
#
# Boucle Fast-Fail ciblée sur les fichiers modifiés (Makefile : `make verify`).
#
# Contrat :
#   - Cible les fichiers modifiés non commités (git diff --name-only + fichiers non suivis).
#   - Exécute séquentiellement les garde-fous QA via le conteneur Docker (règle 1 du AGENTS.md).
#   - Silencieux en cas de succès complet (économie de tokens pour l'agent).
#   - Au premier échec : affiche l'étape fautive et sa trace brute, puis sort avec son code retour.
#
# Étapes : php -l, php-cs-fixer, twig-cs-fixer, lint:twig (fichiers modifiés) ;
#          lint:container, doctrine:schema:validate --skip-sync, phpstan (toujours) ;
#          phpunit (toujours, regression fonctionnelle).
set -euo pipefail

DOCKER_CMD=(docker compose exec -T app)

# --- Garde-fou : le conteneur applicatif doit être démarré (isolation Docker stricte) ---
if ! "${DOCKER_CMD[@]}" true > /dev/null 2>&1; then
    echo "❌ [Verify] Le conteneur 'app' n'est pas joignable. Démarrez l'environnement : docker compose up -d --build" >&2
    exit 1
fi

# --- Collecte des fichiers modifiés non commités (modifiés + non suivis) ---
CHANGED_FILES=$( { git diff --name-only HEAD 2>/dev/null; git ls-files --others --exclude-standard; } | sort -u || true)

CHANGED_PHP=()
CHANGED_TWIG=()
while IFS= read -r file; do
    [[ -z "$file" || ! -f "$file" ]] && continue
    if [[ "$file" == *.php ]]; then
        CHANGED_PHP+=("$file")
    elif [[ "$file" == *.twig ]]; then
        CHANGED_TWIG+=("$file")
    fi
done <<< "$CHANGED_FILES"

# --- Exécution d'une étape : silencieuse en succès, trace brute en échec ---
# usage : run_step <nom_de_l_etape> <commande...>
run_step() {
    local name="$1"
    shift
    local output rc=0
    output=$("$@" 2>&1) || rc=$?
    if [[ $rc -ne 0 ]]; then
        echo "" >&2
        echo "❌ [Verify] ÉCHEC DE L'ÉTAPE : $name" >&2
        echo "---------------- SORTIE DÉTAILLÉE ----------------" >&2
        echo "$output" >&2
        echo "--------------------------------------------------" >&2
        exit "$rc"
    fi
}

# 1. Syntaxe PHP des fichiers modifiés (gate la plus rapide, fail-early)
if [[ ${#CHANGED_PHP[@]} -gt 0 ]]; then
    for file in "${CHANGED_PHP[@]}"; do
        run_step "php -l $file" "${DOCKER_CMD[@]}" php -l "$file"
    done
fi

# 2. Standards PSR-12 & Symfony sur les fichiers PHP modifiés
if [[ ${#CHANGED_PHP[@]} -gt 0 ]]; then
    run_step "PHP-CS-Fixer (fichiers modifiés)" "${DOCKER_CMD[@]}" vendor/bin/php-cs-fixer fix --dry-run --diff -- "${CHANGED_PHP[@]}"
fi

# 3. Standards Twig sur les fichiers modifiés
if [[ ${#CHANGED_TWIG[@]} -gt 0 ]]; then
    run_step "Twig-CS-Fixer (fichiers modifiés)" "${DOCKER_CMD[@]}" vendor/bin/twig-cs-fixer lint "${CHANGED_TWIG[@]}"
    run_step "Symfony lint:twig (fichiers modifiés)" "${DOCKER_CMD[@]}" bin/console lint:twig -- "${CHANGED_TWIG[@]}"
fi

# 4. Intégrité du conteneur d'injection de dépendances (toujours : toute modification peut casser le wiring)
run_step "Symfony lint:container" "${DOCKER_CMD[@]}" bin/console lint:container --quiet

# 5. Analyse statique stricte : fichiers src/ et tests/ modifiés, sinon projet complet
PHPSTAN_TARGETS=()
for file in "${CHANGED_PHP[@]}"; do
    [[ "$file" == src/* || "$file" == tests/* ]] && PHPSTAN_TARGETS+=("$file")
done
if [[ ${#PHPSTAN_TARGETS[@]} -gt 0 ]]; then
    run_step "PHPStan niveau 8 (fichiers modifiés)" "${DOCKER_CMD[@]}" vendor/bin/phpstan analyse --level=8 --no-progress -- "${PHPSTAN_TARGETS[@]}"
else
    run_step "PHPStan niveau 8 (projet)" "${DOCKER_CMD[@]}" vendor/bin/phpstan analyse --no-progress
fi

# 6. Intégrité du mapping Doctrine (zéro désynchronisation, sans base de données)
run_step "Doctrine schema:validate (mapping)" "${DOCKER_CMD[@]}" bin/console doctrine:schema:validate --skip-sync --quiet

# 7. Non-régression fonctionnelle : suite PHPUnit complète (isolation transactionnelle)
run_step "PHPUnit (suite complète)" "${DOCKER_CMD[@]}" bin/phpunit --no-coverage

# Succès complet : aucune sortie (économie de tokens pour l'agent)
exit 0

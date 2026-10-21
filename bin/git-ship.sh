#!/usr/bin/env bash
#
# Pipeline de livraison Gitflow automatisé (Makefile : `make ship TYPE=... NAME=... MSG="..."`).
#
# Contrat (règle 6 du AGENTS.md) :
#   ./bin/git-ship.sh <TYPE> <NAME> <MSG>
#     TYPE : feat | fix | refactor | test | hotfix
#     NAME : nom kebab-case de la branche (ex: auth-voter)
#     MSG  : description du changement (utilisée pour le commit et la PR)
#
#   - Branche cible : TYPE/NAME (développée depuis 'develop', 'main' pour hotfix/*).
#   - Gate QA fail-closed : bin/agent-verify.sh doit passer avant tout commit.
#   - Push sur origin + Pull Request via GitHub CLI (gh pr create).
#   - Aucune fusion : la décision de merge reste humaine, derrière les 5 Gates CI.
set -euo pipefail

if [[ $# -ne 3 ]]; then
    echo "Usage : ./bin/git-ship.sh <TYPE> <NAME> <MSG>" >&2
    echo "  TYPE : feat | fix | refactor | test | hotfix" >&2
    echo "  NAME : nom kebab-case (ex: auth-voter)" >&2
    echo "  MSG  : description (ex: \"add login voter\")" >&2
    exit 1
fi

TYPE="$1"
NAME="$2"
MSG="$3"
BRANCH="$TYPE/$NAME"

# --- Validation des arguments ---
if [[ ! "$TYPE" =~ ^(feat|fix|refactor|test|hotfix)$ ]]; then
    echo "❌ [Ship] TYPE invalide : '$TYPE' (attendu : feat | fix | refactor | test | hotfix)" >&2
    exit 1
fi
if ! [[ "$NAME" =~ ^[a-z0-9][a-z0-9-]*$ ]]; then
    echo "❌ [Ship] NAME invalide : '$NAME' (kebab-case requis : minuscules, chiffres, tirets)" >&2
    exit 1
fi
if [[ -z "$MSG" ]]; then
    echo "❌ [Ship] MSG vide : une description est requise" >&2
    exit 1
fi

# Gitflow : develop est la base par défaut, main pour les hotfix de production
BASE_BRANCH="develop"
if [[ "$TYPE" == "hotfix" ]]; then
    BASE_BRANCH="main"
fi

# --- Résolution de la branche de travail ---
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "")
if [[ "$CURRENT_BRANCH" == "$BRANCH" ]]; then
    echo "📦 [Ship] Poursuite sur la branche existante '$BRANCH'."
elif [[ "$CURRENT_BRANCH" == "$BASE_BRANCH" ]]; then
    git fetch origin "$BASE_BRANCH"
    git pull --ff-only origin "$BASE_BRANCH"
    git checkout -b "$BRANCH"
    echo "📦 [Ship] Branche '$BRANCH' créée depuis '$BASE_BRANCH'."
else
    echo "❌ [Ship] Branche courante '$CURRENT_BRANCH' inattendue." >&2
    echo "   Placez-vous sur '$BASE_BRANCH' (création de la branche) ou directement sur '$BRANCH' (poursuite)." >&2
    exit 1
fi

# --- Gate QA fail-closed : la boucle Fast-Fail doit passer avant tout commit ---
echo "🔍 [Ship] Gate QA (bin/agent-verify.sh) — silencieux en cas de succès..."
./bin/agent-verify.sh

# --- Indexation et commit (le hook pre-commit applique ses propres garde-fous) ---
git add -A
if git diff --cached --quiet; then
    echo "❌ [Ship] Rien à livrer : aucun changement indexé après 'git add -A'." >&2
    exit 1
fi
git commit -m "$TYPE: $MSG"

# --- Push et Pull Request ---
git push -u origin "$BRANCH"
echo "🚀 [Ship] Ouverture de la Pull Request vers '$BASE_BRANCH'..."
PR_URL=$(gh pr create \
    --base "$BASE_BRANCH" \
    --head "$BRANCH" \
    --title "$TYPE: $MSG" \
    --body "## Livraison automatisée (bin/git-ship.sh)

- Branche : \`$BRANCH\` (depuis \`$BASE_BRANCH\`)
- Description : $MSG

## Validation

- Gate QA locale (\`bin/agent-verify.sh\`) : succès
- Garde-fous pre-commit (style, Twig, conteneur DI, PHPStan, mapping Doctrine) : succès

La CI (Pipeline SecOps 5 Gates + matrice PHP 8.4/8.5 + revue IA) doit être verte avant fusion.
")
echo "✅ [Ship] Pull Request ouverte : $PR_URL"

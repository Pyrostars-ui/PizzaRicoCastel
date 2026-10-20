# Procédure Opérationnelle : Gitflow Strict & Automatisation GitHub CLI (`gh`)

Ce document régit le cycle de vie des branches Git et l'automatisation des Pull Requests pour tout agent autonome.

---

## 1. Topologie et Rôles des Branches

```text
main      ●───────────────────────────● (v1.0.0) ────────● (v1.0.1)
           \                         /                  / \
hotfix/*    └───────●───────────────┼──────────────────┘   \
                                   /                        v
develop   ●───●───────────●───────┼───●────────────────────●───►
               \         /       /
feat/*          └───●───┘       /
release/*                └─────●
```

| Branche | Branche source | Fusionnée dans | Commits directs | Rôle |
| :--- | :--- | :--- | :--- | :--- |
| **`main`** | — | — | **Interdits** | Production stable. Chaque fusion produit un tag Git (`vX.Y.Z`). |
| **`develop`** | `main` | — | **Interdits** | Branche tampon d'intégration par défaut. |
| **`feat/*`** | `develop` | `develop` | Autorisés | Nouvelles fonctionnalités métier. |
| **`fix/*`** | `develop` | `develop` | Autorisés | Corrections de bugs détectés en intégration. |
| **`refactor/*`** | `develop` | `develop` | Autorisés | Améliorations structurelles sans changement fonctionnel. |
| **`test/*`** | `develop` | `develop` | Autorisés | Ajout ou renforcement de tests automatisés. |
| **`release/vX.Y.Z`** | `develop` | `main` **ET** `develop` | Correctifs mineurs de release | Préparation et stabilisation d'une version de production. |
| **`hotfix/*`** | `main` | `main` **ET** `develop` | Correctif critique | Correction urgente d'un incident en production. |

---

## 2. Procédures Pas-à-Pas par Scénario

### A. Développement Standard (`feat/*`, `fix/*`, `refactor/*`, `test/*`)

1. **S'assurer que `develop` existe (créée depuis `main` si absente), la synchroniser et créer la branche de travail :**
   ```bash
   git fetch origin
   git show-ref --verify --quiet refs/heads/develop || git branch develop origin/main
   git checkout -b feat/user-profile develop
   ```
2. **Développer et exécuter la boucle Fast-Fail dans Docker :**
   ```bash
   ./bin/agent-verify.sh
   ```
3. **Expédier et ouvrir la Pull Request vers `develop` :**
   ```bash
   make ship TYPE=feat NAME=user-profile MSG="add user profile management"
   # Équivalent à : ./bin/git-ship.sh feat user-profile "add user profile management"
   ```
   Le script exécute `./bin/agent-verify.sh`, `make check`, commite au format Conventional Commits, pousse la branche et exécute :
   ```bash
   gh pr create --base develop --head feat/user-profile --title "feat: add user profile management" --body "..."
   ```
   L'ouverture ou la mise à jour de la Pull Request déclenche automatiquement le pipeline GitHub Actions `.github/workflows/ci.yml` (5 Gates *Fail-Closed*, 18/18 critères). Toute fusion dans `develop` ou `main` exige le succès intégral de la CI (`gh pr checks`).

### B. Préparation d'une Release (`release/vX.Y.Z`)

1. **Dériver depuis `develop` :**
   ```bash
   git fetch origin develop
   git checkout -b release/v1.2.0 origin/develop
   ```
2. **Valider l'oracle qualité complet et le smoke test FrankenPHP :**
   ```bash
   make check
   make smoke-worker
   ```
3. **Ouvrir la PR vers `main`, fusionner et taguer :**
   ```bash
   ./bin/git-ship.sh release v1.2.0 "prepare release v1.2.0"
   gh pr merge release/v1.2.0 --merge --delete-branch=false
   git checkout main && git pull origin main
   git tag -a v1.2.0 -m "Release v1.2.0"
   git push origin v1.2.0
   ```
4. **Réintégrer obligatoirement dans `develop` :**
   ```bash
   gh pr create --base develop --head release/v1.2.0 --title "chore(release): back-merge v1.2.0 into develop" --fill
   gh pr merge --merge --delete-branch
   ```

### C. Correction Urgente de Production (`hotfix/*`)

1. **Dériver strictement depuis `main` :**
   ```bash
   git fetch origin main
   git checkout -b hotfix/security-token origin/main
   ```
2. **Appliquer le correctif + test de régression et vérifier :**
   ```bash
   ./bin/agent-verify.sh
   make check
   ```
3. **Pousser et créer la PR vers `main` :**
   ```bash
   ./bin/git-ship.sh hotfix security-token "fix token expiration validation"
   ```
4. **Après fusion dans `main` et création du tag de patch, réintégrer dans `develop` :**
   ```bash
   gh pr create --base develop --head hotfix/security-token --title "chore(hotfix): back-merge hotfix/security-token into develop" --fill
   ```

---

## 3. Protocole d'Abandon Fail-Safe (Max 3 Essais)

Si un linter (`php-cs-fixer`, `twig-cs-fixer`, `lint:twig`, `lint:container`), `phpstan` ou `phpunit` échoue **3 fois consécutives** :

1. **Interdiction absolue** de forcer le commit (`--no-verify`) ou de pousser un état dégradé.
2. **Réinitialisation immédiate sur `origin/develop` :**
   ```bash
   git reset --hard origin/develop
   ```
3. **Signalement automatisé dans l'issue GitHub avec la trace brute :**
   ```bash
   gh issue comment <ID> --body "🚨 [Fail-Safe Agent] Abandon après 3 tentatives. Trace brute : ..."
   ```
4. **Arrêt de l'agent** et passage de relais à l'équipe humaine.

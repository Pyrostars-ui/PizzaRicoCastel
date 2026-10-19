# Directives d'Ingénierie pour Agents Autonomes (Stack Symfony 7+/8+ & FrankenPHP)

Ce fichier définit les règles de gouvernance strictes et non négociables pour tout agent IA intervenant sur ce dépôt.

---

## 1. Isolation d'Exécution (Docker-Only)
- **Interdiction formelle** d'exécuter des commandes PHP, Composer ou Symfony directement sur la machine hôte.
- Toute commande technique doit transiter par le conteneur applicatif en mode non-interactif :
  ```bash
  docker compose exec -T app <commande>
  ```
- Privilégier systématiquement les cibles normalisées du `Makefile` (`make check`, `make verify`, `make test`, `make db-fresh`, `make worker-reload`, `make env-dump`, `make ship`).

## 2. Cycle de Vie Dynamique (Zero EOL)
- Avant toute initialisation ou montée de version, interroger obligatoirement :
  - `https://endoflife.date/api/php.json`
  - `https://endoflife.date/api/symfony.json`
- **Cibles autorisées :** uniquement des versions en **support actif** (`support > date_du_jour`) :
  - **PHP :** `8.4` ou `8.5` (`declare(strict_types=1);`, classes/propriétés `readonly`, promotion de constructeur).
  - **Symfony :** `7.2+` (LTS `7.4`) ou `8.x` (attributs natifs exclusivement : `#[Route]`, `#[ORM\...]`, `#[Assert\...]`, `#[Autowire]`, `#[IsGranted]`).
- **Interdiction formelle** de cibler ou maintenir une branche en fin de vie (EOL). Exécuter `docker compose exec -T app php scripts/check-eol.php` pour valider la conformité.

## 3. FrankenPHP Worker Mode & Persistance Mémoire
- Le Kernel Symfony démarre une seule fois et **persiste en RAM entre les requêtes HTTP**.
- **Zéro état statique mutable :** toute propriété statique non constante est proscrite (risque critique de fuite de données inter-utilisateurs).
- **Contrat de réinitialisation obligatoire :** tout service conservant un état interne mutable (cache local, buffer, contexte utilisateur/tenant, compteur) doit obligatoirement implémenter `Symfony\Contracts\Service\ResetInterface` avec une méthode `reset(): void` (automatiquement taguée `kernel.reset` via l'autoconfiguration Symfony).
- Après modification de configuration ou de services : recharger les workers sans coupure via `make worker-reload` (`docker compose exec -T app frankenphp reload --config /etc/frankenphp/Caddyfile --adapter caddyfile --force`) et valider l'absence de fuite avec `./bin/smoke-worker.sh`.

## 4. Base de Données & Identifiants
- **Moteur par défaut :** PostgreSQL (image officielle `postgres:16-alpine` ou supérieur, service `db`).
- **Identifiants primaires :** toutes les entités Doctrine utilisent exclusivement des **UUIDv7** séquentiels et horodatés (`Symfony\Component\Uid\Uuid::v7()`, type Doctrine `UuidType::NAME` / `uuid`). Les auto-incréments entiers (`SERIAL`/`IDENTITY`) et UUIDv4 aléatoires sont interdits pour les clés primaires.
- **Sécurité SQL :** 100 % de requêtes préparées via Doctrine ORM/DBAL, zéro concaténation SQL.

## 5. Front-End, Assets & Temps Réel
- **AssetMapper & Symfony UX :** gestion des assets via `importmap.php` (sans runtime Node.js obligatoire), composants `#[AsTwigComponent]`, Stimulus et Hotwire Turbo.
- **Audit de sécurité front-end :** exécuter systématiquement `docker compose exec -T app bin/console importmap:audit`.
- **Temps réel :** publication d'événements via le hub Mercure intégré à FrankenPHP (`Symfony\Component\Mercure\HubInterface`).

## 6. Gitflow Strict & Automatisation GitHub CLI (`gh`)
- **`main` :** branche de production protégée. Commits directs strictement interdits. Reçoit uniquement les fusions de `release/vX.Y.Z` et `hotfix/*` (avec tag Git).
- **`develop` :** branche tampon d'intégration par défaut. Commits directs strictement interdits.
- **Branches de travail :** dérivées systématiquement depuis `develop` :
  - `feat/<nom>`, `fix/<nom>`, `refactor/<nom>`, `test/<nom>`
- **Releases :** `release/vX.Y.Z` dérivée de `develop`, fusionnée dans `main` (avec tag `vX.Y.Z`) puis réintégrée dans `develop`.
- **Exceptions de production :** `hotfix/<nom>` dérivée de `main`, fusionnée dans `main` (avec tag) **ET** réintégrée dans `develop`.
- **Livraison automatisée & CI SecOps :** utiliser `make ship TYPE=feat NAME=ma-feature MSG="description"` (qui exécute `./bin/git-ship.sh` et ouvre la Pull Request via `gh pr create --base develop`), déclenchant le pipeline GitHub Actions `.github/workflows/ci.yml` (5 Gates *Fail-Closed*).

## 7. Boucle Fast-Fail, Protocole d'Abandon & Fail-Safe (Max 3 Essais)
- Après chaque modification de fichiers, lancer `./bin/agent-verify.sh` (`make verify`).
- En cas d'échec d'un linter (`php-cs-fixer`, `twig-cs-fixer`, `lint:twig`, `lint:container`), de `phpstan` (niveau 8) ou de `phpunit`, analyser la sortie et corriger.
- **Plafond strict de 3 tentatives :** si l'erreur persiste après **3 essais consécutifs**, il est formellement interdit de forcer un commit (`--no-verify`), d'abaisser le niveau PHPStan ou de supprimer un test.
- **Procédure Fail-Safe obligatoire au 3e échec :**
  1. Réinitialiser l'arbre de travail : `git reset --hard origin/develop`
  2. Publier la trace brute de l'erreur sur l'issue concernée :
     ```bash
     gh issue comment <ID> --body "🚨 [Fail-Safe Agent] Abandon après 3 échecs de validation. Trace brute : ..."
     ```
  3. Interrompre le cycle autonome et passer le relais à un opérateur humain.

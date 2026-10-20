# Protocole de Fusion Non-Destructive sur Projet Existant (Merge Strategy)

Lors de l'application du skill `symfony-stack-enterprise` sur un projet Symfony existant, l'agent suit ce protocole pour préserver l'intégrité du code métier tout en installant les garde-fous d'infrastructure.

---

## 1. Initialisation Gitflow & Préservation Absolue du Code Métier
- Vérifier dès le clonage ou l'audit si la branche `develop` existe (`git branch -a`). Si elle est absente, la créer immédiatement depuis `main` (`git branch develop origin/main` ou `git checkout -b develop main`) avant toute création de branche thématique.
- Ne jamais écraser les entités, contrôleurs, services, migrations ou templates existants.
- Toujours inspecter `composer.json`, `compose.yaml` et `Dockerfile` avant toute modification.

## 2. Alignement Dynamique du Cycle de Vie (Zero EOL)
1. Interroger `https://endoflife.date/api/php.json` et `https://endoflife.date/api/symfony.json` via `scripts/check-eol.php`.
2. Si le projet existant cible une version EOL de PHP (< 8.4) ou de Symfony (< 7.2 / hors support actif), planifier la montée de version assistée par `rector.php` (`make upgrade-stack`).

## 3. Fusion de `composer.json`
- Conserver l'intégralité des dépendances métier (`require`).
- Ajouter les outils QA requis dans `require-dev` : `phpstan/phpstan`, `phpstan/phpstan-symfony`, `phpstan/phpstan-doctrine`, `friendsofphp/php-cs-fixer`, `vincentlanglet/twig-cs-fixer`, `rector/rector`, `dama/doctrine-test-bundle`, `zenstruck/foundry`.
- Fusionner les entrées de `assets/composer-scripts.json` dans la section `scripts` et ajouter `git config core.hooksPath .githooks || true` dans `post-install-cmd` et `post-update-cmd`.

## 4. Fusion de `compose.yaml`
- Aligner le service applicatif sur le nom `app` (FrankenPHP worker) et la base PostgreSQL Alpine sur `db` (avec alias réseau `database` pour la rétrocompatibilité).
- Conserver les volumes de données existants afin d'éviter toute perte de données locales.

## 5. Déploiement du Pipeline CI/CD & Configurations QA
- Déployer `.github/workflows/ci.yml` (pipeline SecOps 5 Gates *Fail-Closed* avec détection dynamique de la version PHP depuis `composer.json` et conteneur `postgres:16-alpine`). Si un workflow CI existait déjà, fusionner ses étapes spécifiques sans affaiblir les 5 Gates de sécurité.
- Installer `phpstan.neon.dist`, `.php-cs-fixer.dist.php`, `.twig-cs-fixer.dist.php`, `rector.php`, `scripts/check.php` et `scripts/check-eol.php`.


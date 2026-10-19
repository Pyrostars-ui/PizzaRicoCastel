# Garde-Fous de Sécurité, Cycle de Vie Zero EOL & Protection Contextuelle

Ce document définit les règles de sécurité applicative, d'isolation des secrets, de gouvernance des dépendances et de résilience opérationnelle pour l'agent autonome.

---

## 1. Cycle de Vie Dynamique (Zero EOL)

Il est **formellement interdit** d'initialiser ou de maintenir un projet sur une version de PHP ou de Symfony en fin de vie (EOL) ou sortie du support actif.

### Vérification Automatisée Obligatoire
Avant tout démarrage ou mise à niveau (`make eol-check` / `php scripts/check-eol.php`), l'agent interroge les deux endpoints de référence :
- `https://endoflife.date/api/php.json`
- `https://endoflife.date/api/symfony.json`

### Cibles Autorisées
- **PHP :** branches en support actif uniquement (`8.4` ou `8.5`, où `support >= date_du_jour`).
- **Symfony :** branches en support actif uniquement (`7.4 LTS` ou `8.x`, où `support >= date_du_jour`).
- Toute dépendance bloquant la montée vers une branche supportée doit être identifiée via `docker compose exec -T app composer prohibits php 8.4 --tree --locked` et remplacée ou mise à jour.

---

## 2. Chiffrement Symfony Vault & Isolation des Fichiers `.env`

### Cascade d'Environnement Symfony
L'ordre de résolution des variables d'environnement est strict :
1. Variables d'environnement système / conteneur Docker.
2. `.env.<APP_ENV>.local` (surcharges locales par environnement, **jamais versionné**).
3. `.env.<APP_ENV>` (valeurs par défaut d'environnement, versionné, ex: `.env.test`).
4. `.env.local` (surcharges locales de développement, **jamais versionné**, ignoré en `APP_ENV=test`).
5. `.env` (gabarit de référence sans aucun secret réel, versionné).

En production sous FrankenPHP Worker Mode, les fichiers `.env` ne doivent jamais être parsés à chaque requête :
```bash
docker compose exec -T app composer dump-env prod
# ou : make env-dump -> génère .env.local.php
```

### Coffre-Fort Cryptographique (Symfony Secrets Vault)
Tous les secrets sensibles (clés API tierces, tokens JWT, DSN de production) doivent être chiffrés dans le Vault Symfony :
```bash
docker compose exec -T app bin/console secrets:generate-keys
docker compose exec -T app bin/console secrets:set MERCURE_JWT_SECRET
```
- **Fichiers autorisés dans Git :** `config/secrets/<env>/<env>.encrypt.public.php` et les fichiers `.sodium` chiffrés.
- **Fichier strictement banni de Git et du contexte IA (`.gitignore`, `.aiignore`, `.dockerignore`, `.githooks/pre-commit`, `.github/workflows/ci.yml` Gate 3.1) :**
  `config/secrets/*/*.decrypt.private.key`

---

## 3. Audit de la Chaîne d'Approvisionnement (Backend & Frontend)

L'application n'utilise pas de runtime Node.js obligatoire : le front-end repose sur **AssetMapper** et **Symfony UX**. Deux audits de vulnérabilités CVE sont exécutés obligatoirement dans `make check` et dans la Gate 1 de `.github/workflows/ci.yml` :

1. **Audit des dépendances PHP (Composer) :**
   ```bash
   docker compose exec -T app composer audit
   ```
2. **Audit des dépendances JavaScript/CSS (AssetMapper Importmap) :**
   ```bash
   docker compose exec -T app bin/console importmap:audit
   ```

Toute vulnérabilité de sévérité haute ou critique bloque immédiatement le pipeline local (`check.php`) et la CI GitHub Actions (`.github/workflows/ci.yml`).

---

## 4. Base de Données PostgreSQL & Identifiants UUIDv7

- **PostgreSQL 16+ Alpine** est l'unique moteur de base de données de référence (service `db`).
- Toutes les clés primaires d'entités Doctrine utilisent exclusivement **UUIDv7** (`Symfony\Component\Uid\Uuid::v7()`) :
  - Tri chronologique natif (préserve la localité des index B-Tree PostgreSQL contrairement à UUIDv4).
  - Protection contre l'énumération prédictible des ressources (IDOR) contrairement aux entiers auto-incrémentés.

---

## 5. Protocole Fail-Safe & Abandon Automatique (Max 3 Essais)

Pour éviter les boucles d'hallucination ou la dégradation du dépôt :
- Lorsque `./bin/agent-verify.sh` échoue, l'agent analyse la sortie et corrige son code.
- Le nombre de tentatives consécutives est plafonné à **3 essais maximum**.
- **Interdictions absolues :**
  - Forcer un commit avec `git commit --no-verify`.
  - Ajouter `@phpstan-ignore` injustifié ou réduire le niveau de `phpstan.neon.dist` (< 8).
  - Supprimer ou désactiver un test PHPUnit en échec.
- **Action imposée au 3e échec :**
  1. Réinitialisation immédiate sur la branche d'intégration :
     ```bash
     git reset --hard origin/develop
     ```
  2. Publication de la trace brute dans l'issue GitHub associée :
     ```bash
     gh issue comment <ID> --body "🚨 [Fail-Safe Agent] Abandon après 3 échecs. Trace : ..."
     ```
  3. Arrêt complet et escalade à l'opérateur humain.

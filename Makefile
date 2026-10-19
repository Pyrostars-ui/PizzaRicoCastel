DOCKER_APP = docker compose exec -T app
CONSOLE    = $(DOCKER_APP) bin/console

.PHONY: check verify test db-fresh worker-reload env-dump ship smoke-worker cs-fix phpstan messenger-consume upgrade-stack eol-check

# Boucle de vérification QA complète (silencieuse en cas de succès)
check:
	$(DOCKER_APP) composer check

# Boucle Fast-Fail ciblée sur les fichiers modifiés (git diff --name-only)
verify:
	./bin/agent-verify.sh

# Tests unitaires et fonctionnels avec isolation transactionnelle (DAMA)
test:
	$(DOCKER_APP) bin/phpunit --no-coverage

# Réinitialisation complète de la base PostgreSQL + migrations + fixtures
db-fresh:
	$(CONSOLE) doctrine:database:drop --if-exists --force
	$(CONSOLE) doctrine:database:create
	$(CONSOLE) doctrine:migrations:migrate --no-interaction --allow-no-migration
	$(CONSOLE) doctrine:fixtures:load --no-interaction

# Rechargement à chaud des workers FrankenPHP sans coupure de service
worker-reload:
	$(DOCKER_APP) frankenphp reload --config /etc/frankenphp/Caddyfile --adapter caddyfile --force

# Compilation statique des variables d'environnement pour la production (.env.local.php)
env-dump:
	$(DOCKER_APP) composer dump-env prod

# Pipeline de livraison Gitflow automatisé : make ship TYPE=feat NAME=auth MSG="add login voter"
ship:
	./bin/git-ship.sh "$(TYPE)" "$(NAME)" "$(MSG)"

# Test de non-régression et de fuite mémoire FrankenPHP (50 requêtes HTTP consécutives)
smoke-worker:
	./bin/smoke-worker.sh

# Formatage automatique du code PHP et des templates Twig
cs-fix:
	$(DOCKER_APP) vendor/bin/php-cs-fixer fix
	$(DOCKER_APP) vendor/bin/twig-cs-fixer fix templates/

# Analyse statique PHPStan niveau 8 strict
phpstan:
	$(DOCKER_APP) vendor/bin/phpstan analyse --level=8 --no-progress

# Consommateur de messages asynchrones Symfony Messenger
messenger-consume:
	$(CONSOLE) messenger:consume async --memory-limit=128M --time-limit=3600

# Contrôle dynamique Zero EOL via https://endoflife.date/api/{php,symfony}.json
eol-check:
	$(DOCKER_APP) php scripts/check-eol.php

# Cycle de maintenance : vérification EOL, mise à jour Symfony, Rector et validation QA
upgrade-stack:
	$(DOCKER_APP) php scripts/check-eol.php
	$(DOCKER_APP) composer update "symfony/*" --with-all-dependencies
	$(DOCKER_APP) vendor/bin/rector process
	$(DOCKER_APP) composer check

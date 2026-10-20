# Guide Pratique : FrankenPHP Worker Mode & Gestion de la Persistance Mémoire

Ce document détaille l'architecture d'exécution sous **FrankenPHP Worker Mode** et les règles impératives pour éviter les fuites mémoire et la contamination d'état entre requêtes HTTP.

---

## 1. Architecture du Kernel Persistant

Contrairement au modèle PHP-FPM classique (où tout l'environnement est détruit à la fin de chaque requête HTTP), **FrankenPHP Worker Mode** démarre l'application Symfony une seule fois :

1. Le conteneur d'injection de dépendances et le Kernel Symfony sont initialisés en RAM au lancement du worker (`public/index.php` via `Runtime\FrankenPhpSymfony\Runtime`).
2. Une boucle d'événements native (`frankenphp_handle_request()`) intercepte chaque requête HTTP entrante et l'achemine directement au Kernel déjà chargé.
3. À l'issue de la réponse HTTP, Symfony déclenche le `ServicesResetter` (tag `kernel.reset`) sans détruire les instances de services.

---

## 2. Les Pièges Critiques à Proscrire Absolument

### A. Variables et Propriétés Statiques Mutables
Toute propriété `static` modifiée au cours d'une requête **conserve sa valeur pour toutes les requêtes suivantes** exécutées par le même worker, y compris pour des utilisateurs différents.

```php
// ❌ INTERDIT : Fuite de données inter-utilisateurs et croissance mémoire infinie
final class BadContextService
{
    private static array $cache = [];
    private static ?User $currentUser = null;
}
```

### B. Accumulation d'État dans les Services Singletons
Par défaut, tous les services du conteneur Symfony sont partagés (singletons à l'échelle du worker). Un tableau interne `$this->items[]` qui n'est jamais vidé provoquera :
- Des effets de bord métier (données de la requête N-1 visibles dans la requête N).
- Une fuite mémoire progressive (OOM Kill du conteneur).

### C. Références Directes à `Request` ou `UserInterface` en Propriété
Ne jamais stocker l'objet `Request` ou l'entité `User` courante dans une propriété de service sans nettoyage explicite : privilégier `RequestStack::getCurrentRequest()` et `Security::getUser()` à la volée.

---

## 3. Implémentation Obligatoire de `ResetInterface` (`kernel.reset`)

Tout service qui doit légitimement conserver un état temporaire (cache en mémoire le temps d'une requête, buffer de logs, accumulateur de métriques, état de client HTTP) **DOIT** implémenter `Symfony\Contracts\Service\ResetInterface`.

Grâce à l'autoconfiguration Symfony (`_defaults: autoconfigure: true`), toute classe implémentant `ResetInterface` reçoit automatiquement le tag `kernel.reset` et voit sa méthode `reset()` appelée après chaque requête HTTP.

```php
<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\Service\ResetInterface;

final class PricingCalculationCache implements ResetInterface
{
    /** @var array<string, int> */
    private array $computedPrices = [];

    public function getOrCompute(string $sku, callable $computer): int
    {
        return $this->computedPrices[$sku] ??= (int) $computer();
    }

    /**
     * Appelé automatiquement par Symfony à la fin de chaque requête HTTP (tag kernel.reset).
     */
    public function reset(): void
    {
        $this->computedPrices = [];
    }
}
```

Si un service tiers ne peut pas implémenter `ResetInterface`, déclarer explicitement le tag `kernel.reset` dans `config/services.yaml` :

```yaml
services:
    App\Integration\ExternalSdkClient:
        tags:
            - { name: 'kernel.reset', method: 'clearState' }
```

---

## 4. Doctrine ORM & Connexions PostgreSQL en Worker Mode

- Le `Registry` Doctrine implémente nativement `ResetInterface` et appelle `$entityManager->clear()` entre deux requêtes, détachant toutes les entités chargées.
- **Conséquence :** ne jamais conserver une entité Doctrine dans une propriété d'un service personnalisé d'une requête à l'autre (elle serait détachée de l'`EntityManager`).
- Le recyclage préventif des workers est assuré dans `docker/Caddyfile` via `env MAX_REQUESTS {$MAX_REQUESTS:500}` afin d'absorber toute micro-fuite interne aux extensions C.

---

## 5. Vérification & Commandes d'Exploitation

1. **Rechargement à chaud des workers (après modification de code ou de config) :**
   ```bash
   docker compose exec -T app frankenphp reload --config /etc/frankenphp/Caddyfile --adapter caddyfile --force
   # ou : make worker-reload
   ```
2. **Test de non-régression et de fuite mémoire (50 requêtes consécutives) :**
   ```bash
   ./bin/smoke-worker.sh
   # ou : make smoke-worker
   ```
   Ce script exécute un warm-up initial puis 50 requêtes HTTP consécutives tout en mesurant l'empreinte mémoire RSS du processus `frankenphp` pour certifier que `ResetInterface` stabilise la RAM.

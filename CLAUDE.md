# RLSQ-FRAM

Framework PHP inspiré de Symfony, construit from scratch, avec frontend Svelte + Vite.

## Architecture

```
RLSQ-FRAM/
├── public/index.php          Front controller PHP (point d'entrée HTTP)
├── bin/console               Point d'entrée CLI
├── src/RLSQ/                 Code source du framework (namespace RLSQ\)
│   ├── HttpFoundation/       Request, Response, ParameterBag, HeaderBag
│   ├── EventDispatcher/      Pattern Observer pour le cycle de vie
│   ├── Routing/              Matching URL → contrôleur
│   ├── HttpKernel/           Cœur : orchestre Request → Response
│   ├── DependencyInjection/  Service Container, autowiring
│   ├── Config/               Chargement YAML/PHP
│   ├── Controller/           AbstractController, raccourcis
│   ├── Templating/           Moteur de templates (type Twig)
│   ├── Console/              Commandes CLI
│   ├── Database/             DBAL + ORM (type Doctrine)
│   ├── Security/             Auth, firewall, voters
│   └── Form/                 Formulaires, validation
├── src/App/                  Code applicatif (contrôleurs, entités…)
├── frontend/                 Application Svelte + Vite
│   ├── src/                  Composants Svelte
│   ├── vite.config.js        Config Vite (build → public/build/)
│   └── package.json
├── config/                   Fichiers de configuration
├── templates/                Templates PHP/moteur custom
├── tests/                    Tests PHPUnit (miroir de src/)
└── var/                      Cache, logs (gitignored)
```

## Conventions

- **PHP 8.2+** minimum, `declare(strict_types=1)` dans chaque fichier
- **PSR-4** autoloading : `RLSQ\` → `src/RLSQ/`, `App\` → `src/App/`
- **PSR-12** coding style
- **Interfaces d'abord** : toujours définir l'interface avant l'implémentation
- **Tests** : chaque composant doit avoir ses tests PHPUnit dans `tests/`

## Commandes

```bash
# Backend PHP
composer install              # Installer les dépendances PHP
composer test                 # Lancer les tests PHPUnit
composer dev                  # Serveur de dev PHP (localhost:8000)

# Frontend Svelte
cd frontend && npm install    # Installer les dépendances JS
cd frontend && npm run dev    # Serveur Vite dev (localhost:5173)
cd frontend && npm run build  # Build prod → public/build/
```

## Intégration Frontend ↔ Backend

- **Dev** : Vite tourne sur `:5173`, proxy `/api` vers PHP sur `:8000`
- **Prod** : `npm run build` compile dans `public/build/`, PHP sert les assets via un helper Vite (manifest.json)
- Les routes API PHP sont préfixées `/api/`
- Le frontend Svelte consomme les API en JSON (`JsonResponse`)

## Plan d'implémentation

Phases 0→12 progressives. Voir la conversation initiale pour le détail complet.
Les phases critiques (0-5) doivent être terminées avant les phases avancées (8-12).

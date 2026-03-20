# RLSQ-FRAM

**Plateforme no-code/low-code multi-tenant** construite sur un framework PHP from scratch inspire de Symfony, avec frontend **Svelte 5 + TailwindCSS**.

[![Tests](https://img.shields.io/badge/tests-620%20passed-brightgreen)]()
[![Assertions](https://img.shields.io/badge/assertions-1341-blue)]()
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4)]()
[![Svelte](https://img.shields.io/badge/Svelte-5-FF3E00)]()
[![License](https://img.shields.io/badge/license-MIT-green)]()

---

## Vue d'ensemble

RLSQ-FRAM est a la fois :

1. **Un framework PHP complet** (17 composants) construit from scratch, inspire de Symfony
2. **Une plateforme no-code multi-tenant** permettant de construire des applications web visuellement

```
                    +---------------------------------------------+
                    |          RLSQ Platform (SaaS)               |
                    |                                             |
                    |  +----------+  +----------+  +----------+  |
                    |  | Tenant A |  | Tenant B |  | Tenant C |  |
                    |  | (Feder.) |  | (Club X) |  | (Club Y) |  |
                    |  |  Own DB  |  |  Own DB  |  |  Own DB  |  |
                    |  +----------+  +----------+  +----------+  |
                    |                                             |
                    |  +-------------------------------------+    |
                    |  |       Base Plateforme (master)       |    |
                    |  |  tenants, users, plugins, versions   |    |
                    |  +-------------------------------------+    |
                    +---------------------------------------------+
                                       |
                    +--------------------------------------+
                    |   Frontend : Svelte 5 + TailwindCSS   |
                    |   3 dashboards : Org / Club / Membre  |
                    |   Editeurs visuels : DB, Form, Page   |
                    +--------------------------------------+
```

---

## Stack technique

| Couche | Technologie |
|--------|-------------|
| Backend | PHP 8.2+ (framework RLSQ-FRAM custom) |
| Frontend | Svelte 5 + TailwindCSS + Vite |
| Base de donnees | SQLite / MySQL / PostgreSQL (1 DB par tenant) |
| Auth | JWT (HS256) + 2FA par email |
| Paiement | Stripe, PayPal, Moneris, Global Payments |
| API | REST + OpenAPI (Swagger UI) + GraphQL (GraphiQL) |
| Tests | PHPUnit (620 tests, 1341 assertions) |

---

## Installation

### Prerequis

- PHP 8.2+ avec extensions : pdo_sqlite, mbstring, intl, curl
- Composer 2.x
- Node.js 18+

### Installation

```bash
# Cloner
git clone https://github.com/dasdi2020/RLSQ-FRAM.git
cd RLSQ-FRAM

# Backend
composer install

# Frontend
cd frontend
npm install
cd ..

# Executer les migrations
php bin/console migration:run

# Demarrer les serveurs de dev
composer dev          # PHP sur localhost:8000
cd frontend && npm run dev  # Vite sur localhost:5173
```

### Credentials par defaut

- **Email** : `admin@rlsq-fram.local`
- **Mot de passe** : `admin123`
- Le code 2FA est visible dans `var/mail_log/`

---

## Framework RLSQ-FRAM (17 composants)

Le framework est entierement construit from scratch sans dependance externe (sauf PHPUnit pour les tests).

| Composant | Description |
|-----------|-------------|
| **HttpFoundation** | Request, Response, JsonResponse, RedirectResponse, Session, Cookie, FileBag |
| **EventDispatcher** | Pattern Observer avec priorites, subscribers, propagation stoppable |
| **Routing** | Route matching avec parametres `{id}`, regex, generation d'URLs, attribut `#[Route]` |
| **HttpKernel** | Cycle Request -> Response avec 7 evenements (request, controller, view, response, exception, terminate) |
| **DI Container** | Autowiring, tags, compiler passes, factories, alias, parametres `%param%` |
| **Config** | Parser YAML maison, loaders PHP/YAML, TreeBuilder pour validation, ConfigCache |
| **Controller** | AbstractController avec raccourcis, `#[Route]` avec prefixe classe, ValueResolvers |
| **Templating** | Moteur type Twig : `{{ }}`, `{% if/for/block/extends/include %}`, 16 filtres, heritage |
| **Console** | Application CLI, commandes, arguments/options, Table helper |
| **Database/ORM** | Connection PDO multi-driver, QueryBuilder, EntityManager, UnitOfWork, mapping par attributs PHP 8 |
| **Security** | JWT, 2FA, Firewall, `#[IsGranted]`, `#[RequireAuth]`, Voters, Argon2id |
| **Form** | FormBuilder, 12 types de champs, validation, binding objet, rendu HTML |
| **Mailer** | Email builder, transports (SMTP/Log/Null), queue filesystem, commandes CLI |
| **Dotenv** | `.env` -> `.env.local` -> `.env.{APP_ENV}`, interpolation `${VAR}` |
| **Profiler** | Web Debug Toolbar avec onglets (Request, Routing, Performance, Events, Mailer) |
| **OpenAPI** | Generation automatique de spec OpenAPI 3.0, Swagger UI |
| **GraphQL** | Schema, types, queries, mutations, Executor, GraphiQL |

### ValueResolvers (injection automatique dans les controleurs)

```php
#[Route('/article/{id}')]
public function show(
    Article $article,        // Entity auto-loaded depuis {id}
    Mailer $mailer,          // Service injecte depuis le Container
    Request $request,        // Requete HTTP
    int $page = 1            // Parametre avec defaut
): Response {
    // Tout est resolu automatiquement
}
```

---

## Plateforme No-Code (Phases 0-10)

### Phase 0 - Kernel + Auth

- **Kernel applicatif** avec bootstrap via ContainerBuilder
- **JWT** (access 15min + refresh 7j) + **2FA par email** (code 6 chiffres, 10min TTL)
- **Systeme de migrations** (run, rollback, status)
- **Frontend** Svelte 5 avec login 2FA et dashboard admin

### Phase 1 - Multi-Tenant

- **Database-per-tenant** : chaque organisation a sa propre base de donnees
- **TenantResolver** : resolution par header `X-Tenant-Slug`, URL `/t/{slug}/`, ou sous-domaine
- **Provisioning automatique** : creation de la DB + migrations de base
- **8 endpoints API** pour l'administration des tenants

### Phase 2 - Visual Database Builder

- **Schema Builder** : creer des tables, colonnes (12 types), relations visuellement
- **Dynamic CRUD auto-genere** pour chaque table : filtres, recherche, tri, pagination
- **Synchronisation** meta-tables -> DDL physique (CREATE TABLE, ALTER TABLE)
- **Export CSV/JSON** des donnees
- **Frontend** : editeur visuel avec type picker, dialog de creation

### Phase 3 - Systeme de Plugins

- **Architecture plugin** extensible : `PluginInterface`, `PluginRegistry`, `PluginManager`
- **5 plugins core** :
  - **Formations** : gestion des formations + inscriptions + paiement
  - **Activites** : evenements + sessions recurrentes + inscriptions
  - **Calendrier** : calendrier partage avec evenements publics/prives
  - **Location de salles** : salles + reservations + tarification
  - **Paiements** : systeme de paiement multi-providers (voir Phase 6)
- **Installation/desinstallation** par tenant avec creation/suppression des tables
- **Frontend** : Plugin Store style app store avec activation en un clic

### Phase 4 - Dashboards Multi-Niveaux

- **3 dashboards** par role :
  - **Federation** (admin) : statistiques globales, membres, clubs
  - **Club** (admin club) : statistiques du club, ses membres
  - **Membre** : espace personnel
- **Widgets dynamiques** : Counter, DataTable, Chart, Welcome
  - Donnees resolues en temps reel depuis les tables du tenant
- **Sidebar dynamique** avec menus des plugins actifs
- **Hierarchie des roles** : SUPER_ADMIN > FEDERATION_ADMIN > CLUB_ADMIN > MEMBER

### Phase 5 - Visual Form Builder

- **12 types de champs** : text, textarea, email, number, phone, date, select, checkbox, radio, file, richtext, hidden
- **Proprietes par champ** : required, visible, readonly, width (grille 1-12), placeholder, help_text, validation
- **Regles de visibilite conditionnelle** : `show_if: {field: "x", operator: "equals", value: "y"}`
- **Liaison table** : les soumissions inserent automatiquement dans les tables dynamiques
- **Apercu live** du formulaire
- **Soumissions** avec validation et historique pagine

### Phase 6 - Systeme de Paiement

- **4 gateways** : Stripe, PayPal, Moneris, Global Payments
- **Mode test** integre pour chaque gateway
- **Remboursements** : partiels et totaux avec tracking automatique du statut
- **Abonnements** : creation, annulation, renouvellement automatique, suivi des periodes
- **Webhooks** : endpoint par gateway avec verification de signature
- **Statistiques** : revenue totale, paiements completes/en attente/echoues/rembourses
- **Configuration** : credentials par gateway (masques dans l'API), gateway par defaut

### Phase 7 - Page Builder

- **12 types de composants** : heading (H1-H6), text, image, button, card, divider, spacer, HTML, form, datatable, iframe, richtext
- **Editeur visuel** avec grille 12 colonnes, drag-and-drop, preview inline
- **Preview responsive** : desktop, tablet, mobile (dans un iframe)
- **Export Svelte** : genere un fichier `.svelte` pret a l'emploi
- **Duplication** de pages avec tous les composants
- **Styles inline** : backgroundColor, color, padding, margin, borderRadius, textAlign

### Phase 8 - Versioning + Deploiement

- **Snapshots** : capture l'etat complet du tenant (12 tables)
- **Restauration** : rollback vers n'importe quelle version
- **Diff** : compare deux versions et reporte les changements
- **Generateur standalone** : cree un projet PHP + Svelte autonome deployable
- **Deploiement Plesk** : via API REST (domaine, PHP, fichiers, DB, SSL Let's Encrypt)

### Phase 9 - Systeme d'Embed (iframe)

- **Snippet JS** a copier-coller : cree un iframe avec auto-resize
- **4 modules rendus** : formations (cartes), activites, calendrier, salles
- **Securite** : token unique 64 chars, domaines autorises (exact, wildcard), CORS
- **Communication parent-iframe** : postMessage pour resize et callbacks de paiement
- **Theming** : couleurs, police, fond personnalisables par embed

### Phase 10 - Features Additionnelles

| Feature | Description |
|---------|-------------|
| **Audit Logs** | Tracabilite complete : qui a fait quoi, quand, avec quels changements |
| **Webhooks** | Endpoints sortants avec signature HMAC-SHA256, historique des livraisons |
| **Media Manager** | Upload de fichiers, dossiers, organisation par date |
| **i18n** | Traductions key-value, parametres `{name}`, multi-locale (FR/EN) |
| **Themes** | Branding par tenant : couleurs, logo, favicon, CSS custom |
| **Roles & Permissions** | Builder visuel : 30+ permissions, roles custom |
| **Workflow Engine** | Declencheurs, conditions (7 operateurs), actions (email, webhook, notification) |
| **Notifications** | Temps reel via SSE, unread count, mark as read |
| **Rate Limiter** | Protection anti-abus par IP (configurable requests/window) |
| **Import/Export** | CSV et JSON, mapping de colonnes, validation |
| **Backup/Restore** | Dump SQL + JSON par tenant, restauration complete |

---

## API Endpoints

### Authentification

| Methode | URL | Description |
|---------|-----|-------------|
| POST | `/api/auth/login` | Login (email/password) -> code 2FA |
| POST | `/api/auth/verify-2fa` | Verifier code -> JWT |
| POST | `/api/auth/refresh` | Refresh token |
| GET | `/api/auth/me` | Profil utilisateur |

### Administration

| Methode | URL | Description |
|---------|-----|-------------|
| CRUD | `/api/admin/tenants` | Gestion des tenants |
| POST | `/api/admin/tenants/{id}/provision` | Provisionner la DB |
| CRUD | `/api/admin/tenants/{id}/versions` | Versioning |
| POST | `/api/admin/tenants/{id}/generate` | Generer standalone |
| POST | `/api/admin/tenants/{id}/deploy` | Deployer sur Plesk |

### Par Tenant (`/api/t/{slug}/...`)

| Methode | URL | Description |
|---------|-----|-------------|
| CRUD | `/schema/tables` | Database Builder |
| CRUD | `/data/{table}` | CRUD dynamique |
| CRUD | `/forms` | Form Builder |
| CRUD | `/pages` | Page Builder |
| CRUD | `/dashboards` | Dashboards + widgets |
| CRUD | `/plugins` | Gestion des plugins |
| CRUD | `/embeds` | Configuration des embeds |
| CRUD | `/payments` | Paiements + abonnements |
| GET | `/auth/me` | Profil dans le tenant |

### Outils

| URL | Description |
|-----|-------------|
| `/api/docs` | Swagger UI |
| `/api/openapi.json` | Spec OpenAPI 3.0 |
| `/graphql` | Endpoint GraphQL |
| `/graphiql` | Interface GraphiQL |
| `/embed/{token}` | Contenu iframe embed |

---

## Commandes CLI

```bash
php bin/console list                    # Toutes les commandes
php bin/console migration:run           # Executer les migrations
php bin/console migration:status        # Statut des migrations
php bin/console migration:rollback      # Rollback derniere migration
php bin/console mailer:send-test <to>   # Envoyer un email test
php bin/console mailer:queue:status     # Statut de la queue
php bin/console mailer:queue:process    # Traiter la queue
```

---

## Tests

```bash
# Tous les tests
vendor/bin/phpunit

# Un composant specifique
vendor/bin/phpunit --filter="Security"
vendor/bin/phpunit --filter="DatabaseBuilder"
vendor/bin/phpunit --filter="Payment"
```

**620 tests, 1341 assertions** couvrant :
- Framework (17 composants)
- Multi-tenant (CRUD, provisioning, connexions dynamiques)
- Database Builder (schema, DDL, CRUD dynamique, filtres, validation)
- Plugin system (install, uninstall, activate, settings)
- Dashboards (widgets, data resolution)
- Form Builder (champs, validation, soumissions, liaison table)
- Payment system (4 gateways, checkout, refund, subscriptions)
- Page Builder (composants, rendu HTML, export Svelte)
- Versioning (snapshot, restore, diff)
- Embed (CRUD, domain validation, rendering)
- Phase 10 (audit, webhooks, i18n, theme, roles, workflows, notifications, rate limit, import/export, backup)

---

## Structure du projet

```
RLSQ-FRAM/
|-- public/index.php              # Front controller
|-- bin/console                   # CLI
|-- src/
|   |-- RLSQ/                    # Framework (17 composants)
|   |   |-- HttpFoundation/
|   |   |-- EventDispatcher/
|   |   |-- Routing/
|   |   |-- HttpKernel/
|   |   |-- DependencyInjection/
|   |   |-- Config/
|   |   |-- Controller/
|   |   |-- Templating/
|   |   |-- Console/
|   |   |-- Database/
|   |   |-- Security/
|   |   |-- Form/
|   |   |-- Mailer/
|   |   |-- Dotenv/
|   |   |-- Profiler/
|   |   |-- OpenApi/
|   |   |-- GraphQL/
|   |   |-- Plugin/
|   |   +-- Kernel.php
|   +-- App/                      # Code applicatif
|       |-- Controller/           # 10 controleurs API
|       |-- Tenant/               # Multi-tenant system
|       |-- DatabaseBuilder/      # Schema + CRUD dynamique
|       |-- FormBuilder/          # Form builder
|       |-- PageBuilder/          # Page builder
|       |-- Dashboard/            # Dashboard + widgets
|       |-- Versioning/           # Snapshots
|       |-- Deployment/           # Standalone + Plesk
|       |-- Embed/                # Systeme d'embed
|       |-- Plugin/               # 5 plugins core
|       |-- AuditLog/
|       |-- Webhook/
|       |-- Media/
|       |-- I18n/
|       |-- Theme/
|       |-- RolePermission/
|       |-- Workflow/
|       |-- Notification/
|       |-- RateLimit/
|       |-- Backup/
|       |-- ImportExport/
|       +-- Migration/
|-- frontend/                     # Svelte 5 + TailwindCSS
|   |-- src/
|   |   |-- routes/               # Pages SPA
|   |   |-- lib/
|   |   |   |-- components/ui/    # Button, Input, Card, widgets
|   |   |   |-- api/              # Client HTTP avec JWT auto-refresh
|   |   |   +-- stores/           # Auth store (Svelte 5 runes)
|   |   +-- App.svelte            # Router SPA
|   +-- vite.config.js
|-- config/                       # YAML config
|-- templates/                    # Templates moteur custom
|-- tests/                        # 620 tests PHPUnit
|-- .env                          # Variables d'environnement
+-- CLAUDE.md                     # Documentation du projet
```

---

## Variables d'environnement

```env
APP_ENV=dev
APP_DEBUG=true
APP_SECRET=change_me

DATABASE_DRIVER=sqlite
DATABASE_PATH=var/db.sqlite

JWT_SECRET=${APP_SECRET}
JWT_TTL=900              # 15 minutes
JWT_REFRESH_TTL=604800   # 7 jours

MAILER_DSN=null://null
MAILER_FROM=noreply@rlsq-fram.local

# Plesk (optionnel)
PLESK_HOST=
PLESK_LOGIN=
PLESK_PASSWORD=
```

---

## Licence

MIT

---

**Construit avec Claude Code** | Framework PHP from scratch + Plateforme no-code complete

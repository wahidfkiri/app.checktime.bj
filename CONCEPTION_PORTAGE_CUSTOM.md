# Conception logique — Portage des fonctionnalités CheckTime‑Custom vers CheckTime‑Client (multi‑tenant)

> But : intégrer dans **CheckTime‑Client** les fonctionnalités présentes dans **CheckTime‑Custom**, en **conservant l'architecture multi‑client (multi‑tenant)** et l'espace super‑admin. **Hors périmètre : l'assistant d'installation (Installer)** — non requis.
>
> Document de conception (pas d'implémentation ici). Chaque fonctionnalité mono‑tenant de Custom est ré‑exprimée pour le multi‑tenant.

---

## 1. Principes directeurs multi‑tenant (règles d'or, valables pour TOUTE la suite)

1. **Scoping par `client_id`** : toute nouvelle table « métier » porte une colonne `client_id` (FK → `clients`, `onDelete('cascade')`) + index `(client_id, …)`. Exceptions : tables purement globales (catalogue de permissions, sauvegarde BDD globale — voir §5.9).
2. **Résolution du client via `App\Support\CurrentClient`** : jamais `Client::where('user_id', auth()->id())`. Toute lecture/écriture scopée utilise `CurrentClient::get()` / `CurrentClient::id()`. Garantit le fonctionnement de l'**impersonation** super‑admin (« entrer dans un client »).
3. **Placement des routes** :
   - Fonctionnalités d'un client → **groupe métier** `['auth','web','role:super-admin|client','client.active']`.
   - Actions réservées à l'exploitant → **groupe super‑admin** `['auth','web','role:super-admin']` (préfixe `/admin`).
4. **Contraintes d'unicité** : toute unicité « globale » de Custom devient **composite avec `client_id`** (ex. `unique(client_id, date)` pour les jours fériés). Jamais d'unique global sur une donnée tenant.
5. **Suppression d'un client** : `ClientAdminController@destroy` doit nettoyer les nouvelles tables (via cascade FK, ou suppression explicite si pas de FK — cf. signataires).
6. **Observabilité** : chaque écriture significative journalisée avec `client_id` + `user_id` (cf. §5.10).

---

## 2. Décision centrale : modèle de permissions en multi‑tenant

Custom utilise un contrôle d'accès **fin par permission de menu** (`menu.settings`, `menu.email-templates`, `menu.report-templates`, `menu.holidays`, `suivi-ponctualite`, …) via `role_or_permission:…`. Mais Custom est mono‑tenant : ses permissions sont **globales**. En multi‑tenant il faut éviter toute fuite inter‑client.

**Recommandation : garder `teams = false`** (comme aujourd'hui) et appliquer :

- **Catalogue de permissions GLOBAL** (`menu.*`, `suivi-ponctualite`, `attendance-reports`, …) — une permission = une capacité, indépendante du client. Seedé une fois (`MenuPermissionSeeder`).
- **Rôle `client` = socle commun** : reçoit l'ensemble « de base » des permissions de menu (tous les administrateurs de client ont le même socle). Attribué à la création d'un client.
- **Permissions fines par sous‑compte = permissions DIRECTES par utilisateur** (`model_has_permissions`). Elles sont **naturellement isolées** : une permission attachée à l'utilisateur U (du client A) ne concerne que U. Pas de fuite inter‑tenant, sans activer les Teams.
- **Super‑admin = bypass total** : déjà en place via `Gate::before` (AuthServiceProvider). Donc dans les middlewares, `permission:menu.x` suffit — le super‑admin passe toujours.
- **Mapping des middlewares Custom → Client** : `role_or_permission:admin|menu.x` (Custom) ⇒ `permission:menu.x` (Client), le super‑admin étant couvert par `Gate::before`, et `client.active` restant requis pour le groupe métier.

**Option future (si un client doit avoir ses propres RÔLES personnalisés)** : activer Spatie **Teams** avec `team_foreign_key = client_id`. Plus puissant mais impactant (migration `model_has_roles`/`model_has_permissions` + `team_id`, résolution d'équipe = `CurrentClient::id()`). Non retenu en première intention.

---

## 3. Conception par fonctionnalité

Pour chaque fonctionnalité : **table(s)**, **modèle**, **contrôleur**, **routes/groupe**, **scoping**, **permission**, **vues**, **notes**.

### 3.1 Socle — Permissions par menu  *(prérequis des autres)*
- **Tables** : aucune nouvelle (tables spatie existantes). Seeder `MenuPermissionSeeder` (catalogue `menu.*`) + attribution du socle au rôle `client`.
- **Scoping** : permissions globales ; isolation par attribution directe aux sous‑comptes (cf. §2).
- **Impact** : les routes des fonctionnalités ci‑dessous sont protégées par `permission:menu.x`.
- **Nav** : la barre latérale affiche chaque entrée via `@can('menu.x')`.

### 3.2 Gestion des utilisateurs (sous‑comptes d'un client)
- **Existant réutilisé** : table `client_users` déjà présente (lien client ↔ utilisateurs). À auditer pour confirmer qu'elle modélise bien les sous‑comptes.
- **Modèle** : `User` (+ pivot `client_users`) ; un sous‑compte appartient à **un** client.
- **Contrôleur** : `UserController` (CRUD), **scopé `CurrentClient::id()`** : un admin‑client ne voit/gère que les utilisateurs de SON client. Le super‑admin impersonant agit dans le client courant.
- **Permissions** : attribution de permissions `menu.*` **directes** par utilisateur (écran de gestion des droits).
- **Routes** : groupe métier + `permission:menu.users`.
- **Vues** : `resources/views/users/`.
- **Notes** : à la création d'un sous‑compte, assigner le rôle `client` (socle) OU un jeu de permissions directes ; **ne jamais** exposer les utilisateurs d'un autre client.

### 3.3 Jours fériés (Holidays)
- **Table NOUVELLE** : `holidays` (`id`, `client_id` FK cascade, `date`, `label`, `is_recurring?`, timestamps), **`unique(client_id, date)`**.
- **Modèle** : `Holiday` (+ scope `->where('client_id', CurrentClient::id())`).
- **Contrôleur** : `HolidayController` (CRUD) scopé client.
- **Routes** : groupe métier + `permission:menu.holidays`.
- **Vues** : `resources/views/holidays/`.
- **Intégration** : les calculs de présence/ponctualité (ReportController, CustomReportController) doivent exclure les jours fériés **du client courant**.

### 3.4 Gestion des types de congés (Leave types)
- **Existant** : table `leave_types` déjà présente → **ajouter `is_active`** (migration) + colonne `client_id` si absente (à vérifier ; sinon les types sont partagés — décider : par client recommandé).
- **Contrôleur** : `LeaveTypeController` (CRUD + activation/désactivation) scopé client.
- **Routes** : groupe métier + `permission:menu.leave-types`.
- **Notes** : vérifier d'abord si `leave_types` est déjà scopé `client_id` ; sinon migration d'ajout + backfill.

### 3.5 Modèles d'e‑mails (Email templates)
- **Table NOUVELLE** : `email_templates` (`id`, `client_id` FK cascade, `key`/`type`, `subject`, `body`, `is_active`, timestamps), **`unique(client_id, key)`**.
- **Modèle** : `EmailTemplate`.
- **Contrôleur** : `EmailTemplateController` (index/edit/save/reset + upload média). Média stocké sous un chemin **par client** (`storage/app/public/clients/{id}/email-media/`).
- **Routes** : groupe métier + `permission:menu.email-templates`.
- **Intégration** : les envois (rapports RH, SMS récap, etc.) résolvent le template du **client courant**, avec fallback sur un template par défaut si absent.

### 3.6 Modèles de rapports (Report templates)
- **Distinction** : `report_settings` existe déjà (paramètres) — **ne pas confondre** avec `report_templates` (modèles mis en page).
- **Table NOUVELLE** : `report_templates` (`id`, `client_id` FK cascade, `name`, `type`, `content/layout`, `is_default`, timestamps).
- **Contrôleur** : `ReportTemplateController` (index/list/store/edit) scopé client.
- **Routes** : groupe métier + `permission:menu.report-templates`.

### 3.7 Paramètres de notifications (SMTP / SMS / Jobs)
- **Existant** : `settings` / `report_settings` par client. Étendre pour couvrir SMTP + SMS + activation des jobs **par client**.
- **Contrôleur** : `NotificationSettingsController` (`smtp`, `testSmtp`, `sms`, `testSms`, `updateJobs`, `runJob`) scopé client.
- **Routes** : groupe métier + `permission:menu.settings` (sous‑préfixe `settings/notifications`).
- **Notes** : le « test » et le « run now » agissent dans le contexte du client courant ; ne jamais utiliser une config SMTP d'un autre client.

### 3.8 Notifications planifiées (Scheduled notifications)
- **Table NOUVELLE** : `scheduled_notifications` (`id`, `client_id` FK cascade, `type`, `cron`/`frequency`, `run_at`, `is_active`, `last_run_at`, `payload`, timestamps).
- **Modèle** : `ScheduledNotification`.
- **Scheduler** (`app/Console/Kernel.php`) : au lieu d'un `command(...)` fixe, **itérer sur toutes les notifications actives** et exécuter chacune **dans le contexte de son `client_id`** (positionner le client courant, exécuter, nettoyer). Clé : un même job (sync pointage, backup, rapport RH) tourne pour **chaque client**.
- **Seeds** : équivalents de `seed_attendance_sync_scheduled_notification` / `seed_backup_scheduled_notification`, mais **créés par client** (à la création d'un client via `ClientProvisioner`/`ClientController@store`).
- **Routes** : pilotage via §3.7 (`updateJobs`, `runJob`).

### 3.9 Sauvegarde de la base (DB backup)
- **Spécificité multi‑tenant** : la base est **unique et partagée**. Deux niveaux :
  - **Sauvegarde globale** (dump complet) → **réservée au super‑admin**, package `backup_data` + commande `SendDatabaseBackup`. Routes dans le **groupe super‑admin** (`/admin`).
  - **Export par client** (optionnel) → extraction des données d'un seul `client_id` (utile pour livrer/migrer un client). À implémenter en seconde phase.
- **Notes** : ne jamais exposer la sauvegarde globale à un rôle `client`.

### 3.10 Journalisation / Audit (package `journalisation`)
- **Table NOUVELLE** : `activity_logs` (`id`, `client_id` (nullable pour actions super‑admin hors contexte), `user_id`, `action`, `subject_type`, `subject_id`, `properties` JSON, `ip`, `created_at`).
- **Composants** (portés du package Custom, adaptés) : `ActivityLog`, `ActivityLogger`, `ModelActivityObserver`, middleware `LogRequestActivity`, `JournalisationController` + vue.
- **Scoping** : l'observer renseigne `client_id = CurrentClient::id()` à l'écriture ; la **consultation** est filtrée par client (le super‑admin peut voir tout, ou le client courant s'il impersonne).
- **Routes** : consultation dans le groupe métier (`permission:menu.journal`) pour le client ; vue globale dans `/admin` pour le super‑admin.

### 3.11 Workflow d'approbation des permissions employés  *(+ correction d'un bug existant)*
- **Bug actuel Client** : `EmployeePermissionController` écrit `approved_by`, `approved_at`, `rejection_reason` (approve/reject/update) **mais aucune migration ne crée ces colonnes** → erreur SQL « Unknown column » dès qu'on approuve/rejette.
- **Correctif** : migration `add_approval_columns_to_employee_permissions_table` (`approved_by` nullable FK users, `approved_at` nullable timestamp, `rejection_reason` nullable text). Déjà scopé `client_id` (table existante).
- **Priorité** : à traiter **en premier** (c'est un bug bloquant, indépendant du reste).

### 3.12 Robustesse des données
- **`daily_attendances`** : ajouter une **contrainte unique composite** `unique(client_id, employee_id, attendance_date)` (Custom a `dedupe_and_unique`). **Prérequis : dédoublonnage** préalable (script de migration) **par `client_id`**. Aligner l'enum `status` si nécessaire + colonnes manquantes.
- **`attendance_transactions`** : vérifier la présence dans Client ; si absente, créer (`client_id`, `processed_at`, …) alignée sur Custom.
- **`employee_schedules`** : `is_working_day`, et harmonisation `specific_date → schedule_date` si l'écart existe.
- **Règle** : toute contrainte unique inclut `client_id`.

### 3.13 Trait `FiltersActiveEmployees`
- Porter `app/Models/Concerns/FiltersActiveEmployees.php` (filtrage employés actifs) + politique « employés actifs par défaut ». Combiner avec le scoping `client_id` existant sur `Employee`.

---

## 4. Impact sur l'existant
- **Routes** (`routes/web.php`) : ajouter les nouveaux groupes/entrées ; mapper `role_or_permission:admin|menu.x` → `permission:menu.x`.
- **Navigation** (`components/nav-bar.blade.php` / sidebar) : entrées conditionnées par `@can('menu.x')`.
- **Seeders** : `MenuPermissionSeeder` (catalogue + socle rôle `client`) ; étendre `RoleSeeder`.
- **Provisioning client** (`ClientController@store`) : à la création d'un client, créer ses `scheduled_notifications` par défaut, attribuer le rôle `client` + socle de permissions, (option) templates e‑mail/rapport par défaut.
- **Suppression client** (`Admin\ClientAdminController@destroy`) : étendre le nettoyage aux nouvelles tables sans FK cascade.

---

## 5. Phasage recommandé (ordre + dépendances)

| Phase | Lot | Dépend de | Pourquoi d'abord |
|------|-----|-----------|------------------|
| 0 | **Fix colonnes d'approbation** (§3.11) | — | Bug bloquant |
| 1 | **Socle permissions par menu** (§3.1) + nav | — | Prérequis de tout le reste |
| 2 | **Jours fériés** (§3.3), **Types de congés** (§3.4) | 1 | Simples, scopés, fort usage métier |
| 3 | **Gestion utilisateurs** (§3.2) | 1 | Dépend des permissions |
| 4 | **Paramètres notifications** (§3.7) + **Notifications planifiées** (§3.8) | 1 | Socle pour envois/jobs par client |
| 5 | **Modèles e‑mails** (§3.5), **Modèles rapports** (§3.6) | 1,4 | Utilisés par les envois |
| 6 | **Journalisation** (§3.10) | 1 | Transversal |
| 7 | **Sauvegarde BDD** (§3.9) | — | Super‑admin |
| 8 | **Robustesse données** (§3.12), **trait actifs** (§3.13) | — | À planifier (dédoublonnage prudent) |

---

## 6. Migrations à créer (synthèse)
- `add_approval_columns_to_employee_permissions_table`
- `create_holidays_table` (avec `client_id`, `unique(client_id,date)`)
- `add_is_active_to_leave_types_table` (+ `client_id` si manquant)
- `create_email_templates_table` (`client_id`, `unique(client_id,key)`)
- `create_report_templates_table` (`client_id`)
- `create_scheduled_notifications_table` (`client_id`, `run_at`)
- `create_activity_logs_table` (`client_id` nullable, `user_id`)
- `create_attendance_transactions_table` (si absente) / `add_processed_at…`
- `dedupe_and_unique_daily_attendance` (**dédoublonnage par client**, puis `unique(client_id,employee_id,attendance_date)`)
- `add_is_working_day_to_employee_schedules` (si écart)
- (permissions) `seed_menu_permissions` via seeder dédié

---

## 7. Risques & points d'attention
1. **Isolation des permissions** : valider que les droits fins sont attribués **par utilisateur** (directs) et non au rôle global `client`, sinon fuite inter‑client. (Teams si besoin de rôles par client.)
2. **Contraintes uniques** : toujours composites avec `client_id` — une migration oubliée casserait l'isolation ou bloquerait des clients légitimes.
3. **Dédoublonnage `daily_attendances`** : opération destructive → sauvegarde + dédoublonnage **par `client_id`** avant d'ajouter l'unique.
4. **Scheduler par client** : un job qui tournait « une fois » devient « une fois par client » → vérifier charge/temps d'exécution et idempotence.
5. **Sauvegarde** : la sauvegarde globale ne doit **jamais** être accessible à un rôle `client`.
6. **Médias / stockage** : cloisonner par client (`clients/{id}/…`).
7. **Provisioning/suppression client** : ne pas oublier d'initialiser/nettoyer les nouvelles tables.

---

*Document de conception — à valider avant implémentation. L'ordre du §5 permet des livraisons incrémentales et vérifiables.*

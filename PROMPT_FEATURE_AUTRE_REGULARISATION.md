# Prompt — Fonctionnalité « Autre » (régularisation de pointage)

> **Usage** : copier-coller ce fichier comme prompt (à Claude Code ou à un dev) pour
> reproduire la fonctionnalité dans un autre projet CheckTime. Adapter les quelques
> points marqués **[ADAPTER]** selon que le projet est multi-tenant (table `clients`,
> `CurrentClient`) ou mono-tenant.

---

## 1. Objectif

Ajouter, dans le menu **« Gestion des autorisations »**, un sous-menu **« Autre »** permettant de
**régulariser manuellement des pointages** pour un ou plusieurs employés. Deux situations typiques
(mêmes mécanique, seule la **cause** change) :

1. **Coupure d'électricité** : les pointeuses n'ont pas enregistré → saisir la cause, les employés,
   la date, l'heure d'arrivée et l'heure de départ.
2. **Oubli de pointage** : l'employé a oublié de pointer → même formulaire, cause différente.

La **cause** est **sélectionnable** dans une liste déroulante, avec un **bouton « + »** pour en
**ajouter une nouvelle** à la volée.

La régularisation doit se **répercuter dans TOUS les rapports** : pages HTML, exports PDF, et
rapports envoyés par e-mail.

---

## 2. Principe d'intégration (le point clé)

Tous les rapports (HTML / PDF / e-mails) calculent la présence à partir de la table
**`daily_attendances`** (champs : `attendance_date`, `check_in`, `check_out`, `status`, `notes`,
`emp_code`, `employee_id`). 

➡️ **Une régularisation ÉCRIT / MET À JOUR une ligne `daily_attendances`** (statut présent + heures
+ cause en `notes`). Ainsi elle apparaît **automatiquement partout**, sans modifier le code des
rapports. La cause remonte même dans les observations des rapports (qui affichent `notes` pour un
présent).

> **[ADAPTER]** Vérifier les noms exacts des colonnes de `daily_attendances` dans le projet cible
> (ici : `attendance_date`, `check_in` datetime, `check_out` datetime, `work_hours`, `status`
> chaîne libre type `PRESENT`/`ABSENT`, `is_late`, `is_early_leave`, `is_overtime`, `notes`,
> `raw_data`, `emp_code`).

---

## 3. Modèle de données

Créer deux tables (migrations).

### `regularization_causes`
- `id`
- `client_id` **[ADAPTER : uniquement si multi-tenant]** (FK `clients`, cascade)
- `label` (string)
- `is_protected` (boolean, défaut false) — causes par défaut non supprimables
- timestamps
- Unicité : `(client_id, label)` (ou `label` seul en mono-tenant)

### `attendance_regularizations` (journal / traçabilité)
- `id`
- `client_id` **[ADAPTER]**
- `cause_id` (FK `regularization_causes`, cascade)
- `employee_id` (FK `employees`, cascade)
- `date` (date)
- `check_in` (time)
- `check_out` (time)
- `created_by` (FK `users`, nullable, nullOnDelete)
- timestamps

Modèles Eloquent correspondants : `RegularizationCause` (hasMany regularizations) et
`AttendanceRegularization` (belongsTo cause, employee, client).

---

## 4. Backend — `RegularizationController`

> **[ADAPTER]** Scoping : en multi-tenant, scoper TOUTES les requêtes par `CurrentClient::id()`
> (client courant / impersonation super-admin). En mono-tenant, retirer ce scoping.

Causes par défaut garanties (créées à la 1re visite via `firstOrCreate`, `is_protected = true`) :
`"Coupure d'électricité"`, `"Oubli de pointage"`.

Méthodes :

- **`index()`** : renvoie la vue avec : liste des `causes`, liste des `employees` (id, emp_code,
  full_name), et le journal `recent` (50 dernières régularisations avec cause + employé).

- **`checkConflicts(Request)`** — *test temps réel*. Entrée : `date`, `employee_ids[]`.
  Retourne, pour chaque employé ayant **déjà un pointage ce jour-là** (ligne `daily_attendances`
  avec `check_in` non nul OU `status != ABSENT`), ses heures existantes :
  `{ employee_id: { check_in, check_out, status } }`.

- **`store(Request)`** — application. Validation : `cause_id` requis (appartient au client),
  `employee_ids[]` (min 1), `date` requise, `check_in` (H:i), `check_out` (H:i, `after:check_in`),
  `force` (bool).
  Pour chaque employé (dans une transaction) :
  - chercher la ligne `daily_attendances` existante (client + employee + date) ;
  - si un pointage existe **et** `force` est faux → **ignorer** cet employé (le collecter dans
    `skipped`) ;
  - sinon **upsert** `daily_attendances` : `check_in = date + check_in`, `check_out = date +
    check_out`, `work_hours` = écart en heures, `status = 'PRESENT'`, `is_late/is_early_leave/
    is_overtime = false`, `notes = "Régularisation — {cause}"`, `raw_data = {source:'regularization',
    cause, by, at}` ;
  - créer la ligne `attendance_regularizations` (journal).
  Réponse : message récap `applied`/`skipped`.

- **`storeCause(Request)`** (bouton +) : `label` requis, unicité par client (insensible casse),
  crée une cause `is_protected = false`.

- **`destroyCause($id)`** : refuse si `is_protected` ou si utilisée par des régularisations.

---

## 5. Routes

Dans le groupe **`authorizations`** (préfixe `authorizations`, nom `authorizations.`) — qui doit
être derrière l'authentification **[ADAPTER : + `client.active` et `permission:menu.regularizations`
en multi-tenant]** :

```php
Route::prefix('regularizations')->name('regularizations.')
    ->middleware(['client.active', 'permission:menu.regularizations']) // [ADAPTER]
    ->group(function () {
        Route::get('/',              [RegularizationController::class, 'index'])->name('index');
        Route::post('/check',        [RegularizationController::class, 'checkConflicts'])->name('check');
        Route::post('/',             [RegularizationController::class, 'store'])->name('store');
        Route::post('/causes',       [RegularizationController::class, 'storeCause'])->name('causes.store');
        Route::delete('/causes/{id}',[RegularizationController::class, 'destroyCause'])->name('causes.destroy');
    });
```

> **[ADAPTER]** Permissions : si le projet a un système de permissions par menu, ajouter
> `menu.regularizations` au catalogue et l'attribuer au rôle de base. Sinon retirer le
> middleware `permission:`.

---

## 6. Frontend — vue `regularizations/index.blade.php`

Même ergonomie que la page `/authorizations/employee-permissions` : **bouton + formulaire dans une
modal**.

- **Page** : un bouton **« Nouvelle régularisation »** (ouvre la modal) + un **tableau** des
  régularisations récentes (Employé, Date, Horaire, Cause, Enregistré le).

- **Modal** (`modal-lg`), formulaire :
  - **Cause** : `<select>` des causes + **bouton « + »** (ouvre un prompt SweetAlert → POST
    `causes.store` → ajoute l'option et la sélectionne).
  - **Date**, **Heure d'arrivée** (`check_in`), **Heure de départ** (`check_out`).
  - **Employés = liste de CASES À COCHER** (scrollable, `max-height` ~260px) avec :
    - un **champ de filtre** (code / nom) qui masque/affiche les items,
    - une case **« Tout cocher »** (sur les employés visibles),
    - un **compteur** « N sélectionné(s) ».
  - **Panneau de conflits** (`alert alert-warning`, masqué par défaut).

- **Temps réel** : à chaque changement de date ou (dé)cochage (debounce ~250 ms), appeler
  `check` ; marquer en rouge les employés ayant déjà un pointage (petit tag « · pointage
  existant ») et les lister dans le panneau avec leurs heures existantes.

- **Soumission** :
  - si des employés sélectionnés sont en conflit → SweetAlert **3 choix** :
    **Écraser** (`force=1`) / **Ignorer ces employés** (`force=0`) / **Annuler** ;
  - sinon POST direct (`force=0`) ;
  - afficher les erreurs de validation sous les champs ; spinner sur le bouton ; recharger à
    la réussite.

Stack front identique au reste du projet : jQuery + SweetAlert2 + Bootstrap modal (CSRF via
`{{ csrf_token() }}`).

---

## 7. Menu (sidebar)

Dans le sous-menu **« Gestion des autorisations »**, ajouter un item **« Autre »** pointant vers
`authorizations.regularizations.index` **[ADAPTER : conditionné par `@can('menu.regularizations')`
si permissions]**.

```blade
<li class="submenu-item {{ request()->routeIs('authorizations.regularizations.*') ? 'active' : '' }}">
    <a href="{{ route('authorizations.regularizations.index') }}">
        <i class="bi bi-lightning-charge me-2"></i>
        <span>Autre</span>
    </a>
</li>
```

---

## 8. Critères d'acceptation (vérification)

1. Le sous-menu **« Autre »** apparaît sous *Gestion des autorisations*.
2. La modal permet de choisir une **cause** (et d'en **ajouter** via « + »), une **date**, des
   **heures**, et **plusieurs employés par cases à cocher** (avec filtre + tout cocher).
3. En choisissant date + employés, les employés **ayant déjà un pointage** ce jour-là sont
   **signalés en temps réel**.
4. À la validation : création/MAJ des lignes `daily_attendances` ; les conflits sont ignorés sauf
   confirmation d'**écrasement**.
5. La régularisation est **visible dans** : rapport présence/ponctualité (HTML), exports PDF, et
   rapports e-mail — **sans modification du code des rapports** (car ils lisent
   `daily_attendances`). La cause apparaît dans les observations.
6. `php -l` OK sur tous les fichiers ; vues Blade compilent ; routes enregistrées.

---

## 9. Fichiers de référence (implémentation d'origine)

- `app/Http/Controllers/RegularizationController.php`
- `app/Models/RegularizationCause.php`, `app/Models/AttendanceRegularization.php`
- `database/migrations/*_create_regularization_causes_table.php`
- `database/migrations/*_create_attendance_regularizations_table.php`
- `resources/views/regularizations/index.blade.php`
- Ajouts : `routes/web.php` (groupe `authorizations.regularizations.*`),
  `resources/views/components/side-bar.blade.php` (item « Autre »),
  catalogue permissions (`menu.regularizations`).

> Astuce : sur une copie **du même projet**, le plus simple est de **copier ces fichiers** puis
> d'exécuter les migrations. Sur un projet **mono-tenant**, appliquer les **[ADAPTER]** (retirer
> `client_id` / `CurrentClient` / `client.active` / permissions).

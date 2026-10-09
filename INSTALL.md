# Installation

This is SortaFolders v1.0.1, released as `SortaFolders-v1.0.1.zip`.

## Requirements

- Kanboard >= 1.2.54.
- MySQL, MariaDB, or SQLite. The plugin adds four tables, `sortafolders_groups`, `sortafolders_access`, `sortafolders_access_applied` and `sortafolders_notes`, the first time it loads.


## Install

1. Unzip `SortaFolders-v1.0.1.zip` and put the `SortaFolders` folder into `/path/to/kanboard/plugins/`.
2. Reload PHP-FPM if OPcache is on and a changed file doesn't show up, since OPcache can serve the old copy for a few minutes. A first install has no old copy, so it loads right away.
3. As an admin, open *Settings -> SortaFolders* and add a folder.
4. As an admin or project manager, create a new folder from the main header menu or "+" menu, open a project you manage, click "Edit folder" in its settings sidebar, choose the folder, and click "Save".


## Remove

Before you delete the plugin, remove each folder's grants on the admin page's Access dialog. A membership added through SortaFolders remains as an ordinary membership even after the plugin is gone.

Delete `plugins/SortaFolders`. The `sortafolders_groups`, `sortafolders_access`, `sortafolders_access_applied` and `sortafolders_notes` tables, each project's `sortafolders_group` and `sortafolders_applied` metadata, the `sortafolders_` settings, each user's `sortafolders_filter` settings and the plugin's row in `plugin_schema_versions` stay in the database in case SortaFolders is installed again. Projects and tasks aren't affected.

To remove the data for good, run these against Kanboard's database after deleting the plugin folder:

```sql
DROP TABLE sortafolders_groups;
```

```sql
DROP TABLE sortafolders_access;
```

```sql
DROP TABLE sortafolders_access_applied;
```

```sql
DROP TABLE sortafolders_notes;
```

```sql
DELETE FROM project_has_metadata WHERE name = 'sortafolders_group';
```

```sql
DELETE FROM project_has_metadata WHERE name = 'sortafolders_applied';
```

```sql
DELETE FROM settings WHERE `option` LIKE 'sortafolders%';
```

```sql
DELETE FROM user_has_metadata WHERE name LIKE 'sortafolders_filter%';
```

```sql
DELETE FROM plugin_schema_versions WHERE plugin = 'sortafolders';
```


## Compatibility

- No Kanboard core or third-party plugin files are edited and no services are replaced. SortaFolders overrides one core template, `dashboard/layout`, with a copy that adds its bars, so another plugin that overrides the same template conflicts with it (BusyBee is the exception, since it leaves that template alone when SortaFolders is on).
- The JSON-RPC procedures `saveProjectMetadata` and `removeProjectMetadata` are taken over so a project's folder follows the Edit folder page's rule (see the README), and another plugin overriding either one would conflict with SortaFolders.
- It uses the `template:config:sidebar`, `template:project-list:menu:after`, `template:dashboard:sidebar`, `template:dashboard:page-header:menu`, `template:header:creation-dropdown`, `template:header:dropdown`, `template:project:creation:form`, `template:project:sidebar`, `template:dashboard:project:after-title`, `template:project:header:before`, `template:project:header:after`, `template:layout:css` and `template:layout:js` hooks, and it listens for `bigboard:projects:ids`, which a BigBoard with that hook runs. A plugin that attaches to the same hooks shows its content next to SortaFolders' content.
- [BusyBee](https://github.com/christefano/BusyBee), a separate plugin, adds its question bar through the `template:sortafolders:folder:before-tabs` and `template:sortafolders:dashboard:before-tabs` hooks that SortaFolders renders. SortaFolders works without it and shows no bar. Install it from its own folder.
- With a database other than MySQL, MariaDB, or SQLite the plugin logs an error and turns itself off.

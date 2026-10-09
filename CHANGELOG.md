# Changelog

## v1.0.1

- Added screenshots.
- Added a *View folder* link to the folder's bar.
- Updated the breadcrumb in the heading of a folder page with links showing the folder name and its parent folder (if any)
- A project's sidebar has a *View folder* link to the folder the project is in, left out for anyone but an admin when that folder (or one above it) is hidden, since a hidden folder's page isn't found for them
- Hidden folders are hidden from everyone but admins. A hidden folder, its subfolders, and their projects are hidden from *My folders*, the folder pages, the notes pages, any select menus, the attention strip, BigBoard's group switcher and "Selected projects" list (through a new `bigboard:projects:ids` hook that a BigBoard with that hook calls), and the dashboard's *My projects*, *My tasks*, *My subtasks*, and *Overview* lists. The project badge, folder message, and sidebar links are hidden to everyone but admins, a project in a hidden folder has no *Edit folder* page for anyone but an admin, and the API's folder key can't be changed on one.
- mrsuner's BigBoard fork, which looks for the PortfolioDashboard plugin's model, now displays SortaFolders' folders thanks to a class alias. It gets skipped if a plugin called "PortfolioDashboard" is installed.
- Fixed the "Not moved in 30 days" tab not working with SQLite (so it was never dimmed).
- Added CSRF lisks so a filter tab is now saved only when the address carries Kanboard's reusable CSRF token. A filter without the token applies to that page view and isn't saved, so a link on another site can't change a user's saved tab. This covers *My folders*, *My projects*, *My tasks*, and *My subtasks*.
- Added the `projectPaths()` helper, which gives BusyBee the folder path of a project outside its page.
- Added tests: saved filter token, a closed blocker, a finished subtask, a task that moved recently, and BigBoard's label.
- BigBoard's "Ungrouped projects" menu is now "Unsorted projects" with the term an admin sets for folders (e.g. "Unsorted folders").
- Saving a project's folder on its *Edit folder* page opens that folder right away.

## v1.0.0

First initial release.

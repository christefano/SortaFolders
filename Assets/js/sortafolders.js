(function () {
    var KEY = 'sortafolders-closed';

    function read() {
        try {
            var value = JSON.parse(window.localStorage.getItem(KEY) || '[]');
            return Array.isArray(value) ? value : [];
        } catch (e) {
            return [];
        }
    }

    function write(value) {
        try {
            window.localStorage.setItem(KEY, JSON.stringify(value));
        } catch (e) {}
    }

    function init() {
        var closed = read();
        var nodes = document.querySelectorAll('.sortafolders-tree details[data-folder]');

        Array.prototype.forEach.call(nodes, function (node) {
            var id = node.getAttribute('data-folder');

            if (closed.indexOf(id) !== -1) {
                node.removeAttribute('open');
            }

            node.addEventListener('toggle', function () {
                var current = read().filter(function (item) {
                    return item !== id;
                });

                if (!node.open) {
                    current.push(id);
                }

                write(current);
            });
        });
    }

    // The folder message on a project's pages opens and closes the same way, and one choice covers every project in the folder.
    function initMotd() {
        var key = 'sortafolders-motd-closed';
        var notes = document.querySelectorAll('details.sortafolders-motd[data-motd]');

        function list() {
            try {
                var value = JSON.parse(window.localStorage.getItem(key) || '[]');
                return Array.isArray(value) ? value : [];
            } catch (e) {
                return [];
            }
        }

        Array.prototype.forEach.call(notes, function (note) {
            var id = note.getAttribute('data-motd');

            if (list().indexOf(id) !== -1) {
                note.removeAttribute('open');
            }

            note.addEventListener('toggle', function () {
                var current = list().filter(function (item) {
                    return item !== id;
                });

                if (!note.open) {
                    current.push(id);
                }

                try {
                    window.localStorage.setItem(key, JSON.stringify(current));
                } catch (e) {}
            });
        });
    }

    // Kanboard has no hook right under "Edit project", so the project sidebar item is moved there.
    function moveProjectItem() {
        var item = document.querySelector('.sidebar li[data-sortafolders="project-item"]');

        if (!item) {
            return;
        }

        // Shown again whether or not "Edit project" is found, so the item can't stay hidden.
        item.classList.add('sortafolders-placed');

        var links = document.querySelectorAll('.sidebar li > a');

        for (var i = 0; i < links.length; i++) {
            var href = links[i].getAttribute('href') || '';

            if (/\/project\/\d+\/edit(\?|$)|controller=ProjectEditController/.test(href)) {
                links[i].parentNode.parentNode.insertBefore(item, links[i].parentNode.nextSibling);
                return;
            }
        }
    }

    // The avatar menu has no hook around "Projects management": "My folders" goes above it, "Folders management" below.
    function moveUserMenuItem() {
        var first = document.querySelector('li[data-sortafolders="user-menu-first"]');
        var after = document.querySelector('li[data-sortafolders="user-menu-after"]');

        if (!first) {
            return;
        }

        var links = document.querySelectorAll('.dropdown li > a');

        for (var i = 0; i < links.length; i++) {
            var href = links[i].getAttribute('href') || '';

            if (/controller=ProjectListController&action=show\b|\/projects(\?|$)/.test(href) && links[i].parentNode.parentNode === first.parentNode) {
                var li = links[i].parentNode;
                li.parentNode.insertBefore(first, li);

                if (after) {
                    li.parentNode.insertBefore(after, li.nextSibling);
                }

                return;
            }
        }
    }

    // The "+" menu has no hook between "New project" and "New personal project", so "New folder" is moved
    // in front of the personal item (the one with the lock icon). Without one it stays where the hook put it.
    function moveCreationItem() {
        var item = document.querySelector('li[data-sortafolders="creation-item"]');

        if (!item) {
            return;
        }

        var lock = item.parentNode.querySelector('li > a > .fa-lock');

        if (lock) {
            var personal = lock.parentNode.parentNode;
            personal.parentNode.insertBefore(item, personal);
        }
    }

    // The dashboard tabs: "New folder" sits after "New personal project", like the "+" menu.
    function moveTabItem() {
        var item = document.querySelector('li[data-sortafolders="tab-item"]');

        if (!item) {
            return;
        }

        var lock = item.parentNode.querySelector('li > a > .fa-lock');

        if (lock) {
            var personal = lock.parentNode.parentNode;
            personal.parentNode.insertBefore(item, personal.nextSibling);
        }
    }

    // The dashboard tabs: the folders link sits right after "Project management".
    function moveTabFolders() {
        var item = document.querySelector('li[data-sortafolders="tab-folders"]');

        if (!item) {
            return;
        }

        var links = item.parentNode.querySelectorAll('li > a');

        for (var i = 0; i < links.length; i++) {
            if (/controller=ProjectListController&action=show\b|\/projects(\?|$)/.test(links[i].getAttribute('href') || '')) {
                links[i].parentNode.parentNode.insertBefore(item, links[i].parentNode.nextSibling);
                return;
            }
        }
    }

    // Runs in the page head, before the sidebar exists, so the CSS can hide the item until it is placed.
    document.documentElement.classList.add('sortafolders-js');

    function run() {
        init();
        initMotd();
        moveProjectItem();
        moveUserMenuItem();
        moveCreationItem();
        moveTabItem();
        moveTabFolders();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();

<?php

namespace Kanboard\Plugin\SortaFolders;

use Kanboard\Core\Plugin\Base;
use Kanboard\Core\Security\Role;
use Kanboard\Plugin\SortaFolders\Api\MetadataGuard;
use Kanboard\Plugin\SortaFolders\Helper\DashboardHelper;
use Kanboard\Plugin\SortaFolders\Helper\FolderHelper;
use Kanboard\Plugin\SortaFolders\Model\AccessModel;
use Kanboard\Plugin\SortaFolders\Model\FolderModel;
use Kanboard\Plugin\SortaFolders\Model\HealthModel;
use Kanboard\Plugin\SortaFolders\Model\LinkCountModel;
use Kanboard\Plugin\SortaFolders\Model\NoteModel;

class Plugin extends Base
{
    public function initialize()
    {
        // Only MySQL and SQLite have a schema. Without the table every page would fail.
        if (! in_array(DB_DRIVER, array('mysql', 'sqlite'), true)) {
            $this->logger->error('SortaFolders: needs MySQL or SQLite and found '.DB_DRIVER.', so it is off.');
            return;
        }

        $this->container['sortaFolderModel'] = function ($c) {
            return new FolderModel($c);
        };

        $this->container['sortaAccessModel'] = function ($c) {
            return new AccessModel($c);
        };

        $this->container['sortaHealthModel'] = function ($c) {
            return new HealthModel($c);
        };

        $this->container['sortaLinkCountModel'] = function ($c) {
            return new LinkCountModel($c);
        };

        $this->container['sortaNoteModel'] = function ($c) {
            return new NoteModel($c);
        };

        $this->helper->register('sortaFolders', FolderHelper::class);
        $this->helper->register('sortaDashboard', DashboardHelper::class);

        $this->applicationAccessMap->add('FolderAdminController', '*', Role::APP_ADMIN);
        $this->applicationAccessMap->add('FolderAccessController', '*', Role::APP_ADMIN);
        // Creating a folder is open to Project Managers too. The controller checks who may.
        $this->applicationAccessMap->add('FolderAdminController', array('create', 'save'), Role::APP_USER);
        // Any signed-in user may open the notes page. The controller checks who can see the folder and who can write.
        $this->applicationAccessMap->add('NoteController', '*', Role::APP_USER);
        $this->projectAccessMap->add('ProjectFolderController', '*', Role::PROJECT_MANAGER);

        // Core's API lets any project member write project metadata, where the folder lives. A callback
        // is found before core's procedure of the same name, so these two calls check the folder key first.
        $guard = new MetadataGuard($this->container);
        $this->api->getProcedureHandler()
            ->withCallback('saveProjectMetadata', function ($project_id, array $values) use ($guard) {
                return $guard->save($project_id, $values);
            })
            ->withCallback('removeProjectMetadata', function ($project_id, $name) use ($guard) {
                return $guard->remove($project_id, $name);
            });

        // The New project form posts a folder, and core has no event to tell us the project exists.
        // Only the shutdown function is registered here. The controller, the action, and the login
        // are checked inside it, because core's router has not run yet while plugins load.
        $form = $this->request->getRawFormValues();

        if ($this->request->isPost() && ! empty($form['sortafolders_folder_id'])) {
            $container = $this->container;
            $since = time() - 5;

            register_shutdown_function(function () use ($container, $since, $form) {
                try {
                    $userId = (int) $container['userSession']->getId();

                    if ($container['router']->getController() !== 'ProjectCreationController'
                        || $container['router']->getAction() !== 'save'
                        || ! $container['userSession']->isLogged()
                        || $userId <= 0) {
                        return;
                    }

                    $folderId = (int) $form['sortafolders_folder_id'];
                    $options = $container['sortaFolderModel']->getFolderOptionsForUser($userId);

                    // A folder the user cannot see is dropped, so a forged post sorts nothing.
                    if (! isset($options[$folderId])) {
                        return;
                    }

                    $container['sortaFolderModel']->assignNewProject(
                        isset($form['name']) ? (string) $form['name'] : '',
                        $userId,
                        $folderId,
                        $since
                    );
                } catch (\Throwable $e) {
                    $container['logger']->error('SortaFolders: could not sort the new project: '.$e->getMessage());
                }
            });
        }

        $this->hook->on('template:layout:css', array('template' => 'plugins/SortaFolders/Assets/css/sortafolders.css'));
        $this->hook->on('template:layout:js', array('template' => 'plugins/SortaFolders/Assets/js/sortafolders.js'));

        // Pretty URLs. Every other action keeps the query-string form, which still works.
        // The folder pages use the admin's term in the path (/spaces/5), and /folders keeps working. The first route registered is the one links use.
        $terms = FolderHelper::getTerms();
        $plural = $terms[$this->helper->sortaFolders->getTermKey()][1];
        $slugs = array_unique(array(str_replace(' ', '-', $plural), 'folders'));

        foreach ($slugs as $slug) {
            $this->route->addRoute('/'.$slug, 'FolderController', 'show', 'SortaFolders');
            $this->route->addRoute('/'.$slug.'/:folder_id', 'FolderController', 'show', 'SortaFolders');
        }

        $this->route->addRoute('/settings/sortafolders', 'FolderAdminController', 'index', 'SortaFolders');
        $this->route->addRoute('/project/:project_id/folder', 'ProjectFolderController', 'show', 'SortaFolders');

        // The BigBoard links and filter tabs, and the hook BusyBee's question bar renders in, on My projects, My tasks and My subtasks. Core's three
        // pages share one layout, so the plugin ships a copy of it, and each page's own list query takes the filter tab.
        $this->template->setTemplateOverride('dashboard/layout', 'SortaFolders:dashboard/layout');
        $container = $this->container;

        foreach (array('project' => 'projects', 'task' => 'tasks', 'subtask' => 'subtasks') as $hook => $page) {
            $this->hook->on('pagination:dashboard:'.$hook.':query', function ($query) use ($container, $page) {
                $container['helper']->sortaDashboard->narrow($page, $query);
                $container['helper']->sortaDashboard->hide($page === 'projects' ? 'projects.id' : 'tasks.project_id', $query);
            });
        }

        // BigBoard's own hook: the project ids it lists and lets a user select. A project in a folder hidden from the viewer goes.
        $this->hook->on('bigboard:projects:ids', function (&$ids) use ($container) {
            $ids = array_values(array_diff(array_map('intval', $ids), $container['sortaFolderModel']->getHiddenProjectIds()));
        });

        $this->template->hook->attach('template:config:sidebar', 'SortaFolders:hook/config_sidebar');
        $this->template->hook->attach('template:project-list:menu:after', 'SortaFolders:hook/project_list_menu');
        $this->template->hook->attach('template:header:creation-dropdown', 'SortaFolders:hook/creation_item');
        $this->template->hook->attach('template:header:dropdown', 'SortaFolders:hook/user_dropdown');
        $this->template->hook->attach('template:dashboard:sidebar', 'SortaFolders:hook/dashboard_sidebar');
        $this->template->hook->attach('template:dashboard:page-header:menu', 'SortaFolders:hook/dashboard_header');
        $this->template->hook->attach('template:project:creation:form', 'SortaFolders:hook/project_creation_form');
        $this->template->hook->attach('template:project:sidebar', 'SortaFolders:hook/project_sidebar');
        $this->template->hook->attach('template:dashboard:project:after-title', 'SortaFolders:hook/badge');
        $this->template->hook->attach('template:project:header:before', 'SortaFolders:hook/badge');
        $this->template->hook->attach('template:project:header:after', 'SortaFolders:hook/motd');
    }

    /**
     * BigBoard's own strings ("Portfolio group", "Selected projects") follow the selected term without BigBoard knowing, and "Ungrouped projects" becomes "Unsorted projects" with every term.
     * Translator::$locales is public and checked before the English text, so adding a key here wins.
     * mrsuner's BigBoard reads its group list from PortfolioDashboard's PortfolioProjectModel, so that name is made to
     * point at FolderModel, which has the same getProjectGrouping(), unless PortfolioDashboard is really installed.
     */
    public function onStartup()
    {
        if (! in_array(DB_DRIVER, array('mysql', 'sqlite'), true) || ! isset($this->container['sortaFolderModel'])) {
            return;
        }

        $terms = FolderHelper::getTerms();
        $key = $this->helper->sortaFolders->getTermKey();
        $singular = $terms[$key][0];
        $plural = $terms[$key][1];

        $portfolioModel = 'Kanboard\\Plugin\\PortfolioDashboard\\Model\\PortfolioProjectModel';

        if (! class_exists($portfolioModel)) {
            class_alias('Kanboard\\Plugin\\SortaFolders\\Model\\FolderModel', $portfolioModel);
        }

        \Kanboard\Core\Translator::$locales['Selected projects'] = 'Selected '.$plural;
        \Kanboard\Core\Translator::$locales['Ungrouped projects'] = 'Unsorted projects';
        \Kanboard\Core\Translator::$locales['Portfolio group'] = ucfirst($plural);
        \Kanboard\Core\Translator::$locales['Portfolio groups'] = ucfirst($plural);
        \Kanboard\Core\Translator::$locales['No accessible projects are available in this portfolio group.'] = 'No accessible projects are available in this '.$singular.'.';
    }

    public function getPluginName()
    {
        return 'SortaFolders';
    }

    public function getPluginDescription()
    {
        return t('Sort of sorts projects into nested folders');
    }

    public function getPluginAuthor()
    {
        return 'Christefano Reyes';
    }

    public function getPluginVersion()
    {
        return '1.0.2';
    }

    public function getPluginHomepage()
    {
        return 'https://github.com/christefano/SortaFolders';
    }

    public function getCompatibleVersion()
    {
        return '>=1.2.54';
    }
}

<?php

namespace Kanboard\Plugin\SortaFolders\Helper;

use Kanboard\Core\Base;
use Kanboard\Plugin\SortaFolders\Controller\FolderController;
use Kanboard\Plugin\SortaFolders\Model\LinkCountModel;

/**
 * Registered as $this->sortaDashboard. The BigBoard links and the filter tabs on core's My projects, My tasks and
 * My subtasks pages, and the scope BusyBee's question bar reads. Core's pages share one layout, which the plugin's
 * copy of it extends with these bars, so every method first asks which of the three pages is open.
 */
class DashboardHelper extends Base
{
    const FILTER_KEY = 'sortafolders_filter_';  // plus the page: projects, tasks or subtasks

    private $filters = array();
    private $scopes = array();

    const PAGES = array('projects', 'tasks', 'subtasks');

    /**
     * The page open now: "projects", "tasks", "subtasks", or "" for anything else.
     */
    public function page()
    {
        if ($this->router->getController() !== 'DashboardController') {
            return '';
        }

        $action = $this->router->getAction();

        return in_array($action, self::PAGES, true) ? $action : '';
    }

    /**
     * Whether core's Overview page is open, which has no tab bar but renders the question bar's hook.
     */
    public function isOverview()
    {
        return $this->router->getController() === 'DashboardController' && $this->router->getAction() === 'show';
    }

    /**
     * The user whose dashboard is open: the user_id in the address, else the signed-in user.
     */
    public function pageUserId()
    {
        $id = $this->request->getIntegerParam('user_id');

        return $id > 0 ? $id : (int) $this->userSession->getId();
    }

    /**
     * Narrows the tasks to the page's user, in the form the answers and the tab counts read: null on My projects (every task
     * in the projects), array('tasks' => id) on My tasks (tasks the user owns or has a subtask in) and array('subtasks' => id)
     * on My subtasks (tasks with an open subtask assigned to the user).
     */
    public function mine($page, $userId)
    {
        return array('tasks' => array('tasks' => $userId), 'subtasks' => array('subtasks' => $userId))[$page] ?? null;
    }

    /**
     * The filter tabs the page offers: key => label. All three pages keep the whole bar, so the tabs look and sit the same as on
     * the folder page. A tab with nothing behind it is dimmed.
     */
    public function filters($page)
    {
        return FolderController::getFilters();
    }

    /**
     * The filter tab in force, read from the address and kept in the user's metadata for each page, so the
     * paginator's own Previous and Next links, which drop it, keep the list narrowed.
     */
    public function filter($page)
    {
        if (isset($this->filters[$page])) {
            return $this->filters[$page];
        }

        $viewer = (int) $this->userSession->getId();
        $known = array_keys($this->filters($page));
        $asked = $this->request->getStringParam('filter', '');

        if ($asked !== '' && in_array($asked, $known, true)) {
            if ($asked === 'all') {
                $this->userMetadataModel->remove($viewer, self::FILTER_KEY.$page);
            } else {
                $this->userMetadataModel->save($viewer, array(self::FILTER_KEY.$page => $asked));
            }

            return $this->filters[$page] = $asked;
        }

        $saved = (string) $this->userMetadataModel->get($viewer, self::FILTER_KEY.$page, 'all');

        return $this->filters[$page] = (in_array($saved, $known, true) ? $saved : 'all');
    }

    /**
     * Applied to core's list query by the plugin's pagination hooks. Does nothing on any other page. My projects keeps the projects
     * a tab keeps. On My tasks and My subtasks the tabs about due dates, owner, links and movement read the task, and Quiet
     * and Needs attention keep the tasks of the projects they keep.
     */
    public function narrow($page, $query)
    {
        if ($this->page() !== $page || $this->filter($page) === 'all') {
            return;
        }

        $filter = $this->filter($page);
        $settings = $this->sortaHealthModel->getSettings();
        $viewer = (int) $this->userSession->getId();

        if ($page === 'projects' || $filter === 'quiet' || $filter === 'attention') {
            $ids = array_keys($this->sortaHealthModel->filterProjects($this->projects($this->pageUserId()), $filter, $settings, $viewer));
            $query->in($page === 'projects' ? 'projects.id' : 'tasks.project_id', empty($ids) ? array(0) : $ids);

            return;
        }

        $now = time();
        $user = $this->pageUserId();

        switch ($filter) {
            case 'overdue':
                $query->gt('tasks.date_due', 0)->lt('tasks.date_due', $now);
                break;
            case 'today':
                $query->gte('tasks.date_due', strtotime('today'))->lt('tasks.date_due', strtotime('tomorrow'));
                break;
            case 'soon':
                $query->gte('tasks.date_due', $now)->lte('tasks.date_due', $now + (int) $settings['soon_days'] * 86400);
                break;
            case 'mine':
                $query->eq('tasks.owner_id', $user);
                break;
            case 'unowned':
                $query->eq('tasks.owner_id', 0);
                break;
            case 'blocked':
                $query->inSubquery('tasks.id', $this->db->table('task_has_links')->columns('task_id')->in('link_id', $this->db->table('links')->columns('id')->eq('label', 'is blocked by')));
                break;
            case 'stuck':
                $query->addCondition('(CASE WHEN tasks.date_moved > 0 THEN tasks.date_moved ELSE tasks.date_creation END) BETWEEN 1 AND '.($now - LinkCountModel::STUCK_DAYS * 86400));
                break;
        }
    }

    /**
     * The user's active projects with the totals and health light the tabs read ("sf_totals" and "sf_health").
     */
    public function projects($userId)
    {
        $settings = $this->sortaHealthModel->getSettings();
        $projects = $this->scope($userId);
        $totals = $this->sortaFolderModel->getTaskTotals(array_keys($projects), $settings['soon_days'], (int) $this->userSession->getId());
        $empty = array('open' => 0, 'overdue' => 0, 'soon' => 0, 'closed' => 0, 'today' => 0, 'unowned' => 0, 'stuck' => 0, 'blocked' => 0, 'mine' => 0, 'subtasks' => 0, 'hours' => 0, 'estimated' => 0);

        foreach ($projects as $id => $project) {
            $projects[$id]['sf_totals'] = isset($totals[$id]) ? $totals[$id] : $empty;
            $projects[$id]['sf_health'] = $this->sortaHealthModel->assess($projects[$id]['sf_totals'], (int) $project['last_modified'], $settings);
        }

        return $projects;
    }

    /**
     * The user's active projects keyed by id, each with "sf_path", the folder path the answer rows show.
     */
    public function scope($userId)
    {
        if (isset($this->scopes[(int) $userId])) {
            return $this->scopes[(int) $userId];
        }

        $projects = array();

        foreach ($this->projectModel->getAllByIds($this->projectPermissionModel->getActiveProjectIds($userId)) as $project) {
            if ((int) $project['is_active'] === 1) {
                $projects[(int) $project['id']] = $project;
            }
        }

        $assignments = empty($projects) ? array() : $this->sortaFolderModel->getAssignments();

        foreach ($projects as $id => $project) {
            $projects[$id]['sf_path'] = isset($assignments[$id]) ? $this->sortaFolderModel->getPathLabel($assignments[$id]) : $this->helper->sortaFolders->t('No folder');
        }

        return $this->scopes[(int) $userId] = $projects;
    }

    /**
     * What chips.php needs besides the tabs: "kind", "counts" (what each tab keeps, for dimming) and "bb" (null without BigBoard,
     * else the scope, the suffix and the counts of the BigBoard searches). BigBoard shows the projects the user picked in it, so
     * its counts cover those projects (among the user's active ones). On My tasks and My subtasks each search is narrowed with
     * assignee:me. BigBoard can't search by subtask assignee, so on My subtasks the searches find the tasks you own.
     */
    public function tabs($page)
    {
        $userId = $this->pageUserId();
        $settings = $this->sortaHealthModel->getSettings();
        $viewer = (int) $this->userSession->getId();
        $keys = array_diff(array_keys(FolderController::getFilters()), array('all'));
        $projects = $this->projects($userId);
        $mine = $this->mine($page, $userId);

        if ($page === 'projects') {
            $counts = $this->sortaHealthModel->filterCounts($projects, $keys, $settings, $viewer);
        } else {
            $counts = $this->sortaLinkCountModel->linkCounts(array_keys($projects), $settings['soon_days'], $userId, $mine);
            $counts += $this->sortaHealthModel->filterCounts($projects, array('quiet', 'attention'), $settings, $viewer);
        }

        $bb = null;

        if ($this->helper->sortaFolders->hasBigboard() && isset($this->container['bigboardModel'])) {
            $shown = array_values(array_intersect($this->bigboardModel->selectFindAllProjectsById($userId), array_keys($projects)));
            $own = $page !== 'projects';
            $bb = array(
                'scope' => array(),
                'suffix' => $own ? ' assignee:me' : '',
                'counts' => $this->sortaLinkCountModel->linkCounts($shown, $settings['soon_days'], $userId, $own ? array('owner' => $userId) : null),
            );
        }

        return array('kind' => $page, 'counts' => $counts, 'bb' => $bb);
    }
}

<?php

namespace Kanboard\Plugin\SortaFolders\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\PageNotFoundException;

/**
 * The one folder page: the viewer's active projects grouped by folder, in the dashboard layout.
 * Everyone sees their own projects (the ones they are a member of). An admin can switch to all
 * active projects with scope=all. Anyone may open it.
 */
class FolderController extends BaseController
{
    const PER_PAGE = 25;
    const FILTER_KEY = 'sortafolders_filter';

    /**
     * What the filter chips offer: the `filter` value => its label. Anything else means "all".
     */
    public static function getFilters()
    {
        return array(
            'all'       => 'All',
            'overdue'   => 'Overdue',
            'today'     => 'Due today',
            'soon'      => 'Due this week',
            'mine'      => 'My tasks',
            'unowned'   => 'No owner',
            'blocked'   => 'Blocked',
            'stuck'     => 'Not moved in 30 days',
            'quiet'     => 'Quiet',
            'attention' => 'Needs attention',
        );
    }

    /**
     * What the Sort menu offers: the request's `order` value => the project row key it sorts by.
     * The values are the ones Kanboard's own project list uses, plus the last activity date.
     */
    public static function getSortColumns()
    {
        return array(
            'projects.id'            => 'id',
            'projects.name'          => 'name',
            'projects.start_date'    => 'start_date',
            'projects.end_date'      => 'end_date',
            'projects.is_public'     => 'is_public',
            'projects.is_private'    => 'is_private',
            'projects.last_modified' => 'last_modified',
        );
    }

    public function show()
    {
        $all = $this->userSession->isAdmin() && $this->request->getStringParam('scope') === 'all';
        $helper = $this->helper->sortaFolders;

        if ($all) {
            $projectIds = $this->projectModel->getAllIds();
        } else {
            $projectIds = $this->projectPermissionModel->getActiveProjectIds($this->userSession->getId());
        }

        $projects = array();

        foreach ($this->projectModel->getAllByIds($projectIds) as $project) {
            if ((int) $project['is_active'] === 1) {
                $projects[(int) $project['id']] = $project;
            }
        }

        $health = $this->sortaHealthModel;
        $settings = $health->getSettings();
        $totals = $this->sortaFolderModel->getTaskTotals(array_keys($projects), $settings['soon_days'], $this->userSession->getId());
        $managers = $this->sortaFolderModel->getManagers(array_keys($projects));
        $sparks = $health->getSparklines(array_keys($projects));
        $empty = array('open' => 0, 'overdue' => 0, 'soon' => 0, 'closed' => 0, 'today' => 0, 'unowned' => 0, 'stuck' => 0, 'blocked' => 0, 'mine' => 0, 'subtasks' => 0, 'hours' => 0, 'estimated' => 0);
        $assignments = $this->sortaFolderModel->getAssignments();

        foreach ($projects as $id => $project) {
            $projects[$id]['sf_totals'] = isset($totals[$id]) ? $totals[$id] : $empty;
            $projects[$id]['sf_managers'] = isset($managers[$id]) ? $managers[$id] : array();
            $projects[$id]['sf_health'] = $health->assess($projects[$id]['sf_totals'], (int) $project['last_modified'], $settings);
            $projects[$id]['sf_spark'] = $sparks[$id];
            $projects[$id]['sf_path'] = isset($assignments[$id]) ? $this->sortaFolderModel->getPathLabel($assignments[$id]) : $helper->t('No folder');
        }

        $filter = $this->getFilter();

        // Sorting orders the projects inside each folder. Folders keep the order set on the admin page.
        $columns = self::getSortColumns();
        $order = $this->request->getStringParam('order', 'projects.name');
        $order = isset($columns[$order]) ? $order : 'projects.name';
        $direction = strtoupper($this->request->getStringParam('direction', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        $key = $columns[$order];

        uasort($projects, function ($a, $b) use ($key, $direction) {
            $x = $a[$key];
            $y = $b[$key];
            if ($key === 'name') {
                $result = strcasecmp((string) $x, (string) $y);
            } elseif (is_numeric($x) && is_numeric($y)) {
                $result = $x <=> $y;
            } else {
                $result = strcmp((string) $x, (string) $y);
            }

            if ($result === 0) {
                $result = (int) $a['id'] - (int) $b['id'];
            }

            return $direction === 'DESC' ? -$result : $result;
        });

        // The strip and the 404 check use every project the viewer can see. The chips narrow only the tree below.
        $folderId = $this->request->getIntegerParam('folder_id');
        $scope = $projects;

        if ($folderId > 0) {
            list($allNodes) = $this->sortaFolderModel->buildTree($projects);
            $seen = $this->sortaFolderModel->findNode($allNodes, $folderId);

            // A folder the viewer can't see anything in answers like one that doesn't exist.
            if ($seen === null) {
                throw new PageNotFoundException();
            }

            $scope = $this->collectProjects($seen);
        }

        $attention = $this->helper->sortaFolders->attentionEnabled() ? $health->attention($scope) : array();
        // What each tab would keep, for dimming the empty ones, and what BigBoard would show: the folder when one is open, else the projects picked in BigBoard.
        $tabKeys = array_diff(array_keys(self::getFilters()), array('all'));
        $tabCounts = $health->filterCounts($scope, $tabKeys, $settings, $this->userSession->getId());
        $tabBigboard = null;

        if ($helper->hasBigboard() && isset($this->container['bigboardModel'])) {
            $shown = $scope;

            if ($folderId <= 0) {
                $shown = array_intersect_key($scope, array_flip($this->bigboardModel->selectFindAllProjectsById($this->userSession->getId())));
            }

            $sums = array('overdue' => 0, 'today' => 0, 'soon' => 0, 'mine' => 0, 'unowned' => 0, 'blocked' => 0, 'stuck' => 0);

            foreach ($shown as $project) {
                foreach ($sums as $key => $value) {
                    $sums[$key] += (int) $project['sf_totals'][$key];
                }
            }

            $tabBigboard = array('scope' => $folderId > 0 ? array('scope' => 'group:'.$folderId) : array(), 'suffix' => '', 'counts' => $sums);
        }

        $projects = $this->sortaHealthModel->filterProjects($projects, $filter, $settings, $this->userSession->getId());

        // Empty folders show only on the unfiltered page: every one on All folders, the viewer's own on My folders.
        $showEmpty = array();

        if ($filter === 'all' && $folderId <= 0) {
            foreach ($this->sortaFolderModel->getAll() as $folder) {
                if ($all || $folder['created_by'] === (int) $this->userSession->getId()) {
                    $showEmpty[$folder['id']] = true;
                }
            }
        }

        list($nodes, $loose) = $this->sortaFolderModel->buildTree($projects, $showEmpty);
        $nodes = $health->addNodeHealth($this->sortaFolderModel->addTotals($nodes));
        $focus = null;

        if ($folderId > 0) {
            $focus = $this->sortaFolderModel->findNode($nodes, $folderId);
            $nodes = $focus === null ? array() : array($focus);
            $loose = array();
        }

        // "Projects with no folder" is a pseudo-folder, so it takes the same totals and the same template as a real one.
        $looseNode = null;

        if (! empty($loose)) {
            $pseudo = array('id' => 0, 'name' => $helper->t('Projects with no folder'), 'description' => '');
            $looseNode = $health->addNodeHealth($this->sortaFolderModel->addTotals(array(
                array('folder' => $pseudo, 'children' => array(), 'projects' => $loose, 'count' => count($loose)),
            )));
            $looseNode = $looseNode[0];
        }

        $sum = $empty;
        $projectCount = 0;

        foreach (array_merge($nodes, $looseNode === null ? array() : array($looseNode)) as $node) {
            $projectCount += $node['count'];

            foreach ($sum as $key => $value) {
                $sum[$key] += $node['totals'][$key];
            }
        }

        // Pages hold top-level folders first (each with everything below it), then ungrouped projects one by one.
        $entries = count($nodes) + count($loose);
        $paginator = $this->paginator
            ->setUrl('FolderController', 'show', array('plugin' => 'SortaFolders', 'scope' => $all ? 'all' : null, 'folder_id' => $folderId > 0 ? $folderId : null, 'filter' => $filter))
            ->setMax(self::PER_PAGE)
            ->setOrder($order)
            ->setDirection($direction)
            ->setTotal($entries)
            ->calculate();

        $offset = ($paginator->getPage() - 1) * self::PER_PAGE;
        $slice = array_slice(array_merge($nodes, $loose), $offset, self::PER_PAGE);
        $pageNodes = array_values(array_filter($slice, function ($item) {
            return isset($item['folder']);
        }));
        $pageLoose = array_values(array_filter($slice, function ($item) {
            return ! isset($item['folder']);
        }));

        // The pseudo-folder keeps the totals of every ungrouped project and lists only this page's share.
        if ($looseNode !== null) {
            $looseNode['projects'] = $pageLoose;
            $looseNode = empty($pageLoose) ? null : $looseNode;
        }

        $pageNodes = $this->attachNotes($pageNodes, $folderId);

        $this->response->html($this->helper->layout->dashboard('SortaFolders:folder/show', array(
            'filter'        => $filter,
            'filters'       => self::getFilters(),
            'attention'     => $attention,
            'tab_counts'    => $tabCounts,
            'tab_bigboard'  => $tabBigboard,
            'sf_scope'      => $scope,
            'url_params'    => array(
                'plugin'    => 'SortaFolders',
                'scope'     => $all ? 'all' : null,
                'folder_id' => $folderId > 0 ? $folderId : null,
                'order'     => $order === 'projects.name' ? null : $order,
                'direction' => $direction === 'ASC' ? null : $direction,
            ),
            'title'         => $all ? $helper->t('All folders') : $helper->t('My folders'),
            'user'          => $this->getUser(),
            'nodes'         => $pageNodes,
            'loose'         => $looseNode,
            'paginator'     => $paginator,
            'focus_path'    => $focus === null ? '' : $this->sortaFolderModel->getPathLabel($focus['folder']['id']),
            'focus_description' => $focus === null ? '' : $focus['folder']['description'],
            'all'           => $all,
            'folder_count'  => $this->countNodes($nodes),
            'project_count' => $projectCount,
            'totals'        => $sum,
        )));
    }

    /**
     * Old "My folders" address, kept so saved links still work.
     */
    public function mine()
    {
        $this->response->redirect($this->helper->url->to('FolderController', 'show', array('plugin' => 'SortaFolders')));
    }

    /**
     * The viewer's filter. A `filter` parameter wins and is remembered (in user metadata, so no schema),
     * `filter=all` forgets it, and with no parameter the remembered one is used. Anything unknown is "all".
     */
    private function getFilter()
    {
        $userId = $this->userSession->getId();
        $known = array_keys(self::getFilters());
        $asked = $this->request->getStringParam('filter', '');

        if ($asked !== '') {
            $filter = in_array($asked, $known, true) ? $asked : 'all';

            if (in_array($asked, $known, true)) {
                if ($filter === 'all') {
                    $this->userMetadataModel->remove($userId, self::FILTER_KEY);
                } else {
                    $this->userMetadataModel->save($userId, array(self::FILTER_KEY => $filter));
                }
            }

            return $filter;
        }

        $saved = (string) $this->userMetadataModel->get($userId, self::FILTER_KEY, 'all');

        return in_array($saved, $known, true) ? $saved : 'all';
    }

    private function collectProjects(array $node)
    {
        $found = array();

        foreach ($node['projects'] as $project) {
            $found[(int) $project['id']] = $project;
        }

        foreach ($node['children'] as $child) {
            $found += $this->collectProjects($child);
        }

        return $found;
    }

    /**
     * Adds the newest status note to every folder node shown (key 'note', or null).
     */
    private function attachNotes(array $nodes, $focusId = 0)
    {
        $ids = array();
        $collect = function (array $list) use (&$collect, &$ids) {
            foreach ($list as $node) {
                $ids[] = (int) $node['folder']['id'];
                $collect($node['children']);
            }
        };
        $collect($nodes);
        $notes = $this->sortaNoteModel->getLatest($ids);
        $userId = $this->userSession->getId();
        $isAdmin = $this->userSession->isAdmin();
        $attach = function (array $list) use (&$attach, $notes, $userId, $isAdmin, $focusId) {
            foreach ($list as $i => $node) {
                $list[$i]['note'] = isset($notes[(int) $node['folder']['id']]) ? $notes[(int) $node['folder']['id']] : null;
                $list[$i]['can_note'] = (int) $node['folder']['id'] > 0 && $this->sortaNoteModel->canWrite($userId, $isAdmin, (int) $node['folder']['id']);
                // A folder page exists for a folder with a project the viewer can see. No link to the page already open.
                $list[$i]['can_view'] = (int) $node['folder']['id'] > 0 && (int) $node['folder']['id'] !== (int) $focusId && $node['count'] > 0;
                $list[$i]['children'] = $attach($node['children']);
            }

            return $list;
        };

        return $attach($nodes);
    }

    private function countNodes(array $nodes)
    {
        $count = count($nodes);

        foreach ($nodes as $node) {
            $count += $this->countNodes($node['children']);
        }

        return $count;
    }
}

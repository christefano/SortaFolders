<?php

namespace Kanboard\Plugin\SortaFolders\Model;

use Kanboard\Core\Base;
use Kanboard\Plugin\SortaFolders\Helper\FolderHelper;

/**
 * Folders form a tree in sortafolders_groups (parent_id 0 is the top level). A project's folder is
 * its project metadata value for METADATA_KEY, so core copies it when a project is duplicated.
 * Internally a folder is a "group", which is also what BigBoard's group switcher calls it.
 */
class FolderModel extends Base
{
    const TABLE = 'sortafolders_groups';
    const METADATA_KEY = 'sortafolders_group';
    const MAX_DEPTH = 5;
    const NAME_MAX = 191;
    const SEPARATOR = ' / ';

    /** @var array|null id => row */
    private $folders = null;

    /** @var array|null parent id => list of rows */
    private $children = null;

    /** @var array|null project id => folder id */
    private $assignments = null;

    /** @var array|null folder id => true */
    private $hidden = null;
    private $viewer = null;

    private function reset()
    {
        $this->folders = null;
        $this->children = null;
        $this->assignments = null;
        $this->hidden = null;
        $this->viewer = null;
    }

    /**
     * All folders, id => row, ordered by position and then id.
     */
    public function getAll()
    {
        if ($this->folders === null) {
            $this->folders = array();

            foreach ($this->db->table(self::TABLE)->asc('position')->asc('id')->findAll() as $row) {
                $row['id'] = (int) $row['id'];
                $row['parent_id'] = (int) $row['parent_id'];
                $row['position'] = (int) $row['position'];
                $row['description'] = isset($row['description']) ? (string) $row['description'] : '';
                $row['created_by'] = isset($row['created_by']) ? (int) $row['created_by'] : 0;
                $this->folders[$row['id']] = $row;
            }
        }

        return $this->folders;
    }

    public function getById($folderId)
    {
        $all = $this->getAll();
        $folderId = (int) $folderId;

        return isset($all[$folderId]) ? $all[$folderId] : null;
    }

    /**
     * Child folders in order. A folder whose parent is missing counts as top level.
     */
    public function getChildren($parentId)
    {
        if ($this->children === null) {
            $this->children = array();
            $all = $this->getAll();

            foreach ($all as $row) {
                $parent = isset($all[$row['parent_id']]) ? $row['parent_id'] : 0;
                $this->children[$parent][] = $row;
            }
        }

        $parentId = (int) $parentId;

        return isset($this->children[$parentId]) ? $this->children[$parentId] : array();
    }

    /**
     * Rows from the top level down to the folder.
     */
    public function getPath($folderId)
    {
        $all = $this->getAll();
        $path = array();
        $seen = array();
        $folderId = (int) $folderId;

        while ($folderId > 0 && isset($all[$folderId]) && ! isset($seen[$folderId])) {
            $seen[$folderId] = true;
            array_unshift($path, $all[$folderId]);
            $folderId = $all[$folderId]['parent_id'];
        }

        return $path;
    }

    /**
     * The folders a signed-in non-admin may see by name: the ones that hold a project they are a member of, the
     * ones they made, and nothing above them. Null for an admin or no session, who see every folder.
     */
    private function getViewerFolders()
    {
        if (! $this->userSession->isLogged() || $this->userSession->isAdmin()) {
            return null;
        }

        $userId = (int) $this->userSession->getId();

        if ($this->viewer === null || $this->viewer['user'] !== $userId) {
            $set = array();
            $projectIds = array_flip(array_map('intval', $this->projectPermissionModel->getActiveProjectIds($userId)));

            foreach ($this->getAssignments() as $projectId => $folderId) {
                if (isset($projectIds[(int) $projectId])) {
                    $set[(int) $folderId] = true;
                }
            }

            foreach ($this->getAll() as $folder) {
                if ((int) $folder['created_by'] === $userId) {
                    $set[(int) $folder['id']] = true;
                }
            }

            $this->viewer = array('user' => $userId, 'set' => $set);
        }

        return $this->viewer['set'];
    }

    /**
     * The folder names from the top level down, leaving out the folders a non-admin has no access to.
     */
    public function getPathLabel($folderId)
    {
        $names = array();
        $visible = $this->getViewerFolders();

        foreach ($this->getPath($folderId) as $folder) {
            if ($visible === null || isset($visible[(int) $folder['id']])) {
                $names[] = $folder['name'];
            }
        }

        return implode(self::SEPARATOR, $names);
    }

    /**
     * Levels from the top down to the folder (a top level folder is 1, none is 0).
     */
    public function getDepth($folderId)
    {
        return count($this->getPath($folderId));
    }

    /**
     * Levels in the folder's own subtree, counting the folder (a folder with no children is 1).
     */
    public function getHeight($folderId)
    {
        $seen = array();

        return $this->height((int) $folderId, $seen);
    }

    private function height($folderId, array &$seen)
    {
        if (isset($seen[$folderId])) {
            return 0;
        }

        $seen[$folderId] = true;
        $deepest = 0;

        foreach ($this->getChildren($folderId) as $child) {
            $deepest = max($deepest, $this->height($child['id'], $seen));
        }

        return 1 + $deepest;
    }

    /**
     * Ids of the folders an admin chose to hide, with every folder below them. Hidden folders stay in the settings
     * table, Access and the edit dialogs, and leave the folder page, the pickers and BigBoard's group switcher.
     */
    public function getHiddenIds()
    {
        if ($this->hidden === null) {
            $this->hidden = array();
            $all = $this->getAll();

            foreach (explode(',', (string) $this->configModel->get(FolderHelper::HIDDEN_KEY, '')) as $id) {
                $id = (int) $id;

                if ($id > 0 && isset($all[$id])) {
                    $this->hidden[$id] = true;

                    foreach ($this->getDescendantIds($id) as $child) {
                        $this->hidden[(int) $child] = true;
                    }
                }
            }
        }

        return $this->hidden;
    }

    public function getDescendantIds($folderId)
    {
        $ids = array();
        $queue = array((int) $folderId);

        while (! empty($queue)) {
            $current = array_shift($queue);

            foreach ($this->getChildren($current) as $child) {
                if (! in_array($child['id'], $ids, true)) {
                    $ids[] = $child['id'];
                    $queue[] = $child['id'];
                }
            }
        }

        return $ids;
    }

    /**
     * Every folder in tree order as list of array('folder' => row, 'depth' => int, 'first' => bool, 'last' => bool),
     * the last two telling whether it is the first or last of its siblings.
     */
    public function getFlatTree()
    {
        $out = array();
        $this->walk(0, 1, array(), $out);

        return $out;
    }

    private function walk($parentId, $depth, array $seen, array &$out)
    {
        $siblings = $this->getChildren($parentId);
        $lastIndex = count($siblings) - 1;

        foreach ($siblings as $index => $folder) {
            if (isset($seen[$folder['id']])) {
                continue;
            }

            $out[] = array('folder' => $folder, 'depth' => $depth, 'first' => $index === 0, 'last' => $index === $lastIndex);
            $this->walk($folder['id'], $depth + 1, $seen + array($folder['id'] => true), $out);
        }
    }

    /**
     * Parent choices for a folder that is $height levels tall (1 for a new one). Leaves out
     * the folder itself, its descendants, and any spot that would pass MAX_DEPTH.
     */
    public function getParentOptions($height = 1, $excludeId = 0)
    {
        $exclude = array();

        if ($excludeId > 0) {
            $exclude = $this->getDescendantIds($excludeId);
            $exclude[] = (int) $excludeId;
        }

        $options = array(0 => $this->helper->sortaFolders->t('No parent folder'));

        foreach ($this->getFlatTree() as $item) {
            $id = $item['folder']['id'];

            if (in_array($id, $exclude, true) || $item['depth'] + $height > self::MAX_DEPTH) {
                continue;
            }

            $options[$id] = $this->getPathLabel($id);
        }

        return $options;
    }

    /**
     * Folder choices for a project.
     */
    public function getFolderOptions($keepId = 0)
    {
        $options = array(0 => $this->helper->sortaFolders->t('No folder'));
        $hidden = $this->hiddenExcept($keepId);

        foreach ($this->getFlatTree() as $item) {
            if (! isset($hidden[$item['folder']['id']])) {
                $options[$item['folder']['id']] = $this->getPathLabel($item['folder']['id']);
            }
        }

        return $options;
    }

    /**
     * Folder choices for one user. App admins get the whole tree. Anyone else gets "No folder" and the
     * folders that hold one of their active projects or sit above such a folder (the same rule the
     * folder page uses, through buildTree()). $keepId, the project's current folder, is always kept.
     * User id 0 (no session) gets "No folder" only.
     */
    public function getFolderOptionsForUser($userId, $keepId = 0)
    {
        $userId = (int) $userId;

        if ($userId > 0 && $this->userSession->isLogged() && $this->userSession->getId() === $userId && $this->userSession->isAdmin()) {
            return $this->getFolderOptions($keepId);
        }

        $options = array(0 => $this->helper->sortaFolders->t('No folder'));

        if ($userId <= 0) {
            return $options;
        }

        $projects = array();

        foreach ($this->projectPermissionModel->getActiveProjectIds($userId) as $projectId) {
            $projects[(int) $projectId] = array('id' => (int) $projectId);
        }

        list($nodes) = $this->buildTree($projects);
        $visible = array();
        $this->collectIds($nodes, $visible);

        if ((int) $keepId > 0 && $this->getById($keepId) !== null) {
            $visible[(int) $keepId] = true;
        }

        // A folder the user made stays pickable while it is still empty. The folders above it stay out of sight.
        foreach ($this->getAll() as $folder) {
            if ((int) $folder['created_by'] === $userId) {
                $visible[(int) $folder['id']] = true;
            }
        }

        $hidden = $this->hiddenExcept($keepId);

        foreach ($this->getFlatTree() as $item) {
            $id = $item['folder']['id'];

            if (isset($visible[$id]) && ! isset($hidden[$id])) {
                $options[$id] = $this->getPathLabel($id);
            }
        }

        return $options;
    }

    /**
     * The hidden folders, minus a project's current folder and the folders above it, so editing a project never drops its folder.
     */
    private function hiddenExcept($keepId)
    {
        $hidden = $this->getHiddenIds();

        if ((int) $keepId > 0 && $this->getById($keepId) !== null) {
            foreach ($this->getPath($keepId) as $folder) {
                unset($hidden[$folder['id']]);
            }
        }

        return $hidden;
    }

    private function collectIds(array $nodes, array &$ids)
    {
        foreach ($nodes as $node) {
            $ids[$node['folder']['id']] = true;
            $this->collectIds($node['children'], $ids);
        }
    }

    public function cleanDescription($description)
    {
        $description = trim((string) $description);

        return mb_substr($description, 0, 2000);
    }

    public function cleanName($name)
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) $name));

        if ($name === '' || mb_strlen($name) > self::NAME_MAX) {
            return '';
        }

        return $name;
    }

    /**
     * True when a folder (0 for a new one) that is $height levels tall can sit under the parent:
     * the parent exists, isn't the folder or inside it, and the result stays within MAX_DEPTH.
     */
    public function canPlace($folderId, $parentId, $height)
    {
        $folderId = (int) $folderId;
        $parentId = (int) $parentId;

        if ($parentId === 0) {
            return $height <= self::MAX_DEPTH;
        }

        if ($this->getById($parentId) === null) {
            return false;
        }

        if ($folderId > 0 && ($parentId === $folderId || in_array($parentId, $this->getDescendantIds($folderId), true))) {
            return false;
        }

        return $this->getDepth($parentId) + $height <= self::MAX_DEPTH;
    }

    /**
     * Whether a sibling under the parent already has this name, ignoring case. A folder never clashes with itself.
     */
    public function nameTaken($name, $parentId, $exceptId = 0)
    {
        $name = mb_strtolower($this->cleanName($name));

        foreach ($this->getChildren($parentId) as $sibling) {
            if ((int) $sibling['id'] !== (int) $exceptId && mb_strtolower($sibling['name']) === $name) {
                return true;
            }
        }

        return false;
    }

    private function nextPosition($parentId)
    {
        $max = 0;

        foreach ($this->getChildren($parentId) as $child) {
            $max = max($max, $child['position']);
        }

        return $max + 1;
    }

    /**
     * @return int|false The new folder's id.
     */
    public function create($name, $parentId = 0, $description = '', $createdBy = 0)
    {
        $name = $this->cleanName($name);
        $parentId = (int) $parentId;

        if ($name === '' || ! $this->canPlace(0, $parentId, 1) || $this->nameTaken($name, $parentId)) {
            return false;
        }

        $id = $this->db->table(self::TABLE)->persist(array(
            'parent_id' => $parentId,
            'name'      => $name,
            'description' => $this->cleanDescription($description),
            'position'  => $this->nextPosition($parentId),
            'created_by' => (int) $createdBy,
        ));

        $this->reset();

        return $id ?: false;
    }

    /**
     * Rename a folder and move it under another parent. A moved folder goes last among its new siblings.
     */
    public function update($folderId, $name, $parentId, $description = null)
    {
        $folder = $this->getById($folderId);
        $name = $this->cleanName($name);
        $parentId = (int) $parentId;

        if ($folder === null || $name === '') {
            return false;
        }

        $values = array('name' => $name);

        if ($description !== null) {
            $values['description'] = $this->cleanDescription($description);
        }

        if ($this->nameTaken($name, $parentId, $folder['id'])) {
            return false;
        }

        if ($parentId !== $folder['parent_id']) {
            if (! $this->canPlace($folder['id'], $parentId, $this->getHeight($folder['id']))) {
                return false;
            }

            $values['parent_id'] = $parentId;
            $values['position'] = $this->nextPosition($parentId);
        }

        if (isset($values['parent_id'])) {
            $this->syncAccess();
        }

        $result = $this->db->table(self::TABLE)->eq('id', $folder['id'])->update($values);
        $this->reset();

        if (isset($values['parent_id'])) {
            $this->syncAccess();
        }

        return $result;
    }

    /**
     * Swap a folder with its neighbor. $direction is -1 for up and 1 for down.
     */
    public function move($folderId, $direction)
    {
        $folder = $this->getById($folderId);

        if ($folder === null) {
            return false;
        }

        $all = $this->getAll();
        $parent = isset($all[$folder['parent_id']]) ? $folder['parent_id'] : 0;
        $ids = array();

        foreach ($this->getChildren($parent) as $sibling) {
            $ids[] = $sibling['id'];
        }

        $from = array_search($folder['id'], $ids, true);
        $to = $from === false ? false : $from + (int) $direction;

        if ($from === false || ! isset($ids[$to])) {
            return false;
        }

        $ids[$from] = $ids[$to];
        $ids[$to] = $folder['id'];

        $this->db->startTransaction();

        try {
            foreach ($ids as $index => $id) {
                $this->db->table(self::TABLE)->eq('id', $id)->update(array('position' => $index + 1));
            }
        } catch (\Throwable $e) {
            $this->db->cancelTransaction();
            $this->reset();

            throw $e;
        }

        $this->db->closeTransaction();
        $this->reset();

        return true;
    }

    /**
     * Remove a folder. Its subfolders and projects move up to its parent (or to no folder at the
     * top level). No project or task is deleted.
     */
    public function remove($folderId)
    {
        $folder = $this->getById($folderId);

        if ($folder === null) {
            return false;
        }

        $all = $this->getAll();
        $parent = isset($all[$folder['parent_id']]) ? $folder['parent_id'] : 0;
        $position = $this->nextPosition($parent);

        $this->syncAccess();
        $this->db->startTransaction();

        try {
            foreach ($this->getChildren($folder['id']) as $child) {
                $this->db->table(self::TABLE)->eq('id', $child['id'])->update(array(
                    'parent_id' => $parent,
                    'position'  => $position++,
                ));
            }

            $assigned = $this->db->table('project_has_metadata')
                ->eq('name', self::METADATA_KEY)
                ->eq('value', (string) $folder['id']);

            if ($parent > 0) {
                $assigned->update(array(
                    'value'      => (string) $parent,
                    'changed_on' => time(),
                    'changed_by' => $this->userSession->getId(),
                ));
            } else {
                $assigned->remove();
            }

            $this->db->table(self::TABLE)->eq('id', $folder['id'])->remove();
        } catch (\Throwable $e) {
            $this->db->cancelTransaction();
            $this->reset();

            throw $e;
        }

        $this->db->closeTransaction();
        $this->reset();
        $this->syncAccess(function () use ($folder) {
            $this->sortaAccessModel->removeByFolder($folder['id']);
        });
        $this->sortaNoteModel->removeByFolder($folder['id']);

        return true;
    }

    /**
     * Folder access follows the tree, so any change to it brings the memberships up to date. A failure
     * is logged and never stops the folder change that caused it.
     */
    private function syncAccess($callback = null)
    {
        try {
            if ($callback === null) {
                $this->sortaAccessModel->sync();
            } else {
                $callback();
            }
        } catch (\Throwable $e) {
            $this->logger->error('SortaFolders: could not update folder access: '.$e->getMessage());
        }
    }

    /**
     * Ids of the active projects in a folder and everything below it, personal projects included.
     */
    public function getActiveProjectIds($folderId)
    {
        $folders = array_flip(array_merge(array((int) $folderId), $this->getDescendantIds($folderId)));
        $ids = array();

        foreach ($this->getAssignments() as $projectId => $assigned) {
            if (isset($folders[$assigned])) {
                $ids[] = (int) $projectId;
            }
        }

        if (empty($ids)) {
            return array();
        }

        return array_map('intval', $this->db->table('projects')
            ->in('id', $ids)
            ->eq('is_active', 1)
            ->asc('name')
            ->findAllByColumn('id'));
    }

    /**
     * Ids of the closed projects in a folder and everything below it, personal projects included.
     */
    public function getClosedProjectIds($folderId)
    {
        $folders = array_flip(array_merge(array((int) $folderId), $this->getDescendantIds($folderId)));
        $ids = array();

        foreach ($this->getAssignments() as $projectId => $assigned) {
            if (isset($folders[$assigned])) {
                $ids[] = (int) $projectId;
            }
        }

        if (empty($ids)) {
            return array();
        }

        return array_map('intval', $this->db->table('projects')
            ->in('id', $ids)
            ->eq('is_active', 0)
            ->asc('name')
            ->findAllByColumn('id'));
    }

    /**
     * Who may create a folder: app admins, and anyone who is a Project Manager of at least one project
     * (directly, or through a group).
     */
    public function canCreate($userId, $isAdmin)
    {
        if ($isAdmin) {
            return true;
        }

        $userId = (int) $userId;

        if ($userId <= 0) {
            return false;
        }

        if ($this->db->table('project_has_users')->eq('user_id', $userId)->eq('role', 'project-manager')->count() > 0) {
            return true;
        }

        $groups = $this->db->table('group_has_users')->eq('user_id', $userId)->findAllByColumn('group_id');

        return ! empty($groups)
            && $this->db->table('project_has_groups')->in('group_id', array_map('intval', $groups))->eq('role', 'project-manager')->count() > 0;
    }

    /**
     * Project id => list of array(id, username, name) for the active users who manage each project.
     * Managers that come through a group are not listed.
     */
    public function getManagers(array $projectIds)
    {
        $managers = array();
        $projectIds = array_values(array_unique(array_map('intval', $projectIds)));

        if (empty($projectIds)) {
            return $managers;
        }

        $rows = $this->db->table('project_has_users')
            ->columns('project_has_users.project_id', 'users.id', 'users.username', 'users.name')
            ->join('users', 'id', 'user_id')
            ->in('project_has_users.project_id', $projectIds)
            ->eq('project_has_users.role', 'project-manager')
            ->eq('users.is_active', 1)
            ->asc('users.username')
            ->findAll();

        foreach ($rows as $row) {
            $managers[(int) $row['project_id']][] = array(
                'id'       => (int) $row['id'],
                'username' => $row['username'],
                'name'     => (string) $row['name'],
            );
        }

        return $managers;
    }

    /**
     * Project id => folder id for every project in an existing folder.
     */
    public function getAssignments()
    {
        if ($this->assignments === null) {
            $this->assignments = array();
            $all = $this->getAll();

            foreach ($this->db->table('project_has_metadata')->eq('name', self::METADATA_KEY)->findAll() as $row) {
                $folderId = (int) $row['value'];

                if (isset($all[$folderId])) {
                    $this->assignments[(int) $row['project_id']] = $folderId;
                }
            }
        }

        return $this->assignments;
    }

    public function getFolderId($projectId)
    {
        $assignments = $this->getAssignments();
        $projectId = (int) $projectId;

        return isset($assignments[$projectId]) ? $assignments[$projectId] : 0;
    }

    /**
     * Core has no project-created event, so the New project form posts `sortafolders_folder_id`
     * and Plugin.php calls this after the response: it finds the project that was just made
     * (same name, same owner, newest, modified since the request began) and files it.
     */
    public function assignNewProject($name, $ownerId, $folderId, $since)
    {
        if ((int) $folderId <= 0 || $name === '') {
            return false;
        }

        $projectId = $this->db->table('projects')
            ->eq('name', $name)
            ->eq('owner_id', (int) $ownerId)
            ->gte('last_modified', (int) $since)
            ->desc('id')
            ->findOneColumn('id');

        return $projectId ? $this->assign($projectId, $folderId) : false;
    }

    /**
     * Put a project in a folder, or in none with 0.
     */
    public function assign($projectId, $folderId)
    {
        $projectId = (int) $projectId;
        $folderId = (int) $folderId;

        // Adopt what a duplicate copied before the filing changes. Only a project with a copied list has anything to adopt.
        if ($this->projectMetadataModel->exists($projectId, AccessModel::METADATA_KEY)) {
            $this->syncAccess();
        }

        if ($folderId === 0) {
            $result = $this->projectMetadataModel->remove($projectId, self::METADATA_KEY);
        } elseif ($this->getById($folderId) === null) {
            return false;
        } else {
            $result = $this->projectMetadataModel->save($projectId, array(self::METADATA_KEY => (string) $folderId));
        }

        $this->reset();
        $this->syncAccess();

        return $result;
    }

    /**
     * Folders as nested nodes for the projects given (id => row, already limited to what the
     * viewer may see). A node is array('folder', 'children', 'projects', 'count'), and folders
     * with no project below them are left out, unless their id is a key of $showEmpty (id => true),
     * which keeps an empty folder in the tree together with the folders above it.
     *
     * @return array array(nodes, projects that are in no folder)
     */
    public function buildTree(array $projects, array $showEmpty = array(), $withHidden = false)
    {
        $assignments = $this->getAssignments();
        $byFolder = array();
        $loose = array();

        foreach ($projects as $projectId => $project) {
            if (isset($assignments[$projectId])) {
                $byFolder[$assignments[$projectId]][] = $project;
            } else {
                $loose[] = $project;
            }
        }

        return array($this->buildNodes(0, $byFolder, array(), $showEmpty, $withHidden ? array() : $this->getHiddenIds()), $loose);
    }

    private function buildNodes($parentId, array $byFolder, array $seen, array $showEmpty = array(), array $hidden = array())
    {
        $nodes = array();
        $restricted = $this->getViewerFolders() !== null;

        foreach ($this->getChildren($parentId) as $folder) {
            if (isset($seen[$folder['id']]) || isset($hidden[$folder['id']])) {
                continue;
            }

            $children = $this->buildNodes($folder['id'], $byFolder, $seen + array($folder['id'] => true), $showEmpty, $hidden);
            $projects = isset($byFolder[$folder['id']]) ? $byFolder[$folder['id']] : array();
            $count = count($projects);

            foreach ($children as $child) {
                $count += $child['count'];
            }

            if ($restricted && empty($projects) && ! isset($showEmpty[$folder['id']])) {
                // A non-admin has no access to this folder, so its name stays out and the folders below move up.
                $nodes = array_merge($nodes, $children);
            } elseif ($count > 0 || ! empty($children) || isset($showEmpty[$folder['id']])) {
                $nodes[] = array('folder' => $folder, 'children' => $children, 'projects' => $projects, 'count' => $count);
            }
        }

        return $nodes;
    }

    public function findNode(array $nodes, $folderId)
    {
        foreach ($nodes as $node) {
            if ($node['folder']['id'] === (int) $folderId) {
                return $node;
            }

            $found = $this->findNode($node['children'], $folderId);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * BigBoard's group switcher reads this shape from PortfolioDashboard's PortfolioProjectModel.
     * Names are full paths, position follows the tree, and a project in no folder has a null group.
     * A parent folder lists only the projects directly in it.
     */
    public function getProjectGrouping()
    {
        $groups = array();
        $position = 0;
        $hidden = $this->getHiddenIds();
        $viewer = $this->getViewerFolders();

        foreach ($this->getFlatTree() as $item) {
            if (isset($hidden[$item['folder']['id']])) {
                continue;
            }

            // A non-admin has no access to this folder, so it stays out of the switcher. Its name would repeat its parent's.
            if ($viewer !== null && ! isset($viewer[(int) $item['folder']['id']])) {
                continue;
            }

            $groups[] = array(
                'id'       => $item['folder']['id'],
                'name'     => $this->getPathLabel($item['folder']['id']),
                'position' => ++$position,
            );
        }

        $assignments = $this->getAssignments();
        $projects = array();

        foreach ($this->projectModel->getAllIds() as $projectId) {
            $projectId = (int) $projectId;

            if (isset($assignments[$projectId], $hidden[$assignments[$projectId]])) {
                continue;
            }

            $projects[] = array(
                'project_id' => $projectId,
                'group_id'   => isset($assignments[$projectId]) ? $assignments[$projectId] : null,
            );
        }

        return array('groups' => $groups, 'projects' => $projects);
    }

    /**
     * Task counts per project id: array(open, subtasks (open subtasks of open tasks), overdue, soon, closed, today, unowned, stuck, blocked, mine), "soon" being due within $soonDays days
     * (7 unless the health setting says otherwise). Tasks with no due date count as open only. Closed tasks feed the progress bar.
     * The last five feed the BigBoard links and count open tasks the way the BigBoard search finds them: due today, no assignee,
     * last moved more than 30 days ago, with an "is blocked by" link (the blocker may be closed), and assigned to $userId.
     */
    public function getTaskTotals(array $projectIds, $soonDays = 7, $userId = 0)
    {
        $totals = array();
        $projectIds = array_values(array_unique(array_map('intval', $projectIds)));

        if (empty($projectIds)) {
            return $totals;
        }

        $now = time();
        $marks = implode(',', array_fill(0, count($projectIds), '?'));

        $sql = 'SELECT project_id,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS n_open,
                    SUM(CASE WHEN is_active = 1 AND date_due > 0 AND date_due < ? THEN 1 ELSE 0 END) AS n_overdue,
                    SUM(CASE WHEN is_active = 1 AND date_due >= ? AND date_due <= ? THEN 1 ELSE 0 END) AS n_soon,
                    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS n_closed,
                    SUM(CASE WHEN is_active = 1 AND date_due >= ? AND date_due < ? THEN 1 ELSE 0 END) AS n_today,
                    SUM(CASE WHEN is_active = 1 AND owner_id = 0 THEN 1 ELSE 0 END) AS n_unowned,
                    SUM(CASE WHEN is_active = 1 AND date_moved > 0 AND date_moved < ? THEN 1 ELSE 0 END) AS n_stuck,
                    SUM(CASE WHEN is_active = 1 AND id IN (SELECT task_id FROM task_has_links WHERE link_id IN (SELECT id FROM links WHERE label LIKE ?)) THEN 1 ELSE 0 END) AS n_blocked,
                    SUM(CASE WHEN is_active = 1 AND owner_id = ? THEN 1 ELSE 0 END) AS n_mine,
                    SUM(time_spent) AS hours,
                    SUM(time_estimated) AS estimated
                FROM tasks WHERE project_id IN ('.$marks.') GROUP BY project_id';

        $today = strtotime('today');
        $stuckBefore = strtotime(date('Y-m-d', $now - 30 * 86400));
        $params = array($now, $now, $now + (int) $soonDays * 86400, $today, strtotime('tomorrow'), $stuckBefore, 'is blocked by', (int) $userId);
        $rows = $this->db->execute($sql, array_merge($params, $projectIds))->fetchAll(\PDO::FETCH_ASSOC);

        // Open subtasks (not done) on open tasks, one count for each project.
        $subtasks = array();
        $subSql = 'SELECT t.project_id, COUNT(*) AS n FROM subtasks s JOIN tasks t ON t.id = s.task_id
                   WHERE t.is_active = 1 AND s.status < 2 AND t.project_id IN ('.$marks.') GROUP BY t.project_id';

        foreach ($this->db->execute($subSql, $projectIds)->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $subtasks[(int) $row['project_id']] = (int) $row['n'];
        }

        foreach ($rows as $row) {
            $totals[(int) $row['project_id']] = array(
                'subtasks' => isset($subtasks[(int) $row['project_id']]) ? $subtasks[(int) $row['project_id']] : 0,
                'open'    => (int) $row['n_open'],
                'overdue' => (int) $row['n_overdue'],
                'soon'    => (int) $row['n_soon'],
                'closed'  => (int) $row['n_closed'],
                'today'   => (int) $row['n_today'],
                'unowned' => (int) $row['n_unowned'],
                'stuck'   => (int) $row['n_stuck'],
                'blocked' => (int) $row['n_blocked'],
                'mine'    => (int) $row['n_mine'],
                'hours'   => round((float) $row['hours'], 2),
                'estimated' => round((float) $row['estimated'], 2),
            );
        }

        return $totals;
    }

    /**
     * Adds 'totals' to each node: its own projects plus every subfolder below it. Each project
     * row needs a 'sf_totals' key.
     */
    public function addTotals(array $nodes)
    {
        foreach ($nodes as $i => $node) {
            $sum = array('open' => 0, 'overdue' => 0, 'soon' => 0, 'closed' => 0, 'today' => 0, 'unowned' => 0, 'stuck' => 0, 'blocked' => 0, 'mine' => 0, 'subtasks' => 0, 'hours' => 0, 'estimated' => 0);
            $nodes[$i]['children'] = $this->addTotals($node['children']);

            foreach ($node['projects'] as $project) {
                foreach ($sum as $key => $value) {
                    $sum[$key] += isset($project['sf_totals'][$key]) ? $project['sf_totals'][$key] : 0;
                }
            }

            foreach ($nodes[$i]['children'] as $child) {
                foreach ($sum as $key => $value) {
                    $sum[$key] += isset($child['totals'][$key]) ? $child['totals'][$key] : 0;
                }
            }

            $nodes[$i]['totals'] = $sum;
        }

        return $nodes;
    }
}

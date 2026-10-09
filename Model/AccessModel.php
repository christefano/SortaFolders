<?php

namespace Kanboard\Plugin\SortaFolders\Model;

use Kanboard\Core\Base;
use Kanboard\Core\Security\Role;
use Kanboard\Model\GroupMemberModel;
use Kanboard\Model\GroupModel;
use Kanboard\Model\ProjectGroupRoleModel;
use Kanboard\Model\ProjectModel;
use Kanboard\Model\ProjectUserRoleModel;
use Kanboard\Model\UserModel;

/**
 * Folder access: an admin grants a user or a Kanboard group a project role on a folder, and the
 * grant reaches every project in the folder and its subfolders, now and later. A grant is only a
 * rule. sync() turns the rules into ordinary project memberships, and remembers the ones it added
 * (ACCESS_APPLIED_TABLE) so taking a grant away removes only those. A membership someone made by
 * hand is never touched, and personal projects are left out.
 */
class AccessModel extends Base
{
    const TABLE = 'sortafolders_access';
    const APPLIED_TABLE = 'sortafolders_access_applied';

    /**
     * Project metadata that lists the grant ids sync() added memberships for. Core copies metadata when
     * it duplicates a project, so a copy carries the list, and sync() adopts the memberships the copy
     * got from the original (see adopt()). Only sync() writes it, and the API refuses it (MetadataGuard).
     */
    const METADATA_KEY = 'sortafolders_applied';

    /**
     * The roles a grant can carry, lowest first.
     */
    public static function getRoles()
    {
        return array(
            Role::PROJECT_VIEWER  => 'Project Viewer',
            Role::PROJECT_MEMBER  => 'Project Member',
            Role::PROJECT_MANAGER => 'Project Manager',
        );
    }

    /**
     * Who can open the projects sorted directly into each folder, for the Access report on the settings page. Folder
     * id => array(projects => count, users => distinct users with a role there, rows => the users and groups).
     * A row is array(kind user|group, name, role text, given (a grant added it), by_hand (someone added it), from
     * (where the grant is), members (a group's size)). Admins are left out, closed and personal projects are not
     * counted, and a group is one row, not one row per member.
     */
    public function getReport()
    {
        $folders = $this->sortaFolderModel->getAll();
        $report = array();

        foreach ($folders as $id => $folder) {
            $report[$id] = array('projects' => 0, 'users' => 0, 'rows' => array());
        }

        $assignments = $this->sortaFolderModel->getAssignments();

        if (empty($assignments)) {
            return $report;
        }

        $byFolder = array();

        foreach ($this->db->table(ProjectModel::TABLE)->columns('id', 'is_active', 'is_private')->in('id', array_keys($assignments))->findAll() as $project) {
            if ((int) $project['is_active'] === 1 && (int) $project['is_private'] === 0) {
                $byFolder[$assignments[(int) $project['id']]][] = (int) $project['id'];
            }
        }

        $projectIds = array();

        foreach ($byFolder as $ids) {
            $projectIds = array_merge($projectIds, $ids);
        }

        if (empty($projectIds)) {
            return $report;
        }

        $grantFolder = array();

        foreach ($this->db->table(self::TABLE)->columns('id', 'folder_id')->findAll() as $grant) {
            $grantFolder[(int) $grant['id']] = (int) $grant['folder_id'];
        }

        // What a grant put on a project, so the report can tell it from a membership someone made by hand.
        $given = array();

        foreach ($this->db->table(self::APPLIED_TABLE)->in('project_id', $projectIds)->findAll() as $row) {
            $kind = (int) $row['user_id'] > 0 ? 'user' : 'group';
            $subject = (int) $row['user_id'] > 0 ? (int) $row['user_id'] : (int) $row['group_id'];
            $given[$kind.':'.$subject.':'.(int) $row['project_id']] = isset($grantFolder[(int) $row['grant_id']]) ? $grantFolder[(int) $row['grant_id']] : 0;
        }

        $users = array();
        $names = array();

        foreach ($this->db->table(UserModel::TABLE)->columns('id', 'username', 'name', 'role')->findAll() as $user) {
            if ($user['role'] !== Role::APP_ADMIN) {
                $users[(int) $user['id']] = true;
                $names[(int) $user['id']] = $user['name'] ?: $user['username'];
            }
        }

        $groupNames = array();
        $members = array();

        foreach ($this->db->table(GroupModel::TABLE)->columns('id', 'name')->findAll() as $group) {
            $groupNames[(int) $group['id']] = $group['name'];
        }

        foreach ($this->db->table(GroupMemberModel::TABLE)->findAll() as $row) {
            if (isset($users[(int) $row['user_id']])) {
                $members[(int) $row['group_id']][(int) $row['user_id']] = true;
            }
        }

        $memberships = array();

        foreach ($this->db->table(ProjectUserRoleModel::TABLE)->columns('project_id', 'user_id', 'role')->in('project_id', $projectIds)->findAll() as $row) {
            if (isset($users[(int) $row['user_id']])) {
                $memberships[(int) $row['project_id']][] = array('user', (int) $row['user_id'], $row['role']);
            }
        }

        foreach ($this->db->table(ProjectGroupRoleModel::TABLE)->columns('project_id', 'group_id', 'role')->in('project_id', $projectIds)->findAll() as $row) {
            if (isset($groupNames[(int) $row['group_id']])) {
                $memberships[(int) $row['project_id']][] = array('group', (int) $row['group_id'], $row['role']);
            }
        }

        $roles = self::getRoles();

        foreach ($byFolder as $folderId => $ids) {
            sort($ids);
            $subjects = array();
            $distinct = array();

            foreach ($ids as $projectId) {
                foreach (isset($memberships[$projectId]) ? $memberships[$projectId] : array() as $m) {
                    list($kind, $subject, $role) = $m;
                    $subjects[$kind][$subject][$projectId] = $role;
                    if ($kind === 'user') {
                        $distinct[$subject] = true;
                    } else {
                        $distinct += isset($members[$subject]) ? $members[$subject] : array();
                    }
                }
            }

            $rows = array();

            foreach ($subjects as $kind => $list) {
                foreach ($list as $subject => $perProject) {
                    $text = $this->roleText($perProject, count($ids), $roles);
                    $from = array();
                    $handMade = false;

                    foreach ($perProject as $projectId => $role) {
                        $key = $kind.':'.$subject.':'.$projectId;

                        if (isset($given[$key])) {
                            $from[$given[$key]] = true;
                        } else {
                            $handMade = true;
                        }
                    }

                    $rows[] = array(
                        'kind'    => $kind,
                        'name'    => $kind === 'user' ? $names[$subject] : $groupNames[$subject],
                        'role'    => $text,
                        'given'   => ! empty($from),
                        'by_hand' => $handMade,
                        'from'    => $this->fromText(array_keys($from), $folderId, $folders),
                        'members' => $kind === 'group' ? (isset($members[$subject]) ? count($members[$subject]) : 0) : 0,
                    );
                }
            }

            usort($rows, function ($a, $b) {
                return $a['kind'] === $b['kind'] ? strcasecmp($a['name'], $b['name']) : ($a['kind'] === 'user' ? -1 : 1);
            });

            $report[$folderId] = array('projects' => count($ids), 'users' => count($distinct), 'rows' => $rows);
        }

        return $report;
    }

    /**
     * "Manager" when the role is the same on every project in the folder, otherwise "Manager on #13, Member on #14".
     */
    private function roleText(array $perProject, $projectCount, array $roles)
    {
        $byRole = array();

        foreach ($perProject as $projectId => $role) {
            $byRole[$role][] = '#'.$projectId;
        }

        $label = function ($role) use ($roles) {
            return isset($roles[$role]) ? preg_replace('/^Project /', '', $roles[$role]) : $role;
        };

        if (count($byRole) === 1 && count($perProject) === $projectCount) {
            return $label(key($byRole));
        }

        $parts = array();

        foreach ($byRole as $role => $ids) {
            $parts[] = $label($role).' on '.implode(' ', $ids);
        }

        return implode(', ', $parts);
    }

    /**
     * Where a grant lives: "from this folder" or "from the parent folder Clients".
     */
    private function fromText(array $grantFolders, $folderId, array $folders)
    {
        $parts = array();

        foreach ($grantFolders as $id) {
            if ((int) $id === (int) $folderId) {
                $parts[] = $this->helper->sortaFolders->t('from this folder');
            } elseif (isset($folders[(int) $id])) {
                $parts[] = $this->helper->sortaFolders->t('from the parent folder %s', $folders[(int) $id]['name']);
            }
        }

        return implode(', ', array_unique($parts));
    }

    /**
     * Grants on one folder, in the order they were made.
     */
    public function getByFolder($folderId)
    {
        return $this->db->table(self::TABLE)->eq('folder_id', (int) $folderId)->asc('id')->findAll();
    }

    public function countByFolder($folderId)
    {
        return (int) $this->db->table(self::TABLE)->eq('folder_id', (int) $folderId)->count();
    }

    public function getById($grantId)
    {
        return $this->db->table(self::TABLE)->eq('id', (int) $grantId)->findOne();
    }

    /**
     * Name of the grant's user or group, or an empty string when it no longer exists.
     */
    public function getSubjectName(array $grant)
    {
        if ((int) $grant['user_id'] > 0) {
            $user = $this->userModel->getById((int) $grant['user_id']);

            return empty($user) ? '' : ($user['name'] ?: $user['username']);
        }

        $group = $this->groupModel->getById((int) $grant['group_id']);

        return empty($group) ? '' : $group['name'];
    }

    /**
     * Make a grant for one user or one group (the other id is 0), then apply it.
     *
     * @return array|false array(added, removed) from sync(), or false when the grant is not valid
     */
    public function add($folderId, $userId, $groupId, $role)
    {
        $folderId = (int) $folderId;
        $userId = (int) $userId;
        $groupId = (int) $groupId;

        if ($this->sortaFolderModel->getById($folderId) === null
            || ! array_key_exists($role, self::getRoles())
            || ($userId > 0) === ($groupId > 0)) {
            return false;
        }

        if ($userId > 0 && empty($this->userModel->getById($userId))) {
            return false;
        }

        if ($groupId > 0 && empty($this->groupModel->getById($groupId))) {
            return false;
        }

        $exists = $this->db->table(self::TABLE)
            ->eq('folder_id', $folderId)
            ->eq('user_id', $userId)
            ->eq('group_id', $groupId)
            ->exists();

        if ($exists) {
            return false;
        }

        $this->db->table(self::TABLE)->insert(array(
            'folder_id' => $folderId,
            'user_id'   => $userId,
            'group_id'  => $groupId,
            'role'      => $role,
        ));

        return $this->sync();
    }

    /**
     * Take a grant away. The memberships it added go too.
     */
    public function remove($grantId)
    {
        // A duplicate that nobody has synced yet carries copies of this grant's memberships. Adopt them first.
        $this->sync();

        if (! $this->db->table(self::TABLE)->eq('id', (int) $grantId)->remove()) {
            return false;
        }

        return $this->sync();
    }

    /**
     * Drop every grant on a folder that is being removed.
     */
    public function removeByFolder($folderId)
    {
        $this->sync();
        $this->db->table(self::TABLE)->eq('folder_id', (int) $folderId)->remove();

        return $this->sync();
    }

    /**
     * What sorting a project into each folder would give, for the forms where a manager picks a folder.
     * A folder carries its own grants and those of every folder above it.
     *
     * @return array folder id => list of array(user_id, name, role), user_id 0 for a group, folders with none left out
     */
    public function getGrantNotes(array $folderIds)
    {
        $roles = self::getRoles();
        $byFolder = array();

        foreach ($this->db->table(self::TABLE)->asc('id')->findAll() as $grant) {
            $byFolder[(int) $grant['folder_id']][] = $grant;
        }

        if (empty($byFolder)) {
            return array();
        }

        $notes = array();

        foreach ($folderIds as $folderId) {
            $folderId = (int) $folderId;

            foreach ($this->sortaFolderModel->getPath($folderId) as $folder) {
                if (empty($byFolder[(int) $folder['id']])) {
                    continue;
                }

                foreach ($byFolder[(int) $folder['id']] as $grant) {
                    $name = $this->getSubjectName($grant);

                    if ($name !== '') {
                        $notes[$folderId][] = array(
                            'user_id' => (int) $grant['user_id'],
                            'name'    => $name,
                            'role'    => t(isset($roles[$grant['role']]) ? $roles[$grant['role']] : $grant['role']),
                        );
                    }
                }
            }
        }

        return $notes;
    }

    /**
     * Make the project memberships match the grants. Memberships it added for grants that are gone,
     * or for projects that left the folder, are removed first, then the missing ones are added.
     *
     * @return array array(added, removed) counts
     */
    public function sync()
    {
        $this->pruneGrants();

        $grants = array();

        foreach ($this->db->table(self::TABLE)->findAll() as $grant) {
            $grants[(int) $grant['id']] = $grant;
        }

        $private = array_flip(array_map('intval', $this->db->table('projects')->eq('is_private', 1)->findAllByColumn('id')));
        $existing = array_flip(array_map('intval', $this->db->table('projects')->findAllByColumn('id')));
        $assignments = $this->sortaFolderModel->getAssignments();
        $desired = array();

        foreach ($grants as $id => $grant) {
            $folders = array_flip(array_merge(
                array((int) $grant['folder_id']),
                $this->sortaFolderModel->getDescendantIds($grant['folder_id'])
            ));

            foreach ($assignments as $projectId => $folderId) {
                if (isset($folders[$folderId]) && isset($existing[$projectId]) && ! isset($private[$projectId])) {
                    $desired[$id.':'.$projectId] = array($grant, (int) $projectId);
                }
            }
        }

        $applied = array();

        foreach ($this->db->table(self::APPLIED_TABLE)->findAll() as $row) {
            $applied[(int) $row['grant_id'].':'.(int) $row['project_id']] = $row;
        }

        $listed = $this->getListed();
        $added = 0;
        $removed = 0;

        $this->db->startTransaction();

        try {
            foreach ($applied as $key => $row) {
                if (isset($desired[$key])) {
                    continue;
                }

                $this->removeMembership($row);
                $this->db->table(self::APPLIED_TABLE)
                    ->eq('grant_id', (int) $row['grant_id'])
                    ->eq('project_id', (int) $row['project_id'])
                    ->remove();
                unset($applied[$key]);
                $removed++;
            }

            foreach ($desired as $key => $pair) {
                if (isset($applied[$key])) {
                    continue;
                }

                list($grant, $projectId) = $pair;

                // A duplicate carries its original's membership and its list of grants: the membership
                // came from this grant, so it is recorded and goes when the grant does.
                if (isset($listed[$projectId][(int) $grant['id']]) && $this->hasExact($projectId, $grant)) {
                    $applied[$key] = $this->record($grant, $projectId);
                    continue;
                }

                // Someone already made this person or group a member by hand, or the person already has
                // this role or a better one through a group, so leave it alone.
                if ($this->hasAccess($projectId, $grant)) {
                    continue;
                }

                if ((int) $grant['user_id'] > 0) {
                    $done = $this->projectUserRoleModel->addUser($projectId, (int) $grant['user_id'], $grant['role']);
                } else {
                    $done = $this->projectGroupRoleModel->addGroup($projectId, (int) $grant['group_id'], $grant['role']);
                }

                // A failed statement makes the database layer roll back, so stop here and let the caller log it.
                if (! $done) {
                    throw new \RuntimeException('could not add the membership on project '.$projectId);
                }

                $applied[$key] = $this->record($grant, $projectId);
                $added++;
            }

            $this->writeListed($applied, $listed);
        } catch (\Throwable $e) {
            $this->db->cancelTransaction();

            throw $e;
        }

        $this->db->closeTransaction();

        return array($added, $removed);
    }

    /**
     * Grants whose user or group is gone are deleted: core removed the memberships with the user or
     * group, and an id can be reused (SQLite), which would hand the grant to a stranger.
     */
    private function pruneGrants()
    {
        $grants = $this->db->escapeIdentifier(self::TABLE);
        $users = $this->db->escapeIdentifier('users');
        $groups = $this->db->escapeIdentifier('groups');
        $gone = array_merge(
            $this->db->execute('SELECT a.id FROM '.$grants.' a LEFT JOIN '.$users.' u ON u.id = a.user_id WHERE a.user_id > 0 AND u.id IS NULL')->fetchAll(\PDO::FETCH_COLUMN),
            $this->db->execute('SELECT a.id FROM '.$grants.' a LEFT JOIN '.$groups.' g ON g.id = a.group_id WHERE a.user_id = 0 AND g.id IS NULL')->fetchAll(\PDO::FETCH_COLUMN)
        );

        foreach ($gone as $id) {
            $this->db->table(self::TABLE)->eq('id', (int) $id)->remove();
        }
    }

    private function record(array $grant, $projectId)
    {
        $row = array(
            'grant_id'   => (int) $grant['id'],
            'project_id' => (int) $projectId,
            'user_id'    => (int) $grant['user_id'],
            'group_id'   => (int) $grant['group_id'],
            'role'       => $grant['role'],
        );

        $this->db->table(self::APPLIED_TABLE)->insert($row);

        return $row;
    }

    /**
     * @return array project id => array(grant id => true), from the projects' own metadata
     */
    private function getListed()
    {
        $listed = array();

        foreach ($this->db->table('project_has_metadata')->eq('name', self::METADATA_KEY)->findAll() as $row) {
            foreach (explode(',', (string) $row['value']) as $id) {
                if ((int) $id > 0) {
                    $listed[(int) $row['project_id']][(int) $id] = true;
                }
            }
        }

        return $listed;
    }

    /**
     * Keep each project's metadata list equal to the grants recorded for it. Written only when it differs.
     */
    private function writeListed(array $applied, array $listed)
    {
        $want = array();

        foreach ($applied as $row) {
            $want[(int) $row['project_id']][] = (int) $row['grant_id'];
        }

        foreach (array_unique(array_merge(array_keys($want), array_keys($listed))) as $projectId) {
            $new = array();

            if (isset($want[$projectId])) {
                $new = array_unique($want[$projectId]);
                sort($new);
            }

            $old = isset($listed[$projectId]) ? array_keys($listed[$projectId]) : array();
            sort($old);

            if ($new === $old) {
                continue;
            }

            $table = $this->db->table('project_has_metadata')->eq('project_id', $projectId)->eq('name', self::METADATA_KEY);

            if (empty($new)) {
                $table->remove();
            } elseif ($table->exists()) {
                $table->update(array('value' => implode(',', $new), 'changed_on' => time()));
            } else {
                $this->db->table('project_has_metadata')->insert(array(
                    'project_id' => $projectId,
                    'name'       => self::METADATA_KEY,
                    'value'      => implode(',', $new),
                    'changed_on' => time(),
                ));
            }
        }
    }

    /**
     * True when the person or group is a member with exactly the grant's role.
     */
    private function hasExact($projectId, array $grant)
    {
        if ((int) $grant['user_id'] > 0) {
            return $this->db->table(ProjectUserRoleModel::TABLE)
                ->eq('project_id', $projectId)
                ->eq('user_id', (int) $grant['user_id'])
                ->eq('role', $grant['role'])
                ->exists();
        }

        return $this->db->table(ProjectGroupRoleModel::TABLE)
            ->eq('project_id', $projectId)
            ->eq('group_id', (int) $grant['group_id'])
            ->eq('role', $grant['role'])
            ->exists();
    }

    private function hasAccess($projectId, array $grant)
    {
        if ((int) $grant['user_id'] > 0) {
            $direct = $this->db->table(ProjectUserRoleModel::TABLE)
                ->eq('project_id', $projectId)
                ->eq('user_id', (int) $grant['user_id'])
                ->exists();

            if ($direct) {
                return true;
            }

            // Core reads a direct role before any group role, so a direct row below the role the person
            // has through a group would lower it.
            $ranks = array_flip(array_keys(self::getRoles()));
            $groupRole = $this->projectGroupRoleModel->getUserRole($projectId, (int) $grant['user_id']);

            return $groupRole !== null && isset($ranks[$groupRole]) && $ranks[$groupRole] >= $ranks[$grant['role']];
        }

        return $this->db->table(ProjectGroupRoleModel::TABLE)
            ->eq('project_id', $projectId)
            ->eq('group_id', (int) $grant['group_id'])
            ->exists();
    }

    /**
     * Remove a membership this class added, unless a project manager has changed its role since.
     */
    private function removeMembership(array $row)
    {
        $projectId = (int) $row['project_id'];

        if ((int) $row['user_id'] > 0) {
            $this->db->table(ProjectUserRoleModel::TABLE)
                ->eq('project_id', $projectId)
                ->eq('user_id', (int) $row['user_id'])
                ->eq('role', $row['role'])
                ->remove();
        } else {
            $this->db->table(ProjectGroupRoleModel::TABLE)
                ->eq('project_id', $projectId)
                ->eq('group_id', (int) $row['group_id'])
                ->eq('role', $row['role'])
                ->remove();
        }
    }
}

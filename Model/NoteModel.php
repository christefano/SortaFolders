<?php

namespace Kanboard\Plugin\SortaFolders\Model;

use Kanboard\Core\Base;

/**
 * Status notes on a folder: a short plain-text note with a green, yellow or red flag. Whoever can see
 * a folder can read its notes. Admins and the Project Managers of an active project in the folder (or
 * a subfolder) can add one. The author and admins can delete one.
 */
class NoteModel extends Base
{
    const TABLE = 'sortafolders_notes';
    const BODY_MAX = 280;
    const PER_PAGE = 20;

    public static function getFlags()
    {
        return array('green', 'yellow', 'red');
    }

    public function cleanBody($body)
    {
        $body = trim(preg_replace('/\s+/u', ' ', (string) $body));

        return $body;
    }

    private function length($body)
    {
        return function_exists('mb_strlen') ? mb_strlen($body) : strlen($body);
    }

    /**
     * Ids of the active projects the user is a member of (directly or through a group) filed in this folder itself.
     * Projects in its subfolders don't count: a folder above the user's own is one they have no access to.
     */
    private function visibleProjectIds($userId, $folderId)
    {
        $mine = array_flip(array_map('intval', $this->projectPermissionModel->getActiveProjectIds($userId)));
        $ids = array();

        foreach ($this->sortaFolderModel->getAssignments() as $projectId => $assigned) {
            if ((int) $assigned === (int) $folderId && isset($mine[(int) $projectId])) {
                $ids[] = (int) $projectId;
            }
        }

        return $ids;
    }

    /**
     * Whether the user can see a folder: admins see every folder, others see one they made or one with an active project
     * of theirs filed in it, the same rule the folder lists use.
     */
    public function canRead($userId, $isAdmin, $folderId)
    {
        $folder = $this->sortaFolderModel->getById($folderId);

        if ($folder === null) {
            return false;
        }

        // A manager can always read the notes of a folder they made, even while it is still empty.
        return $isAdmin || (int) $folder['created_by'] === (int) $userId || count($this->visibleProjectIds($userId, $folderId)) > 0;
    }

    public function canWrite($userId, $isAdmin, $folderId)
    {
        if ($this->sortaFolderModel->getById($folderId) === null) {
            return false;
        }

        if ($isAdmin) {
            return true;
        }

        foreach ($this->visibleProjectIds($userId, $folderId) as $projectId) {
            if ($this->projectUserRoleModel->getUserRole($projectId, $userId) === 'project-manager') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return int|false id of the new note
     */
    public function create($folderId, $userId, $flag, $body)
    {
        $body = $this->cleanBody($body);

        if ($body === '' || $this->length($body) > self::BODY_MAX || ! in_array($flag, self::getFlags(), true) || $this->sortaFolderModel->getById($folderId) === null) {
            return false;
        }

        $inserted = $this->db->table(self::TABLE)->insert(array(
            'folder_id'     => (int) $folderId,
            'user_id'       => (int) $userId,
            'flag'          => $flag,
            'body'          => $body,
            'date_creation' => time(),
        ));

        return $inserted ? (int) $this->db->getLastId() : false;
    }

    public function getById($noteId)
    {
        $row = $this->db->table(self::TABLE)->eq('id', (int) $noteId)->findOne();

        return empty($row) ? null : $row;
    }

    public function canDelete($userId, $isAdmin, array $note)
    {
        return $isAdmin || ((int) $userId > 0 && (int) $note['user_id'] === (int) $userId);
    }

    public function remove($noteId)
    {
        return $this->db->table(self::TABLE)->eq('id', (int) $noteId)->remove();
    }

    public function removeByFolder($folderId)
    {
        return $this->db->table(self::TABLE)->eq('folder_id', (int) $folderId)->remove();
    }

    public function count($folderId)
    {
        return (int) $this->db->table(self::TABLE)->eq('folder_id', (int) $folderId)->count();
    }

    /**
     * One page of a folder's notes, newest first, with the author's name.
     */
    public function getPage($folderId, $limit, $offset)
    {
        return $this->decorate($this->db->table(self::TABLE)
            ->eq('folder_id', (int) $folderId)
            ->desc('id')
            ->limit((int) $limit)
            ->offset((int) $offset)
            ->findAll());
    }

    /**
     * Folder id => the newest note of each folder given.
     */
    public function getLatest(array $folderIds)
    {
        $folderIds = array_values(array_unique(array_filter(array_map('intval', $folderIds))));

        if (empty($folderIds)) {
            return array();
        }

        $marks = implode(',', array_fill(0, count($folderIds), '?'));
        $ids = $this->db->execute(
            'SELECT MAX(id) FROM '.self::TABLE.' WHERE folder_id IN ('.$marks.') GROUP BY folder_id',
            $folderIds
        )->fetchAll(\PDO::FETCH_COLUMN);

        if (empty($ids)) {
            return array();
        }

        $notes = array();

        foreach ($this->decorate($this->db->table(self::TABLE)->in('id', array_map('intval', $ids))->findAll()) as $note) {
            $notes[(int) $note['folder_id']] = $note;
        }

        return $notes;
    }

    /**
     * Adds the author's name to each note. A viewer sees it only for themselves, for an author they share an active
     * project with, and as an admin. Anyone else gets an empty name, so people who do not know of one another never
     * see each other's names.
     */
    private function decorate(array $rows)
    {
        $viewerId = (int) $this->userSession->getId();
        $viewerAdmin = $this->userSession->isAdmin();
        $mine = null;
        $shares = array();

        foreach ($rows as $i => $row) {
            $authorId = (int) $row['user_id'];
            $user = $authorId > 0 ? $this->userModel->getById($authorId) : null;

            if (empty($user)) {
                $rows[$i]['author_id'] = 0;
                $rows[$i]['author'] = t('Unknown user');
                continue;
            }

            if (! isset($shares[$authorId])) {
                if ($viewerAdmin || $authorId === $viewerId) {
                    $shares[$authorId] = true;
                } else {
                    if ($mine === null) {
                        $mine = array_flip(array_map('intval', $this->projectPermissionModel->getActiveProjectIds($viewerId)));
                    }

                    $shares[$authorId] = count(array_intersect_key($mine, array_flip(array_map('intval', $this->projectPermissionModel->getActiveProjectIds($authorId))))) > 0;
                }
            }

            $rows[$i]['author_id'] = $shares[$authorId] ? (int) $user['id'] : 0;
            $rows[$i]['author'] = $shares[$authorId] ? ($user['name'] !== '' ? $user['name'] : $user['username']) : '';
        }

        return $rows;
    }
}

<?php

namespace Kanboard\Plugin\SortaFolders\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\PageNotFoundException;
use Kanboard\Plugin\SortaFolders\Model\AccessModel;

/**
 * Folder access: who gets a project role on every project in a folder. Admins only (see Plugin.php).
 */
class FolderAccessController extends BaseController
{
    public function show(array $values = array(), array $errors = array())
    {
        $folder = $this->getFolder();
        $grants = array();

        // Repairs any filing that never ran a sync (audit F6), and drops grants whose user or group is gone.
        try {
            $this->sortaAccessModel->sync();
        } catch (\Throwable $e) {
            $this->logger->error('SortaFolders: could not update folder access: '.$e->getMessage());
        }

        foreach ($this->sortaAccessModel->getByFolder($folder['id']) as $grant) {
            $grant['subject'] = $this->sortaAccessModel->getSubjectName($grant);
            $grants[] = $grant;
        }

        $groups = array(0 => '—');

        foreach ($this->groupModel->getAll() as $group) {
            $groups[(int) $group['id']] = $group['name'];
        }

        $this->response->html($this->template->render('SortaFolders:access/show', array(
            'folder' => $folder,
            'path'   => $this->sortaFolderModel->getPathLabel($folder['id']),
            'grants' => $grants,
            'users'  => array(0 => '—') + $this->userModel->getActiveUsersList(),
            'groups' => $groups,
            'roles'  => array_map('t', AccessModel::getRoles()),
            'values' => $values + array('role' => 'project-member'),
            'errors' => $errors,
        )));
    }

    public function save()
    {
        $this->checkCSRFForm();

        $folder = $this->getFolder();
        $values = $this->request->getValues();
        $userId = isset($values['user_id']) ? (int) $values['user_id'] : 0;
        $groupId = isset($values['group_id']) ? (int) $values['group_id'] : 0;
        $role = isset($values['role']) ? (string) $values['role'] : '';

        if (($userId > 0) === ($groupId > 0)) {
            return $this->show($values, array('user_id' => array(t('Pick one user or one group, not both.'))));
        }

        $result = $this->sortaAccessModel->add($folder['id'], $userId, $groupId, $role);

        if ($result === false) {
            $this->flash->failure($this->helper->sortaFolders->t('Unable to grant access: it may already exist, or the user, the group, or the role is not valid.'));
        } else {
            $this->flash->success($this->helper->sortaFolders->t('Access granted: added to %d projects.', $result[0]));
        }

        $this->goToIndex();
    }

    public function remove()
    {
        $this->checkCSRFParam();

        $grant = $this->sortaAccessModel->getById($this->request->getIntegerParam('grant_id'));

        if (empty($grant)) {
            throw new PageNotFoundException();
        }

        $result = $this->sortaAccessModel->remove($grant['id']);

        if ($result === false) {
            $this->flash->failure($this->helper->sortaFolders->t('Unable to remove this access.'));
        } else {
            $this->flash->success($this->helper->sortaFolders->t('Access removed: taken from %d projects.', $result[1]));
        }

        $this->goToIndex();
    }

    private function getFolder()
    {
        $folder = $this->sortaFolderModel->getById($this->request->getIntegerParam('folder_id'));

        if ($folder === null) {
            throw new PageNotFoundException();
        }

        return $folder;
    }

    private function goToIndex()
    {
        $this->response->redirect($this->helper->url->to('FolderAdminController', 'index', array('plugin' => 'SortaFolders')));
    }
}

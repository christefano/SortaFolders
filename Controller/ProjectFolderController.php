<?php

namespace Kanboard\Plugin\SortaFolders\Controller;

use Kanboard\Controller\BaseController;

/**
 * Pick a project's folder. Project managers and admins only (see Plugin.php).
 */
class ProjectFolderController extends BaseController
{
    public function show()
    {
        $project = $this->getProject();

        $folderId = $this->sortaFolderModel->getFolderId($project['id']);
        $userId = $this->userSession->getId();
        $isAdmin = $this->userSession->isAdmin();
        $latest = $folderId > 0 ? $this->sortaNoteModel->getLatest(array($folderId)) : array();

        $this->response->html($this->helper->layout->project('SortaFolders:project/show', array(
            'node'    => $folderId > 0 ? array(
                'folder'   => array('id' => $folderId),
                'note'     => isset($latest[$folderId]) ? $latest[$folderId] : null,
                'can_note' => $this->sortaNoteModel->canWrite($userId, $isAdmin, $folderId),
                'can_view' => (int) $project['is_active'] === 1 && ! isset($this->sortaFolderModel->getHiddenIds()[$folderId]),
            ) : null,
            'project' => $project,
            'values'  => array('folder_id' => $this->sortaFolderModel->getFolderId($project['id'])),
            'options' => $this->sortaFolderModel->getFolderOptionsForUser($this->userSession->getId(), $this->sortaFolderModel->getFolderId($project['id'])),
            'title'   => $this->helper->sortaFolders->t('Edit folder'),
        )));
    }

    public function save()
    {
        $project = $this->getProject();
        $this->checkCSRFForm();

        $values = $this->request->getValues();
        $folderId = isset($values['folder_id']) ? (int) $values['folder_id'] : 0;

        $options = $this->sortaFolderModel->getFolderOptionsForUser($this->userSession->getId(), $this->sortaFolderModel->getFolderId($project['id']));

        if (isset($options[$folderId]) && $this->sortaFolderModel->assign($project['id'], $folderId)) {
            $this->flash->success($this->helper->sortaFolders->t('Folder saved.'));
        } else {
            $this->flash->failure($this->helper->sortaFolders->t('Unable to save the folder.'));
        }

        $this->response->redirect($this->helper->url->to('ProjectFolderController', 'show', array('plugin' => 'SortaFolders', 'project_id' => $project['id'])));
    }
}

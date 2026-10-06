<?php

namespace Kanboard\Plugin\SortaFolders\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Core\Controller\PageNotFoundException;
use Kanboard\Plugin\SortaFolders\Model\NoteModel;

/**
 * Folder notes on a folder: the notes page (with the form for people who may add one), add, and delete.
 * Anyone signed in may open it (see Plugin.php), and this controller then checks the folder itself: a folder
 * the viewer can't see answers like one that doesn't exist.
 */
class NoteController extends BaseController
{
    public function index()
    {
        $folder = $this->getReadableFolder();
        $folderId = (int) $folder['id'];
        $userId = $this->userSession->getId();
        $isAdmin = $this->userSession->isAdmin();

        $paginator = $this->paginator
            ->setUrl('NoteController', 'index', array('plugin' => 'SortaFolders', 'folder_id' => $folderId))
            ->setMax(NoteModel::PER_PAGE)
            ->setOrder('id')
            ->setDirection('DESC')
            ->setTotal($this->sortaNoteModel->count($folderId))
            ->calculate();

        $notes = $this->sortaNoteModel->getPage($folderId, NoteModel::PER_PAGE, ($paginator->getPage() - 1) * NoteModel::PER_PAGE);

        foreach ($notes as $i => $note) {
            $notes[$i]['can_delete'] = $this->sortaNoteModel->canDelete($userId, $isAdmin, $note);
        }

        $this->response->html($this->helper->layout->dashboard('SortaFolders:note/index', array(
            'title'     => $this->helper->sortaFolders->t('Folder notes'),
            'user'      => $this->getUser(),
            'folder'    => $folder,
            'path'      => $this->sortaFolderModel->getPathLabel($folderId),
            'notes'     => $notes,
            'paginator' => $paginator,
            'can_write' => $this->sortaNoteModel->canWrite($userId, $isAdmin, $folderId),
            'flags'     => NoteModel::getFlags(),
            'body_max'  => NoteModel::BODY_MAX,
        )));
    }

    /**
     * The "Add folder note" modal, opened from the folder page. Only people who may add a note get it.
     */
    public function create()
    {
        $folder = $this->getReadableFolder();

        if (! $this->sortaNoteModel->canWrite($this->userSession->getId(), $this->userSession->isAdmin(), $folder['id'])) {
            throw new AccessForbiddenException();
        }

        $this->response->html($this->template->render('SortaFolders:note/form', array(
            'folder'   => $folder,
            'flags'    => NoteModel::getFlags(),
            'body_max' => NoteModel::BODY_MAX,
        )));
    }

    public function save()
    {
        $this->checkCSRFForm();
        $folder = $this->getReadableFolder();

        if (! $this->sortaNoteModel->canWrite($this->userSession->getId(), $this->userSession->isAdmin(), $folder['id'])) {
            throw new AccessForbiddenException();
        }

        $values = $this->request->getValues();
        $flag = isset($values['flag']) ? (string) $values['flag'] : '';
        $body = isset($values['body']) ? (string) $values['body'] : '';

        if ($this->sortaNoteModel->create($folder['id'], $this->userSession->getId(), $flag, $body)) {
            $this->flash->success(t('Note added.'));
        } else {
            $this->flash->failure(t('Write a note and pick a flag.'));
        }

        if ($this->request->getStringParam('return') === 'show') {
            $this->response->redirect($this->helper->url->to('FolderController', 'show', array('plugin' => 'SortaFolders')));

            return;
        }

        $this->response->redirect($this->helper->url->to('NoteController', 'index', array('plugin' => 'SortaFolders', 'folder_id' => $folder['id'])));
    }

    public function remove()
    {
        $this->checkCSRFForm();
        $folder = $this->getReadableFolder();
        $note = $this->sortaNoteModel->getById($this->request->getIntegerParam('note_id'));

        if ($note === null || (int) $note['folder_id'] !== (int) $folder['id']) {
            throw new PageNotFoundException();
        }

        if (! $this->sortaNoteModel->canDelete($this->userSession->getId(), $this->userSession->isAdmin(), $note)) {
            throw new AccessForbiddenException();
        }

        $this->sortaNoteModel->remove($note['id']);
        $this->flash->success(t('Note removed.'));
        $this->response->redirect($this->helper->url->to('NoteController', 'index', array('plugin' => 'SortaFolders', 'folder_id' => $folder['id'])));
    }

    private function getReadableFolder()
    {
        $folderId = $this->request->getIntegerParam('folder_id');
        $folder = $this->sortaFolderModel->getById($folderId);

        if ($folder === null || ! $this->sortaNoteModel->canRead($this->userSession->getId(), $this->userSession->isAdmin(), $folderId)) {
            throw new PageNotFoundException();
        }

        return $folder;
    }
}

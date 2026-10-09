<?php

namespace Kanboard\Plugin\SortaFolders\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Core\Controller\PageNotFoundException;
use Kanboard\Plugin\SortaFolders\Helper\FolderHelper;
use Kanboard\Plugin\SortaFolders\Model\FolderModel;

/**
 * Create, rename, move, reorder, and remove folders. Admins only (see Plugin.php).
 */
class FolderAdminController extends BaseController
{
    public function index()
    {
        $counts = array_count_values($this->sortaFolderModel->getAssignments());

        // A folder page only exists for folders with an active project in them, so only those names link.
        list($nodes) = $this->sortaFolderModel->buildTree($this->getActiveProjects());
        $linkable = array();
        $collect = function (array $nodes) use (&$collect, &$linkable) {
            foreach ($nodes as $node) {
                $linkable[(int) $node['folder']['id']] = true;
                $collect($node['children']);
            }
        };
        $collect($nodes);

        $this->response->html($this->helper->layout->config('SortaFolders:admin/index', array(
            'title'  => t('Settings').' &gt; SortaFolders',
            'items'  => $this->sortaFolderModel->getFlatTree(),
            'counts' => $counts,
            'linkable' => $linkable,
            'term'   => $this->helper->sortaFolders->getTermKey(),
            'terms'  => FolderHelper::getTerms(),
            'icon'   => $this->helper->sortaFolders->getIconKey(),
            'icons'  => FolderHelper::getIcons(),
            'motd'   => $this->helper->sortaFolders->motdEnabled(),
            'attention_on' => $this->helper->sortaFolders->attentionEnabled(),
            'hidden_ids' => array_keys($this->sortaFolderModel->getHiddenIds()),
            'hidden_choices' => $this->hiddenChoices(),
            'health' => $this->sortaHealthModel->getSettings(),
            'grants' => $this->countGrants(),
            'report' => $this->sortaAccessModel->getReport(),
            'archived' => $this->archivedFolders(),
            'max_depth' => FolderModel::MAX_DEPTH,
        )));
    }

    /**
     * Every folder as id => path label, for the hide list. Ids stored directly, not the ones hidden through a parent.
     */
    private function hiddenChoices()
    {
        $choices = array();

        foreach ($this->sortaFolderModel->getFlatTree() as $item) {
            $choices[(int) $item['folder']['id']] = $this->sortaFolderModel->getPathLabel($item['folder']['id']);
        }

        return $choices;
    }

    /**
     * Folders that are archived: closed projects in them and nothing active. The row shows Unarchive for these
     * and Archive for the rest, never both.
     */
    private function archivedFolders()
    {
        $archived = array();

        foreach ($this->sortaFolderModel->getFlatTree() as $item) {
            $id = (int) $item['folder']['id'];

            if (! $this->sortaFolderModel->getActiveProjectIds($id) && $this->sortaFolderModel->getClosedProjectIds($id)) {
                $archived[$id] = true;
            }
        }

        return $archived;
    }

    public function saveSettings()
    {
        $this->checkCSRFForm();

        $values = $this->request->getValues();
        $key = isset($values['term']) ? (string) $values['term'] : 'default';

        if (! array_key_exists($key, FolderHelper::getTerms())) {
            $key = 'default';
        }

        $icon = isset($values['icon']) ? (string) $values['icon'] : 'folder-o';

        if (! array_key_exists($icon, FolderHelper::getIcons())) {
            $icon = 'folder-o';
        }

        $hide = array();

        foreach ((array) (isset($values['hidden']) ? $values['hidden'] : array()) as $id) {
            if ($this->sortaFolderModel->getById((int) $id) !== null) {
                $hide[(int) $id] = (int) $id;
            }
        }

        if ($this->configModel->save(array(
            FolderHelper::HIDDEN_KEY => implode(',', $hide),
            FolderHelper::TERM_KEY => $key,
            FolderHelper::ICON_KEY => $icon,
            FolderHelper::MOTD_KEY => empty($values['motd']) ? '0' : '1',
            FolderHelper::ATTENTION_KEY => empty($values['attention']) ? '0' : '1',
        ) + $this->sortaHealthModel->cleanValues($values))) {
            $this->flash->success(t('Settings saved successfully.'));
        } else {
            $this->flash->failure(t('Unable to save your settings.'));
        }

        $this->goToIndex();
    }

    /**
     * The parent choices for a new folder: every folder for an admin, the folders a Project Manager can already
     * pick (the ones holding their projects, and the ones they made) for anyone else.
     */
    private function getParentChoices()
    {
        $options = $this->sortaFolderModel->getParentOptions();

        if ($this->userSession->isAdmin()) {
            return $options;
        }

        $mine = $this->sortaFolderModel->getFolderOptionsForUser($this->userSession->getId());

        return array_intersect_key($options, $mine + array(0 => true));
    }

    private function checkCanCreate()
    {
        if (! $this->sortaFolderModel->canCreate($this->userSession->getId(), $this->userSession->isAdmin())) {
            throw new AccessForbiddenException();
        }
    }

    public function create(array $values = array(), array $errors = array())
    {
        $this->checkCanCreate();

        if (empty($values)) {
            $values = array(
                'parent_id' => $this->request->getIntegerParam('parent_id'),
                'return'    => $this->request->getStringParam('return') === 'index' ? 'index' : 'show',
            );
        }

        $this->response->html($this->template->render('SortaFolders:admin/form', array(
            'title'   => $this->helper->sortaFolders->t('New folder'),
            'action'  => $this->helper->url->href('FolderAdminController', 'save', array('plugin' => 'SortaFolders')),
            'values'  => $values,
            'errors'  => $errors,
            'parents' => $this->getParentChoices(),
        )));
    }

    public function save()
    {
        $this->checkCSRFForm();
        $this->checkCanCreate();

        $values = $this->request->getValues();
        $name = isset($values['name']) ? $values['name'] : '';
        $parentId = isset($values['parent_id']) ? (int) $values['parent_id'] : 0;
        $errors = array();

        if ($this->sortaFolderModel->cleanName($name) === '') {
            $errors['name'] = array(t('Enter a name of up to %d characters.', FolderModel::NAME_MAX));
        }

        if (! array_key_exists($parentId, $this->getParentChoices())) {
            $parentId = -1;
        }

        if ($parentId < 0 || ! $this->sortaFolderModel->canPlace(0, $parentId, 1)) {
            $errors['parent_id'] = array($this->helper->sortaFolders->t('A folder can go at most %d levels deep.', FolderModel::MAX_DEPTH));
        }

        if (! isset($errors['name']) && $parentId >= 0 && $this->sortaFolderModel->nameTaken($name, $parentId)) {
            $errors['name'] = array($this->helper->sortaFolders->t('A folder with this name already exists here.'));
        }

        if (! empty($errors)) {
            return $this->create($values, $errors);
        }

        if ($this->sortaFolderModel->create($name, $parentId, isset($values['description']) ? $values['description'] : '', $this->userSession->getId())) {
            $this->flash->success($this->helper->sortaFolders->t('Folder created.'));
        } else {
            $this->flash->failure($this->helper->sortaFolders->t('Unable to create this folder.'));
        }

        if (isset($values['return']) && $values['return'] === 'index' && $this->userSession->isAdmin()) {
            $this->goToIndex();
        } else {
            $this->response->redirect($this->helper->url->to('FolderController', 'show', array('plugin' => 'SortaFolders')));
        }
    }

    public function edit(array $values = array(), array $errors = array())
    {
        $folder = $this->getFolder();

        if (empty($values)) {
            $values = array(
                'name'        => $folder['name'],
                'parent_id'   => $folder['parent_id'],
                'description' => $folder['description'],
                'return'      => $this->request->getStringParam('return') === 'show' ? 'show' : 'index',
            );
        }

        $this->response->html($this->template->render('SortaFolders:admin/form', array(
            'title'   => $this->helper->sortaFolders->t('Edit folder'),
            'action'  => $this->helper->url->href('FolderAdminController', 'update', array('plugin' => 'SortaFolders', 'folder_id' => $folder['id'])),
            'values'  => $values,
            'errors'  => $errors,
            'parents' => $this->sortaFolderModel->getParentOptions($this->sortaFolderModel->getHeight($folder['id']), $folder['id']),
        )));
    }

    public function update()
    {
        $this->checkCSRFForm();

        $folder = $this->getFolder();
        $values = $this->request->getValues();
        $name = isset($values['name']) ? $values['name'] : '';
        $parentId = isset($values['parent_id']) ? (int) $values['parent_id'] : 0;
        $errors = array();

        if ($this->sortaFolderModel->cleanName($name) === '') {
            $errors['name'] = array(t('Enter a name of up to %d characters.', FolderModel::NAME_MAX));
        }

        if ($parentId !== $folder['parent_id'] && ! $this->sortaFolderModel->canPlace($folder['id'], $parentId, $this->sortaFolderModel->getHeight($folder['id']))) {
            $errors['parent_id'] = array($this->helper->sortaFolders->t('A folder can go at most %d levels deep and can\'t go inside itself.', FolderModel::MAX_DEPTH));
        }

        if (! isset($errors['name']) && $this->sortaFolderModel->nameTaken($name, $parentId, $folder['id'])) {
            $errors['name'] = array($this->helper->sortaFolders->t('A folder with this name already exists here.'));
        }

        if (! empty($errors)) {
            return $this->edit($values, $errors);
        }

        if ($this->sortaFolderModel->update($folder['id'], $name, $parentId, isset($values['description']) ? $values['description'] : '')) {
            $this->flash->success($this->helper->sortaFolders->t('Folder updated.'));
        } else {
            $this->flash->failure($this->helper->sortaFolders->t('Unable to update this folder.'));
        }

        if (isset($values['return']) && $values['return'] === 'show') {
            $this->response->redirect($this->helper->url->to('FolderController', 'show', array('plugin' => 'SortaFolders', 'scope' => 'all')));
        } else {
            $this->goToIndex();
        }
    }

    public function confirm()
    {
        $folder = $this->getFolder();

        $this->response->html($this->template->render('SortaFolders:admin/remove', array(
            'folder' => $folder,
            'grants' => $this->sortaAccessModel->countByFolder($folder['id']),
        )));
    }

    public function remove()
    {
        $this->checkCSRFParam();
        $folder = $this->getFolder();

        if ($this->sortaFolderModel->remove($folder['id'])) {
            $this->flash->success($this->helper->sortaFolders->t('Folder removed.'));
        } else {
            $this->flash->failure($this->helper->sortaFolders->t('Unable to remove this folder.'));
        }

        $this->goToIndex();
    }

    public function archiveConfirm()
    {
        $folder = $this->getFolder();
        $projects = $this->projectModel->getAllByIds($this->sortaFolderModel->getActiveProjectIds($folder['id']));

        $this->response->html($this->template->render('SortaFolders:admin/archive', array(
            'folder'   => $folder,
            'projects' => $projects,
        )));
    }

    /**
     * Archive a folder: close every active project in it and its subfolders. The folder, the sort, and
     * every task stay as they are, so a project can be reopened on Kanboard's Projects page. Personal
     * projects in the folder are closed too.
     */
    public function archive()
    {
        $this->checkCSRFParam();
        $folder = $this->getFolder();
        $closed = 0;

        foreach ($this->sortaFolderModel->getActiveProjectIds($folder['id']) as $projectId) {
            if ($this->projectModel->disable($projectId)) {
                $closed++;
            }
        }

        $this->flash->success($closed === 1
            ? $this->helper->sortaFolders->t('Folder archived: 1 project closed.')
            : $this->helper->sortaFolders->t('Folder archived: %d projects closed.', $closed));
        $this->goToIndex();
    }

    public function unarchiveConfirm()
    {
        $folder = $this->getFolder();

        $this->response->html($this->template->render('SortaFolders:admin/unarchive', array(
            'folder'   => $folder,
            'projects' => $this->projectModel->getAllByIds($this->sortaFolderModel->getClosedProjectIds($folder['id'])),
        )));
    }

    /**
     * Undo an archive: reopen every closed project in the folder and its subfolders, personal projects included.
     * A project that was closed before the folder was archived is reopened too, so the confirmation lists them all.
     */
    public function unarchive()
    {
        $this->checkCSRFParam();
        $folder = $this->getFolder();
        $opened = 0;

        foreach ($this->sortaFolderModel->getClosedProjectIds($folder['id']) as $projectId) {
            if ($this->projectModel->enable($projectId)) {
                $opened++;
            }
        }

        $this->flash->success($opened === 1
            ? $this->helper->sortaFolders->t('Folder unarchived: 1 project reopened.')
            : $this->helper->sortaFolders->t('Folder unarchived: %d projects reopened.', $opened));
        $this->goToIndex();
    }

    public function moveUp()
    {
        $this->reorder(-1);
    }

    public function moveDown()
    {
        $this->reorder(1);
    }

    private function reorder($direction)
    {
        $this->checkCSRFParam();
        $folder = $this->getFolder();

        $this->sortaFolderModel->move($folder['id'], $direction);
        $this->goToIndex();
    }

    private function countGrants()
    {
        $counts = array();

        foreach ($this->sortaFolderModel->getAll() as $id => $folder) {
            $counts[$id] = $this->sortaAccessModel->countByFolder($id);
        }

        return $counts;
    }

    private function getActiveProjects()
    {
        $projects = array();

        foreach ($this->projectModel->getAllByIds($this->projectModel->getAllIds()) as $project) {
            if ((int) $project['is_active'] === 1) {
                $projects[(int) $project['id']] = $project;
            }
        }

        return $projects;
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

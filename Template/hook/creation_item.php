<?php if ($this->sortaFolders->canCreateFolder()): ?>
    <li data-sortafolders="creation-item">
        <?= $this->modal->medium('plus', $this->sortaFolders->t('New folder'), 'FolderAdminController', 'create', array('plugin' => 'SortaFolders')) ?>
    </li>
<?php endif ?>

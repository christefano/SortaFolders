<li data-sortafolders="tab-folders">
    <?= $this->url->icon($this->sortaFolders->icon(true), $this->sortaFolders->t('Project folders'), 'FolderController', 'show', array('plugin' => 'SortaFolders')) ?>
</li>
<?php if ($this->sortaFolders->canCreateFolder()): ?>
    <li data-sortafolders="tab-item">
        <?= $this->modal->medium('plus', $this->sortaFolders->t('New folder'), 'FolderAdminController', 'create', array('plugin' => 'SortaFolders')) ?>
    </li>
<?php endif ?>

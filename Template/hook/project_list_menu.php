<li>
    <?= $this->url->icon($this->sortaFolders->icon(true), $this->sortaFolders->t('Project folders'), 'FolderController', 'show', array('plugin' => 'SortaFolders')) ?>
</li>
<?php if ($this->sortaFolders->canCreateFolder()): ?>
    <li>
        <?= $this->modal->medium('plus', $this->sortaFolders->t('New folder'), 'FolderAdminController', 'create', array('plugin' => 'SortaFolders')) ?>
    </li>
<?php endif ?>

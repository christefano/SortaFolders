<li data-sortafolders="user-menu-first">
    <?= $this->url->icon($this->sortaFolders->icon(), $this->sortaFolders->t('My folders'), 'FolderController', 'show', array('plugin' => 'SortaFolders')) ?>
</li>
<?php if ($this->user->isAdmin()): ?>
    <li data-sortafolders="user-menu-after">
        <?= $this->url->icon('cog', $this->sortaFolders->t('Folders management'), 'FolderAdminController', 'index', array('plugin' => 'SortaFolders')) ?>
    </li>
<?php endif ?>

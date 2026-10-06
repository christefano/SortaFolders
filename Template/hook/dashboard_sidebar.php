<li <?= $this->app->checkMenuSelection('FolderController', 'show') ?>>
    <?= $this->url->link($this->sortaFolders->t('My folders'), 'FolderController', 'show', array('plugin' => 'SortaFolders')) ?>
</li>

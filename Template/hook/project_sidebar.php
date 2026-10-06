<?php if ($this->user->hasProjectAccess('ProjectFolderController', 'show', $project['id'])): ?>
    <li data-sortafolders="project-item" <?= $this->app->checkMenuSelection('ProjectFolderController') ?>>
        <?= $this->url->link($this->sortaFolders->t('Edit folder'), 'ProjectFolderController', 'show', array('plugin' => 'SortaFolders', 'project_id' => $project['id'])) ?>
    </li>
<?php endif ?>

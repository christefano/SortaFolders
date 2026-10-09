<?php if ($this->user->hasProjectAccess('ProjectFolderController', 'show', $project['id']) && ! $this->sortaFolders->isHiddenProject($project['id'])): ?>
    <li data-sortafolders="project-item" <?= $this->app->checkMenuSelection('ProjectFolderController') ?>>
        <?= $this->url->link($this->sortaFolders->t('Edit folder'), 'ProjectFolderController', 'show', array('plugin' => 'SortaFolders', 'project_id' => $project['id'])) ?>
    </li>
<?php endif ?>
<?php $sfFolder = (isset($project['is_active']) && (int) $project['is_active'] === 0) ? null : $this->sortaFolders->getFolder($project['id']) ?>
<?php if ($sfFolder !== null): ?>
    <li data-sortafolders="project-folder-link">
        <?= $this->url->link($this->sortaFolders->t('View folder'), 'FolderController', 'show', $this->sortaFolders->folderParams($sfFolder['id'])) ?>
    </li>
<?php endif ?>

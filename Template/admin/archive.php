<div class="page-header">
    <h2><?= $this->sortaFolders->t('Archive a folder') ?></h2>
</div>

<div class="confirm">
    <p class="alert alert-info">
        <?= $this->sortaFolders->t('Do you really want to archive this folder: "%s"?', $folder['name']) ?>
    </p>

    <?php if (empty($projects)): ?>
        <p><?= $this->sortaFolders->t('It has no open project, so nothing would be closed.') ?></p>
    <?php else: ?>
        <p><?= $this->sortaFolders->t('This closes these projects:') ?></p>
        <ul class="sortafolders-archive-list">
            <?php foreach ($projects as $project): ?>
                <li><?= $this->text->e($project['name']) ?></li>
            <?php endforeach ?>
        </ul>
        <p><?= $this->sortaFolders->t('No tasks or projects are deleted. You can re-open a closed project any time.') ?></p>

        <?= $this->modal->confirmButtons(
            'FolderAdminController',
            'archive',
            array('plugin' => 'SortaFolders', 'folder_id' => $folder['id']),
            t('Archive')
        ) ?>
    <?php endif ?>
</div>

<div class="page-header">
    <h2><?= $this->sortaFolders->t('Unarchive a folder') ?></h2>
</div>

<div class="confirm">
    <p class="alert alert-info">
        <?= $this->sortaFolders->t('Do you really want to unarchive this folder: "%s"?', $folder['name']) ?>
    </p>

    <?php if (empty($projects)): ?>
        <p><?= $this->sortaFolders->t('It has no closed project, so nothing would be reopened.') ?></p>
    <?php else: ?>
        <p><?= $this->sortaFolders->t('This reopens these projects, in this folder and its subfolders:') ?></p>
        <ul class="sortafolders-archive-list">
            <?php foreach ($projects as $project): ?>
                <li><?= $this->text->e($project['name']) ?></li>
            <?php endforeach ?>
        </ul>
        <p><?= $this->sortaFolders->t('This includes any project that was closed before the folder was archived. No tasks are changed.') ?></p>

        <?= $this->modal->confirmButtons(
            'FolderAdminController',
            'unarchive',
            array('plugin' => 'SortaFolders', 'folder_id' => $folder['id']),
            t('Unarchive')
        ) ?>
    <?php endif ?>
</div>

<div class="page-header">
    <h2><?= $this->sortaFolders->t('Remove a folder') ?></h2>
</div>

<div class="confirm">
    <p class="alert alert-info">
        <?= $this->sortaFolders->t('Do you really want to remove this folder: "%s"?', $folder['name']) ?>
    </p>
    <p><?= $this->sortaFolders->t('Its subfolders and projects move up one level. No project is deleted.') ?></p>
    <?php if ($grants > 0): ?>
        <p><?= $this->sortaFolders->t('Its folder access ends too, and the project memberships that access added are removed.') ?></p>
    <?php endif ?>

    <?= $this->modal->confirmButtons(
        'FolderAdminController',
        'remove',
        array('plugin' => 'SortaFolders', 'folder_id' => $folder['id'])
    ) ?>
</div>

<div class="page-header">
    <h2><?= $this->sortaFolders->t('Edit folder') ?></h2>
</div>

<?php if (! empty($node)): ?>
    <?= $this->render('SortaFolders:folder/note', array('node' => $node)) ?>
<?php endif ?>

<?php if (count($options) <= 1): ?>
    <p class="alert">
        <?= $this->sortaFolders->t('There is no folder yet.') ?>
        <?php if ($this->user->hasAccess('FolderAdminController', 'index')): ?>
            <?= $this->url->link($this->sortaFolders->t('Manage folders'), 'FolderAdminController', 'index', array('plugin' => 'SortaFolders')) ?>
        <?php endif ?>
    </p>
<?php else: ?>
    <form method="post" action="<?= $this->url->href('ProjectFolderController', 'save', array('plugin' => 'SortaFolders', 'project_id' => $project['id'])) ?>" autocomplete="off">
        <?= $this->form->csrf() ?>

        <?= $this->form->label($this->sortaFolders->t('Folder for this project'), 'folder_id') ?>
        <?= $this->form->select('folder_id', $options, $values) ?>
        <?= $this->render('SortaFolders:hook/grant_notes', array('notes' => $this->sortaFolders->getGrantNotes($options))) ?>

        <div class="form-actions">
            <button type="submit" class="btn btn-blue"><?= t('Save') ?></button>
        </div>
    </form>
<?php endif ?>


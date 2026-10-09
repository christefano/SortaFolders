<div class="page-header">
    <h2><?= $this->sortaFolders->t('Add folder note') ?>: <?= $this->text->e($this->sortaFolders->folderName($folder)) ?></h2>
</div>
<form method="post" action="<?= $this->url->href('NoteController', 'save', array('plugin' => 'SortaFolders', 'folder_id' => $folder['id'], 'return' => 'show')) ?>" autocomplete="off">
    <?= $this->form->csrf() ?>
    <?= $this->form->label(t('Flag'), 'flag') ?>
    <select name="flag" id="form-flag">
        <?php foreach ($flags as $flag): ?>
            <option value="<?= $flag ?>"><?= $this->text->e(ucfirst(t(ucfirst($flag)))) ?></option>
        <?php endforeach ?>
    </select>
    <?= $this->form->label(t('Note'), 'body') ?>
    <textarea name="body" id="form-body" rows="3" maxlength="<?= (int) $body_max ?>" autofocus required></textarea>
    <p class="form-help"><?= t('Plain text, up to %d characters.', $body_max) ?> <?= $this->sortaFolders->t('Everyone who can see this folder will see this note.') ?></p>
    <?= $this->modal->submitButtons(array('submitLabel' => t('Add note'))) ?>
</form>

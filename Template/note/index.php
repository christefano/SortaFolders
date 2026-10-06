<div class="page-header">
    <h2><?= $this->sortaFolders->t('Folder notes') ?>: <?= $this->text->e($path) ?></h2>
    <ul>
        <li><?= $this->url->icon('arrow-left', $this->sortaFolders->t('Back to folders'), 'FolderController', 'show', array('plugin' => 'SortaFolders', 'folder_id' => $folder['id'])) ?></li>
    </ul>
</div>

<?php if ($can_write): ?>
    <form method="post" class="sortafolders-note-form" action="<?= $this->url->href('NoteController', 'save', array('plugin' => 'SortaFolders', 'folder_id' => $folder['id'])) ?>" autocomplete="off">
        <?= $this->form->csrf() ?>
        <?= $this->form->label(t('Flag'), 'flag') ?>
        <select name="flag" id="form-flag">
            <?php foreach ($flags as $flag): ?>
                <option value="<?= $flag ?>"><?= $this->text->e(ucfirst(t(ucfirst($flag)))) ?></option>
            <?php endforeach ?>
        </select>
        <?= $this->form->label(t('Note'), 'body') ?>
        <textarea name="body" id="form-body" rows="3" maxlength="<?= (int) $body_max ?>" required></textarea>
        <p class="form-help"><?= t('Plain text, up to %d characters.', $body_max) ?> <?= $this->sortaFolders->t('Everyone who can see this folder will see this note.') ?></p>
        <button type="submit" class="btn btn-blue"><?= t('Add note') ?></button>
    </form>
<?php endif ?>

<?php if (empty($notes)): ?>
    <p class="alert"><?= t('No note yet.') ?></p>
<?php else: ?>
    <ul class="sortafolders-notes">
        <?php foreach ($notes as $note): ?>
            <li>
                <?= $this->sortaFolders->flag($note['flag']) ?>
                <?= $this->text->e($note['body']) ?>
                <small><?= $note['author'] !== '' ? $this->sortaFolders->userLink($note['author_id'], $note['author']).', ' : '' ?><?= $this->dt->datetime((int) $note['date_creation']) ?></small>
                <?php if ($note['can_delete']): ?>
                    <form method="post" class="sortafolders-inline" action="<?= $this->url->href('NoteController', 'remove', array('plugin' => 'SortaFolders', 'folder_id' => $folder['id'], 'note_id' => $note['id'])) ?>">
                        <?= $this->form->csrf() ?>
                        <button type="submit" class="btn btn-red"><?= t('Remove') ?></button>
                    </form>
                <?php endif ?>
            </li>
        <?php endforeach ?>
    </ul>
    <?= $paginator ?>
<?php endif ?>

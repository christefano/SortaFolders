<?php if (! empty($notes)): ?>
    <div class="alert alert-info sortafolders-grant-notes">
        <p class="sortafolders-grant-lead"><?= $this->sortaFolders->t('Projects in a folder can be accessed by any users and groups given access to that folder or its parent folders.') ?></p>
        <?php foreach ($notes as $note): ?>
            <p class="sortafolders-grant-row">
                <strong><?= $this->text->e($note['path']) ?></strong>:
                <?php $entries = array() ?>
                <?php foreach ($note['list'] as $entry): ?>
                    <?php $entries[] = $this->sortaFolders->userLink($entry['user_id'], $entry['name']).' ('.$this->text->e($entry['role']).')' ?>
                <?php endforeach ?>
                <?= implode(', ', $entries) ?>
            </p>
        <?php endforeach ?>
    </div>
<?php endif ?>

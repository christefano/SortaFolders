<?php $note = empty($node['note']) ? null : $node['note'] ?>
<?php $parts = $note ? $this->sortaFolders->flagParts($note['flag']) : array() ?>
<div class="sortafolders-note<?= $note ? ' sortafolders-note-'.$this->text->e($note['flag']) : ' sortafolders-note-bare' ?>">
    <?php if (! empty($node['can_view'])): ?>
        <small class="sortafolders-note-view"><?= $this->url->link($this->sortaFolders->t('View folder'), 'FolderController', 'show', $this->sortaFolders->folderParams($node['folder']['id']), false, '', $this->sortaFolders->t('View this folder.')) ?></small>
    <?php endif ?>
    <?php if ($note): ?>
        <div class="sortafolders-note-main">
            <?php if ($parts): ?>
                <span title="<?= $this->text->e($parts['reason']) ?>"><i class="fa fa-<?= $parts['icon'] ?> fa-fw" aria-hidden="true"></i><span class="sortafolders-sr"><?= $this->text->e($parts['word']) ?>: <?= $this->text->e($parts['reason']) ?>. </span></span>
            <?php endif ?>
            <?= $this->text->e($note['body']) ?>
            <small class="sortafolders-note-by"><?= $note['author'] !== '' ? $this->sortaFolders->userLink($note['author_id'], $note['author']).' ' : '' ?><?= $this->dt->date((int) $note['date_creation']) ?></small>
        </div>
    <?php endif ?>
    <small class="sortafolders-note-aside">
        <div class="dropdown">
            <a href="#" class="dropdown-menu" aria-label="<?= $this->text->e($this->sortaFolders->t('Folder links')) ?>"><i class="fa fa-ellipsis-h fa-fw" aria-hidden="true"></i><i class="fa fa-caret-down" aria-hidden="true"></i></a>
            <ul>
                <?php if (! empty($node['can_note'])): ?>
                    <li><a href="<?= $this->url->href('NoteController', 'create', array('plugin' => 'SortaFolders', 'folder_id' => $node['folder']['id'])) ?>" class="js-modal-small"><?= $this->sortaFolders->t('Add note') ?></a></li>
                <?php endif ?>
                <li><a href="<?= $this->url->href('NoteController', 'index', array('plugin' => 'SortaFolders', 'folder_id' => $node['folder']['id'])) ?>"><?= $this->sortaFolders->t('View notes') ?></a></li>
                <?php if (! empty($node['can_view'])): ?>
                    <li><a href="<?= $this->url->href('FolderController', 'show', $this->sortaFolders->folderParams($node['folder']['id'])) ?>"><?= $this->sortaFolders->t('View folder') ?></a></li>
                <?php endif ?>
                <?php if ($this->user->hasAccess('FolderAdminController', 'edit')): ?>
                    <li><a href="<?= $this->url->href('FolderAdminController', 'edit', array('plugin' => 'SortaFolders', 'folder_id' => $node['folder']['id'], 'return' => 'show')) ?>" class="js-modal-medium"><?= $this->sortaFolders->t('Edit folder') ?></a></li>
                <?php endif ?>
                <?php if ($node['count'] > 0): ?>
                    <?= $this->sortaFolders->bigboardMenuItems($node['folder']['id'], $node['totals']) ?>
                <?php endif ?>
            </ul>
        </div>
    </small>
</div>

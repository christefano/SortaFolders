<?php $folder = (isset($project['is_active']) && (int) $project['is_active'] === 0) ? null : $this->sortaFolders->getFolder($project['id']) ?>
<?php if ($folder !== null && $folder['description'] !== '' && $this->sortaFolders->motdEnabled()): ?>
    <details class="sortafolders-motd" data-motd="<?= (int) $folder['id'] ?>" open>
        <summary>
            <i class="fa fa-<?= $this->sortaFolders->icon(true) ?> fa-fw" aria-hidden="true"></i>
            <strong><?= $this->text->e($folder['name']) ?></strong>
        </summary>
        <div class="markdown"><?= $this->text->markdown($folder['description']) ?></div>
    </details>
<?php endif ?>

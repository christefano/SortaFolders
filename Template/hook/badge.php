<?php $folder = (isset($project['is_active']) && (int) $project['is_active'] === 0) ? null : $this->sortaFolders->getFolder($project['id']) ?>
<?php if ($folder !== null): ?>
    <?= $this->url->link(
        '<i class="fa fa-'.$this->sortaFolders->icon().' fa-fw" aria-hidden="true"></i>'.$this->text->e($folder['name'])
            .($folder['path'] !== $folder['name'] ? '<span class="sortafolders-sr"> ('.$this->text->e($folder['path']).')</span>' : ''),
        'FolderController',
        'show',
        $this->sortaFolders->folderParams($folder['id']),
        false,
        'sortafolders-badge',
        $this->text->e($folder['path'])
    ) ?>
<?php endif ?>

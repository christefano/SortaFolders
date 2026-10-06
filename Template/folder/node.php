<details class="sortafolders-node" data-folder="<?= (int) $node['folder']['id'] ?>" open>
    <summary class="table-list-row table-border-left sortafolders-folder">
        <i class="fa fa-<?= $this->sortaFolders->icon(true) ?> fa-fw" aria-hidden="true"></i>
        <strong><?= $this->text->e($node['folder']['name']) ?></strong>
        <?php if ((int) $node['folder']['id'] > 0 && $node['folder']['description'] !== ''): ?>
            <span class="sortafolders-info"><?= $this->app->tooltipMarkdown($node['folder']['description']) ?></span>
        <?php endif ?>
        <?php
        if ($node['count'] === 0) {
            $counts = $this->sortaFolders->t('No active projects');
        } else {
            $hours = $this->sortaFolders->hoursText($node['totals']);
            $counts = $this->sortaFolders->summaryText($node['count'], $node['totals']).'.'.($hours !== '' ? ' '.$hours.'.' : '');
        }
        ?>
        <small class="sortafolders-count"><?= $counts ?></small>
        <?php if ($node['count'] > 0): ?>
            <span class="sortafolders-meta"><?= $this->render('SortaFolders:folder/progress', array('totals' => $node['totals'])) ?></span>
        <?php endif ?>
    </summary>
    <?php if ((int) $node['folder']['id'] > 0): ?>
        <?= $this->render('SortaFolders:folder/note', array('node' => $node)) ?>
    <?php endif ?>
    <div class="sortafolders-children">
        <?php foreach ($node['projects'] as $project): ?>
            <?= $this->render('SortaFolders:folder/project', array('project' => $project)) ?>
        <?php endforeach ?>

        <?php foreach ($node['children'] as $child): ?>
            <?= $this->render('SortaFolders:folder/node', array('node' => $child)) ?>
        <?php endforeach ?>
    </div>
</details>

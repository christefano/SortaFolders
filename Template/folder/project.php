<div class="table-list-row table-border-left sortafolders-project">
    <div>
        <?php if ($this->user->hasProjectAccess('ProjectViewController', 'show', $project['id'])): ?>
            <?= $this->render('project/dropdown', array('project' => $project)) ?>
        <?php else: ?>
            <strong><?= '#'.$project['id'] ?></strong>
        <?php endif ?>

        <span class="table-list-title">
            <?= $this->url->link($this->text->e($project['name']), 'BoardViewController', 'show', array('project_id' => $project['id'])) ?>
        </span>
        <?= $this->sortaFolders->light($project['sf_health']['level'], $project['sf_health']['reason']) ?>
        <?php if ($project['sf_health']['level'] === 'none'): ?>
            <small class="sortafolders-quiet"><?= t('No open tasks') ?></small>
        <?php endif ?>
        <?php if ($project['sf_health']['quiet_days'] !== null && $project['sf_health']['quiet_days'] >= $this->sortaFolders->healthSetting('yellow_quiet')): ?>
            <small class="sortafolders-quiet"><?= t('Quiet for %d days', (int) $project['sf_health']['quiet_days']) ?></small>
        <?php endif ?>
        <div class="sortafolders-details">
        <span><?= $this->sortaFolders->totalsText($project['sf_totals']) ?></span>
        <span class="sortafolders-meta"><?= $this->render('SortaFolders:folder/progress', array('totals' => $project['sf_totals'])) ?></span>
        <?php if ($this->sortaFolders->hoursText($project['sf_totals']) !== ''): ?>
            <span><?= $this->sortaFolders->hoursText($project['sf_totals']) ?></span>
        <?php endif ?>
        <?php if (! empty($project['sf_managers'])): ?>
            <span><?= count($project['sf_managers']) === 1 ? t('Manager') : t('Managers') ?>: <?= $this->sortaFolders->managerList($project['sf_managers']) ?></span>
        <?php endif ?>
        <?php if ($this->sortaFolders->lastActivity((int) $project['last_modified']) !== ''): ?>
            <span><?= $this->text->e($this->sortaFolders->lastActivity((int) $project['last_modified'])) ?></span>
        <?php endif ?>
        </div>
    </div>

    <div class="table-list-icons">
        <span class="sortafolders-spark-box" title="<?= t('Activity, last 12 weeks') ?>"><?= $this->sortaFolders->sparkline($project['sf_spark']) ?> <small><?= t('12 weeks') ?></small></span>
        <?php if ($project['is_public']): ?>
            <i class="fa fa-share-alt fa-fw" title="<?= t('Shared project') ?>" role="img" aria-label="<?= t('Shared project') ?>"></i>
        <?php endif ?>

        <?php if ($project['is_private']): ?>
            <i class="fa fa-lock fa-fw" title="<?= t('Personal project') ?>" role="img" aria-label="<?= t('Personal project') ?>"></i>
        <?php endif ?>

        <?php if (! empty($project['description'])): ?>
            <?= $this->app->tooltipMarkdown($project['description']) ?>
        <?php endif ?>
    </div>
</div>

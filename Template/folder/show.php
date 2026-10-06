<div class="page-header">
    <?php if ($focus_path !== ''): ?>
        <h2><?= t('Everything for') ?> <strong><?= $this->text->e($focus_path) ?></strong></h2>
    <?php else: ?>
        <h2>
            <?= $this->text->e($all ? $this->sortaFolders->t('All folders') : $this->sortaFolders->t('My folders')) ?>
            (<?= (int) $folder_count ?>)
        </h2>
    <?php endif ?>
</div>

<?php if ($focus_path !== ''): ?>
    <p class="sortafolders-path">
        <?= $this->url->link($this->sortaFolders->t('Project folders'), 'FolderController', 'show', array('plugin' => 'SortaFolders')) ?>
        &rsaquo; <strong><?= $this->text->e($focus_path) ?></strong>
    </p>
    <?php if ($focus_description !== ''): ?>
        <p class="sortafolders-description"><?= $this->text->e($focus_description) ?></p>
    <?php endif ?>
<?php endif ?>

<?= $this->hook->render('template:sortafolders:folder:before-tabs', array(
    'sf_page' => 'folder',
    'sf_scope' => $sf_scope,
    'sf_mine' => null,
    'url_params' => $url_params,
    'ask_controller' => 'FolderController',
    'ask_action' => 'show',
)) ?>
<?= $this->render('SortaFolders:folder/strip', array('attention' => $attention)) ?>
<?= $this->render('SortaFolders:folder/chips', array('filters' => $filters, 'filter' => $filter, 'url_params' => $url_params, 'all' => $all, 'kind' => 'projects', 'counts' => $tab_counts, 'bb' => $tab_bigboard)) ?>

<?php if (empty($nodes) && $loose === null): ?>
    <p class="alert"><?= $filter !== 'all' ? t('No project matches this filter.') : ($all ? t('There is no project.') : t('You are not a member of any project.')) ?></p>
<?php else: ?>
    <div class="table-list sortafolders-tree">
        <div class="table-list-header">
            <div class="table-list-header-count">
                <?= $this->sortaFolders->summaryText($project_count, $totals) ?>
            </div>
            <div class="table-list-header-menu">
                <?= $this->render('SortaFolders:folder/sort_menu', array('paginator' => $paginator)) ?>
            </div>
        </div>

        <?php foreach ($nodes as $node): ?>
            <?= $this->render('SortaFolders:folder/node', array('node' => $node)) ?>
        <?php endforeach ?>

        <?php if ($loose !== null): ?>
            <?= $this->render('SortaFolders:folder/node', array('node' => $loose)) ?>
        <?php endif ?>
    </div>

    <?= $paginator ?>
<?php endif ?>

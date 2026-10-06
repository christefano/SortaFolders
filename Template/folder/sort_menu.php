<div class="dropdown">
    <a href="#" class="dropdown-menu dropdown-menu-link-icon"><strong><?= t('Sort') ?> <i class="fa fa-caret-down"></i></strong></a>
    <ul>
        <li>
            <?= $paginator->order(t('Project ID'), 'projects.id') ?>
        </li>
        <li>
            <?= $paginator->order(t('Project name'), 'projects.name') ?>
        </li>
        <li>
            <?= $paginator->order(t('Last activity'), 'projects.last_modified') ?>
        </li>
        <li>
            <?= $paginator->order(t('Start date'), 'projects.start_date') ?>
        </li>
        <li>
            <?= $paginator->order(t('End date'), 'projects.end_date') ?>
        </li>
        <li>
            <?= $paginator->order(t('Public'), 'projects.is_public') ?>
        </li>
        <li>
            <?= $paginator->order(t('Personal'), 'projects.is_private') ?>
        </li>
    </ul>
</div>

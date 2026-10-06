<?php
// The one tab bar of the folder page and the dashboard pages. Clicking a tab's label filters the list. A tab BigBoard can answer also
// has a small caret with a two-item menu: "Filter this list" and "Open in BigBoard". A tab with no match is dimmed but stays a link.
// Variables: filters (key => label), filter (the open one), url_params, all, kind ("projects", "tasks" or "subtasks"), counts (key => matches),
// bb (null without BigBoard, else scope, suffix and counts for the BigBoard searches), chips_controller and chips_action.
$chipsController = isset($chips_controller) ? $chips_controller : 'FolderController';
$chipsAction = isset($chips_action) ? $chips_action : 'show';
$kind = isset($kind) ? $kind : 'projects';
$counts = isset($counts) ? $counts : array();
$bb = isset($bb) ? $bb : null;
?>
<div class="table-list sortafolders-tabs">
<div class="views-switcher-component">
    <ul class="views" aria-label="<?= t('Filter') ?>">
        <?php foreach ($filters as $key => $label): ?>
            <?php if ($key === 'all' && $chipsController === 'FolderController' && $this->user->hasAccess('FolderAdminController', 'index')): ?>
                <li class="dropdown<?= $filter === $key ? ' active' : '' ?>">
                    <a href="#" class="dropdown-menu" title="<?= $this->text->e($this->sortaFolders->t('Choose which projects to view.')) ?>"<?= $filter === $key ? ' aria-current="true"' : '' ?>><i class="fa fa-<?= $this->sortaFolders->tabIcon($key) ?> fa-fw" aria-hidden="true"></i><?= $this->text->e($all ? $this->sortaFolders->t('All folders') : $this->sortaFolders->t('My folders')) ?> <i class="fa fa-caret-down" aria-hidden="true"></i></a>
                    <ul>
                        <li><?= $this->url->link($this->sortaFolders->t('My folders'), 'FolderController', 'show', array('plugin' => 'SortaFolders'), false, '', $this->sortaFolders->t('View the projects you belong to.')) ?></li>
                        <li><?= $this->url->link($this->sortaFolders->t('All folders'), 'FolderController', 'show', array('plugin' => 'SortaFolders', 'scope' => 'all'), false, '', $this->sortaFolders->t('View every active project.')) ?></li>
                    </ul>
                </li>
                <?php continue ?>
            <?php endif ?>
            <?php
            $empty = $key !== 'all' && isset($counts[$key]) && (int) $counts[$key] === 0;
            $filterUrl = $this->url->href($chipsController, $chipsAction, $url_params + array('filter' => $key));
            $search = $bb !== null ? $this->sortaFolders->tabSearch($key) : '';
            $classes = trim(($filter === $key ? 'active ' : '').($empty ? 'sortafolders-tab-off' : ''));
            ?>
            <li<?= $classes !== '' ? ' class="'.$classes.'"' : '' ?>>
                <a class="sortafolders-tab-label" href="<?= $filterUrl ?>" title="<?= $this->text->e($this->sortaFolders->tabTitle($kind, $key, $empty)) ?>"<?= $filter === $key ? ' aria-current="true"' : '' ?>><i class="fa fa-<?= $this->sortaFolders->tabIcon($key) ?> fa-fw" aria-hidden="true"></i><?= $this->text->e($key === 'soon' ? $this->sortaFolders->soonLabel() : t($label)) ?></a>
                <?php if ($search !== ''): ?>
                    <?php
                    $bbEmpty = isset($bb['counts'][$key]) && (int) $bb['counts'][$key] === 0;
                    $bbUrl = $this->url->href('Bigboard', 'index', array('plugin' => 'Bigboard') + $bb['scope'] + array('search' => $search.$bb['suffix']));
                    ?>
                    <span class="dropdown sortafolders-tab-caret">
                        <a href="#" class="dropdown-menu" aria-label="<?= $this->text->e(t('Open the menu for %s.', $key === 'soon' ? $this->sortaFolders->soonLabel() : t($label))) ?>"><i class="fa fa-caret-down" aria-hidden="true"></i></a>
                        <ul>
                            <li<?= $empty ? ' class="sortafolders-link-off"' : '' ?>><a href="<?= $filterUrl ?>" title="<?= $this->text->e($this->sortaFolders->tabTitle($kind, $key, $empty)) ?>"><?= $this->text->e(rtrim($this->sortaFolders->tabTitle($kind, $key), '.')) ?></a></li>
                            <li<?= $bbEmpty ? ' class="sortafolders-link-off"' : '' ?>><a href="<?= $this->text->e($bbUrl) ?>" title="<?= $this->text->e($this->sortaFolders->bigboardTitle($kind, $key, $bbEmpty)) ?>"><?= t('Switch to BigBoard') ?></a></li>
                        </ul>
                    </span>
                <?php endif ?>
            </li>
        <?php endforeach ?>
    </ul>
    <?php if ($filter !== 'all'): ?>
        <a class="sortafolders-clear" href="<?= $this->url->href($chipsController, $chipsAction, $url_params + array('filter' => 'all')) ?>" title="<?= t('View the whole list.') ?>"><?= t('Clear') ?></a>
    <?php endif ?>
</div>
</div>

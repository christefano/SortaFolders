<?php
$page = $this->sortaDashboard->page();
?>
<?php if ($page !== ''): ?>
    <?php $params = array('user_id' => $user['id']) ?>
    <?php $viewed = $this->sortaDashboard->pageUserId() ?>
    <?= $this->hook->render('template:sortafolders:dashboard:before-tabs', array(
        'sf_page' => $page,
        'sf_scope' => $this->sortaDashboard->scope($viewed),
        'sf_mine' => $this->sortaDashboard->mine($page, $viewed),
        'url_params' => $params,
        'ask_controller' => 'DashboardController',
        'ask_action' => $page,
    )) ?>
    <?= $this->render('SortaFolders:folder/chips', array(
        'filters' => $this->sortaDashboard->filters($page),
        'filter' => $this->sortaDashboard->filter($page),
        'url_params' => $params,
        'all' => false,
        'chips_controller' => 'DashboardController',
        'chips_action' => $page,
    ) + $this->sortaDashboard->tabs($page)) ?>
<?php endif ?>
<?php if ($page === '' && $this->sortaDashboard->isOverview()): ?>
    <?= $this->hook->render('template:sortafolders:dashboard:before-tabs', array(
        'sf_page' => 'overview',
        'sf_scope' => $this->sortaDashboard->scope($this->sortaDashboard->pageUserId()),
        'sf_mine' => null,
        'url_params' => array('user_id' => $user['id']),
        'ask_controller' => 'DashboardController',
        'ask_action' => 'show',
    )) ?>
<?php endif ?>

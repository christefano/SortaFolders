<div class="page-header">
    <h2><?= $this->sortaFolders->t('Folder access') ?>: <?= $this->text->e($path) ?></h2>
</div>

<p class="alert alert-info">
    <?= $this->sortaFolders->t('A user or group listed here gets this role on every project in this folder and its subfolders, now and for projects sorted here later. Access added this way is taken away again when the project leaves the folder or the access is removed here. Memberships that someone added by hand are never changed or removed. Personal projects are left out.') ?>
</p>

<?php if (empty($grants)): ?>
    <p><?= $this->sortaFolders->t('Nobody has access through this folder yet.') ?></p>
<?php else: ?>
    <table class="table-striped">
        <tr>
            <th><?= t('User or group') ?></th>
            <th><?= t('Role') ?></th>
            <th><?= t('Action') ?></th>
        </tr>
        <?php foreach ($grants as $grant): ?>
            <tr>
                <td>
                    <i class="fa fa-<?= (int) $grant['user_id'] > 0 ? 'user' : 'users' ?> fa-fw" aria-hidden="true"></i>
                    <?= $grant['subject'] === '' ? '<em>'.t('Removed').'</em>' : ((int) $grant['user_id'] > 0 ? $this->sortaFolders->userLink($grant['user_id'], $grant['subject']) : $this->text->e($grant['subject'])) ?>
                </td>
                <td><?= $this->text->e(isset($roles[$grant['role']]) ? $roles[$grant['role']] : $grant['role']) ?></td>
                <td>
                    <?= $this->url->icon('trash-o', t('Remove'), 'FolderAccessController', 'remove', array('plugin' => 'SortaFolders', 'folder_id' => $folder['id'], 'grant_id' => $grant['id']), true) ?>
                </td>
            </tr>
        <?php endforeach ?>
    </table>
<?php endif ?>

<form method="post" action="<?= $this->url->href('FolderAccessController', 'save', array('plugin' => 'SortaFolders', 'folder_id' => $folder['id'])) ?>" autocomplete="off">
    <?= $this->form->csrf() ?>

    <h3><?= t('Add access') ?></h3>

    <?= $this->form->label(t('User'), 'user_id') ?>
    <?= $this->form->select('user_id', $users, $values, $errors) ?>

    <?= $this->form->label(t('Group'), 'group_id') ?>
    <?= $this->form->select('group_id', $groups, $values, $errors) ?>

    <?= $this->form->label(t('Role'), 'role') ?>
    <?= $this->form->select('role', $roles, $values) ?>

    <?= $this->modal->submitButtons(array('submitLabel' => t('Grant access'))) ?>
</form>

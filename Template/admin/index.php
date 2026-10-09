<div class="page-header">
    <h2>SortaFolders</h2>
    <ul>
        <li>
            <?= $this->modal->medium('plus', $this->sortaFolders->t('New folder'), 'FolderAdminController', 'create', array('plugin' => 'SortaFolders', 'return' => 'index')) ?>
        </li>
        <li>
            <?= $this->url->icon($this->sortaFolders->icon(true), $this->sortaFolders->t('View folders'), 'FolderController', 'show', array('plugin' => 'SortaFolders')) ?>
        </li>
    </ul>
</div>

<form class="sortafolders-settings" method="post" action="<?= $this->url->href('FolderAdminController', 'saveSettings', array('plugin' => 'SortaFolders')) ?>" autocomplete="off">
    <?= $this->form->csrf() ?>

    <fieldset>
        <legend><?= $this->sortaFolders->t('Folders') ?></legend>
        <?php if (empty($items)): ?>
            <p class="alert"><?= $this->sortaFolders->t('There is no folder yet.') ?></p>
        <?php else: ?>
            <table class="table-striped table-scrolling">
                <tr>
                    <th class="column-50"><?= $this->sortaFolders->t('Folder') ?></th>
                    <th class="column-15"><?= t('Projects') ?></th>
                    <th><?= t('Action') ?></th>
                </tr>
                <?php foreach ($items as $item): ?>
                    <?php $folder = $item['folder'] ?>
                    <tr>
                        <td>
                            <span style="margin-left: <?= ((int) $item['depth'] - 1) * 1.5 ?>em">
                                <i class="fa fa-<?= $this->sortaFolders->icon() ?> fa-fw" aria-hidden="true"></i>
                                <span class="sortafolders-sr"><?= t('Level %d', (int) $item['depth']) ?>:</span>
                                <?php if (isset($linkable[(int) $folder['id']])): ?>
                                    <?= $this->url->link($this->text->e($this->sortaFolders->folderName($folder)), 'FolderController', 'show', array('plugin' => 'SortaFolders', 'folder_id' => $folder['id'])) ?>
                                <?php else: ?>
                                    <?= $this->text->e($this->sortaFolders->folderName($folder)) ?>
                                <?php endif ?>
                            </span>
                        </td>
                        <td><?= isset($counts[$folder['id']]) ? (int) $counts[$folder['id']] : 0 ?></td>
                        <td>
                            <?php if ((int) $item['depth'] < $max_depth): ?>
                                <?= $this->modal->medium('plus', $this->sortaFolders->t('Add subfolder'), 'FolderAdminController', 'create', array('plugin' => 'SortaFolders', 'parent_id' => $folder['id'], 'return' => 'index')) ?>
                            <?php endif ?>
                            <?= $this->modal->medium('edit', t('Edit'), 'FolderAdminController', 'edit', array('plugin' => 'SortaFolders', 'folder_id' => $folder['id'])) ?>
                            <?php if (! $item['first']): ?>
                                <?= $this->url->icon('arrow-up', t('Move up'), 'FolderAdminController', 'moveUp', array('plugin' => 'SortaFolders', 'folder_id' => $folder['id']), true) ?>
                            <?php endif ?>
                            <?php if (! $item['last']): ?>
                                <?= $this->url->icon('arrow-down', t('Move down'), 'FolderAdminController', 'moveDown', array('plugin' => 'SortaFolders', 'folder_id' => $folder['id']), true) ?>
                            <?php endif ?>
                            <?= $this->modal->medium('lock', t('Access').(! empty($grants[$folder['id']]) ? ' ('.(int) $grants[$folder['id']].')' : ''), 'FolderAccessController', 'show', array('plugin' => 'SortaFolders', 'folder_id' => $folder['id'])) ?>
                            <?php if (isset($archived[(int) $folder['id']])): ?>
                                <?= $this->modal->confirm('undo', t('Unarchive'), 'FolderAdminController', 'unarchiveConfirm', array('plugin' => 'SortaFolders', 'folder_id' => $folder['id'])) ?>
                            <?php else: ?>
                                <?= $this->modal->confirm('archive', t('Archive'), 'FolderAdminController', 'archiveConfirm', array('plugin' => 'SortaFolders', 'folder_id' => $folder['id'])) ?>
                            <?php endif ?>
                            <?= $this->modal->confirm('trash-o', t('Remove'), 'FolderAdminController', 'confirm', array('plugin' => 'SortaFolders', 'folder_id' => $folder['id'])) ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </table>
        <?php endif ?>
    </fieldset>

    <fieldset>
        <legend><?= $this->sortaFolders->t('Access') ?></legend>
        <?php if (empty($items)): ?>
            <p class="form-help"><?= $this->sortaFolders->t('There is no folder yet.') ?></p>
        <?php else: ?>
            <p class="form-help"><?= $this->sortaFolders->t('Who can access each folder\'s projects and their source of access. Admins see everything, so they are not listed. Read only: access is still granted with "Access" on the folder list.') ?></p>
            <p class="sortafolders-legend">
                <i class="fa fa-lock fa-fw" aria-hidden="true"></i> <?= $this->sortaFolders->t('given by folder access') ?>
                <i class="fa fa-user fa-fw" aria-hidden="true"></i> <?= $this->sortaFolders->t('added to a project by admin') ?>
                <i class="fa fa-users fa-fw" aria-hidden="true"></i> <?= $this->sortaFolders->t('group added by admin') ?>
            </p>
            <table class="table-striped table-scrolling">
                <tr>
                    <th class="column-25"><?= $this->sortaFolders->t('Folder') ?></th>
                    <th><?= t('Users and groups') ?></th>
                    <th class="column-10"><?= t('Projects') ?></th>
                    <th class="column-10"><?= t('Users') ?></th>
                </tr>
                <?php foreach ($items as $item): ?>
                    <?php $folder = $item['folder'] ?>
                    <?php $line = isset($report[(int) $folder['id']]) ? $report[(int) $folder['id']] : array('projects' => 0, 'users' => 0, 'rows' => array()) ?>
                    <tr>
                        <td>
                            <span style="margin-left: <?= ((int) $item['depth'] - 1) * 1.5 ?>em">
                                <i class="fa fa-<?= $this->sortaFolders->icon() ?> fa-fw" aria-hidden="true"></i>
                                <span class="sortafolders-sr"><?= t('Level %d', (int) $item['depth']) ?>:</span>
                                <?= $this->text->e($this->sortaFolders->folderName($folder)) ?>
                            </span>
                        </td>
                        <td>
                            <?php if (empty($line['rows'])): ?>
                                <span class="sortafolders-none"><?= $this->sortaFolders->t('No users or groups added.') ?></span>
                            <?php endif ?>
                            <?php foreach ($line['rows'] as $row): ?>
                                <span class="sortafolders-who">
                                    <?php if ($row['given']): ?><i class="fa fa-lock fa-fw" title="<?= $this->sortaFolders->t('given by folder access') ?>" aria-hidden="true"></i><span class="sortafolders-sr"><?= $this->sortaFolders->t('given by folder access') ?>:</span><?php endif ?>
                                    <?php if ($row['by_hand']): ?><i class="fa fa-<?= $row['kind'] === 'group' ? 'users' : 'user' ?> fa-fw" aria-hidden="true"></i><span class="sortafolders-sr"><?= $row['kind'] === 'group' ? $this->sortaFolders->t('group added by admin') : $this->sortaFolders->t('added to a project by admin') ?>:</span><?php endif ?>
                                    <?= $this->text->e($row['name']) ?>
                                    <small class="sortafolders-src">
                                        <?= $this->text->e($row['role']) ?><?= $row['kind'] === 'group' ? ', '.$this->text->e(t('group of %d', (int) $row['members'])) : '' ?><?= $row['from'] !== '' ? ', '.$this->text->e($row['from']) : '' ?>
                                    </small>
                                </span>
                            <?php endforeach ?>
                        </td>
                        <td><?= (int) $line['projects'] ?></td>
                        <td><?= (int) $line['users'] ?></td>
                    </tr>
                <?php endforeach ?>
            </table>
            <p class="form-help"><?= $this->sortaFolders->t('A user with different roles on two projects in one folder shows both. A group shows once, not by member. Closed projects are not counted.') ?></p>
        <?php endif ?>
    </fieldset>

    <fieldset>
        <legend><?= t('Folder term') ?></legend>
        <?= $this->form->label(t('Term to display for folders'), 'term') ?>
        <?= $this->form->select('term', array_map(function ($t) { return t($t[2]); }, $terms), array('term' => $term)) ?>
    </fieldset>

    <fieldset>
        <legend><?= t('Icon') ?></legend>
        <div class="sortafolders-icons">
            <?php foreach ($icons as $name => $label): ?>
                <label>
                    <input type="radio" name="icon" value="<?= $this->text->e($name) ?>" <?= $icon === $name ? 'checked' : '' ?>>
                    <i class="fa fa-<?= $this->text->e($name) ?> fa-fw" aria-hidden="true"></i>
                    <?= $this->text->e(t($label)) ?>
                </label>
            <?php endforeach ?>
        </div>
    </fieldset>

    <fieldset>
        <legend><?= $this->sortaFolders->t('Hidden folders') ?></legend>
        <?php if (empty($hidden_choices)): ?>
            <p class="form-help"><?= $this->sortaFolders->t('There is no folder yet.') ?></p>
        <?php else: ?>
            <label for="sortafolders-hidden"><?= $this->sortaFolders->t('Folders to hide') ?></label>
            <select name="hidden[]" id="sortafolders-hidden" multiple size="<?= min(10, max(3, count($hidden_choices))) ?>">
                <?php foreach ($hidden_choices as $id => $label): ?>
                    <option value="<?= (int) $id ?>" <?= in_array($id, $hidden_ids, true) ? 'selected' : '' ?>><?= $this->text->e($label) ?></option>
                <?php endforeach ?>
            </select>
            <p class="form-help"><?= $this->sortaFolders->t('A hidden folder, the folders inside it, and their projects are hidden from everyone but admins, who see everything. Projects stay where they are. Settings still lists the folders. Hold Ctrl or Cmd to pick more than one.') ?></p>
        <?php endif ?>
    </fieldset>

    <fieldset>
        <legend><?= $this->sortaFolders->t('Folder description') ?></legend>
        <label>
            <input type="checkbox" name="motd" value="1" <?= $motd ? 'checked' : '' ?>>
            <?= $this->sortaFolders->t('Show a project\'s folder description (if there is one) at the top of each project') ?>
        </label>
    </fieldset>

    <fieldset>
        <legend><?= t('Project health') ?></legend>
        <p><?= t('A project is red or yellow from its overdue tasks and from how long it has been quiet. These numbers apply to every folder and project.') ?></p>
        <?= $this->form->label(t('Red when this share of open tasks is overdue (percent)'), 'red_pct') ?>
        <?= $this->form->number('red_pct', array('red_pct' => $health['red_pct']), array(), array('min="1"', 'max="100"')) ?>
        <?= $this->form->label(t('Red after this many quiet days'), 'red_quiet') ?>
        <?= $this->form->number('red_quiet', array('red_quiet' => $health['red_quiet']), array(), array('min="1"', 'max="365"')) ?>
        <?= $this->form->label(t('Yellow after this many quiet days'), 'yellow_quiet') ?>
        <?= $this->form->number('yellow_quiet', array('yellow_quiet' => $health['yellow_quiet']), array(), array('min="1"', 'max="365"')) ?>
        <?= $this->form->label(t('Due soon means within this many days'), 'soon_days') ?>
        <?= $this->form->number('soon_days', array('soon_days' => $health['soon_days']), array(), array('min="1"', 'max="365"')) ?>
        <label>
            <input type="checkbox" name="attention" value="1" <?= $attention_on ? 'checked' : '' ?>>
            <?= $this->sortaFolders->t('Show projects in folders needing attention') ?>
        </label>
    </fieldset>

    <button type="submit" class="btn btn-blue"><?= t('Save') ?></button>
</form>

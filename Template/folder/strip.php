<?php if (! empty($attention)): ?>
    <details class="sortafolders-strip" open>
        <summary><strong><?= t('Projects in folders needing attention') ?></strong> (<?= count($attention) ?>)</summary>
        <ul>
            <?php foreach ($attention as $project): ?>
                <li>
                    <?= $this->sortaFolders->light($project['sf_health']['level'], $project['sf_health']['reason']) ?>
                    <?= $this->url->link($this->text->e($project['name']), 'BoardViewController', 'show', array('project_id' => $project['id'])) ?>
                    <small>
                        <?= $this->text->e($project['sf_path']) ?>:
                        <?= $this->text->e($project['sf_health']['reason']) ?>
                    </small>
                </li>
            <?php endforeach ?>
        </ul>
    </details>
<?php endif ?>

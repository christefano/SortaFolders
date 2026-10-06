<?php $percent = $this->sortaFolders->progress($totals) ?>
<span class="sortafolders-progress">
    <progress max="100" value="<?= $percent ?>" aria-label="<?= t('%d%% of tasks closed', $percent) ?>"></progress>
    <?= $percent ?>%
</span>

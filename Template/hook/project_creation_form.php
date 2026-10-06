<?php $options = $this->sortaFolders->getOptions() ?>
<?php if (count($options) > 1): ?>
    <?= $this->form->label($this->sortaFolders->t('Folder'), 'sortafolders_folder_id') ?>
    <?= $this->form->select('sortafolders_folder_id', $options, $values) ?>
    <?= $this->render('SortaFolders:hook/grant_notes', array('notes' => $this->sortaFolders->getGrantNotes($options))) ?>
<?php endif ?>

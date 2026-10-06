<div class="page-header">
    <h2><?= $title ?></h2>
</div>
<form method="post" action="<?= $action ?>" autocomplete="off">
    <?= $this->form->csrf() ?>
    <?= $this->form->hidden('return', $values) ?>

    <?= $this->form->label(t('Name'), 'name') ?>
    <?= $this->form->text('name', $values, $errors, array('autofocus', 'required', 'maxlength="191"')) ?>

    <?= $this->form->label($this->sortaFolders->t('Parent folder'), 'parent_id') ?>
    <?= $this->form->select('parent_id', $parents, $values, $errors) ?>

    <?= $this->form->label(t('Description'), 'description') ?>
    <?= $this->form->textarea('description', $values, $errors, array('maxlength="2000"', 'rows="4"')) ?>

    <?= $this->modal->submitButtons() ?>
</form>

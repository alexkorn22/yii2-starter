<?php

$this->title                   = 'Update role';
$this->params['breadcrumbs'][] = ['label' => 'Permissions', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
$this->params['heading']       = 'Permissions';
$this->params['subheading']    = 'Update Role';
?>

<div class="role-update">

	<?= $this->render('_form-role', [
		'model' => $model,
	]) ?>

</div>


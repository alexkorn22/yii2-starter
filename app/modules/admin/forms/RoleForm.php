<?php

namespace app\modules\admin\forms;

use app\models\AuthItemChild;
use app\models\AuthItems;
use yii\helpers\ArrayHelper;
use yii\rbac\Role;
use Yii;
use yii\base\ErrorException;


class RoleForm extends AuthItems
{
	/**
	 * @var string
	 */
	public $allow_permissions;

	/**
	 * @var string
	 */
	public $deny_permissions;

	public $inherit_permissions;

	public $role;

	public $permissions;

	public $permissions_search;

	public $scenario = 'add';


	/**
	 * @inheritdoc
	 * @return array
	 */
	public function rules()
	{
		return array_merge(parent::rules(), [
			['name', 'uniqueName', 'on' => 'add'],
			[['allow_permissions', 'deny_permissions', 'permissions', 'inherit_permissions'], 'safe'],
		]);
	}

	/**
	 * @param $attribute
	 * @return bool
	 */
	public function uniqueName($attribute)
	{
		if (static::findOne(['name' => $this->attributes['name']])) {
			$this->addError($attribute, 'Name must be unique');

			return false;
		}

		return true;
	}

	/**
	 * @return bool
	 */
	public function beforeValidate()
	{
		$this->type = Role::TYPE_ROLE;
		$this->permissions = explode(',', $this->allow_permissions);
		return parent::beforeValidate();
	}

	/**
	 * @return bool|null|string
	 */
	public function getInheritPermissions()
	{
		$allow_permissions = $this->findWithChildItem();

		if(empty($allow_permissions) || !is_array($allow_permissions)){
			return null;
		}

		$permissions = '';
		foreach ($allow_permissions as $permission){
			if ($permission['childItem']['type'] == Role::TYPE_ROLE) {
				$permissions .= $permission['child'] . ',';
			}
		}

		return substr($permissions, 0, -1);
	}

	/**
	 * @param $value
	 * @return mixed
	 */
	public function setInheritPermissions($value)
	{
		return $this->inherit_permissions = $value;
	}

	/**
	 * @return bool|null|string
	 */
	public function getAllowPermissions()
	{
		$allow_permissions = $this->findWithChildItem();

		if(empty($allow_permissions) || !is_array($allow_permissions)){
			return null;
		}

		$permissions = '';
		foreach ($allow_permissions as $permission){
			if ($permission['childItem']['type'] == Role::TYPE_PERMISSION) {
				$permissions .= $permission['child'] . ',';
			}
		}

		return substr($permissions, 0, -1);
	}

	/**
	 * @param $value
	 * @return mixed
	 */
	public function setAllowPermissions($value)
	{
		return $this->allow_permissions = $value;
	}

	/**
	 * @return mixed
	 */
	public function getListInheritPermissions()
	{
		$roles = $this->rolesList;
		ArrayHelper::remove($roles, $this->name);

		return $roles;
	}

	/**
	 * @return array|\yii\db\ActiveRecord[]
	 */
	public function findWithChildItem()
	{
		return AuthItemChild::find()
			->with('childItem')
			->where(['parent' => $this->name])
			->asArray()
			->all();
	}

	/**
	 * @return bool
	 */
	public function store()
	{
		$old_inherit_permissions = explode(',', $this->inheritPermissions);

		if(!$this->save()){
			 throw new ErrorException($this->errors);
		}

		$name = $this->name;
		$new_role = Yii::$app->authManager->getRole($name);

		if ($this->inherit_permissions){
			foreach ($this->inherit_permissions as $role){
				if (!AuthItemChild::findOne(['child' => $role, 'parent' => $name])) {
					$child_role = Yii::$app->authManager->getRole($role);
					Yii::$app->authManager->addChild($new_role, $child_role);
				}

				foreach ($old_inherit_permissions as $key => $value){
					if ($value == $role) {
						unset($old_inherit_permissions[$key]);
					}
				}
			}
		}

		if (!empty($old_inherit_permissions)) {
			foreach ($old_inherit_permissions as $permission) {
				if($permission_for_remove = AuthItemChild::find()->where(['parent' => $name, 'child' => $permission])->one()) {
					$permission_for_remove->delete();
				}
			}
		}

		if ($this->permissions) {
			foreach ($this->permissions as $permission) {
				if (!AuthItemChild::findOne(['parent' => $name, 'child' => $permission])) {
					$new_permission = new AuthItemChild([
						'parent' => $name,
						'child'  => $permission
					]);
					$new_permission->save();
				}
			}
		}

		if ($this->deny_permissions){
			$deny_permissions = explode(',', $this->deny_permissions);
			foreach ($deny_permissions as $permission) {
				if($permission_for_remove = AuthItemChild::find()->where(['parent' => $name, 'child' => $permission])->one()) {
					$permission_for_remove->delete();
				}
			}
		}

		return true;
	}

}
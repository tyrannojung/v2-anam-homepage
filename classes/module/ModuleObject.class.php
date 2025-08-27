<?php

/**
 * ModuleObject
 *
 * @author NAVER (developers@xpressengine.com)
 */
class ModuleObject extends BaseObject
{
	// Variables about the current module
	public $module;
	public $module_info;
	public $origin_module_info;
	public $module_config;
	public $module_path;
	public $xml_info;

	// Variables about the current module instance and the current request
	public $module_srl;
	public $mid;
	public $act;

	// Variables about the layout and/or template
	public $template_path;
	public $template_file;
	public $layout_path;
	public $layout_file;
	public $edited_layout_file;

	// Variables to control processing
	public $stop_proc = false;

	// Variables for convenience
	public $user;
	public $request;

	// Other variables for compatibility
	public $ajaxRequestMethod = array('XMLRPC', 'JSON');
	public $gzhandler_enable = true;

	/**
	 * Constructor
	 *
	 * @param int $error Error code
	 * @param string $message Error message
	 * @return void
	 */
	public function __construct($error = 0, $message = 'success')
	{
		parent::__construct($error, $message);
	}

	/**
	 * Singleton
	 *
	 * @param string $module_hint (optional)
	 * @return static
	 */
	public static function getInstance($module_hint = null)
	{
		// If an instance already exists, return it.
		$class_name = static::class;
		if (isset($GLOBALS['_module_instances_'][$class_name]))
		{
			return $GLOBALS['_module_instances_'][$class_name];
		}

		// Get some information about the class.
		if ($module_hint)
		{
			$module_path = \RX_BASEDIR . 'modules/' . $module_hint . '/';
			$module = $module_hint;
		}
		else
		{
			$class_filename = (new ReflectionClass($class_name))->getFileName();
			preg_match('!^(.+[/\\\\]modules[/\\\\]([^/\\\\]+)[/\\\\])!', $class_filename, $matches);
			$module_path = $matches[1];
			$module = $matches[2];
		}

		// Create a new instance.
		$obj = new $class_name;

		// Populate default properties.
		$obj->setModulePath($module_path);
		$obj->setModule($module);
		$obj->user = Context::get('logged_info');
		if(!($obj->user instanceof Rhymix\Framework\Helpers\SessionHelper))
		{
			$obj->user = Rhymix\Framework\Session::getMemberInfo();
		}
		$obj->request = \Context::getCurrentRequest();

		// Return the instance.
		return $GLOBALS['_module_instances_'][$class_name] = $obj;
	}

	/**
	 * setter to set the name of module
	 *
	 * @param string $module name of module
	 * @return $this
	 */
	public function setModule($module)
	{
		$this->module = $module;
		return $this;
	}

	/**
	 * setter to set the name of module path
	 *
	 * @param string $path the directory path to a module directory
	 * @return $this
	 */
	public function setModulePath($path)
	{
		if(substr_compare($path, '/', -1) !== 0)
		{
			$path.='/';
		}
		$this->module_path = $path;
		return $this;
	}

	/**
	 * setter to set an url for redirection
	 *
	 * @param string|array $url url for redirection
	 * @return object
	 */
	public function setRedirectUrl($url = './', $output = NULL)
	{
		if (is_array($url))
		{
			$url = getNotEncodedUrl($url);
		}
		$this->add('redirect_url', $url);

		if($output !== NULL && is_object($output))
		{
			return $output;
		}
		else
		{
			return $this;
		}
	}

	/**
	 * get url for redirection
	 *
	 * @return ?string
	 */
	public function getRedirectUrl()
	{
		return $this->get('redirect_url');
	}

	/**
	 * Set the template path for refresh.html
	 * refresh.html is executed as a result of method execution
	 * Tpl as the common run of the refresh.html ..
	 *
	 * @deprecated
	 * @return $this
	 */
	public function setRefreshPage()
	{
		$this->setTemplatePath('./common/tpl');
		$this->setTemplateFile('refresh');
		return $this;
	}

	/**
	 * Set the action name
	 *
	 * @param string $act
	 * @return $this
	 */
	public function setAct($act)
	{
		$this->act = $act;
		return $this;
	}

	/**
	 * Set module information
	 *
	 * @param object $module_info object containing module information
	 * @param object $xml_info object containing module description
	 * @return $this
	 */
	public function setModuleInfo($module_info, $xml_info)
	{
		// Set default variables
		$this->mid = $module_info->mid;
		$this->module_srl = $module_info->module_srl ?? null;
		$this->module_info = $module_info;
		$this->origin_module_info = $module_info;
		$this->xml_info = $xml_info;
		$this->skin_vars = $module_info->skin_vars ?? null;
		$this->module_config = ModuleModel::getInstance()->getModuleConfig($this->module, $module_info->site_srl);

		// Set privileges(granted) information
		if($this->setPrivileges() !== true)
		{
			$this->stop('msg_not_permitted');
			return;
		}

		// Set admin layout
		if(preg_match('/^disp[A-Z][a-z0-9\_]+Admin/', $this->act))
		{
			if(config('view.manager_layout') === 'admin')
			{
				$this->setLayoutPath('modules/admin/tpl');
				$this->setLayoutFile('layout');
			}
			else
			{
				$oTemplate = new Rhymix\Framework\Template('modules/admin/tpl', '_admin_common.html');
				$oTemplate->compile();
			}
		}

		// Execute init
		if(method_exists($this, 'init'))
		{
			try
			{
				$this->init();
			}
			catch (Rhymix\Framework\Exception $e)
			{
				$this->stop($e->getMessage(), -2);
				$this->add('rx_error_location', $e->getUserFileAndLine());
			}
		}

		return $this;
	}

	/**
	 * Set privileges(granted) information of current user and check permission of current module
	 *
	 * @return bool
	 */
	public function setPrivileges()
	{
		if (!$this->user->isAdmin())
		{
			// Get privileges(granted) information for target module by <permission check> of module.xml
			if(($permission = $this->xml_info->action->{$this->act}->permission) && $permission->check_var)
			{
				// Ensure that the list of modules to check is the right type and not empty
				$check_var = Context::get($permission->check_var);
				if (is_scalar($check_var))
				{
					if (empty($check_module_srl = trim($check_var)))
					{
						return false;
					}

					// Convert string to array. delimiter is ,(comma) or |@|
					if(preg_match('/,|\|@\|/', $check_module_srl, $delimiter) && $delimiter[0])
					{
						$check_module_srl = explode($delimiter[0], $check_module_srl);
					}
					else
					{
						$check_module_srl = array($check_module_srl);
					}
				}
				else
				{
					$check_module_srl = array_map('trim', $check_var);
					if (!count($check_var))
					{
						return false;
					}
				}

				// Check permission by privileges(granted) information for target module
				foreach($check_module_srl as $target_srl)
				{
					// Get privileges(granted) information of current user for target module
					$check_grant = ModuleModel::getPrivilegesBySrl($target_srl, $permission->check_type);
					if ($check_grant === false)
					{
						return false;
					}

					// Check permission
					if(!$this->checkPermission($check_grant, $this->user, $failed_requirement))
					{
						$this->stop($this->_generatePermissionError($failed_requirement));
						return false;
					}
				}
			}
		}

		// Check permission based on the grant information for the current module.
		if (isset($check_grant))
		{
			$grant = $check_grant;
		}
		else
		{
			$grant = ModuleModel::getInstance()->getGrant($this->module_info, $this->user, $this->xml_info);
		}

		if(!$this->checkPermission($grant, $this->user, $failed_requirement))
		{
			$this->stop($this->_generatePermissionError($failed_requirement));
			return false;
		}

		// If member action, grant access for log-in, sign-up, member pages
		if(preg_match('/^(disp|proc)(Member|Communication)[A-Z][a-zA-Z]+$/', $this->act))
		{
			$grant->access = true;
		}

		// Set aliases to grant object
		$this->grant = $grant;
		Context::set('grant', $grant);

		return true;
	}

	/**
	 * Check permission
	 *
	 * @param object $grant privileges(granted) information of user
	 * @param object $member_info member information
	 * @param string|array &$failed_requirement
	 * @return bool
	 */
	public function checkPermission($grant = null, $member_info = null, &$failed_requirement = '')
	{
		// Get logged-in member information
		if(!$member_info)
		{
			$member_info = $this->user;
		}

		// Get privileges(granted) information of the member for current module
		if(!$grant)
		{
			$grant = ModuleModel::getGrant($this->module_info, $member_info, $this->xml_info);
		}

		// If an administrator, Pass
		if($grant->root)
		{
			return true;
		}

		// Get permission types(guest, member, manager, root) of the currently requested action
		$permission = $this->xml_info->action->{$this->act}->permission->target ?: ($this->xml_info->permission->{$this->act} ?? null);

		// If admin action, set default permission
		if(empty($permission) && stripos($this->act, 'admin') !== false)
		{
			$permission = 'root';
		}

		// If there is no permission or eveyone is allowed, pass
		if (empty($permission) || $permission === 'guest' || $permission === 'everyone')
		{
			return true;
		}

		// If permission is 'member', the user must be logged in
		if ($permission === 'member')
		{
			if ($member_info->member_srl)
			{
				return true;
			}
			else
			{
				$failed_requirement = 'member';
				return false;
			}
		}

		// If permission is 'not_member', the user must be logged out
		if ($permission === 'not_member' || $permission === 'not-member')
		{
			if (!$member_info->member_srl || $grant->manager)
			{
				return true;
			}
			else
			{
				$failed_requirement = 'not_member';
				return false;
			}
		}

		// If permission is 'root', false
		// Because an administrator who have root privilege(granted) was passed already
		if ($permission == 'root')
		{
			$failed_requirement = 'root';
			return false;
		}

		// If permission is 'manager', check 'is user have manager privilege(granted)'
		if (preg_match('/^(manager(?::(.+))?|([a-z0-9\_]+)-managers)$/', $permission, $type))
		{
			// If permission is manager(:scope), check manager privilege and scope
			if ($grant->manager)
			{
				if (empty($type[2]))
				{
					return true;
				}
				elseif ($grant->can($type[2]))
				{
					return true;
				}
			}

			// If permission is '*-managers', search modules to find manager privilege of the member
			if(Context::get('is_logged') && isset($type[3]))
			{
				// Manager privilege of the member is found by search all modules, Pass
				if($type[3] == 'all' && ModuleModel::findManagerPrivilege($member_info) !== false)
				{
					return true;
				}
				// Manager privilege of the member is found by search same module as this module, Pass
				elseif($type[3] == 'same' && ModuleModel::findManagerPrivilege($member_info, $this->module) !== false)
				{
					return true;
				}
				// Manager privilege of the member is found by search same module as the module, Pass
				elseif(ModuleModel::findManagerPrivilege($member_info, $type[3]) !== false)
				{
					return true;
				}
			}

			$failed_requirement = 'manager';
			return false;
		}

		// Check grant name
		// If multiple names are given, all of them must pass.
		elseif ($grant_names = array_map('trim', explode(',', $permission)))
		{
			foreach ($grant_names as $name)
			{
				if (!isset($grant->{$name}))
				{
					return false;
				}
				if (!$grant->{$name})
				{
					$failed_requirement = $grant->whocan($name);
					return false;
				}
			}
			return true;
		}

		return false;
	}

	/**
	 * Generate an error message for a failed permission.
	 *
	 * @param mixed $failed_requirement
	 * @return string
	 */
	protected function _generatePermissionError($failed_requirement)
	{
		if ($failed_requirement === 'member' || !$this->user->isMember())
		{
			return 'msg_not_logged';
		}
		elseif ($failed_requirement === 'not_member')
		{
			return 'msg_required_not_logged';
		}
		elseif ($failed_requirement === 'manager' || $failed_requirement === 'root')
		{
			return 'msg_administrator_only';
		}
		elseif (is_array($failed_requirement) && count($failed_requirement))
		{
			if (class_exists('PointModel'))
			{
				$min_level = PointModel::getMinimumLevelForGroup($failed_requirement);
				if ($min_level)
				{
					return sprintf(lang('member.msg_required_minimum_level'), $min_level);
				}
			}
			return 'member.msg_required_specific_group';
		}
		else
		{
			return 'msg_not_permitted_act';
		}
	}

	/**
	 * Stop processing this module instance.
	 *
	 * @param string $msg_code
	 * @param int $error_code
	 * @return ModuleObject $this
	 */
	public function stop($msg_code, $error_code = -1)
	{
		if($this->stop_proc !== true)
		{
			// flag setting to stop the proc processing
			$this->stop_proc = true;

			// Error handling
			$this->setError($error_code ?: -1);
			$this->setMessage($msg_code);

			// Get backtrace
			$backtrace = debug_backtrace(false);
			$caller = array_shift($backtrace);
			$location = $caller['file'] . ':' . $caller['line'];

			// Error message display by message module
			$oMessageObject = MessageView::getInstance();
			$oMessageObject->setError(-1);
			$oMessageObject->setMessage($msg_code);
			$oMessageObject->dispMessage('', $location);

			$this->setTemplatePath($oMessageObject->getTemplatePath());
			$this->setTemplateFile($oMessageObject->getTemplateFile());
			$this->setHttpStatusCode($oMessageObject->getHttpStatusCode());
		}

		return $this;
	}

	/**
	 * set the file name of the template file
	 *
	 * @param string name of file
	 * @return $this
	 */
	public function setTemplateFile($filename)
	{
		$this->template_file = $filename;
		return $this;
	}

	/**
	 * retrieve the directory path of the template directory
	 *
	 * @return ?string
	 */
	public function getTemplateFile()
	{
		return $this->template_file;
	}

	/**
	 * set the directory path of the template directory
	 *
	 * @param string path of template directory.
	 * @return $this
	 */
	public function setTemplatePath($path)
	{
		if(!$path) return $this;
		if (!preg_match('!^(?:\\.?/|[A-Z]:[\\\\/]|\\\\\\\\)!i', $path))
		{
			$path = './' . $path;
		}
		if(substr_compare($path, '/', -1) !== 0)
		{
			$path .= '/';
		}
		$this->template_path = $path;
		return $this;
	}

	/**
	 * retrieve the directory path of the template directory
	 *
	 * @return ?string
	 */
	public function getTemplatePath()
	{
		return $this->template_path;
	}

	/**
	 * set the file name of the temporarily modified by admin
	 *
	 * @param string name of file
	 * @return $this
	 */
	public function setEditedLayoutFile($filename)
	{
		if(!$filename) return $this;
		$this->edited_layout_file = $filename;
		return $this;
	}

	/**
	 * retreived the file name of edited_layout_file
	 *
	 * @return ?string
	 */
	public function getEditedLayoutFile()
	{
		return $this->edited_layout_file;
	}

	/**
	 * set the file name of the layout file
	 *
	 * @param string name of file
	 * @return $this
	 */
	public function setLayoutFile($filename)
	{
		$this->layout_file = $filename;
		return $this;
	}

	/**
	 * get the file name of the layout file
	 *
	 * @return ?string
	 */
	public function getLayoutFile()
	{
		return $this->layout_file;
	}

	/**
	 * set the directory path of the layout directory
	 *
	 * @param string path of layout directory.
	 * @return $this
	 */
	public function setLayoutPath($path)
	{
		if(!$path) return;
		if (!preg_match('!^(?:\\.?/|[A-Z]:[\\\\/]|\\\\\\\\)!i', $path))
		{
			$path = './' . $path;
		}
		if(substr_compare($path, '/', -1) !== 0)
		{
			$path .= '/';
		}
		$this->layout_path = $path;
		return $this;
	}

	/**
	 * set the directory path of the layout directory
	 *
	 * @return ?string
	 */
	public function getLayoutPath()
	{
		return $this->layout_path;
	}

	/**
	 * Automatically set layout and template path based on skin settings.
	 *
	 * @param string $type 'P' or 'M'
	 * @param object $config
	 * @return void
	 */
	public function setLayoutAndTemplatePaths($type, $config)
	{
		// Set the layout path.
		if ($type === 'P')
		{
			$layout_srl = $config->layout_srl ?? 0;
			if ($layout_srl == -1)
			{
				$layout_srl = LayoutAdminModel::getInstance()->getSiteDefaultLayout('P');
			}

			if ($layout_srl > 0)
			{
				$layout_info = LayoutModel::getInstance()->getLayout($layout_srl);
				if($layout_info)
				{
					$this->setLayoutPath($layout_info->path);
					if ($config->layout_srl > 0)
					{
						$this->module_info->layout_srl = $layout_srl;
					}
				}
			}
		}
		else
		{
			$layout_srl = $config->mlayout_srl ?? 0;
			if ($layout_srl == -2)
			{
				$layout_srl = $config->layout_srl ?: -1;
				if ($layout_srl == -1)
				{
					$layout_srl = LayoutAdminModel::getInstance()->getSiteDefaultLayout('P');
				}
			}
			elseif ($layout_srl == -1)
			{
				$layout_srl = LayoutAdminModel::getInstance()->getSiteDefaultLayout('M');
			}

			if ($layout_srl > 0)
			{
				$layout_info = LayoutModel::getInstance()->getLayout($layout_srl);
				if($layout_info)
				{
					$this->setLayoutPath($layout_info->path);
					if ($config->mlayout_srl > 0)
					{
						$this->module_info->mlayout_srl = $layout_srl;
					}
				}
			}
		}

		// Set the skin path.
		if ($type === 'P')
		{
			$skin = ($config->skin ?? '') ?: 'default';
			if ($skin === '/USE_DEFAULT/')
			{
				$skin = ModuleModel::getModuleDefaultSkin($this->module, 'P') ?: 'default';
			}
			$template_path = sprintf('%sskins/%s', $this->module_path, $skin);
			if (!Rhymix\Framework\Storage::exists($template_path))
			{
				$template_path = sprintf('%sskins/%s', $this->module_path, 'default');
			}
		}
		else
		{
			$mskin = ($config->mskin ?? '') ?: 'default';
			if ($mskin === '/USE_DEFAULT/')
			{
				$mskin = ModuleModel::getModuleDefaultSkin($this->module, 'M') ?: 'default';
			}

			if($mskin === '/USE_RESPONSIVE/')
			{
				$skin = ($config->skin ?? '') ?: 'default';
				if ($skin === '/USE_DEFAULT/')
				{
					$skin = ModuleModel::getModuleDefaultSkin($this->module, 'P') ?: 'default';
				}
				$template_path = sprintf('%sskins/%s', $this->module_path, $skin);
				if (!Rhymix\Framework\Storage::exists($template_path))
				{
					$template_path = sprintf('%sskins/%s', $this->module_path, 'default');
				}
			}
			else
			{
				$template_path = sprintf('%sm.skins/%s', $this->module_path, $mskin);
				if (!Rhymix\Framework\Storage::exists($template_path))
				{
					$template_path = sprintf("%sm.skins/%s/", $this->module_path, 'default');
				}
			}
		}
		$this->setTemplatePath($template_path);
	}

	/**
	 * excute the member method specified by $act variable
	 * @return bool
	 */
	public function proc()
	{
		// pass if stop_proc is true
		if($this->stop_proc)
		{
			return FALSE;
		}

		// Check mobile status
		$is_mobile = Mobile::isFromMobilePhone();

		// trigger call
		$triggerOutput = ModuleHandler::triggerCall('moduleObject.proc', 'before', $this);
		if(!$triggerOutput->toBool())
		{
			$this->setError($triggerOutput->getError());
			$this->setMessage($triggerOutput->getMessage());
			return FALSE;
		}

		// execute an addon(call called_position as before_module_proc)
		$called_position = 'before_module_proc';
		$oAddonController = AddonController::getInstance();
		$addon_file = $oAddonController->getCacheFilePath($is_mobile ? "mobile" : "pc");
		if(FileHandler::exists($addon_file)) include($addon_file);

		// Check mobile status again, in case a trigger changed it
		$is_mobile = Mobile::isFromMobilePhone();

		// Perform action if it exists
		if(isset($this->xml_info->action->{$this->act}) && method_exists($this, $this->act))
		{
			// Check permissions
			if($this->module_srl && !$this->grant->access)
			{
				$this->stop("msg_not_permitted_act");
				return FALSE;
			}

			// Set module skin
			if(isset($this->module_info->skin) && $this->module_info->module === $this->module && strpos($this->act, 'Admin') === false)
			{
				$use_default_skin = $this->module_info->{$is_mobile ? 'is_mskin_fix' : 'is_skin_fix'} === 'N';
				if(!$this->getTemplatePath() || $use_default_skin)
				{
					$this->setLayoutAndTemplatePaths($is_mobile ? 'M' : 'P', $this->module_info);
				}
				ModuleModel::syncSkinInfoToModuleInfo($this->module_info);
				Context::set('module_info', $this->module_info);
			}

			// Trigger before specific action
			$triggerAct = sprintf('act:%s.%s', $this->module, $this->act);
			$triggerOutput = ModuleHandler::triggerCall($triggerAct, 'before', $this);
			if(!$triggerOutput->toBool())
			{
				$this->setError($triggerOutput->getError());
				$this->setMessage($triggerOutput->getMessage());
				return false;
			}

			// Run
			try
			{
				$output = $this->{$this->act}();
			}
			catch (Rhymix\Framework\Exception $e)
			{
				$output = new BaseObject(-2, $e->getMessage());
				$output->add('rx_error_location', $e->getUserFileAndLine());
			}

			// Trigger after specific action
			ModuleHandler::triggerCall($triggerAct, 'after', $output);
		}
		else
		{
			return FALSE;
		}

		// check return value of action
		if($output instanceof BaseObject)
		{
			$this->setError($output->getError());
			$this->setMessage($output->getMessage());
			if($output->getError() && $output->get('rx_error_location'))
			{
				$this->add('rx_error_location', $output->get('rx_error_location'));
			}
			$original_output = clone $output;
		}
		else
		{
			$original_output = null;
		}

		// trigger call
		$triggerOutput = ModuleHandler::triggerCall('moduleObject.proc', 'after', $this);
		if(!$triggerOutput->toBool())
		{
			$this->setError($triggerOutput->getError());
			$this->setMessage($triggerOutput->getMessage());
			if($triggerOutput->get('rx_error_location'))
			{
				$this->add('rx_error_location', $triggerOutput->get('rx_error_location'));
			}
			return FALSE;
		}

		// execute an addon(call called_position as after_module_proc)
		$called_position = 'after_module_proc';
		$oAddonController = AddonController::getInstance();
		$addon_file = $oAddonController->getCacheFilePath($is_mobile ? "mobile" : "pc");
		if(FileHandler::exists($addon_file)) include($addon_file);

		if($original_output instanceof BaseObject && !$original_output->toBool())
		{
			return FALSE;
		}
		elseif($output instanceof BaseObject && $output->getError())
		{
			$this->setError($output->getError());
			$this->setMessage($output->getMessage());
			if($output->get('rx_error_location'))
			{
				$this->add('rx_error_location', $output->get('rx_error_location'));
			}
			return FALSE;
		}

		// execute api methods of the module if view action is and result is XMLRPC or JSON
		if(isset($this->module_info->module_type) && in_array($this->module_info->module_type, ['view', 'mobile']))
		{
			if(Context::getResponseMethod() == 'XMLRPC' || Context::getResponseMethod() == 'JSON')
			{
				$oAPI = getAPI($this->module_info->module);
				if($oAPI instanceof ModuleObject && method_exists($oAPI, $this->act))
				{
					$oAPI->{$this->act}($this);
				}
			}
		}
		return TRUE;
	}

	/**
	 * Copy the response of another ModuleObject into this instance.
	 *
	 * @param self $instance
	 * @return void
	 */
	public function copyResponseFrom(self $instance)
	{
		// Copy error and status information.
		$this->error = $instance->getError();
		$this->message = $instance->getMessage();
		$this->httpStatusCode = $instance->getHttpStatusCode();

		// Copy template settings.
		$this->setTemplatePath($instance->getTemplatePath());
		$this->setTemplateFile($instance->getTemplateFile());
		$this->setLayoutPath($instance->getLayoutPath());
		$this->setLayoutFile($instance->getLayoutFile());
		$this->setEditedLayoutFile($instance->getEditedLayoutFile());

		// Copy all other variables: redirect URL, message type, etc.
		foreach ($instance->getVariables() as $key => $val)
		{
			$this->variables[$key] = $val;
		}
	}
}

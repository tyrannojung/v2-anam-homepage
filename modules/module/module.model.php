<?php
/* Copyright (C) NAVER <http://www.navercorp.com> */
/**
 * @class  moduleModel
 * @author NAVER (developers@xpressengine.com)
 * @brief Model class of module module
 */
class ModuleModel extends Module
{
	/**
	 * Internal cache
	 */
	public static $_mid_map = [];
	public static $_module_srl_map = [];

	/**
	 * @brief Initialization
	 */
	public function init()
	{
	}

	/**
	 * @brief Check if mid is available
	 */
	public static function isIDExists($id, $module = null)
	{
		if (!preg_match('/^[a-z]{1}([a-z0-9_]+)$/i', $id))
		{
			return true;
		}
		if (Context::isReservedWord(strtolower($id)) && $id !== $module)
		{
			return true;
		}

		$dirs = array_map('strtolower', FileHandler::readDir(RX_BASEDIR));
		$dirs[] = 'rss';
		$dirs[] = 'atom';
		$dirs[] = 'api';
		if (in_array(strtolower($id), $dirs))
		{
			return true;
		}

		// mid test
		$args = new stdClass();
		$args->mid = $id;
		$output = executeQuery('module.isExistsModuleName', $args);
		if($output->data->count) return true;

		return false;
	}

	/**
	 * @brief Get all domains
	 */
	public static function getAllDomains($count = 20, $page = 1)
	{
		$args = new stdClass;
		$args->list_count = $count;
		$args->page = $page;
		$output = executeQueryArray('module.getDomains', $args);
		foreach ($output->data as &$domain)
		{
			$domain->settings = $domain->settings ? json_decode($domain->settings) : new stdClass;
		}
		return $output;
	}

	/**
	 * @brief Get default domain information
	 */
	public static function getDefaultDomainInfo()
	{
		$domain_info = Rhymix\Framework\Cache::get('site_and_module:domain_info:default');
		if ($domain_info === null)
		{
			$args = new stdClass();
			$args->is_default_domain = 'Y';
			$output = executeQuery('module.getDomainInfo', $args);
			if ($output->data)
			{
				$domain_info = $output->data;
				$domain_info->site_srl = 0;
				$domain_info->settings = $domain_info->settings ? json_decode($domain_info->settings) : new stdClass;
				$domain_info->default_language = $domain_info->settings->language ?: config('locale.default_lang');
				Rhymix\Framework\Cache::set('site_and_module:domain_info:default', $domain_info, 0, true);
			}
			else
			{
				$domain_info = false;
			}
		}

		return $domain_info;
	}

	/**
	 * @brief Get site information by domain_srl
	 */
	public static function getSiteInfo($domain_srl)
	{
		$domain_srl = intval($domain_srl);
		$domain_info = Rhymix\Framework\Cache::get('site_and_module:domain_info:srl:' . $domain_srl);
		if ($domain_info === null)
		{
			$args = new stdClass();
			$args->domain_srl = $domain_srl;
			$output = executeQuery('module.getDomainInfo', $args);
			if ($output->data)
			{
				$domain_info = $output->data;
				$domain_info->site_srl = 0;
				$domain_info->settings = $domain_info->settings ? json_decode($domain_info->settings) : new stdClass;
				$domain_info->default_language = $domain_info->settings->language ?: config('locale.default_lang');
				Rhymix\Framework\Cache::set('site_and_module:domain_info:srl:' . $domain_srl, $domain_info, 0, true);
			}
			else
			{
				$domain_info = false;
			}
		}

		return $domain_info;
	}

	/**
	 * @brief Get site information by domain name
	 */
	public static function getSiteInfoByDomain($domain)
	{
		if (strpos($domain, '/') !== false)
		{
			$domain = Rhymix\Framework\URL::getDomainFromURL($domain);
		}
		if (strpos($domain, 'xn--') !== false)
		{
			$domain = Rhymix\Framework\URL::decodeIdna($domain);
		}
		if (strval($domain) === '')
		{
			return false;
		}

		$domain = strtolower($domain);
		$domain_info = Rhymix\Framework\Cache::get('site_and_module:domain_info:domain:' . $domain);
		if ($domain_info === null)
		{
			$args = new stdClass();
			$args->domain = $domain;
			$output = executeQuery('module.getDomainInfo', $args);
			if ($output->data)
			{
				$domain_info = $output->data;
				$domain_info->site_srl = 0;
				$domain_info->settings = $domain_info->settings ? json_decode($domain_info->settings) : new stdClass;
				if(!isset($domain_info->settings->color_scheme)) $domain_info->settings->color_scheme = 'auto';
				$domain_info->default_language = $domain_info->settings->language ?: config('locale.default_lang');
				Rhymix\Framework\Cache::set('site_and_module:domain_info:domain:' . $domain, $domain_info, 0, true);
			}
			else
			{
				$domain_info = false;
			}
		}

		return $domain_info;
	}

	/**
	 * @brief Get module information with document_srl
	 * In this case, it is unable to use the cache file
	 */
	public static function getModuleInfoByDocumentSrl($document_srl)
	{
		$document_srl = intval($document_srl);
		$module_info = Rhymix\Framework\Cache::get('site_and_module:document_srl:' . $document_srl);
		if (!$module_info)
		{
			$args = new stdClass();
			$args->document_srl = $document_srl;
			$output = executeQuery('module.getModuleInfoByDocument', $args);
			$module_info = $output->data;
			if ($module_info)
			{
				Rhymix\Framework\Cache::set('site_and_module:document_srl:' . $document_srl, $module_info, 0, true);
			}
		}

		self::_applyDefaultSkin($module_info);
		return self::addModuleExtraVars($module_info);
	}

	/**
	 * @brief Get the default mid according to the domain
	 */
	public static function getDefaultMid($domain = '')
	{
		// Get current domain.
		$domain = $domain ? Rhymix\Framework\URL::decodeIdna($domain) : Rhymix\Framework\URL::getCurrentDomain();

		// Find the domain information.
		$domain_info = $domain ? self::getSiteInfoByDomain($domain) : null;
		if (!$domain_info)
		{
			$domain_info = self::getDefaultDomainInfo();
			if (!$domain_info)
			{
				$domain_info = Module::getInstance()->migrateDomains();
			}
			$domain_info->is_default_replaced = true;
		}

		// Fill in module extra vars and return.
		if ($domain_info->module_srl)
		{
			return self::addModuleExtraVars($domain_info);
		}
		else
		{
			return $domain_info;
		}
	}

	/**
	 * @brief Get module information by mid
	 */
	public static function getModuleInfoByMid($mid)
	{
		if(!$mid || ($mid && !preg_match("/^[a-z][a-z0-9_-]+$/i", $mid)))
		{
			return;
		}

		$args = new stdClass();
		$args->mid = $mid;

		$module_srl = isset(self::$_mid_map[$mid]) ? self::$_mid_map[$mid] : Rhymix\Framework\Cache::get('site_and_module:module_srl:' . $mid);
		if($module_srl)
		{
			$module_info = Rhymix\Framework\Cache::get('site_and_module:mid_info:' . $module_srl);
		}
		else
		{
			$module_info = null;
		}

		if($module_info === null)
		{
			$output = executeQuery('module.getMidInfo', $args);
			$module_info = $output->data;
			if($module_info)
			{
				Rhymix\Framework\Cache::set('site_and_module:module_srl:' . $mid, $module_info->module_srl, 0, true);
				Rhymix\Framework\Cache::set('site_and_module:mid_info:' . $module_info->module_srl, $module_info, 0, true);
			}
			else
			{
				return;
			}
		}

		self::_applyDefaultSkin($module_info);
		if(!$module_info->module_srl && $module_info->data[0]) $module_info = $module_info->data[0];
		return self::addModuleExtraVars($module_info);
	}

	/**
	 * Get module info by menu_item_srl.
	 *
	 * @params int $menu_item_srl
	 *
	 * @return Object $moduleInfo
	 */
	public function getModuleInfoByMenuItemSrl($menu_item_srl = 0)
	{
		$menuItemSrl = Context::get('menu_item_srl');
		$menuItemSrl = (!$menuItemSrl) ? $menu_item_srl : $menuItemSrl;

		if(!$menuItemSrl)
		{
			return new BaseObject(-1, 'msg_invalid_request');
		}

		$args = new stdClass();
		$args->menu_item_srl = $menuItemSrl;
		$output = executeQuery('module.getModuleInfoByMenuItemSrl', $args);
		if(!$output->toBool())
		{
			return $output;
		}

		$moduleInfo = $output->data;
		$mid = $moduleInfo->mid;

		$moduleInfo->designSettings = new stdClass();
		$moduleInfo->designSettings->layout = new stdClass();
		$moduleInfo->designSettings->skin = new stdClass();

		$oLayoutAdminModel = getAdminModel('layout');
		$layoutSrlPc = ($moduleInfo->layout_srl == -1) ? $oLayoutAdminModel->getSiteDefaultLayout('P') : $moduleInfo->layout_srl;
		$layoutSrlMobile = ($moduleInfo->mlayout_srl == -1) ? $oLayoutAdminModel->getSiteDefaultLayout('M') : $moduleInfo->mlayout_srl;
		$skinNamePc = ($moduleInfo->is_skin_fix == 'N') ? self::getModuleDefaultSkin($moduleInfo->module, 'P') : $moduleInfo->skin;
		$skinNameMobile = ($moduleInfo->is_mskin_fix == 'N') ? self::getModuleDefaultSkin($moduleInfo->module, $moduleInfo->mskin === '/USE_RESPONSIVE/' ? 'P' : 'M') : $moduleInfo->mskin;

		$oLayoutModel = getModel('layout');
		$layoutInfoPc = $layoutSrlPc ? $oLayoutModel->getLayoutRawData($layoutSrlPc, array('title')) : NULL;
		$layoutInfoMobile = $layoutSrlMobile ? $oLayoutModel->getLayoutRawData($layoutSrlMobile, array('title')) : NULL;
		$skinInfoPc = self::loadSkinInfo(Modulehandler::getModulePath($moduleInfo->module), $skinNamePc);
		$skinInfoMobile = self::loadSkinInfo(Modulehandler::getModulePath($moduleInfo->module), $skinNameMobile, 'm.skins');
		if(!$skinInfoPc)
		{
			$skinInfoPc = new stdClass();
			$skinInfoPc->title = $skinNamePc;
		}
		if(!$skinInfoMobile)
		{
			$skinInfoMobile = new stdClass();
			$skinInfoMobile->title = $skinNameMobile;
		}

		$moduleInfo->designSettings->layout->pcIsDefault = $moduleInfo->layout_srl == -1 ? 1 : 0;
		$moduleInfo->designSettings->layout->pc = $layoutInfoPc->title ?? null;
		$moduleInfo->designSettings->layout->mobileIsDefault = $moduleInfo->mlayout_srl == -1 ? 1 : 0;
		$moduleInfo->designSettings->layout->mobile = $layoutInfoMobile->title ?? null;
		$moduleInfo->designSettings->skin->pcIsDefault = $moduleInfo->is_skin_fix == 'N' ? 1 : 0;
		$moduleInfo->designSettings->skin->pc = $skinInfoPc->title ?? null;
		$moduleInfo->designSettings->skin->mobileIsDefault = ($moduleInfo->is_mskin_fix == 'N' && $moduleInfo->mskin !== '/USE_RESPONSIVE/') ? 1 : 0;
		$moduleInfo->designSettings->skin->mobile = $skinInfoMobile->title ?? null;

		$module_srl = Rhymix\Framework\Cache::get('site_and_module:module_srl:' . $mid);
		if($module_srl)
		{
			$mid_info = Rhymix\Framework\Cache::get('site_and_module:mid_info:' . $module_srl);
		}
		else
		{
			$mid_info = null;
		}

		if($mid_info === null)
		{
			Rhymix\Framework\Cache::set('site_and_module:module_srl:' . $mid, $output->data->module_srl, 0, true);
			Rhymix\Framework\Cache::set('site_and_module:mid_info:' . $output->data->module_srl, $moduleInfo, 0, true);
		}
		else
		{
			$mid_info->designSettings = $moduleInfo->designSettings;
			$moduleInfo = $mid_info;
		}

		$moduleInfo = self::addModuleExtraVars($moduleInfo);

		if($moduleInfo->module == 'page' && $moduleInfo->page_type != 'ARTICLE')
		{
			unset($moduleInfo->skin);
			unset($moduleInfo->mskin);
		}

		$this->add('module_info_by_menu_item_srl', $moduleInfo);

		return $moduleInfo;
	}

	/**
	 * @brief Get module information corresponding to module_srl
	 */
	public static function getModuleInfoByModuleSrl($module_srl, $columnList = array())
	{
		if(intval($module_srl) == 0)
		{
			return false;
		}
		$mid_info = Rhymix\Framework\Cache::get("site_and_module:mid_info:$module_srl");
		if($mid_info === null)
		{
			// Get data
			$args = new stdClass();
			$args->module_srl = $module_srl;
			$output = executeQuery('module.getMidInfo', $args);
			if(!$output->toBool()) return;
			$mid_info = $output->data;
			if($mid_info)
			{
				self::_applyDefaultSkin($mid_info);
				Rhymix\Framework\Cache::set("site_and_module:mid_info:$module_srl", $mid_info, 0, true);
			}
		}

		if($mid_info && count($columnList))
		{
			$module_info = new stdClass();
			foreach($mid_info as $key => $item)
			{
				if(in_array($key, $columnList))
				{
					$module_info->$key = $item;
				}
			}
		}
		else
		{
			$module_info = $mid_info;
		}

		self::_applyDefaultSkin($module_info);
		return self::addModuleExtraVars($module_info);
	}

	/**
	 * @brief Shortcut to getModuleInfoByModuleSrl()
	 *
	 * @param int $module_srl
	 * @return object
	 */
	public static function getModuleInfo($module_srl)
	{
		return self::getModuleInfoByModuleSrl(intval($module_srl));
	}

	/**
	 * Apply default skin info
	 *
	 * @param stdClass $moduleInfo Module information
	 */
	private static function _applyDefaultSkin($module_info)
	{
		if(isset($module_info->is_skin_fix) && $module_info->is_skin_fix == 'N')
		{
			$module_info->skin = '/USE_DEFAULT/';
		}

		if(isset($module_info->is_mskin_fix) && $module_info->is_mskin_fix == 'N' && $module_info->mskin !== '/USE_RESPONSIVE/')
		{
			$module_info->mskin = '/USE_DEFAULT/';
		}
	}

	/**
	 * @brief Get module information corresponding to layout_srl
	 */
	public static function getModulesInfoByLayout($layout_srl, $columnList = array())
	{
		// Imported data
		$args = new stdClass;
		$args->layout_srl = $layout_srl;
		$output = executeQueryArray('module.getModulesByLayout', $args, $columnList);

		$count = count($output->data);

		$modules = array();
		for($i=0;$i<$count;$i++)
		{
			$modules[] = $output->data[$i];
		}
		return self::addModuleExtraVars($modules);
	}

	/**
	 * @brief Get module information corresponding to multiple module_srls
	 */
	public static function getModulesInfo($module_srls, $columnList = array())
	{
		if (!is_array($module_srls))
		{
			$module_srls = explode(',', $module_srls);
		}
		if (!count($module_srls))
		{
			return [];
		}

		$cache_key = 'site_and_module:modules_info:' . implode(',', $module_srls) . ':' . implode(',', $columnList ?: []);
		$result = Rhymix\Framework\Cache::get($cache_key);
		if ($result !== null)
		{
			return $result;
		}

		$args = new stdClass();
		$args->module_srls = $module_srls;
		$output = executeQueryArray('module.getModulesInfo', $args, $columnList);
		$result = $output->data ?: [];
		if (!$columnList)
		{
			$result = self::addModuleExtraVars($result);
		}

		Rhymix\Framework\Cache::set($cache_key, $result, 0, true);
		return $result;
	}

	/**
	 * @brief Add extra vars to the module basic information
	 */
	public static function addModuleExtraVars($module_info)
	{
		// Process although one or more module informaion is requested
		if (!is_array($module_info))
		{
			$target_module_info = array($module_info);
		}
		else
		{
			$target_module_info = $module_info;
		}

		// Get module_srl
		$module_srls = array();
		foreach($target_module_info as $key => $val)
		{
			if ($val->module_srl)
			{
				$module_srls[] = $val->module_srl;
			}
		}
		if (!count($module_srls))
		{
			return $module_info;
		}

		// Extract extra information of the module and skin
		$extra_vars = self::getModuleExtraVars($module_srls);
		if (!count($extra_vars))
		{
			return $module_info;
		}

		foreach($target_module_info as $key => $val)
		{
			if (!isset($val->module_srl) || !$val->module_srl)
			{
				continue;
			}
			if (!isset($extra_vars[$val->module_srl]))
			{
				continue;
			}

			foreach($extra_vars[$val->module_srl] as $k => $v)
			{
				if(isset($target_module_info[$key]->{$k}) && $target_module_info[$key]->{$k})
				{
					continue;
				}
				$target_module_info[$key]->{$k} = $v;
			}
		}

		if(is_array($module_info))
		{
			return $target_module_info;
		}
		else
		{
			return $target_module_info[0];
		}
	}

	/**
	 * Get the next available mid with the given prefix.
	 *
	 * @param string $prefix
	 * @return string
	 */
	public static function getNextAvailableMid($prefix)
	{
		$prefix = trim($prefix);
		if (!$prefix)
		{
			return '';
		}

		$args = new stdClass;
		$args->mid_prefix = $prefix;
		$output = executeQueryArray('module.getMidInfo', $args, ['mid']);

		$max = 0;
		$len = strlen($prefix);
		foreach ($output->data as $info)
		{
			$suffix = substr($info->mid, $len);
			if (ctype_digit($suffix))
			{
				$max = max($max, intval($suffix));
			}
		}
		return $prefix . ($max + 1);
	}

	/**
	 * @brief Get a complete list of mid, which is created in the DB
	 */
	public static function getMidList($args = null, $columnList = array())
	{
		// Remove site_srl.
		if (is_object($args) && isset($args->site_srl))
		{
			unset($args->site_srl);
		}

		// Check if there are any arguments.
		$argsCount = $args === null ? 0 : countobj($args);
		if(!$argsCount)
		{
			$columnList = array();
		}

		// Use cache only if there are no arguments. Otherwise, get data from DB.
		$list = $argsCount ? null : Rhymix\Framework\Cache::get('site_and_module:module:mid_list');
		if($list === null)
		{
			$output = executeQueryArray('module.getMidList', $args, $columnList);
			if(!$output->toBool()) return $output;
			$list = $output->data;

			// Set cache only if there are no arguments.
			if(!$argsCount)
			{
				Rhymix\Framework\Cache::set('site_and_module:module:mid_list', $list, 0, true);
			}
		}

		if(!$list) return;

		if(!is_array($list)) $list = array($list);

		foreach($list as $val)
		{
			$mid_list[$val->mid] = $val;
		}

		return $mid_list;
	}

	/**
	 * @brief Get a complete list of module_srl, which is created in the DB
	 */
	public static function getModuleSrlList($args = null, $columnList = array())
	{
		$output = executeQueryArray('module.getMidList', $args, $columnList);
		if(!$output->toBool()) return $output;

		$list = $output->data;
		if(!$list) return;

		return $list;
	}

	/**
	 * @brief Return an array of module_srl corresponding to a mid list
	 *
	 * @param int|string|array $mid
	 * @param bool $assoc
	 * @return array
	 */
	public static function getModuleSrlByMid($mid, $assoc = false)
	{
		if ($mid && !is_array($mid))
		{
			$mid = explode(',', $mid);
		}

		if (count($mid) == 1 && ($first_mid = array_first($mid)) && isset(self::$_mid_map[$first_mid]))
		{
			return array($assoc ? $first_mid : 0 => self::$_mid_map[$first_mid]);
		}

		$args = new stdClass;
		$args->mid = $mid;
		$output = executeQueryArray('module.getModuleSrlByMid', $args);

		$module_srl_list = [];
		foreach($output->data as $row)
		{
			$module_srl_list[$row->mid] = $row->module_srl;
			self::$_mid_map[$row->mid] = $row->module_srl;
		}

		return $assoc ? $module_srl_list : array_values($module_srl_list);
	}

	/**
	 * @brief Return mid corresponding to a module_srl list
	 *
	 * @param int|array $module_srl
	 * @return string|array
	 */
	public static function getMidByModuleSrl($module_srl)
	{
		if (is_array($module_srl))
		{
			$result = array();
			foreach ($module_srl as $item)
			{
				$result[intval($item)] = self::getMidByModuleSrl($item);
			}
			return $result;
		}

		$module_srl = intval($module_srl);
		if (isset(self::$_module_srl_map[$module_srl]))
		{
			return self::$_module_srl_map[$module_srl];
		}

		$mid = Rhymix\Framework\Cache::get('site_and_module:module_srl_mid:' . $module_srl);
		if (isset($mid))
		{
			return $mid;
		}

		$args = new stdClass;
		$args->module_srls = $module_srl;
		$output = executeQuery('module.getModuleInfoByModuleSrl', $args, ['mid']);
		if ($output->data)
		{
			$mid = self::$_module_srl_map[$module_srl] = $output->data->mid;
			Rhymix\Framework\Cache::set('site_and_module:module_srl_mid:' . $module_srl, $mid, 0, true);
			return $mid;
		}
		else
		{
			return null;
		}
	}

	/**
	 * @brief Get forward value by the value of act
	 */
	public static function getActionForward($act = null)
	{
		$action_forward = Rhymix\Framework\Cache::get('action_forward');
		if($action_forward === null)
		{
			$args = new stdClass();
			$output = executeQueryArray('module.getActionForward', $args);
			if(!$output->toBool())
			{
				return;
			}

			$action_forward = array();
			foreach($output->data as $item)
			{
				if ($item->route_regexp) $item->route_regexp = unserialize($item->route_regexp);
				if ($item->route_config) $item->route_config = unserialize($item->route_config);
				$action_forward[$item->act] = $item;
			}

			Rhymix\Framework\Cache::set('action_forward', $action_forward, 0, true);
		}

		if(!isset($act))
		{
			return $action_forward;
		}
		if(!isset($action_forward[$act]))
		{
			return;
		}

		return $action_forward[$act];
	}

	/**
	 * @brief Get trigger functions
	 */
	public static function getTriggerFunctions($trigger_name, $called_position)
	{
		if(isset($GLOBALS['__trigger_functions__'][$trigger_name][$called_position]))
		{
			return $GLOBALS['__trigger_functions__'][$trigger_name][$called_position];
		}
		else
		{
			return array();
		}
	}

	/**
	 * @brief Get a list of all triggers on the trigger_name
	 */
	public static function getTriggers($trigger_name, $called_position)
	{
		if(!isset($GLOBALS['__triggers__']))
		{
			$triggers = Rhymix\Framework\Cache::get('triggers');
			if($triggers === null)
			{
				$output = executeQueryArray('module.getTriggers');
				$triggers = $output->data;
				if($output->toBool())
				{
					Rhymix\Framework\Cache::set('triggers', $triggers, 0, true);
				}
			}

			$triggers = $triggers ?: array();
			foreach($triggers as $item)
			{
				$GLOBALS['__triggers__'][$item->trigger_name][$item->called_position][] = $item;
			}
		}

		return $GLOBALS['__triggers__'][$trigger_name][$called_position] ?? [];
	}

	/**
	 * @brief Get specific triggers from the trigger_name
	 */
	public static function getTrigger($trigger_name, $module, $type, $called_method, $called_position)
	{
		$triggers = self::getTriggers($trigger_name, $called_position);

		if($triggers && is_array($triggers))
		{
			foreach($triggers as $item)
			{
				if($item->module == $module && $item->type == $type && $item->called_method == $called_method)
				{
					return $item;
				}
			}
		}

		return NULL;
	}

	/**
	 * @brief Get module extend
	 *
	 * @deprecated
	 */
	public static function getModuleExtend($parent_module, $type, $kind = '')
	{
		return false;
	}

	/**
	 * @brief Get all the module extend
	 *
	 * @deprecated
	 *
	 */
	public static function loadModuleExtends()
	{
		return array();
	}

	/**
	 * @brief Get information from conf/info.xml
	 */
	public static function getModuleInfoXml($module)
	{
		// Check the path and XML file name.
		$module_path = ModuleHandler::getModulePath($module);
		if (!$module_path) return;
		$xml_file = $module_path . 'conf/info.xml';
		if (!file_exists($xml_file)) return;

		// Load the XML file and cache the definition.
		$lang_type = Context::getLangType() ?: 'en';
		$mtime1 = filemtime($xml_file);
		$mtime2 = file_exists($module_path . 'conf/module.xml') ? filemtime($module_path . 'conf/module.xml') : 0;
		$cache_key = sprintf('site_and_module:module_info_xml:%s:%s:%d:%d', $module, $lang_type, $mtime1, $mtime2);
		$info = Rhymix\Framework\Cache::get($cache_key);
		if($info === null)
		{
			$info = Rhymix\Framework\Parsers\ModuleInfoParser::loadXML($xml_file);
			Rhymix\Framework\Cache::set($cache_key, $info, 0, true);
		}

		return $info;
	}

	/**
	 * @brief Return permisson and action data by conf/module.xml
	 */
	public static function getModuleActionXml($module)
	{
		// Check the path and XML file name.
		$module_path = ModuleHandler::getModulePath($module);
		if (!$module_path) return;
		$xml_file = $module_path . 'conf/module.xml';
		if (!file_exists($xml_file)) return;

		// Load the XML file and cache the definition.
		$lang_type = Context::getLangType() ?: 'en';
		$mtime = filemtime($xml_file);
		$cache_key = sprintf('site_and_module:module_action_xml:%s:%s:%d', $module, $lang_type, $mtime);
		$info = Rhymix\Framework\Cache::get($cache_key);
		if($info === null)
		{
			$info = Rhymix\Framework\Parsers\ModuleActionParser::loadXML($xml_file);
			Rhymix\Framework\Cache::set($cache_key, $info, 0, true);
		}

		return $info;
	}

	/**
	 * Get a skin list for js API.
	 * return void
	 */
	public function getModuleSkinInfoList()
	{
		$module = Context::get('module_type');

		if($module == 'ARTICLE')
		{
			$module = 'page';
		}

		$skinType = Context::get('skin_type');

		$path = ModuleHandler::getModulePath($module);
		$dir = ($skinType == 'M') ? 'm.skins' : 'skins';
		$skin_list = self::getSkins($path, $dir);

		$this->add('skin_info_list', $skin_list);
	}

	/**
	 * @brief Get a list of skins for the module
	 * Return file analysis of skin and skin.xml
	 */
	public static function getSkins($path, $dir = 'skins')
	{
		if(substr($path, -1) == '/')
		{
			$path = substr($path, 0, -1);
		}

		$skin_list = array();
		$skin_path = sprintf("%s/%s/", $path, $dir);
		$list = FileHandler::readDir($skin_path);
		//if(!count($list)) return;

		natcasesort($list);

		foreach($list as $skin_name)
		{
			if(!is_dir($skin_path . $skin_name))
			{
				continue;
			}
			unset($skin_info);
			$skin_info = self::loadSkinInfo($path, $skin_name, $dir);
			if(!$skin_info)
			{
				$skin_info = new stdClass();
				$skin_info->title = $skin_name;
			}

			$skin_list[$skin_name] = $skin_info;
		}

		$tmpPath = strtr($path, array('/' => ' '));
		$tmpPath = trim($tmpPath);
		$module = array_last(explode(' ', $tmpPath));

		if($dir == 'skins')
		{
			$oAdminModel = getAdminModel('admin');
			$themesInfo = $oAdminModel->getThemeList();

			foreach($themesInfo as $themeName => $info)
			{
				$skinInfos = $info->skin_infos;
				if(isset($skinInfos[$module]) && $skinInfos[$module]->is_theme)
				{
					$themeSkinInfo = $GLOBALS['__ThemeModuleSkin__'][$module]['skins'][$skinInfos[$module]->name] ?? null;
					$skin_list[$skinInfos[$module]->name] = $themeSkinInfo;
				}
			}
		}

		$siteInfo = Context::get('site_module_info');
		$oMenuAdminModel = getAdminModel('menu');
		$installedMenuTypes = $oMenuAdminModel->getModuleListInSitemap();
		$moduleName = $module;
		if($moduleName === 'page')
		{
			$moduleName = 'ARTICLE';
		}

		$useDefaultList = array();
		if(array_key_exists($moduleName, $installedMenuTypes))
		{
			$defaultSkinName = self::getModuleDefaultSkin($module, $dir == 'skins' ? 'P' : 'M');
			if ($defaultSkinName)
			{
				if ($defaultSkinName === '/USE_RESPONSIVE/')
				{
					$defaultSkinInfo = (object)array('title' => lang('use_responsive_pc_skin'));
				}
				else
				{
					$defaultSkinInfo = self::loadSkinInfo($path, $defaultSkinName, $dir);
				}

				$useDefault = new stdClass();
				$useDefault->title = lang('use_site_default_skin') . ' (' . ($defaultSkinInfo->title ?? null) . ')';

				$useDefaultList['/USE_DEFAULT/'] = $useDefault;
			}
		}
		if($dir == 'm.skins')
		{
			$useDefaultList['/USE_RESPONSIVE/'] = (object)array('title' => lang('use_responsive_pc_skin'));
		}

		$skin_list = array_merge($useDefaultList, $skin_list);

		return $skin_list;
	}

	/**
	 * @brief Get skin information on a specific location
	 */
	public static function loadSkinInfo($path, $skin, $dir = 'skins')
	{
		// Read xml file having skin information
		if (!str_ends_with($path, '/'))
		{
			$path .= '/';
		}
		if (!preg_match('/^[a-zA-Z0-9_-]+$/', $skin ?? ''))
		{
			return;
		}

		$skin_path = sprintf("%s%s/%s/", $path, $dir, $skin);
		$skin_xml_file = $skin_path . 'skin.xml';
		if (!file_exists($skin_xml_file))
		{
			return;
		}

		$skin_info = Rhymix\Framework\Parsers\SkinInfoParser::loadXML($skin_xml_file, $skin, $skin_path);
		return $skin_info;
	}

	/**
	 * @brief Return the number of modules which are registered on a virtual site
	 */
	public static function getModuleCount($site_srl = 0, $module = null)
	{
		$args = new stdClass;
		if(!is_null($module)) $args->module = $module;
		$output = executeQuery('module.getModuleCount', $args);
		return $output->data->count;
	}

	/**
	 * Get global config for a module.
	 *
	 * @param string $module
	 * @return mixed
	 */
	public static function getModuleConfig($module)
	{
		if (!$module || !is_scalar($module))
		{
			return null;
		}

		if(!isset($GLOBALS['__ModuleConfig__'][$module]))
		{
			$config = Rhymix\Framework\Cache::get('site_and_module:module_config:' . $module);
			if($config === null)
			{
				$args = new stdClass;
				$args->module = $module;
				$output = executeQuery('module.getModuleConfig', $args);

				// Only object type
				if(isset($output->data->config) && $output->data->config)
				{
					$config = unserialize($output->data->config);
				}
				else
				{
					$config = -1;  // Use -1 as a temporary value because null cannot be cached
				}

				// Set cache
				if($output->toBool())
				{
					Rhymix\Framework\Cache::set('site_and_module:module_config:' . $module, $config, 0, true);
				}
			}
			$GLOBALS['__ModuleConfig__'][$module] = $config;
		}

		$config = $GLOBALS['__ModuleConfig__'][$module];
		return $config === -1 ? null : $config;
	}

	/**
	 * Get an independent section of module config.
	 *
	 * @param string $module
	 * @param string $section
	 * @return mixed
	 */
	public static function getModuleSectionConfig($module, $section)
	{
		return self::getModuleConfig("$module:$section");
	}

	/**
	 * Get config for a specific module_srl.
	 *
	 * @param string module
	 * @param int $module_srl
	 * @return mixed
	 */
	public static function getModulePartConfig($module, $module_srl)
	{
		if (!$module || !is_scalar($module) || !$module_srl || !is_scalar($module_srl))
		{
			return null;
		}

		if(!isset($GLOBALS['__ModulePartConfig__'][$module][$module_srl]))
		{
			$config = Rhymix\Framework\Cache::get('site_and_module:module_part_config:' . $module . '_' . $module_srl);
			if($config === null)
			{
				$args = new stdClass;
				$args->module = $module;
				$args->module_srl = $module_srl ?: 0;
				$output = executeQuery('module.getModulePartConfig', $args);

				// Object or Array(compatibility) type
				if($output->data && isset($output->data->config))
				{
					$config = unserialize($output->data->config);
				}
				else
				{
					$config = -1;  // Use -1 as a temporary value because null cannot be cached
				}

				// Deprecate use of ArrayObject because of https://bugs.php.net/bug.php?id=77298
				if($config instanceof ArrayObject)
				{
					$config = (object)($config->getArrayCopy());
				}

				// Set cache
				if($output->toBool())
				{
					Rhymix\Framework\Cache::set('site_and_module:module_part_config:' . $module . '_' . $module_srl, $config, 0, true);
				}
			}
			$GLOBALS['__ModulePartConfig__'][$module][$module_srl] = $config;
		}

		$config = $GLOBALS['__ModulePartConfig__'][$module][$module_srl];
		return $config === -1 ? null : $config;
	}

	/**
	 * @brief Get all of module configurations for each mid
	 */
	public static function getModulePartConfigs($module)
	{
		$args = new stdClass();
		$args->module = $module;
		$output = executeQueryArray('module.getModulePartConfigs', $args);

		if(!$output->toBool() || !$output->data)
		{
			return array();
		}

		$result = array();
		foreach($output->data as $key => $val)
		{
			$result[$val->module_srl] = unserialize($val->config);
		}

		return $result;
	}

	/**
	 * @brief Get a list of module category
	 */
	public static function getModuleCategories($moduleCategorySrl = array())
	{
		$args = new stdClass();
		$args->moduleCategorySrl = $moduleCategorySrl;
		// Get data from the DB
		$output = executeQueryArray('module.getModuleCategories', $args);
		if(!$output->toBool()) return $output;
		$category_list = [];
		foreach($output->data as $val)
		{
			$category_list[$val->module_category_srl] = $val;
		}
		return $category_list;
	}

	/**
	 * @brief Get content from the module category
	 */
	public static function getModuleCategory($module_category_srl)
	{
		// Get data from the DB
		$args = new stdClass;
		$args->module_category_srl = $module_category_srl;
		$output = executeQuery('module.getModuleCategory', $args);
		if(!$output->toBool()) return $output;
		return $output->data;
	}

	/**
	 * @brief Get xml information of the module
	 */
	public static function getModulesXmlInfo()
	{
		// Get a list of downloaded and installed modules
		$searched_list = FileHandler::readDir('./modules');
		$searched_count = count($searched_list);
		if(!$searched_count) return;
		sort($searched_list);

		for($i=0;$i<$searched_count;$i++)
		{
			// Module name
			$module_name = $searched_list[$i];

			$path = ModuleHandler::getModulePath($module_name);
			// Get information of the module
			$info = self::getModuleInfoXml($module_name);
			unset($obj);

			if(!isset($info)) continue;
			$info->module = $module_name;
			$info->created_table_count = null; //$created_table_count;
			$info->table_count = null; //$table_count;
			$info->path = $path;
			$info->admin_index_act = $info->admin_index_act ?? null;
			$list[] = $info;
		}
		return $list;
	}

	/**
	 * Get module base class
	 *
	 * This method supports namespaced modules as well as XE-compatible modules.
	 *
	 * @param string $module_name
	 * @return ModuleObject|null
	 */
	public static function getModuleDefaultClass(string $module_name, ?object $module_action_info = null)
	{
		if (!$module_action_info)
		{
			$module_action_info = self::getModuleActionXml($module_name);
		}

		if (isset($module_action_info->namespaces) && count($module_action_info->namespaces))
		{
			$namespace = array_first($module_action_info->namespaces);
		}
		else
		{
			$namespace = 'Rhymix\\Modules\\' . ucfirst($module_name);
		}

		if (isset($module_action_info->classes['default']))
		{
			$class_name = $namespace . '\\' . $module_action_info->classes['default'];
			return class_exists($class_name) ? $class_name::getInstance() : null;
		}

		$class_name = $namespace . '\\Base';
		if (class_exists($class_name))
		{
			return $class_name::getInstance();
		}

		$class_name = $namespace . '\\Controllers\\Base';
		if (class_exists($class_name))
		{
			return $class_name::getInstance();
		}

		if ($oModule = getModule($module_name, 'class'))
		{
			return $oModule;
		}
	}

	/**
	 * Get module install class
	 *
	 * This method supports namespaced modules as well as XE-compatible modules.
	 *
	 * @param string $module_name
	 * @return ModuleObject|null
	 */
	public static function getModuleInstallClass(string $module_name, ?object $module_action_info = null)
	{
		if (!$module_action_info)
		{
			$module_action_info = self::getModuleActionXml($module_name);
		}

		if (isset($module_action_info->namespaces) && count($module_action_info->namespaces))
		{
			$namespace = array_first($module_action_info->namespaces);
		}
		else
		{
			$namespace = 'Rhymix\\Modules\\' . ucfirst($module_name);
		}

		if (isset($module_action_info->classes['install']))
		{
			$class_name = $namespace . '\\' . $module_action_info->classes['install'];
			return class_exists($class_name) ? $class_name::getInstance() : null;
		}

		$class_name = $namespace . '\\Install';
		if (class_exists($class_name))
		{
			return $class_name::getInstance();
		}

		$class_name = $namespace . '\\Controllers\\Install';
		if (class_exists($class_name))
		{
			return $class_name::getInstance();
		}

		if ($oModule = getModule($module_name, 'class'))
		{
			return $oModule;
		}
	}

	public static function checkNeedInstall($module_name)
	{
		$oDB = DB::getInstance();
		$info = null;

		$moduledir = ModuleHandler::getModulePath($module_name);
		if(file_exists(FileHandler::getRealPath($moduledir."schemas")))
		{
			$tmp_files = FileHandler::readDir($moduledir."schemas", '/(\.xml)$/');
			$table_count = count($tmp_files);
			// Check if the table is created
			$created_table_count = 0;
			for($j=0;$j<count($tmp_files);$j++)
			{
				list($table_name) = explode(".",$tmp_files[$j]);
				if($oDB->isTableExists($table_name)) $created_table_count ++;
			}
			// Check if DB is installed
			if($table_count > $created_table_count) return true;
			else return false;
		}
		return false;
	}

	public static function checkNeedUpdate($module_name)
	{
		// Check if it is upgraded to module.class.php on each module
		$oDummy = self::getModuleInstallClass($module_name);
		if($oDummy && method_exists($oDummy, "checkUpdate"))
		{
			return $oDummy->checkUpdate();
		}
		return false;
	}

	/**
	 * @brief 업데이트 적용 여부 확인
	 * @param array|string $update_id
	 * @return Boolean
	 */
	public static function needUpdate($update_id)
	{
		if(!is_array($update_id)) $update_id = array($update_id);

		$args = new stdClass();
		$args->update_id = implode(',', $update_id);
		$output = executeQueryArray('module.getModuleUpdateLog', $args);

		if(!!$output->error) return false;
		if(!$output->data) $output->data = array();
		if(count($update_id) === count($output->data)) return false;

		return true;
	}

	/**
	 * @brief Get a type and information of the module
	 */
	public static function getModuleList()
	{
		// Create DB Object
		$oDB = DB::getInstance();
		// Get a list of downloaded and installed modules
		$searched_list = FileHandler::readDir('./modules', '/^([a-zA-Z0-9_-]+)$/');
		sort($searched_list);

		$searched_count = count($searched_list);
		if(!$searched_count) return;

		// Get action forward
		$action_forward = self::getActionForward();

		foreach ($searched_list as $module_name)
		{
			$path = ModuleHandler::getModulePath($module_name);
			if(!is_dir(FileHandler::getRealPath($path))) continue;

			// Get the number of xml files to create a table in schemas
			$table_count = 0;
			$schema_files = FileHandler::readDir($path.'schemas', '/(\.xml)$/', false, true);
			foreach ($schema_files as $filename)
			{
				if (!preg_match('/<table\s[^>]*deleted="true"/i', file_get_contents($filename)))
				{
					$table_count++;
				}
			}

			// Check if the table is created
			$created_table_count = 0;
			foreach ($schema_files as $filename)
			{
				if (!preg_match('/\/([a-zA-Z0-9_]+)\.xml$/', $filename, $matches))
				{
					continue;
				}

				if($oDB->isTableExists($matches[1]))
				{
					$created_table_count++;
				}
			}
			// Get information of the module
			$info = self::getModuleInfoXml($module_name);
			if(!$info) continue;

			$info->module = $module_name;
			$info->created_table_count = $created_table_count;
			$info->table_count = $table_count;
			$info->path = $path;
			$info->admin_index_act = $info->admin_index_act ?? null;
			$info->need_install = false;
			$info->need_update = false;

			if(!Context::isBlacklistedPlugin($module_name, 'module'))
			{
				// Check if DB is installed
				if($table_count > $created_table_count)
				{
					$info->need_install = true;
				}

				// Check if it is upgraded to module.class.php on each module
				$oDummy = self::getModuleInstallClass($module_name);
				if($oDummy && method_exists($oDummy, "checkUpdate"))
				{
					$info->need_update = $oDummy->checkUpdate();
				}
				unset($oDummy);

				// Check if all action-forwardable routes are registered
				$module_action_info = self::getModuleActionXml($module_name);
				$forwardable_routes = array();
				foreach ($module_action_info->action ?? [] as $action_name => $action_info)
				{
					if (count($action_info->route) && $action_info->standalone === 'true')
					{
						$forwardable_routes[$action_name] = array(
							'regexp' => array(),
							'config' => $action_info->route,
						);
					}
				}
				foreach ($module_action_info->route->GET ?? [] as $regexp => $action_name)
				{
					if (isset($forwardable_routes[$action_name]))
					{
						$forwardable_routes[$action_name]['regexp'][] = ['GET', $regexp];
					}
				}
				foreach ($module_action_info->route->POST ?? [] as $regexp => $action_name)
				{
					if (isset($forwardable_routes[$action_name]))
					{
						$forwardable_routes[$action_name]['regexp'][] = ['POST', $regexp];
					}
				}
				foreach ($forwardable_routes as $action_name => $route_info)
				{
					if (!isset($action_forward[$action_name]) ||
						$action_forward[$action_name]->route_regexp !== $route_info['regexp'] ||
						$action_forward[$action_name]->route_config !== $route_info['config'])
					{
						$info->need_update = true;
					}
				}

				// Clean up any action-forward routes that are no longer needed.
				foreach ($forwardable_routes as $action_name => $route_info)
				{
					unset($action_forward[$action_name]);
				}
				foreach ($action_forward as $action_name => $forward_info)
				{
					if ($forward_info->module === $module_name && $forward_info->route_regexp !== null)
					{
						$info->need_update = true;
					}
				}

				// Check if all event handlers are registered.
				$registered_event_handlers = [];
				foreach ($module_action_info->event_handlers ?? [] as $ev)
				{
					$key = implode(':', [$ev->event_name, $module_name, $ev->class_name, $ev->method, $ev->position]);
					$registered_event_handlers[$key] = true;
					if(!ModuleModel::getTrigger($ev->event_name, $module_name, $ev->class_name, $ev->method, $ev->position))
					{
						$info->need_update = true;
					}
				}
				if (count($registered_event_handlers))
				{
					foreach ($GLOBALS['__triggers__'] as $trigger_name => $val1)
					{
						foreach ($val1 as $called_position => $val2)
						{
							foreach ($val2 as $item)
							{
								if ($item->module === $module_name)
								{
									$key = implode(':', [$trigger_name, $item->module, $item->type, $item->called_method, $called_position]);
									if (!isset($registered_event_handlers[$key]))
									{
										$info->need_update = true;
									}
								}
							}
						}
					}
				}

				// Check if all namespaces are registered.
				$namespaces = config('namespaces') ?? [];
				foreach ($module_action_info->namespaces ?? [] as $name)
				{
					if(!isset($namespaces['mapping'][$name]))
					{
						$info->need_update = true;
					}
				}
				foreach ($namespaces['mapping'] ?? [] as $name => $path)
				{
					$attached_module = preg_replace('!^modules/!', '', $path);
					if ($attached_module === $module_name && !in_array($name, $module_action_info->namespaces ?? []))
					{
						$info->need_update = true;
					}
				}

				// Check if all prefixes are registered.
				foreach ($module_action_info->prefixes ?? [] as $name)
				{
					if(!ModuleModel::getModuleSrlByMid($name))
					{
						$info->need_update = true;
					}
				}
			}

			$list[] = $info;
		}

		return $list;
	}

	/**
	 * @brief Combine module_srls with domain of sites
	 * Because XE DBHandler doesn't support left outer join,
	 * it should be as same as $Output->data[]->module_srl.
	 */
	public static function syncModuleToSite(&$data)
	{
		if(!$data) return;

		if(is_array($data))
		{
			foreach($data as $key => $val)
			{
				$module_srls[] = $val->module_srl;
			}
			if(!count($module_srls)) return;
		}
		else
		{
			$module_srls[] = $data->module_srl;
		}

		$args = new stdClass();
		$args->module_srls = implode(',',$module_srls);
		$output = executeQueryArray('module.getModuleSites', $args);
		if(!$output->data) return array();
		foreach($output->data as $key => $val)
		{
			$modules[$val->module_srl] = $val;
		}

		if(is_array($data))
		{
			foreach($data as $key => $val)
			{
				if (isset($modules[$val->module_srl]))
				{
					$data[$key]->domain = $modules[$val->module_srl]->domain;
				}
				elseif (!isset($data[$key]->domain))
				{
					$data[$key]->domain = null;
				}
			}
		}
		else
		{
			$data->domain = $modules[$data->module_srl]->domain ?? null;
		}
	}

	/**
	 * @brief Check if it is an administrator of site_module_info
	 */
	public static function isSiteAdmin($member_info)
	{
		if ($member_info && isset($member_info->is_admin) && $member_info->is_admin == 'Y')
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * @brief Get admin information of the site
	 */
	public static function getSiteAdmin()
	{
		return array();
	}

	/**
	 * Check if a member is a module administrator
	 *
	 * @return array|bool
	 */
	public static function isModuleAdmin($member_info, $module_srl = null)
	{
		if (!$member_info || !$member_info->member_srl)
		{
			return false;
		}
		if ($member_info->is_admin == 'Y')
		{
			return true;
		}
		if ($module_srl === null)
		{
			$site_module_info = Context::get('site_module_info');
			if(!$site_module_info) return false;
			$module_srl = $site_module_info->module_srl;
		}

		$module_srl = $module_srl ?: 0;
		$module_admins = Rhymix\Framework\Cache::get("site_and_module:module_admins:$module_srl");
		if ($module_admins === null)
		{
			$args = new stdClass;
			$args->module_srl = $module_srl;
			$output = executeQueryArray('module.getModuleAdmin', $args);
			$module_admins = array();
			foreach ($output->data as $module_admin)
			{
				$module_admins[$module_admin->member_srl] = $module_admin->scopes ? json_decode($module_admin->scopes) : true;
			}
			if ($output->toBool())
			{
				Rhymix\Framework\Cache::set("site_and_module:module_admins:$module_srl", $module_admins, 0, true);
			}
		}

		if (isset($module_admins[$member_info->member_srl]))
		{
			return $module_admins[$member_info->member_srl];
		}
		else
		{
			return false;
		}
	}

	/**
	 * @brief Get admin ID of the module
	 */
	public static function getAdminId($module_srl)
	{
		$obj = new stdClass();
		$obj->module_srl = $module_srl;
		$output = executeQueryArray('module.getAdminID', $obj);
		if (!$output->toBool() || !$output->data)
		{
			return;
		}
		foreach ($output->data as $row)
		{
			$row->scopes = !empty($row->scopes) ? json_decode($row->scopes) : null;
		}
		return $output->data;
	}

	/**
	 * @brief Get extra vars of the module
	 * Extra information, not in the modules table
	 */
	public static function getModuleExtraVars($list_module_srl)
	{
		$extra_vars = array();
		$get_module_srls = array();
		if(!is_array($list_module_srl)) $list_module_srl = array($list_module_srl);

		foreach($list_module_srl as $module_srl)
		{
			$vars = Rhymix\Framework\Cache::get("site_and_module:module_extra_vars:$module_srl");
			if($vars !== null)
			{
				$extra_vars[$module_srl] = $vars;
			}
			else
			{
				$get_module_srls[] = $module_srl;
			}
		}

		if(count($get_module_srls) > 0)
		{
			$args = new stdClass();
			$args->module_srl = implode(',', $get_module_srls);
			$output = executeQueryArray('module.getModuleExtraVars', $args);
			if(!$output->toBool())
			{
				return array();
			}

			if(!$output->data)
			{
				foreach($get_module_srls as $module_srl)
				{
					Rhymix\Framework\Cache::set("site_and_module:module_extra_vars:$module_srl", new stdClass, 0, true);
					$extra_vars[$module_srl] = new stdClass;
				}
			}
			foreach($output->data as $key => $val)
			{
				if(in_array($val->name, array('mid','module')) || $val->value == 'Array') continue;

				if(!isset($extra_vars[$val->module_srl]))
				{
					$extra_vars[$val->module_srl] = new stdClass();
				}
				$extra_vars[$val->module_srl]->{$val->name} = $val->value;

				Rhymix\Framework\Cache::set('site_and_module:module_extra_vars:' . $val->module_srl, $extra_vars[$val->module_srl], 0, true);
			}
		}

		return $extra_vars;
	}

	/**
	 * @brief Get skin information of the module
	 */
	public static function getModuleSkinVars($module_srl)
	{
		$skin_vars = Rhymix\Framework\Cache::get("site_and_module:module_skin_vars:$module_srl");
		if($skin_vars === null)
		{
			$args = new stdClass();
			$args->module_srl = $module_srl;
			$output = executeQueryArray('module.getModuleSkinVars',$args);
			if(!$output->toBool()) return;

			$skin_vars = array();
			foreach($output->data as $vars)
			{
				$skin_vars[$vars->name] = $vars;
			}

			Rhymix\Framework\Cache::set("site_and_module:module_skin_vars:$module_srl", $skin_vars, 0, true);
		}

		return $skin_vars;
	}

	/**
	 * Get default skin name
	 */
	public static function getModuleDefaultSkin($module_name, $skin_type = 'P', $site_srl = 0, $updateCache = true)
	{
		$target = ($skin_type == 'M') ? 'mskin' : 'skin';
		$designInfoFile = RX_BASEDIR.'files/site_design/design_0.php';
		if(is_readable($designInfoFile))
		{
			include($designInfoFile);

			$skinName = $designInfo->module->{$module_name}->{$target} ?? null;
		}
		if(!isset($designInfo))
		{
			$designInfo = new stdClass();
		}
		if(!$skinName)
		{
			$dir = ($skin_type == 'M') ? 'm.skins/' : 'skins/';
			$moduleSkinPath = ModuleHandler::getModulePath($module_name).$dir;

			if(is_dir($moduleSkinPath.'default'))
			{
				$skinName = 'default';
			}
			else if(is_dir($moduleSkinPath.'xe_default'))
			{
				$skinName = 'xe_default';
			}
			else
			{
				$skins = FileHandler::readDir($moduleSkinPath);
				if(count($skins) > 0)
				{
					$skinName = $skins[0];
				}
				else
				{
					$skinName = NULL;
				}
			}

			if($updateCache && $skinName)
			{
				if(!isset($designInfo->module))
				{
					$designInfo->module = new stdClass();
				}
				if(!isset($designInfo->module->{$module_name})) $designInfo->module->{$module_name} = new stdClass();
				$designInfo->module->{$module_name}->{$target} = $skinName;

				$oAdminController = getAdminController('admin');
				$oAdminController->makeDefaultDesignFile($designInfo);
			}
		}

		return $skinName;
	}

	/**
	 * @brief Combine skin information with module information
	 */
	public static function syncSkinInfoToModuleInfo(&$module_info)
	{
		if(!$module_info->module_srl) return;

		if(Mobile::isFromMobilePhone() && $module_info->mskin !== '/USE_RESPONSIVE/')
		{
			$skin_vars = self::getModuleMobileSkinVars($module_info->module_srl);
		}
		else
		{
			$skin_vars = self::getModuleSkinVars($module_info->module_srl);
		}

		if(!$skin_vars) return;

		foreach($skin_vars as $name => $val)
		{
			if(isset($module_info->{$name})) continue;
			$module_info->{$name} = $val->value;
		}
	}

	/**
	 * Get mobile skin information of the module
	 * @param $module_srl Sequence of module
	 * @return array
	 */
	public static function getModuleMobileSkinVars($module_srl)
	{
		$skin_vars = Rhymix\Framework\Cache::get("site_and_module:module_mobile_skin_vars:$module_srl");
		if($skin_vars === null)
		{
			$args = new stdClass();
			$args->module_srl = $module_srl;
			$output = executeQueryArray('module.getModuleMobileSkinVars',$args);
			if(!$output->toBool() || !$output->data) return;

			$skin_vars = array();
			foreach($output->data as $vars)
			{
				$skin_vars[$vars->name] = $vars;
			}

			Rhymix\Framework\Cache::set("site_and_module:module_mobile_skin_vars:$module_srl", $skin_vars, 0, true);
		}

		return $skin_vars;
	}

	/**
	 * Combine skin information with module information
	 * @param $module_info Module information
	 */
	public static function syncMobileSkinInfoToModuleInfo(&$module_info)
	{
		if(!$module_info->module_srl) return;

		$skin_vars = Rhymix\Framework\Cache::get('site_and_module:module_mobile_skin_vars:' . $module_info->module_srl);
		if($skin_vars === null)
		{
			$args = new stdClass;
			$args->module_srl = $module_info->module_srl;
			$output = executeQueryArray('module.getModuleMobileSkinVars',$args);
			if(!$output->toBool()) return;
			$skin_vars = $output->data;

			Rhymix\Framework\Cache::set('site_and_module:module_mobile_skin_vars:' . $module_info->module_srl, $skin_vars, 0, true);
		}
		if(!$skin_vars) return;

		foreach($output->data as $val)
		{
			if(isset($module_info->{$val->name})) continue;
			$module_info->{$val->name} = $val->value;
		}
	}

	/**
	 * Get privileges(granted) information by using module info, xml info and member info
	 *
	 * @param object $module_info
	 * @param object $member_info
	 * @param ?object $xml_info
	 * @return Rhymix\Modules\Module\Models\Permission
	 */
	public static function getGrant($module_info, $member_info, $xml_info = null)
	{
		if (empty($module_info->module))
		{
			$module_info = new stdClass;
			$module_info->module = $module_info->module_srl = 0;
		}

		if (isset($GLOBALS['__MODULE_GRANT__'][$module_info->module][intval($module_info->module_srl ?? 0)][intval($member_info->member_srl ?? 0)]))
		{
			$__cache = &$GLOBALS['__MODULE_GRANT__'][$module_info->module][intval($module_info->module_srl ?? 0)][intval($member_info->member_srl ?? 0)];
			if (is_object($__cache) && !$xml_info)
			{
				return $__cache;
			}
		}

		// Get module grant information
		if (!$xml_info)
		{
			$xml_info = self::getModuleActionXml($module_info->module);
		}

		// Generate grant
		$xml_grant_list = isset($xml_info->grant) ? (array)$xml_info->grant : array();
		$module_grants = self::getModuleGrants($module_info->module_srl ?? 0)->data ?: [];
		$grant = new Rhymix\Modules\Module\Models\Permission($xml_grant_list, $module_grants, $module_info, $member_info ?: null);
		return $__cache = $grant;
	}

	/**
	 * Get the list of modules that the member can access.
	 *
	 * @param object $member_info
	 * @return array
	 */
	public static function getAccessibleModuleList($member_info = null)
	{
		if(!$member_info)
		{
			$member_info = Context::get('logged_info');
		}

		$result = Rhymix\Framework\Cache::get(sprintf('site_and_module:accessible_modules:%d', $member_info->member_srl));
		if($result === null)
		{
			$mid_list = self::getMidList();
			$result = array();

			foreach($mid_list as $module_info)
			{
				$grant = self::getGrant($module_info, $member_info);
				if(!$grant->access)
				{
					continue;
				}
				foreach(array('list', 'view') as $require_grant)
				{
					if(isset($grant->{$require_grant}) && $grant->{$require_grant} === false)
					{
						continue 2;
					}
				}
				$result[$module_info->module_srl] = $module_info;
			}
			ksort($result);

			Rhymix\Framework\Cache::set(sprintf('site_and_module:accessible_modules:%d', $member_info->member_srl), $result);
		}

		return $result;
	}

	/**
	 * Get privileges(granted) information of the member for target module by target_srl
	 * @param string $target_srl as module_srl. It may be a reference serial number
	 * @param string $type module name. get module_srl from module
	 * @param object $member_info member information
	 * @return mixed success : object, fail : false
	 * */
	public static function getPrivilegesBySrl($target_srl, $type = null, $member_info = null)
	{
		if(empty($target_srl = trim($target_srl)) || !preg_match('/^([0-9]+)$/', $target_srl) && $type != 'module')
		{
			return false;
		}

		if($type)
		{
			if($type == 'document')
			{
				$target_srl = DocumentModel::getDocument($target_srl, false, false)->get('module_srl');
			}
			else if($type == 'comment')
			{
				$target_srl = CommentModel::getComment($target_srl)->get('module_srl');
			}
			else if($type == 'file')
			{
				$target_srl = FileModel::getFile($target_srl)->module_srl;
			}
			else if($type == 'module')
			{
				$module_info = self::getModuleInfoByMid($target_srl);
			}
		}

		if(!isset($module_info))
		{
			$module_info = self::getModuleInfoByModuleSrl($target_srl);
		}

		if(!$module_info->module_srl)
		{
			return false;
		}

		if(!$member_info)
		{
			$member_info = Context::get('logged_info');
		}

		return self::getGrant($module_info, $member_info);
	}

	/**
	 * @brief Search all modules to find manager privilege of the member
	 * @param object $member_info member information
	 * @param string $module module name. if used, search scope is same module
	 * @return mixed success : object, fail : false
	 */
	public static function findManagerPrivilege($member_info, $module = null)
	{
		if(!$member_info->member_srl || empty($mid_list = self::getMidList()))
		{
			return false;
		}

		foreach($mid_list as $module_info)
		{
			if($module && $module_info->module != $module)
			{
				continue;
			}

			if(($grant = self::getGrant($module_info, $member_info)) && $grant->manager)
			{
				return $grant;
			}
		}

		return false;
	}

	/**
	 * @brief Get module grants
	 */
	public static function getModuleGrants($module_srl)
	{
		$output = Rhymix\Framework\Cache::get("site_and_module:module_grants:$module_srl");
		if ($output === null)
		{
			$args = new stdClass;
			$args->module_srl = $module_srl;
			$output = executeQueryArray('module.getModuleGrants', $args);
			if($output->toBool())
			{
				Rhymix\Framework\Cache::set("site_and_module:module_grants:$module_srl", $output, 0, true);
			}
		}
		return $output;
	}

	public static function getModuleFileBox($module_filebox_srl)
	{
		$args = new stdClass();
		$args->module_filebox_srl = $module_filebox_srl;
		return executeQuery('module.getModuleFileBox', $args);
	}

	public static function getModuleFileBoxList()
	{
		$args = new stdClass();
		$args->page = Context::get('page');
		$args->list_count = 5;
		$args->page_count = 5;
		$output = executeQuery('module.getModuleFileBoxList', $args);
		$output = self::unserializeAttributes($output);
		return $output;
	}

	public static function unserializeAttributes($module_filebox_list)
	{
		if(is_array($module_filebox_list->data))
		{
			foreach($module_filebox_list->data as &$item)
			{
				if(empty($item->comment))
				{
					continue;
				}

				$attributes = explode(';', $item->comment);
				foreach($attributes as $attribute)
				{
					$values = explode(':', $attribute);
					if((count($values) % 2) ==1)
					{
						for($i=2;$i<count($values);$i++)
						{
							$values[1].=":".$values[$i];
						}
					}
					$atts[$values[0]]=$values[1];
				}
				$item->attributes = $atts;
				unset($atts);
			}
		}
		return $module_filebox_list;
	}

	public function getFileBoxListHtml()
	{
		$logged_info = Context::get('logged_info');
		if($logged_info->is_admin !='Y' && !$logged_info->is_site_admin)
		{
			return new BaseObject(-1, 'msg_not_permitted');
		}

		$link = parse_url($_SERVER["HTTP_REFERER"]);
		$link_params = explode('&',$link['query']);
		foreach ($link_params as $param)
		{
			$param = explode("=",$param);
			if($param[0] == 'selected_widget') $selected_widget = $param[1];
		}
		if($selected_widget) $widget_info = WidgetModel::getWidgetInfo($selected_widget);
		Context::set('allow_multiple', $widget_info->extra_var->images->allow_multiple);

		$output = self::getModuleFileBoxList();
		Context::set('filebox_list', $output->data);

		$page = Context::get('page');
		if (!$page) $page = 1;
		Context::set('page', $page);
		Context::set('page_navigation', $output->page_navigation);

		$security = new Security();
		$security->encodeHTML('filebox_list..comment', 'filebox_list..attributes.');

		$oTemplate = TemplateHandler::getInstance();
		$html = $oTemplate->compile(RX_BASEDIR . 'modules/module/tpl/', 'filebox_list_html');

		$this->add('html', $html);
	}

	public static function getModuleFileBoxPath($module_filebox_srl)
	{
		return FileController::getStoragePath('filebox', 0, $module_filebox_srl, 0, '', false);
	}

	/**
	 * @brief Return ruleset cache file path
	 * @param module, act
	 */
	public static function getValidatorFilePath($module, $ruleset, $mid=null)
	{
		// load dynamic ruleset xml file
		if(strpos($ruleset, '@') !== false)
		{
			$rulsetFile = str_replace('@', '', $ruleset);
			$xml_file = sprintf('./files/ruleset/%s.xml', $rulsetFile);
			return FileHandler::getRealPath($xml_file);
		}
		else if (strpos($ruleset, '#') !== false)
		{
			$rulsetFile = str_replace('#', '', $ruleset).'.'.$mid;
			$xml_file = sprintf('./files/ruleset/%s.xml', $rulsetFile);
			if(is_readable($xml_file))
				return FileHandler::getRealPath($xml_file);
			else{
				$ruleset = str_replace('#', '', $ruleset);
			}

		}
		// Get a path of the requested module. Return if not exists.
		$class_path = ModuleHandler::getModulePath($module);
		if(!$class_path) return;

		// Check if module.xml exists in the path. Return if not exist
		$xml_file = sprintf("%sruleset/%s.xml", $class_path, $ruleset);
		if(!file_exists($xml_file)) return;

		return $xml_file;
	}

	public function getLangListByLangcodeForAutoComplete()
	{
		$args = new stdClass;
		$args->page = 1; // /< Page
		$args->list_count = 100; // /< the number of posts to display on a single page
		$args->page_count = 5; // /< the number of pages that appear in the page navigation
		$args->sort_index = 'name';
		$args->order_type = 'asc';
		$args->search_keyword = Context::get('search_keyword'); // /< keyword to search*/
		$output = executeQueryArray('module.getLangListByLangcode', $args);

		$list = array();

		if($output->toBool())
		{
			foreach((array)$output->data as $code_info)
			{
				unset($codeInfo);
				$codeInfo = array('name'=>'$user_lang->'.$code_info->name, 'value'=>$code_info->value);
				$list[] = $codeInfo;
			}
		}
		$this->add('results', $list);
	}

	/**
	 * @brief already instance created module list
	 */
	public static function getModuleListByInstance($site_srl = 0, $columnList = array())
	{
		$args = new stdClass();
		$output = executeQueryArray('module.getModuleListByInstance', $args, $columnList);
		return $output;
	}

	public function getLangByLangcode()
	{
		$langCode = Context::get('langCode');
		if (!$langCode) return;

		$langCode = Context::replaceUserLang($langCode);
		$this->add('lang', $langCode);
	}
}
/* End of file module.model.php */
/* Location: ./modules/module/module.model.php */

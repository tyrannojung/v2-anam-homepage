<?php
/* Copyright (C) NAVER <http://www.navercorp.com> */
/**
 * @class  layoutModel
 * @author NAVER (developers@xpressengine.com)
 * @version 0.1
 * Model class of the layout module
 */
class LayoutModel extends Layout
{
	/**
	 * Check user layout temp
	 * @var string
	 */
	var $useUserLayoutTemp = null;

	/**
	 * Get a layout list created in the DB
	 * If you found a new list, it means that the layout list is inserted to the DB
	 *
	 * @param int $site_srl
	 * @param string $layout_type (P : PC, M : Mobile)
	 * @param array $columnList
	 * @return array layout lists in site
	 */
	public static function getLayoutList($site_srl = 0, $layout_type="P", $columnList = array())
	{
		$args = new stdClass();
		$args->layout_type = $layout_type;
		$output = executeQueryArray('layout.getLayoutList', $args, $columnList);

		foreach($output->data as $no => &$val)
		{
			if(!self::isExistsLayoutFile($val->layout, $layout_type))
			{
				unset($output->data[$no]);
			}
		}

		$oLayoutAdminModel = LayoutAdminModel::getInstance();
		$siteDefaultLayoutSrl = $oLayoutAdminModel->getSiteDefaultLayout($layout_type);
		if($siteDefaultLayoutSrl)
		{
			$siteDefaultLayoutInfo = self::getLayout($siteDefaultLayoutSrl);
			$siteDefaultLayoutInfo->layout_srl = -1;
			$siteDefaultLayoutInfo->layout = $siteDefaultLayoutInfo->title;
			$siteDefaultLayoutInfo->title = lang('use_site_default_layout');

			array_unshift($output->data, $siteDefaultLayoutInfo);
		}
		if ($layout_type === 'M')
		{
			$responsiveLayoutInfo = new stdClass();
			$responsiveLayoutInfo->layout_srl = -2;
			$responsiveLayoutInfo->layout = '';
			$responsiveLayoutInfo->title = lang('use_responsive_pc_layout');
			array_unshift($output->data, $responsiveLayoutInfo);
		}

		return $output->data;
	}

	/**
	 * Get the list layout instance with thumbnail link. for setting design.
	 *
	 * @return void
	 */
	public function getLayoutInstanceListForJSONP()
	{
		$layoutType = Context::get('layout_type');

		$layoutList = $this->getLayoutInstanceList(0, $layoutType);
		$thumbs = array();

		foreach($layoutList as $key => $val)
		{
			if(!empty($thumbs[$val->layouts ?? '']))
			{
				$val->thumbnail = $thumbs[$val->layouts];
				continue;
			}

			$token = explode('|@|', $val->layout);
			if(count($token) == 2)
			{
				$thumbnailPath = sprintf('./themes/%s/layouts/%s/thumbnail.png' , $token[0], $token[1]);
			}
			else if($layoutType == 'M')
			{
				$thumbnailPath = sprintf('./m.layouts/%s/thumbnail.png' , $val->layout);
			}
			else
			{
				$thumbnailPath = sprintf('./layouts/%s/thumbnail.png' , $val->layout);
			}
			if(is_readable($thumbnailPath))
			{
				$val->thumbnail = $thumbnailPath;
			}
			else
			{
				$val->thumbnail = sprintf('./modules/layout/tpl/img/noThumbnail.png');
			}
			$thumbs[$val->layout] = $val->thumbnail;
		}
		$this->add('layout_list', $layoutList);
	}

	/**
	 * Get layout instance list
	 * @param int $siteSrl
	 * @param string $layoutType (P : PC, M : Mobile)
	 * @param string $layout name of layout
	 * @param array $columnList
	 * @return array layout lists in site
	 */
	public static function getLayoutInstanceList($siteSrl = 0, $layoutType = 'P', $layout = null, $columnList = array())
	{
		if ($columnList && !isset($columnList['layout_type']))
		{
			$columnList[] = 'layout_type';
		}
		$args = new stdClass();
		$args->layout_type = $layoutType === 'P' ? 'P' : 'P,M';
		$args->layout = $layout;
		$output = executeQueryArray('layout.getLayoutList', $args, $columnList);

		// Create instance name list
		$instanceList = array();
		if(is_array($output->data))
		{
			foreach($output->data as $no => $iInfo)
			{
				if (file_exists(\RX_BASEDIR . 'files/faceOff/' . getNumberingPath($iInfo->layout_srl) . 'layout.html') ||
					file_exists(\RX_BASEDIR . 'files/faceOff/' . getNumberingPath($iInfo->layout_srl) . 'layout.css'))
				{
					$iInfo->is_edited = true;
				}
				else
				{
					$iInfo->is_edited = false;
				}

				if(self::isExistsLayoutFile($iInfo->layout, $iInfo->layout_type) && $iInfo->layout_type === $layoutType)
				{
					$instanceList[] = $iInfo->layout;
				}
				else
				{
					unset($output->data[$no]);
				}
			}
		}

		// Create downloaded name list
		$downloadedList = array();
		$titleList = array();
		$_downloadedList = self::getDownloadedLayoutList($layoutType);
		if(is_array($_downloadedList))
		{
			foreach($_downloadedList as $dLayoutInfo)
			{
				$downloadedList[$dLayoutInfo->layout] = $dLayoutInfo->layout;
				$titleList[$dLayoutInfo->layout] = $dLayoutInfo->title;
			}
		}

		if($layout)
		{
			if(count($instanceList) < 1 && $downloadedList[$layout])
			{
				$insertArgs = new stdClass();
				$insertArgs->layout_srl = getNextSequence();
				$insertArgs->layout = $layout;
				$insertArgs->title = $titleList[$layout];
				$insertArgs->layout_type = $layoutType;

				$oLayoutAdminController = LayoutAdminController::getInstance();
				$oLayoutAdminController->insertLayout($insertArgs);
				$isCreateInstance = TRUE;
			}
		}
		else
		{
			// Get downloaded name list have no instance
			$noInstanceList = array_diff($downloadedList, $instanceList);
			foreach($noInstanceList as $layoutName)
			{
				$insertArgs = new stdClass();
				$insertArgs->layout_srl = getNextSequence();
				$insertArgs->layout = $layoutName;
				$insertArgs->title = $titleList[$layoutName];
				$insertArgs->layout_type = $layoutType;

				$oLayoutAdminController = LayoutAdminController::getInstance();
				$oLayoutAdminController->insertLayout($insertArgs);
				$isCreateInstance = TRUE;
			}
		}

		// If create layout instance, reload instance list
		if($isCreateInstance)
		{
			$output = executeQueryArray('layout.getLayoutList', $args, $columnList);

			if(is_array($output->data))
			{
				foreach($output->data as $no => $iInfo)
				{
					if(!self::isExistsLayoutFile($iInfo->layout, $layoutType))
					{
						unset($output->data[$no]);
					}
				}
			}
		}

		return $output->data;
	}

	/**
	 * If exists layout file returns true
	 *
	 * @param string $layout layout name
	 * @param string $layoutType P or M
	 * @return bool
	 */
	public static function isExistsLayoutFile($layout, $layoutType)
	{
		//TODO If remove a support themes, remove this codes also.
		if($layoutType == 'P')
		{
			$pathPrefix = RX_BASEDIR . 'layouts/';
			$themePathFormat = RX_BASEDIR . 'themes/%s/layouts/%s';
		}
		else
		{
			$pathPrefix = RX_BASEDIR . 'm.layouts/';
			$themePathFormat = RX_BASEDIR . 'themes/%s/m.layouts/%s';
		}

		if(strpos($layout, '|@|') !== FALSE)
		{
			list($themeName, $layoutName) = explode('|@|', $layout);
			$path = sprintf($themePathFormat, $themeName, $layoutName);
		}
		else
		{
			$path = $pathPrefix . $layout;
		}

		if (file_exists($path . '/layout.html') && is_readable($path . '/layout.html'))
		{
			return true;
		}
		elseif (file_exists($path . '/layout.blade.php') && is_readable($path . '/layout.blade.php'))
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Get one of layout information created in the DB
	 * Return DB info + XML info of the generated layout
	 * @param int $layout_srl
	 * @param bool $use_cache
	 * @return object info of layout
	 */
	public static function getLayout($layout_srl, $use_cache = true)
	{
		// Get information from cache
		$layout_info = Rhymix\Framework\Cache::get("layout:$layout_srl");
		if ($use_cache && $layout_info !== null)
		{
			return $layout_info;
		}

		// Get information from the DB
		$args = new stdClass();
		$args->layout_srl = $layout_srl;
		$output = executeQuery('layout.getLayout', $args);
		if (!$output->data)
		{
			return;
		}

		// Return xml file informaton after listing up the layout and extra_vars
		$layout = $output->data->layout;
		$layout_info = self::getLayoutInfo($layout, $output->data, $output->data->layout_type);
		if (!$layout_info)
		{
			return;
		}

		// Check if layout has been edited
		if (file_exists(\RX_BASEDIR . 'files/faceOff/' . getNumberingPath($layout_srl) . 'layout.html') ||
			file_exists(\RX_BASEDIR . 'files/faceOff/' . getNumberingPath($layout_srl) . 'layout.css'))
		{
			$layout_info->is_edited = true;
		}
		else
		{
			$layout_info->is_edited = false;
		}

		// Store in cache
		Rhymix\Framework\Cache::set("layout:$layout_srl", $layout_info);
		return $layout_info;
	}

	public static function getLayoutRawData($layout_srl, $columnList = array())
	{
		$args = new stdClass();
		$args->layout_srl = $layout_srl;
		$output = executeQuery('layout.getLayout', $args, $columnList);
		if(!$output->toBool())
		{
			return;
		}

		return $output->data;
	}

	/**
	 * Get a layout path
	 * @param string $layout_name
	 * @param string $layout_type (P : PC, M : Mobile)
	 * @return string path of layout
	 */
	public function getLayoutPath($layout_name = "", $layout_type = "P")
	{
		$layout_parse = explode('|@|', $layout_name ?? '');
		if(count($layout_parse) > 1)
		{
			$class_path = './themes/'.$layout_parse[0].'/layouts/'.$layout_parse[1].'/';
		}
		else if($layout_name == 'faceoff')
		{
			$class_path = './modules/layout/faceoff/';
		}
		else if($layout_type == "M")
		{
			$class_path = sprintf("./m.layouts/%s/", $layout_name);
		}
		else
		{
			$class_path = sprintf('./layouts/%s/', $layout_name);
		}
		if(is_dir($class_path)) return $class_path;
		return "";
	}

	/**
	 * Get a type and information of the layout
	 * A type of downloaded layout
	 * @param string $layout_type (P : PC, M : Mobile)
	 * @param boolean $withAutoinstallInfo
	 * @return array info of layout
	 */
	public static function getDownloadedLayoutList($layout_type = "P", $withAutoinstallInfo = false)
	{
		if ($withAutoinstallInfo)
		{
			$oAutoinstallModel = AutoinstallModel::getInstance();
		}

		// Get a list of downloaded layout and installed layout
		$searched_list = self::_getInstalledLayoutDirectories($layout_type);
		$searched_count = count($searched_list);
		if(!$searched_count) return;

		// natcasesort($searched_list);
		// Return information for looping searched list of layouts
		$list = array();
		for($i=0;$i<$searched_count;$i++)
		{
			// Name of the layout
			$layout = $searched_list[$i];
			// Get information of the layout
			$layout_info = self::getLayoutInfo($layout, null, $layout_type);

			if(!$layout_info)
			{
				continue;
			}

			if($withAutoinstallInfo && false)
			{
				// get easyinstall remove url
				$packageSrl = $oAutoinstallModel->getPackageSrlByPath($layout_info->path);
				$layout_info->remove_url = $oAutoinstallModel->getRemoveUrlByPackageSrl($packageSrl);

				// get easyinstall need update
				$package = $oAutoinstallModel->getInstalledPackages($packageSrl);
				$layout_info->need_update = $package[$packageSrl]->need_update;

				// get easyinstall update url
				if($layout_info->need_update)
				{
					$layout_info->update_url = $oAutoinstallModel->getUpdateUrlByPackageSrl($packageSrl);
				}
			}
			$list[] = $layout_info;
		}

		usort($list, array(self::class, 'sortLayoutByTitle'));
		return $list;
	}

	/**
	 * Sort layout by title
	 */
	public static function sortLayoutByTitle($a, $b)
	{
		if(!$a->title)
		{
			$a->title = $a->layout;
		}

		if(!$b->title)
		{
			$b->title = $b->layout;
		}

		$aTitle = strtolower($a->title ?? '');
		$bTitle = strtolower($b->title ?? '');

		if($aTitle == $bTitle)
		{
			return 0;
		}

		return ($aTitle < $bTitle) ? -1 : 1;
	}

	/**
	 * Get a count of layout
	 * @param string $layoutType (P : PC, M : Mobile)
	 * @return int
	 */
	public static function getInstalledLayoutCount($layoutType = 'P')
	{
		$searchedList = self::_getInstalledLayoutDirectories($layoutType);
		return  count($searchedList);
	}

	/**
	 * Get list of layouts directory
	 * @param string $layoutType (P : PC, M : Mobile)
	 * @return array
	 */
	public static function _getInstalledLayoutDirectories($layoutType = 'P')
	{
		if($layoutType == 'M')
		{
			$directory = './m.layouts';
			$globalValueKey = 'MOBILE_LAYOUT_DIRECTOIES';
		}
		else
		{
			$directory = './layouts';
			$globalValueKey = 'PC_LAYOUT_DIRECTORIES';
		}

		if(!empty($GLOBALS[$globalValueKey]))
		{
			return $GLOBALS[$globalValueKey];
		}

		$searchedList = FileHandler::readDir($directory);
		if (!$searchedList) $searchedList = array();
		$GLOBALS[$globalValueKey] = $searchedList;

		return $searchedList;
	}

	/**
	 * Get information by reading conf/info.xml in the module
	 * It uses caching to reduce time for xml parsing ..
	 * @param string $layout
	 * @param object $info
	 * @param string $layoutType (P : PC, M : Mobile)
	 * @return object info of layout
	 */
	public static function getLayoutInfo($layout, $info = null, $layout_type = "P")
	{
		if($info)
		{
			$layout_title = $info->title;
			$layout = $info->layout;
			$layout_srl = $info->layout_srl;
			$site_srl = $info->site_srl;
			$vars = $info->extra_vars ? unserialize($info->extra_vars) : null;

			if($info->module_srl)
			{
				$layout_path = preg_replace('/([a-zA-Z0-9\_\.]+)(\.html)$/','',$info->layout_path);
				$xml_file = sprintf('%sskin.xml', $layout_path);
			}
		}
		else
		{
			$layout_title = $layout_srl = null;
			$site_srl = 0;
			$vars = new stdClass;
		}

		// Get a path of the requested module. Return if not exists.
		if(!isset($layout_path))
		{
			$layout_path = self::getInstance()->getLayoutPath($layout, $layout_type);
		}
		if(!is_dir($layout_path))
		{
			return;
		}

		// Read the xml file for module skin information
		if(!isset($xml_file))
		{
			$xml_file = sprintf("%sconf/info.xml", $layout_path);
		}
		if(!file_exists($xml_file))
		{
			$layout_info = new stdClass;
			$layout_info->title = $layout;
			$layout_info->layout = $layout;
			$layout_info->path = $layout_path;
			$layout_info->layout_title = $layout_title;
			if(empty($layout_info->layout_type))
			{
				$layout_info->layout_type =  $layout_type;
			}
			return $layout_info;
		}

		// Include the cache file if it is valid and then return $layout_info variable
		if(empty($layout_srl))
		{
			$cache_file = self::getLayoutCache($layout, Context::getLangType(), $layout_type);
		}
		else
		{
			$cache_file = self::getUserLayoutCache($layout_srl, Context::getLangType());
		}

		if(file_exists($cache_file) && filemtime($cache_file) > filemtime($xml_file))
		{
			// Read cache file in a way that is compatible with the old format.
			// The old format sets $layout_info directly, while the new format returns an object.
			$layout_info = new stdClass;
			if (is_object($output = include($cache_file)))
			{
				$layout_info = $output;
			}
			if (empty($layout_info->layout))
			{
				return;
			}

			if ($layout_info->extra_var && $vars)
			{
				foreach($vars as $key => $value)
				{
					if(!isset($layout_info->extra_var->{$key}) && !isset($layout_info->{$key}))
					{
						$layout_info->{$key} = $value;
					}
				}
			}
			return $layout_info;
		}

		// If no cache file exists, parse the xml and then return the variable.
		$xml_info = Rhymix\Framework\Parsers\LayoutInfoParser::loadXML($xml_file, $layout, $layout_path);
		if (!$xml_info)
		{
			return;
		}

		// Fill in user configuration
		foreach ($xml_info->extra_var ?: [] as $key => $value)
		{
			if (isset($vars->{$key}))
			{
				$value->value = $vars->{$key};
			}
		}
		foreach ($xml_info->menu ?: [] as $key => $value)
		{
			if (isset($vars->{$key}) && $vars->{$key})
			{
				$value->menu_srl = $vars->{$key};
				$value->xml_file = sprintf('./files/cache/menu/%s.xml.php', $vars->{$key});
				$value->php_file = sprintf('./files/cache/menu/%s.php', $vars->{$key});
			}
		}

		$layout_config = ModuleModel::getModulePartConfig('layout', $layout_srl);
		$xml_info->header_script = trim($layout_config->header_script ?? '');
		$xml_info->layout_srl = $layout_srl;
		$xml_info->layout_title = $layout_title;

		Rhymix\Framework\Storage::writePHPData($cache_file, $xml_info, null, false);
		return $xml_info;
	}

	/**
	 * Return a list of images which are uploaded on the layout setting page
	 * @param int $layout_srl
	 * @return array image list in layout
	 */
	public static function getUserLayoutImageList($layout_srl)
	{
		return FileHandler::readDir(self::getUserLayoutImagePath($layout_srl));
	}

	/**
	 * Get ini configurations and make them an array.
	 * @param int $layout_srl
	 * @param string $layout_name
	 * @return array
	 */
	public function getUserLayoutIniConfig($layout_srl, $layout_name=null)
	{
		$file = self::getUserLayoutIni($layout_srl);
		if($layout_name && FileHandler::exists($file) === FALSE)
		{
			FileHandler::copyFile($this->getDefaultLayoutIni($layout_name), $this->getUserLayoutIni($layout_srl));
		}

		return FileHandler::readIniFile($file);
	}

	/**
	 * get user layout path
	 * @param int $layout_srl
	 * @return string
	 */
	public static function getUserLayoutPath($layout_srl)
	{
		return sprintf("./files/faceOff/%s", getNumberingPath($layout_srl,3));
	}

	/**
	 * get user layout image path
	 * @param int $layout_srl
	 * @return string
	 */
	public static function getUserLayoutImagePath($layout_srl)
	{
		return self::getUserLayoutPath($layout_srl). 'images/';
	}

	/**
	 * css which is set by an administrator on the layout setting page
	 * @param int $layout_srl
	 * @return string
	 */
	public static function getUserLayoutCss($layout_srl)
	{
		return self::getUserLayoutPath($layout_srl). 'layout.css';
	}

	/**
	 * Import faceoff css from css module handler
	 * @param int $layout_srl
	 * @return string
	 */
	public function getUserLayoutFaceOffCss($layout_srl)
	{
		if($this->useUserLayoutTemp == 'temp') return;
		return $this->_getUserLayoutFaceOffCss($layout_srl);
	}

	/**
	 * Import faceoff css from css module handler
	 * @param int $layout_srl
	 * @return string
	 */
	public function _getUserLayoutFaceOffCss($layout_srl)
	{
		return self::getUserLayoutPath($layout_srl). 'faceoff.css';
	}

	/**
	 * get user layout tmp html
	 * @param int $layout_srl
	 * @return string
	 */
	public static function getUserLayoutTempFaceOffCss($layout_srl)
	{
		return self::getUserLayoutPath($layout_srl). 'tmp.faceoff.css';
	}

	/**
	 * user layout html
	 * @param int $layout_srl
	 * @return string
	 */
	public function getUserLayoutHtml($layout_srl)
	{
		$src = self::getUserLayoutPath($layout_srl). 'layout.html';
		if($this->useUserLayoutTemp == 'temp')
		{
			$temp = $this->getUserLayoutTempHtml($layout_srl);
			if(FileHandler::exists($temp) === FALSE) FileHandler::copyFile($src,$temp);
			return $temp;
		}

		return $src;
	}

	/**
	 * user layout tmp html
	 * @param int $layout_srl
	 * @return string
	 */
	public static function getUserLayoutTempHtml($layout_srl)
	{
		return self::getUserLayoutPath($layout_srl). 'tmp.layout.html';
	}

	/**
	 * user layout ini
	 * @param int $layout_srl
	 * @return string
	 */
	public function getUserLayoutIni($layout_srl)
	{
		$src = self::getUserLayoutPath($layout_srl). 'layout.ini';
		if($this->useUserLayoutTemp == 'temp')
		{
			$temp = self::getUserLayoutTempIni($layout_srl);
			if(!file_exists(FileHandler::getRealPath($temp))) FileHandler::copyFile($src,$temp);
			return $temp;
		}

		return $src;
	}

	/**
	 * user layout tmp ini
	 * @param int $layout_srl
	 * @return string
	 */
	public static function getUserLayoutTempIni($layout_srl)
	{
		return self::getUserLayoutPath($layout_srl). 'tmp.layout.ini';
	}

	/**
	 * user layout cache
	 * TODO It may need to remove the file itself
	 * @param int $layout_srl
	 * @param string $lang_type
	 * @return string
	 */
	public static function getUserLayoutCache($layout_srl,$lang_type)
	{
		return self::getUserLayoutPath($layout_srl). "{$lang_type}.cache.php";
	}

	/**
	 * layout cache
	 * @param int $layout_srl
	 * @param string $lang_type
	 * @return string
	 */
	public static function getLayoutCache($layout_name,$lang_type,$layout_type='P')
	{
		if($layout_type=='P')
		{
			return sprintf("%sfiles/cache/layout/%s.%s.cache.php", RX_BASEDIR, $layout_name,$lang_type);
		}
		else
		{
			return sprintf("%sfiles/cache/layout/m.%s.%s.cache.php", RX_BASEDIR, $layout_name,$lang_type);
		}
	}

	/**
	 * default layout ini to prevent arbitrary changes by a user
	 * @param string $layout_name
	 * @return string
	 */
	public function getDefaultLayoutIni($layout_name)
	{
		return $this->getDefaultLayoutPath($layout_name). 'layout.ini';
	}

	/**
	 * default layout html to prevent arbitrary changes by a user
	 * @param string $layout_name
	 * @return string
	 */
	public function getDefaultLayoutHtml($layout_name)
	{
		return $this->getDefaultLayoutPath($layout_name). 'layout.html';
	}

	/**
	 * default layout css to prevent arbitrary changes by a user
	 * @param string $layout_name
	 * @return string
	 */
	public function getDefaultLayoutCss($layout_name)
	{
		return $this->getDefaultLayoutPath($layout_name). 'css/layout.css';
	}

	/**
	 * default layout path to prevent arbitrary changes by a user
	 * @deprecated
	 * @return string
	 */
	public function getDefaultLayoutPath()
	{
		return "./modules/layout/faceoff/";
	}

	/**
	 * faceoff is
	 * @param string $layout_name
	 * @return boolean (true : faceoff, false : layout)
	 */
	public function useDefaultLayout($layout_name)
	{
		$info = $this->getLayoutInfo($layout_name);
		return ($info->type == 'faceoff');
	}

	/**
	 * Set user layout as temporary save mode
	 * @param string $flag (default 'temp')
	 * @return void
	 */
	public function setUseUserLayoutTemp($flag='temp')
	{
		$this->useUserLayoutTemp = $flag;
	}

	/**
	 * Temp file list for User Layout
	 * @param int $layout_srl
	 * @return array temp files info
	 */
	public function getUserLayoutTempFileList($layout_srl)
	{
		return array(
				$this->getUserLayoutTempHtml($layout_srl),
				$this->getUserLayoutTempFaceOffCss($layout_srl),
				$this->getUserLayoutTempIni($layout_srl)
				);
	}

	/**
	 * Saved file list for User Layout
	 * @param int $layout_srl
	 * @return array files info
	 */
	public function getUserLayoutFileList($layout_srl)
	{
		$file_list = array(
			basename($this->getUserLayoutHtml($layout_srl)),
			basename($this->getUserLayoutFaceOffCss($layout_srl)),
			basename($this->getUserLayoutIni($layout_srl)),
			basename($this->getUserLayoutCss($layout_srl))
		);

		$image_path = $this->getUserLayoutImagePath($layout_srl);
		$image_list = FileHandler::readDir($image_path,'/\.(?:jpg|jpeg|gif|bmp|png)$/i');

		foreach($image_list as $image)
		{
			$file_list[] = 'images/' . $image;
		}
		return $file_list;
	}

	/**
	 * faceOff related services for the operation run out
	 * @deprecated
	 * @param object $layout_info
	 * @return void
	 */
	public function doActivateFaceOff(&$layout_info)
	{
		$layout_info->faceoff_ini_config = $this->getUserLayoutIniConfig($layout_info->layout_srl, $layout_info->layout);
		// faceoff layout CSS
		Context::addCSSFile($this->getDefaultLayoutCss($layout_info->layout));
		// CSS generated in the layout manager
		$faceoff_layout_css = $this->getUserLayoutFaceOffCss($layout_info->layout_srl);
		if($faceoff_layout_css) Context::addCSSFile($faceoff_layout_css);
		// CSS output for the widget
		Context::loadFile($this->module_path.'/tpl/css/widget.css', true);
		if($layout_info->extra_var->colorset->value == 'black') Context::loadFile($this->module_path.'/tpl/css/widget@black.css', true);
		else Context::loadFile($this->module_path.'/tpl/css/widget@white.css', true);
		// Different page displayed upon user's permission
		$logged_info = Context::get('logged_info');
		// Display edit button for faceoff layout
		if(Context::get('module')!='admin' && strpos(Context::get('act'),'Admin')===false && ($logged_info->is_admin == 'Y' || $logged_info->is_site_admin))
		{
			Context::addHtmlFooter('<div class="faceOffManager" style="height: 23px; position: fixed; right: 3px; top: 3px;"><a href="'.getUrl('','mid',Context::get('mid'),'act','dispLayoutAdminLayoutModify','delete_tmp','Y').'">'.lang('cmd_layout_edit').'</a></div>');
		}
		// Display menu when editing the faceOff page
		if(Context::get('act')=='dispLayoutAdminLayoutModify' && ($logged_info->is_admin == 'Y' || $logged_info->is_site_admin))
		{
			$oTemplate = TemplateHandler::getInstance();
			Context::addBodyHeader($oTemplate->compile($this->module_path.'/tpl', 'faceoff_layout_menu'));
		}
	}
}
/* End of file layout.model.php */
/* Location: ./modules/layout/layout.model.php */

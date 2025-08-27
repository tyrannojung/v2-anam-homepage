<?php
/* Copyright (C) NAVER <http://www.navercorp.com> */
/**
 * @class  widgetController
 * @author NAVER (developers@xpressengine.com)
 * @brief Controller class for widget modules
 */
class WidgetController extends Widget
{
	// The results are not widget modify/delete and where to use the flag for
	// layout_javascript_mode include all the results into the javascript mode Sikkim
	var $javascript_mode = false;
	var $layout_javascript_mode = false;

	/**
	 * @brief Initialization
	 */
	function init()
	{
	}

	/**
	 * @brief Selected photos - the return of the skin-color three
	 */
	function procWidgetGetColorsetList()
	{
		$widget = Context::get('selected_widget');
		$skin = Context::get('skin');

		$path = sprintf('./widgets/%s/', $widget);
		$skin_info = ModuleModel::loadSkinInfo($path, $skin);

		$colorset_list = [];
		foreach ($skin_info->colorset ?: [] as $colorset)
		{
			$colorset_list[] = sprintf('%s|@|%s', $colorset->name, $colorset->title);
		}
		if (count($colorset_list))
		{
			$colorsets = implode("\n", $colorset_list);
		}

		$this->add('colorset_list', $colorsets);
	}

	/**
	 * @brief Return the generated code of the widget
	 */
	function procWidgetGenerateCode()
	{
		$widget = Context::get('selected_widget');
		if (!$widget)
		{
			throw new Rhymix\Framework\Exceptions\InvalidRequest;
		}
		if (!Context::get('skin'))
		{
			throw new Rhymix\Framework\Exception('msg_widget_skin_is_null');
		}

		$attribute = $this->arrangeWidgetVars($widget, Context::getRequestVars(), $vars);

		$widget_code = sprintf('<img class="zbxe_widget_output" widget="%s" %s />', $widget, implode(' ',$attribute));
		$this->add('widget_code', $widget_code);
	}

	/**
	 * @brief Edit page request for the creation of the widget code
	 */
	function procWidgetGenerateCodeInPage()
	{
		$widget = Context::get('selected_widget');
		if (!$widget)
		{
			throw new Rhymix\Framework\Exceptions\InvalidRequest;
		}
		if (!in_array($widget,array('widgetBox','widgetContent')) && !Context::get('skin'))
		{
			throw new Rhymix\Framework\Exception('msg_widget_skin_is_null');
		}

		$this->arrangeWidgetVars($widget, Context::getRequestVars(), $vars);

		// Wanted results
		$widget_code = $this->execute($widget, $vars, true, false);
		$widget_code = Context::replaceUserLang($widget_code);
		$this->add('widget_code', $widget_code);
	}

	/**
	 * @brief Upload widget styles
	 */
	function procWidgetStyleExtraImageUpload()
	{
		$attribute = $this->arrangeWidgetVars($widget, Context::getRequestVars(), $vars);

		$this->setLayoutPath('./common/tpl');
		$this->setLayoutFile('default_layout.html');
		$this->setTemplatePath($this->module_path.'tpl');
		$this->setTemplateFile("top_refresh.html");
	}

	/**
	 * @brief Add content widget
	 */
	function procWidgetInsertDocument()
	{
		// Variable Wanted
		$module_srl = Context::get('module_srl');
		$document_srl = Context::get('document_srl');
		$content = Context::get('content');
		$editor_sequence = Context::get('editor_sequence');

		$err = 0;
		$layout_info = LayoutModel::getLayout($module_srl);
		if (!$layout_info || $layout_info->type != 'faceoff')
		{
			$err++;
		}

		// Destination Information Wanted page module
		$columnList = array('module_srl', 'module');
		$page_info = ModuleModel::getModuleInfoByModuleSrl($module_srl, $columnList);
		if (!$page_info->module_srl || $page_info->module != 'page')
		{
			$err++;
		}
		if ($err > 1)
		{
			throw new Rhymix\Framework\Exceptions\InvalidRequest;
		}

		// Check permissions
		$logged_info = Context::get('logged_info');
		if (!$logged_info->member_srl)
		{
			throw new Rhymix\Framework\Exceptions\NotPermitted;
		}
		$module_grant = ModuleModel::getGrant($page_info, $logged_info);
		if (!$module_grant->manager)
		{
			throw new Rhymix\Framework\Exceptions\NotPermitted;
		}

		// Enter post
		$obj = new stdClass();
		$obj->module_srl = $module_srl;
		$obj->content = $content;
		$obj->document_srl = $document_srl;
		$obj->use_editor = 'Y';

		$oDocument = DocumentModel::getDocument($obj->document_srl);
		$oDocumentController = DocumentController::getInstance();
		if($oDocument->isExists() && $oDocument->document_srl == $obj->document_srl)
		{
			$output = $oDocumentController->updateDocument($oDocument, $obj);
		}
		else
		{
			$output = $oDocumentController->insertDocument($obj);
			$obj->document_srl = $output->get('document_srl');
		}

		// Stop when an error occurs
		if (!$output->toBool())
		{
			return $output;
		}

		// Return results
		$this->add('document_srl', $obj->document_srl);
	}

	/**
	 * @brief Copy the content widget
	 */
	function procWidgetCopyDocument()
	{
		// Variable Wanted
		$document_srl = Context::get('document_srl');

		$oDocument = DocumentModel::getDocument($document_srl);
		if (!$oDocument->isExists())
		{
			throw new Rhymix\Framework\Exceptions\InvalidRequest;
		}
		$module_srl = $oDocument->get('module_srl');

		// Destination Information Wanted page module
		$columnList = array('module_srl', 'module');
		$page_info = ModuleModel::getModuleInfoByModuleSrl($module_srl, $columnList);
		if(!$page_info->module_srl || $page_info->module != 'page') throw new Rhymix\Framework\Exceptions\InvalidRequest;

		// Check permissions
		$logged_info = Context::get('logged_info');
		if (!$logged_info->member_srl)
		{
			throw new Rhymix\Framework\Exceptions\NotPermitted;
		}
		$module_grant = ModuleModel::getGrant($page_info, $logged_info);
		if (!$module_grant->manager)
		{
			throw new Rhymix\Framework\Exceptions\NotPermitted;
		}

		$oDocumentAdminController = DocumentAdminController::getInstance();
		$output = $oDocumentAdminController->copyDocumentModule(array($oDocument->get('document_srl')), $oDocument->get('module_srl'),0);
		if (!$output->toBool())
		{
			return $output;
		}

		// Return results
		$copied_srls = $output->get('copied_srls');
		$this->add('document_srl', $copied_srls[$oDocument->get('document_srl')]);
	}

	/**
	 * @brief Deleting widgets
	 */
	function procWidgetDeleteDocument()
	{
		// Variable Wanted
		$document_srl = Context::get('document_srl');
		$oDocument = DocumentModel::getDocument($document_srl);
		if (!$oDocument->isExists())
		{
			return;
		}
		$module_srl = $oDocument->get('module_srl');

		// Destination Information Wanted page module
		$page_info = ModuleModel::getModuleInfoByModuleSrl($module_srl);
		if (!$page_info->module_srl || $page_info->module != 'page')
		{
			throw new Rhymix\Framework\Exceptions\InvalidRequest;
		}

		// Check permissions
		$logged_info = Context::get('logged_info');
		if (!$logged_info->member_srl)
		{
			throw new Rhymix\Framework\Exceptions\NotPermitted;
		}
		$module_grant = ModuleModel::getGrant($page_info, $logged_info);
		if (!$module_grant->manager)
		{
			throw new Rhymix\Framework\Exceptions\NotPermitted;
		}

		$oDocumentController = DocumentController::getInstance();
		$output = $oDocumentController->deleteDocument($oDocument->get('document_srl'));
		if (!$output->toBool())
		{
			return $output;
		}
	}

	/**
	 * @brief Modify the code in Javascript widget/Javascript edit mode for dragging and converted to
	 */
	function setWidgetCodeInJavascriptMode()
	{
		$this->layout_javascript_mode = true;
	}

	/**
	 * @brief Widget code compiles and prints the information to trigger
	 * display:: before invoked in
	 */
	function triggerWidgetCompile(&$content)
	{
		if(Context::getResponseMethod()!='HTML') return;
		$content = $this->transWidgetCode($content, $this->layout_javascript_mode);
	}

	/**
	 * @breif By converting the specific content of the widget tag return
	 */
	function transWidgetCode($content, $javascript_mode = false, $isReplaceLangCode = true)
	{
		// Changing user-defined language
		if($isReplaceLangCode)
		{
			$content = Context::replaceUserLang($content);
		}

		// Check whether to include information about editing
		$this->javascript_mode = $javascript_mode;

		// Widget code box change
		$content = preg_replace_callback('!<div([^>]*)widget=([^>]*?)><div><div>((<img.*?>)*)!is', array($this, 'transWidgetBox'), $content);

		// Widget code information change
		$content = preg_replace_callback('!<img([^>]*)widget=([^>]*?)>!is', array($this, 'transWidget'), $content);

		return $content;
	}

	/**
	 * @brief Widget code with the actual code changes
	 */
	function transWidget($matches)
	{
		$xml = simplexml_load_string(trim($matches[0]));
		if ($xml === false)
		{
			return '<div>Invalid XML in widget code.</div>';
		}

		$vars = new stdClass;
		foreach ($xml->img ? $xml->img->attributes() : $xml->attributes() as $key => $val)
		{
			$vars->{$key} = strval($val);
		}

		$widget = $vars->widget;
		if (!$widget)
		{
			return $matches[0];
		}
		unset($vars->widget);

		return $this->execute($widget, $vars, $this->javascript_mode);
	}

	/**
	 * @brief Widget box with the actual code changes
	 */
	function transWidgetBox($matches)
	{
		$buff = preg_replace('/<div><div>(.*)$/i','</div>', $matches[0]);
		$xml = simplexml_load_string(trim($buff));
		$args = new stdClass;
		foreach ($xml->div ? $xml->div->attributes() : $xml->attributes() as $key => $val)
		{
			$args->{$key} = strval($val);
		}

		$widget = $args->widget ?? null;
		if(!$widget)
		{
			return $matches[0];
		}

		$args->widgetbox_content = $matches[3];
		unset($vars->widget);

		return $this->execute($widget, $args, $this->javascript_mode);
	}

	/**
	 * @brief Re-create specific content within a widget
	 * Widget on the page and create cache file in the module using
	 */
	function recompileWidget($content)
	{
		// Language in bringing
		$lang_list = Context::get('lang_supported');

		// Bringing widget cache sequence
		preg_match_all('!<img([^\>]*)widget=([^\>]*?)\>!is', $content, $matches);

		foreach ($matches[0] as $buff)
		{
			$xml = simplexml_load_string(trim($buff));
			if ($xml === false)
			{
				continue;
			}

			$args = new stdClass;
			foreach ($xml->img ? $xml->img->attributes() : $xml->attributes() as $key => $val)
			{
				$args->{$key} = strval($val);
			}

			$widget = $args->widget ?? null;
			if(!$args || !$widget || empty($args->widget_cache))
			{
				continue;
			}

			$args->widget_sequence = $args->widget_sequence ?? 0;
			$args->colorset = $args->colorset ?? '';

			foreach ($lang_list as $lang_type => $val)
			{
				$this->getCache($widget, $args, $lang_type, true);
			}
		}
	}

	/**
	 * @brief Widget cache handling
	 */
	function getCache($widget, $args, $lang_type = null, $ignore_cache = false, $override_sequence = false)
	{
		// Use the current language if not otherwise specified
		if (!$lang_type)
		{
			$lang_type = Context::getLangType();
		}

		// Fix the widget sequence if it is missing
		$widget_sequence = $override_sequence ?: $args->widget_sequence;
		if (!$widget_sequence)
		{
			$widget_sequence = sha1(json_encode($args));
		}

		// Set the widget cache duration
		$widget_cache = $args->widget_cache;
		if (preg_match('/^([0-9\.]+)([smhd])$/i', $widget_cache, $matches))
		{
			$multipliers = array('s' => 1, 'm' => 60, 'h' => 3600, 'd' => 86400);
			$widget_cache = intval(floatval($matches[1]) * $multipliers[strtolower($matches[2])]);
		}
		else
		{
			$widget_cache = intval(floatval($widget_cache) * 60);
		}

		// If widget cache is disabled, just execute the widget and return the result.
		if(!$ignore_cache && !$widget_cache)
		{
			$oWidget = $this->getWidgetObject($widget);
			if (!$oWidget || !method_exists($oWidget, 'proc'))
			{
				return;
			}

			foreach (WidgetModel::getWidgetInfo($widget)->extra_var ?? [] as $key => $val)
			{
				if (!isset($args->{$key}))
				{
					$args->{$key} = $val->default !== '' ? $val->default : null;
				}
			}

			$widget_content = $oWidget->proc($args);
			return Context::replaceUserLang($widget_content);
		}

		// If cached data exists, return it.
		$cache_key = 'widget_cache:' . $widget_sequence . ':' . $lang_type;
		$cache_data = Rhymix\Framework\Cache::get($cache_key);
		if (is_object($cache_data) && isset($cache_data->assets))
		{
			foreach ($cache_data->assets as $asset)
			{
				Context::loadFile($asset);
			}
			return Context::replaceUserLang($cache_data->content);
		}

		// Otherwise, execute the widget, cache the result, and return it.
		$oWidget = $this->getWidgetObject($widget);
		if (!$oWidget || !method_exists($oWidget, 'proc'))
		{
			return;
		}

		foreach (WidgetModel::getWidgetInfo($widget)->extra_var ?? [] as $key => $val)
		{
			if (!isset($args->{$key}))
			{
				$args->{$key} = $val->default !== '' ? $val->default : null;
			}
		}

		$oFrontEndFileHandler = FrontEndFileHandler::getInstance();
		$oFrontEndFileHandler->startLog();

		$widget_content = $oWidget->proc($args);

		$cache_data = new stdClass;
		$cache_data->assets = $oFrontEndFileHandler->endLog();
		$cache_data->content = $widget_content;
		Rhymix\Framework\Cache::set($cache_key, $cache_data, $widget_cache, true);

		return Context::replaceUserLang($widget_content);
	}

	/**
	 * @brief Widget name and argument and produce a result and Return the results
	 * Tags used in templateHandler $this-&gt; execute() will be replaced by the code running
	 *
	 * $Javascript_mode is true when editing your page by the code for handling Includes photos
	 */
	function execute($widget, $args, $javascript_mode = false, $escaped = true)
	{
		// Save for debug run-time widget
		$start = microtime(true);

		// Type juggling
		if (is_array($args))
		{
			$args = (object)$args;
		}

		// Apply urldecode for backward compatibility
		if ($escaped)
		{
			foreach (get_object_vars($args) ?: [] as $key => $val)
			{
				if (!in_array($key, ['body', 'class', 'style', 'document_srl', 'widget', 'widget_sequence', 'widgetstyle', 'widgetbox_content', 'widget_padding_left', 'widget_padding_top', 'widget_padding_bottom', 'widget_padding_right']))
				{
					$args->{$key} = utf8RawUrlDecode($val);
				}
			}
		}

		// Set default
		$args->widget_sequence = $args->widget_sequence ?? 0;
		$args->widget_cache = $args->widget_cache ?? 0;
		$args->colorset = $args->colorset ?? '';

		/**
		 * Widgets widgetContent/widgetBox Wanted If you are not content
		 */
		$widget_content = '';
		if($widget != 'widgetContent' && $widget != 'widgetBox')
		{
			if(!is_dir(sprintf(RX_BASEDIR.'widgets/%s/',$widget))) return;
			// Hold the contents of the widget parameter
			$widget_content = $this->getCache($widget, $args);
		}

		if($widget == 'widgetBox')
		{
			$widgetbox_content = $args->widgetbox_content;
		}

		/**
		 * Wanted specified by the administrator of the widget style
		 */
		// Sometimes the wrong code, background-image: url (none) can be heard but none in this case, the request for the url so unconditionally Removed
		$style = preg_replace('/url\((.+)(\/?)none\)/is','', $args->style ?? '');
		// Find a style statement that based on the internal margin dropping pre-change
		$widget_padding_left = $args->widget_padding_left ?? 0;
		$widget_padding_right = $args->widget_padding_right ?? 0;
		$widget_padding_top = $args->widget_padding_top ?? 0;
		$widget_padding_bottom = $args->widget_padding_bottom ?? 0;
		$inner_style = sprintf("padding:%dpx %dpx %dpx %dpx !important;", $widget_padding_top, $widget_padding_right, $widget_padding_bottom, $widget_padding_left);

		/**
		 * Wanted widget output
		 */

		$widget_content_header = '';
		$widget_content_body = '';
		$widget_content_footer = '';
		// If general call is given on page styles should return immediately dreamin '
		if(!$javascript_mode)
		{
			if(isset($args->id) && $args->id)
			{
				$args->id = ' id="'.$args->id.'" ';
			}
			switch($widget)
			{
				// If a direct orthogonal addition information
				case 'widgetContent' :
					if($args->document_srl)
					{
						$oDocument = DocumentModel::getDocument($args->document_srl, false, true);
						$body = $oDocument->getContent(false, false, false, false);
					}
					else
					{
						$body = base64_decode($args->body);
					}
					// Change the editor component
					$oEditorController = EditorController::getInstance();
					$body = $oEditorController->transComponent($body);

					$widget_content_header = sprintf('<div class="rhymix_content xe_content xe-widget-wrapper ' . ($args->css_class ?? '') . '" %sstyle="%s"><div style="%s">', $args->id ?? '', $style, $inner_style);
					$widget_content_body = $body;
					$widget_content_footer = '</div></div>';

					break;
					// If the widget box; it could
				case 'widgetBox' :
					$widget_content_header = sprintf('<div class="xe-widget-wrapper ' . ($args->css_class ?? '') . '" %sstyle="%s;"><div style="%s"><div>', $args->id ?? '', $style, $inner_style);
					$widget_content_body = $widgetbox_content;

					break;
					// If the General wijetil
				default :
					$widget_content_header = sprintf('<div class="xe-widget-wrapper ' . ($args->css_class ?? '') . '" %sstyle="%s">', $args->id ?? '', $style);
					$widget_content_body = sprintf('<div style="%s">%s</div>', $inner_style,$widget_content);
					$widget_content_footer = '</div>';
					break;
			}
			// Edit page is called when a widget if you add the code for handling
		}
		else
		{
			switch($widget)
			{
				// If a direct orthogonal addition information
				case 'widgetContent' :
					if($args->document_srl)
					{
						$oDocument = DocumentModel::getDocument($args->document_srl, false, true);
						$body = $oDocument->getContent(false, false, false, false);
					}
					else
					{
						$body = base64_decode($args->body);
					}
					// by args
					$attribute = array();
					if($args)
					{
						foreach($args as $key => $val)
						{
							$val = (string)$val;
							if(in_array($key, array('class','style','widget_padding_top','widget_padding_right','widget_padding_bottom','widget_padding_left','widget','widgetstyle','document_srl'))) continue;
							if(strpos($val,'|@|')>0) $val = str_replace('|@|',',',$val);
							$attribute[] = sprintf('%s="%s"', $key, htmlspecialchars($val, ENT_COMPAT | ENT_HTML401, 'UTF-8', false));
						}
					}

					$widget_content_header = vsprintf(
						'<div class="rhymix_content xe_content widgetOutput ' . ($args->css_class ?? '') . '" widgetstyle="%s" style="%s" widget_padding_left="%s" widget_padding_right="%s" widget_padding_top="%s" widget_padding_bottom="%s" widget="widgetContent" document_srl="%d" %s>'.
						'<div class="widgetResize"></div>'.
						'<div class="widgetResizeLeft"></div>'.
						'<div class="widgetBorder">'.
						'<div style="%s">',
					[
						$args->widgetstyle ?? '',
						$style,
						$args->widget_padding_left,
						$args->widget_padding_right,
						$args->widget_padding_top,
						$args->widget_padding_bottom,
						$args->document_srl,
						implode(' ', $attribute),
						$inner_style,
					]);

					$widget_content_body = $body;
					$widget_content_footer = sprintf('</div>'.
						'</div>'.
						'<div class="widgetContent" style="display:none;width:1px;height:1px;overflow:hidden;">%s</div>'.
						'</div>',base64_encode($body));

					break;
					// If the widget box; it could
				case 'widgetBox' :
					// by args
					$attribute = array();
					if($args)
					{
						foreach($args as $key => $val)
						{
							if(in_array($key, array('class','style','widget_padding_top','widget_padding_right','widget_padding_bottom','widget_padding_left','widget','widgetstyle','document_srl'))) continue;
							if(!is_numeric($val) && (!is_string($val) || strlen($val)==0)) continue;
							if(strpos($val,'|@|')>0) $val = str_replace('|@|',',',$val);
							$attribute[] = sprintf('%s="%s"', $key, htmlspecialchars($val, ENT_COMPAT | ENT_HTML401, 'UTF-8', false));
						}
					}

					$widget_content_header = vsprintf(
						'<div class="widgetOutput ' . ($args->css_class ?? '') . '" widgetstyle="%s" widget="widgetBox" style="%s;" widget_padding_top="%s" widget_padding_right="%s" widget_padding_bottom="%s" widget_padding_left="%s" %s >'.
						'<div class="widgetBoxResize"></div>'.
						'<div class="widgetBoxResizeLeft"></div>'.
						'<div class="widgetBoxBorder"><div class="nullWidget" style="%s">',
					[
						$args->widgetstyle ?? '',
						$style,
						$widget_padding_top,
						$widget_padding_right,
						$widget_padding_bottom,
						$widget_padding_left,
						implode(' ', $attribute),
						$inner_style,
					]);

					$widget_content_body = $widgetbox_content;

					break;
					// If the General wijetil
				default :
					// by args
					$attribute = array();
					if($args)
					{
						$allowed_key = array('class','style','widget_padding_top','widget_padding_right','widget_padding_bottom','widget_padding_left','widget');
						foreach($args as $key => $val)
						{
							if(in_array($key, $allowed_key)) continue;
							if(!is_numeric($val) && (!is_string($val) || strlen($val)==0)) continue;
							if(strpos($val,'|@|')>0) $val = str_replace('|@|',',',$val);
							$attribute[] = sprintf('%s="%s"', $key, htmlspecialchars($val, ENT_COMPAT | ENT_HTML401, 'UTF-8', false));
						}
					}

					$widget_content_header = vsprintf('<div class="widgetOutput ' . ($args->css_class ?? '') . '" widgetstyle="%s" style="%s" widget_padding_top="%s" widget_padding_right="%s" widget_padding_bottom="%s" widget_padding_left="%s" widget="%s" %s >'.
						'<div class="widgetResize"></div>'.
						'<div class="widgetResizeLeft"></div>'.
						'<div class="widgetBorder">',
					[
						$args->widgetstyle ?? '',
						$style,
						$widget_padding_top,
						$widget_padding_right,
						$widget_padding_bottom,
						$widget_padding_left,
						$widget,
						implode(' ', $attribute),
					]);

					$widget_content_body = sprintf('<div style="%s">%s</div>',$inner_style, $widget_content);

					$widget_content_footer = '</div></div>';

					break;
			}
		}
		// Compile the widget style.
		if(isset($args->widgetstyle) && $args->widgetstyle)
		{
			$widget_content_body = $this->compileWidgetStyle($args->widgetstyle, $widget, $widget_content_body, $args, $javascript_mode);
		}

		$output = $widget_content_header . $widget_content_body . $widget_content_footer;

		// Debug widget creation time information added to the results
		$elapsed_time = microtime(true) - $start;
		if (!isset($GLOBALS['__widget_excute_elapsed__']))
		{
			$GLOBALS['__widget_excute_elapsed__'] = 0;
		}
		$GLOBALS['__widget_excute_elapsed__'] += $elapsed_time;
		if (Rhymix\Framework\Debug::isEnabledForCurrentUser())
		{
			Rhymix\Framework\Debug::addWidget(array(
				'name' => $widget,
				'elapsed_time' => $elapsed_time,
			));
		}

		// Return result
		return $output;
	}

	/**
	 * @brief Return widget object
	 */
	function getWidgetObject($widget)
	{
		if(!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $widget))
		{
			return lang('msg_invalid_request');
		}

		if(!isset($GLOBALS['_xe_loaded_widgets_'][$widget]))
		{
			// Finding the location of a widget
			$path = WidgetModel::getWidgetPath($widget);

			// If you do not find the class file error output widget (html output)
			$class_file = sprintf('%s%s.class.php', $path, $widget);
			if (!file_exists($class_file))
			{
				return sprintf(lang('msg_widget_is_not_exists'), $widget);
			}

			// Widget classes include
			require_once($class_file);

			// Creating Objects
			if (!class_exists($widget, false))
			{
				return sprintf(lang('msg_widget_object_is_null'), $widget);
			}

			$oWidget = new $widget();
			if (!is_object($oWidget))
			{
				return sprintf(lang('msg_widget_object_is_null'), $widget);
			}
			if (!method_exists($oWidget, 'proc'))
			{
				return sprintf(lang('msg_widget_proc_is_null'), $widget);
			}

			$oWidget->widget_path = $path;

			$GLOBALS['_xe_loaded_widgets_'][$widget] = $oWidget;
		}
		return $GLOBALS['_xe_loaded_widgets_'][$widget];
	}

	function compileWidgetStyle($widgetStyle,$widget,$widget_content_body, $args, $javascript_mode)
	{
		if (!$widgetStyle)
		{
			return $widget_content_body;
		}

		// Bring extra_var widget style tie
		$widgetstyle_info = WidgetModel::getWidgetStyleInfo($widgetStyle);
		if (!$widgetstyle_info)
		{
			return $widget_content_body;
		}

		$widgetstyle_extra_var = new stdClass();
		$widgetstyle_extra_var_key = get_object_vars($widgetstyle_info);
		if(countobj($widgetstyle_extra_var_key['extra_var']))
		{
			foreach($widgetstyle_extra_var_key['extra_var'] as $key => $val)
			{
				$widgetstyle_extra_var->{$key} = $args->{$key} ?? null;
			}
		}
		Context::set('widgetstyle_extra_var', $widgetstyle_extra_var);
		// #18994272 오타를 수정했으나 하위 호환성을 위해 남겨둠 - deprecated
		Context::set('widgetstyle_extar_var', $widgetstyle_extra_var);

		if ($javascript_mode && $widget == 'widgetBox')
		{
			Context::set('widget_content', '<div class="widget_inner">'.$widget_content_body.'</div>');
		}
		else
		{
			Context::set('widget_content', $widget_content_body);
		}

		// Compilation
		$widgetstyle_path = WidgetModel::getWidgetStylePath($widgetStyle);
		$oTemplate = Rhymix\Framework\Template::getInstance();
		$tpl = $oTemplate->compile($widgetstyle_path, 'widgetstyle');

		return $tpl;
	}

	/**
	 * @brief request parameters and variables sort through the information widget
	 */
	function arrangeWidgetVars($widget, $request_vars, &$vars)
	{
		$widget_info = WidgetModel::getWidgetInfo($widget);

		if(!$vars)
		{
			$vars = new stdClass();
		}

		$widget = $vars->selected_widget;
		$vars->css_class = $request_vars->css_class;
		$vars->widgetstyle = $request_vars->widgetstyle;

		$vars->skin = trim($request_vars->skin);
		$vars->colorset = trim($request_vars->colorset);
		$vars->widget_sequence = (int)($request_vars->widget_sequence);
		$vars->widget_cache = (int)($request_vars->widget_cache);
		if($request_vars->widget_cache_unit && in_array($request_vars->widget_cache_unit, array('s', 'm', 'h', 'd')))
		{
			$vars->widget_cache .= $request_vars->widget_cache_unit;
		}
		$vars->style = trim($request_vars->style);
		$vars->widget_padding_left = trim($request_vars->widget_padding_left);
		$vars->widget_padding_right = trim($request_vars->widget_padding_right);
		$vars->widget_padding_top = trim($request_vars->widget_padding_top);
		$vars->widget_padding_bottom = trim($request_vars->widget_padding_bottom);
		$vars->document_srl= trim($request_vars->document_srl);

		foreach ($widget_info->extra_var ?? [] as $key => $val)
		{
			$vars->{$key} = trim($request_vars->{$key} ?? '');
		}

		// Additional configuration for widget styles
		if($request_vars->widgetstyle)
		{
			$widgetStyle_info = WidgetModel::getWidgetStyleInfo($request_vars->widgetstyle);
			foreach ($widgetStyle_info->extra_var ?? [] as $key => $val)
			{
				if (in_array($val->type, ['color', 'text', 'select', 'filebox', 'textarea']))
				{
					$vars->{$key} = trim($request_vars->{$key} ?? '');
				}
			}
		}

		if($vars->widget_sequence)
		{
			$lang_type = Context::getLangType();
			Rhymix\Framework\Cache::delete('widget_cache:' . $vars->widget_sequence . ':' . $lang_type);
		}

		if($vars->widget_cache > 0)
		{
			$vars->widget_sequence = getNextSequence();
		}

		$attribute = array();
		foreach($vars as $key => $val)
		{
			if(!$val)
			{
				unset($vars->{$key});
				continue;
			}
			if(strpos($val,'|@|') > 0) $val = str_replace('|@|', ',', $val);
			$vars->{$key} = Context::convertEncodingStr($val);
			$attribute[] = sprintf('%s="%s"', $key, htmlspecialchars(Context::convertEncodingStr($val), ENT_COMPAT | ENT_HTML401, 'UTF-8', false));
		}

		return $attribute;
	}
}
/* End of file widget.controller.php */
/* Location: ./modules/widget/widget.controller.php */

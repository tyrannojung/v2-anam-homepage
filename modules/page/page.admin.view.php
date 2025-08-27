<?php
/* Copyright (C) NAVER <http://www.navercorp.com> */
/**
 * @class  pageAdminView
 * @author NAVER (developers@xpressengine.com)
 * @brief page admin view of the module class
 */
class PageAdminView extends Page
{
	var $module_srl = 0;
	var $list_count = 20;
	var $page_count = 10;

	/**
	 * @brief Initialization
	 */
	function init()
	{
		// Pre-check if module_srl exists. Set module_info if exists
		$module_srl = Context::get('module_srl');
		// module_srl two come over to save the module, putting the information in advance
		if($module_srl)
		{
			$module_info = ModuleModel::getModuleInfoByModuleSrl($module_srl);
			if(!$module_info)
			{
				Context::set('module_srl','');
				$this->act = 'list';
			}
			else
			{
				ModuleModel::syncModuleToSite($module_info);
				$this->module_info = $module_info;
				Context::set('module_info',$module_info);
			}
		}
		// Get a list of module categories
		$module_category = ModuleModel::getModuleCategories();
		Context::set('module_category', $module_category);
		//Security
		$security = new Security();
		$security->encodeHTML('module_category..title');

		// Get a template path (page in the administrative template tpl putting together)
		$this->setTemplatePath($this->module_path.'tpl');
	}

	/**
	 * @brief Manage a list of pages showing
	 */
	function dispPageAdminContent()
	{
		$args = new stdClass();
		$args->sort_index = "module_srl";
		$args->page = Context::get('page');
		$args->list_count = 40;
		$args->page_count = 10;
		$args->s_module_category_srl = Context::get('module_category_srl');

		$search_target_list = array('s_mid','s_browser_title');
		$search_target = Context::get('search_target');
		$search_keyword = Context::get('search_keyword');
		if(in_array($search_target,$search_target_list) && $search_keyword) $args->{$search_target} = $search_keyword;

		$output = executeQuery('page.getPageList', $args);
		$page_list = ModuleModel::addModuleExtraVars($output->data);
		moduleModel::syncModuleToSite($page_list);

		$oModuleAdminModel = getAdminModel('module'); /* @var $oModuleAdminModel moduleAdminModel */

		$tabChoice = array('tab1'=>1, 'tab3'=>1);
		$selected_manage_content = $oModuleAdminModel->getSelectedManageHTML($this->xml_info->grant, $tabChoice, $this->module_path);
		Context::set('selected_manage_content', $selected_manage_content);

		// To write to a template context:: set
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('page_list', $output->data);
		Context::set('page_navigation', $output->page_navigation);

		//Security
		$security = new Security();
		$security->encodeHTML('page_list..browser_title');
		$security->encodeHTML('page_list..mid');
		$security->encodeHTML('module_info.');

		// Set a template file
		$this->setTemplateFile('index');
	}

	/**
	 * @brief Information output of the selected page
	 */
	function dispPageAdminInfo()
	{
		// Get module_srl by GET parameter
		$module_srl = Context::get('module_srl');
		$module_info = Context::get('module_info');
		// If you do not value module_srl just showing the index page
		if(!$module_srl) return $this->dispPageAdminContent();
		// If the layout is destined to add layout information haejum (layout_title, layout)
		if($module_info->layout_srl > 0)
		{
			$layout_info = LayoutModel::getLayout($module_info->layout_srl);
			$module_info->layout = $layout_info->layout;
			$module_info->layout_title = $layout_info->layout_title;
		}
		// Get a layout list
		$layout_list = LayoutModel::getLayoutList();
		Context::set('layout_list', $layout_list);

		$mobile_layout_list = LayoutModel::getLayoutList(0,"M");
		Context::set('mlayout_list', $mobile_layout_list);
		// Set a template file

		if($this->module_info->page_type == 'ARTICLE')
		{
			$skin_list = ModuleModel::getSkins($this->module_path);
			Context::set('skin_list',$skin_list);

			$mskin_list = ModuleModel::getSkins($this->module_path, "m.skins");
			Context::set('mskin_list', $mskin_list);
		}

		//Security
		$security = new Security();
		$security->encodeHTML('layout_list..layout');
		$security->encodeHTML('layout_list..title');
		$security->encodeHTML('mlayout_list..layout');
		$security->encodeHTML('mlayout_list..title');
		$security->encodeHTML('module_info.');

		$this->setTemplateFile('page_info');
	}

	/**
	 * @brief Additional settings page showing
	 * For additional settings in a service module in order to establish links with other modules peyijiim
	 */
	function dispPageAdminPageAdditionSetup()
	{
		// call by reference content from other modules to come take a year in advance for putting the variable declaration
		$content = '';

		$oEditorView = getView('editor');
		$oEditorView->triggerDispEditorAdditionSetup($content);
		Context::set('setup_content', $content);
		// Set a template file
		$this->setTemplateFile('addition_setup');

		$security = new Security();
		$security->encodeHTML('module_info.');
	}

	function dispPageAdminMobileContent()
	{
		if($this->module_info->page_type == 'OUTSIDE')
		{
			throw new Rhymix\Framework\Exceptions\InvalidRequest;
		}

		if($this->module_srl)
		{
			Context::set('module_srl',$this->module_srl);
		}

		$oPageMobile = getMobile('page');
		$oPageMobile->module_info = $this->module_info;
		$page_type_name = strtolower($this->module_info->page_type);
		$method = '_get' . ucfirst($page_type_name) . 'Content';
		if(method_exists($oPageMobile, $method))
		{
			if($method == '_getArticleContent' && $this->module_info->is_mskin_fix == 'N')
			{
				$oPageMobile->module_info->mskin = ModuleModel::getModuleDefaultSkin('page', 'M');
			}
			$page_content = $oPageMobile->{$method}();
		}
		else
		{
			throw new Rhymix\Framework\Exception(sprintf('%s method is not exists', $method));
		}

		Context::set('module_info', $this->module_info);
		Context::set('page_content', $page_content);

		$this->setLayoutFile('');
		$this->setTemplateFile('mcontent');
	}

	function dispPageAdminMobileContentModify()
	{
		Context::set('module_info', $this->module_info);

		if ($this->module_info->page_type == 'WIDGET')
		{
			$this->_setWidgetTypeContentModify(true);
		}
		else if ($this->module_info->page_type == 'ARTICLE')
		{
			$this->_setArticleTypeContentModify(true);
		}
	}

	/**
	 * @brief Edit Page Content
	 */
	function dispPageAdminContentModify()
	{
		// Set the module information
		Context::set('module_info', $this->module_info);

		if ($this->module_info->page_type == 'WIDGET')
		{
			$this->_setWidgetTypeContentModify();
		}
		else if ($this->module_info->page_type == 'ARTICLE')
		{
			$this->_setArticleTypeContentModify();
		}
	}

	function _setWidgetTypeContentModify($isMobile = false)
	{
		// Setting contents
		if($isMobile)
		{
			$content = Context::get('mcontent');
			if(!$content) $content = $this->module_info->mcontent;
			$templateFile = 'page_mobile_content_modify';
		}
		else
		{
			$content = Context::get('content');
			if(!$content) $content = $this->module_info->content;
			$templateFile = 'page_content_modify';
		}

		Context::set('content', $content);
		// Convert them to teach the widget
		$oWidgetController = WidgetController::getInstance();
		$content = $oWidgetController->transWidgetCode($content, true, !$isMobile);
		// $content = str_replace('$', '&#36;', $content);
		Context::set('page_content', $content);
		// Set widget list
		$widget_list = WidgetModel::getDownloadedWidgetList();
		Context::set('widget_list', $widget_list);

		//Security
		$security = new Security();
		$security->encodeHTML('widget_list..title','module_info.mid');

		// Load admin resources
		$oTemplate = Rhymix\Framework\Template::getInstance();
		$oTemplate->compile('modules/admin/tpl', '_admin_common.html');

		// Set a template file
		$this->setLayoutFile('');
		$this->setTemplateFile($templateFile);
	}

	function _setArticleTypeContentModify($isMobile = false)
	{
		$oDocument = DocumentModel::getDocument(0);

		if($isMobile)
		{
			Context::set('isMobile', 'Y');
			$target = 'mdocument_srl';
		}
		else
		{
			Context::set('isMobile', 'N');
			$target = 'document_srl';
		}

		if($this->module_info->{$target})
		{
			$document_srl = $this->module_info->{$target};
			$oDocument->setDocument($document_srl);
			Context::set('document_srl', $document_srl);
		}
		else if(Context::get('document_srl'))
		{
			$document_srl = Context::get('document_srl');
			$oDocument->setDocument($document_srl);
			Context::set('document_srl', $document_srl);
		}
		else
		{
			$oDocument->add('module_srl', $this->module_info->module_srl);
		}

		Context::addJsFilter($this->module_path.'tpl/filter', 'insert_article.xml');
		Context::set('oDocument', $oDocument);
		Context::set('mid', $this->module_info->mid);

		if(config('view.manager_layout') !== 'admin')
		{
			$this->setLayoutAndTemplatePaths($isMobile ? 'M' : 'P', $this->module_info);
		}
		$skin_path = rtrim($this->getTemplatePath(), '/') . '/';
		if (file_exists($skin_path . 'content_modify.blade.php') || file_exists($skin_path . 'content_modify.html'))
		{
			$this->setTemplateFile('content_modify');
		}
		else
		{
			$this->setTemplatePath($this->module_path . 'tpl');
			$this->setTemplateFile('article_content_modify');
		}
	}

	/**
	 * @brief Delete page output
	 */
	function dispPageAdminDelete()
	{
		$module_srl = Context::get('module_srl');
		if(!$module_srl) return $this->dispContent();

		$columnList = array('module_srl', 'module', 'mid');
		$module_info = ModuleModel::getModuleInfoByModuleSrl($module_srl, $columnList);
		Context::set('module_info',$module_info);
		// Set a template file
		$this->setTemplateFile('page_delete');

		$security = new Security();
		$security->encodeHTML('module_info.');
	}

	/**
	 * @brief Rights Listing
	 */
	function dispPageAdminGrantInfo()
	{
		// Common module settings page, call rights
		$oModuleAdminModel = getAdminModel('module');
		$grant_content = $oModuleAdminModel->getModuleGrantHTML($this->module_info->module_srl, $this->xml_info->grant);
		Context::set('grant_content', $grant_content);

		$this->setTemplateFile('grant_list');

		$security = new Security();
		$security->encodeHTML('module_info.');
	}

	/**
	 * Display skin setting page
	 */
	function dispPageAdminSkinInfo()
	{
		$oModuleAdminModel = getAdminModel('module');
		$skin_content = $oModuleAdminModel->getModuleSkinHTML($this->module_info->module_srl);
		Context::set('skin_content', $skin_content);

		$this->setTemplateFile('skin_info');
	}

	/**
	 * Display mobile skin setting page
	 */
	function dispPageAdminMobileSkinInfo()
	{
		$oModuleAdminModel = getAdminModel('module');
		$skin_content = $oModuleAdminModel->getModuleMobileSkinHTML($this->module_info->module_srl);
		Context::set('skin_content', $skin_content);

		$this->setTemplateFile('skin_info');
	}
}
/* End of file page.admin.view.php */
/* Location: ./modules/page/page.admin.view.php */

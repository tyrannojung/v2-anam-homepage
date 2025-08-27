<?php
/* Copyright (C) NAVER <http://www.navercorp.com> */
/**
 * @class content
 * @author NAVER (developers@xpressengine.com)
 * @brief widget to display content
 * @version 0.1
 */
class bh_gall_widget extends WidgetHandler
{
	/**
	 * @brief Widget handler
	 *
	 * Get extra_vars declared in ./widgets/widget/conf/info.xml as arguments
	 * After generating the result, do not print but return it.
	 */

	function proc($args)
	{
		if (Context::get('ajax') !== 'Y')
		{
			if (isset($args->include_days) && $args->include_days > 0)
			{
				$args->start_regdate = date('YmdHis', time() - ($args->include_days * 86400));
			}
			// Targets to sort
			if(!in_array($args->order_target, array('list_order', 'regdate', 'update_order', 'voted_count', 'readed_count', 'rand()'))) $args->order_target = 'list_order';
			// Sort order
			if(!in_array($args->order_type, array('asc','desc'))) $args->order_type = 'asc';
			// Pages
			$args->page_count = (int)$args->page_count;
			if(!$args->page_count) $args->page_count = 1;
			// The number of displayed lists
			$args->list_count = (int)$args->list_count;
			if(!$args->list_count) $args->list_count = 5;
			if(Mobile::isMobileCheckByAgent() && $args->m_list_count) $args->list_count = (int)$args->m_list_count;
			// The number of thumbnail columns
			$args->cols_list_count = (int)$args->cols_list_count;
			if(!$args->cols_list_count) $args->cols_list_count = 4;
			$args->m_cols_list_count = (int)$args->m_cols_list_count;
			if(!$args->m_cols_list_count) $args->m_cols_list_count = 1;
			// Cut the length of the title
			if(!$args->subject_cut_size) $args->subject_cut_size = 0;
			// Cut the length of contents
			if(!$args->content_cut_size) $args->content_cut_size = 100;
			// Cut the length of nickname
			if(!$args->nickname_cut_size) $args->nickname_cut_size = 0;
			// Display time of the latest post
			if(!$args->duration_new) $args->duration_new = 24;
			// How to create thumbnails
			if(!$args->thumbnail_type) $args->thumbnail_type = 'fill';
			// Horizontal size of thumbnails
			if(!$args->thumbnail_width) $args->thumbnail_width = 100;
			// Vertical size of thumbnails
			if(!$args->thumbnail_height) $args->thumbnail_height = 75;
			// Viewing options
			$args->option_view_arr = explode(',',$args->option_view);
			// 기본값
			if(!$args->tab_type) $args->tab_type = 'none';
			if(!$args->use_slider) $args->use_slider = 'N';
			if(!$args->slider_height_popup) $args->slider_height_popup = 500;
			// Set variables used internally
			$oModuleModel = getModel('module');
			$module_srls = $args->modules_info = $args->module_srls_info = $args->mid_lists = array();
			$site_module_info = Context::get('site_module_info');

			$obj = new stdClass();
			// Apply to all modules in the site if a target module is not specified
			if(empty($args->module_srls))
			{
				$obj->site_srl = (int)$site_module_info->site_srl;
				$output = executeQueryArray('widgets.bh_gall_widget.getMids', $obj);
				if($output->data)
				{
					foreach($output->data as $key => $val)
					{
						$args->modules_info[$val->mid] = $val;
						$args->module_srls_info[$val->module_srl] = $val;
						$args->mid_lists[$val->module_srl] = $val->mid;
						$module_srls[] = $val->module_srl;
					}
				}

				$args->modules_info = $oModuleModel->getMidList($obj);
				// Apply to the module only if a target module is specified
			}
			else
			{
				$obj->module_srls = $args->module_srls;
				$output = executeQueryArray('widgets.bh_gall_widget.getMids', $obj);
				if($output->data)
				{
					foreach($output->data as $key => $val)
					{
						$args->modules_info[$val->mid] = $val;
						$args->module_srls_info[$val->module_srl] = $val;
						$module_srls[] = $val->module_srl;
					}
					$idx = explode(',',$args->module_srls);
					foreach($idx as $srl)
					{
						if(!$args->module_srls_info[$srl]) continue;
						$args->mid_lists[$srl] = $args->module_srls_info[$srl]->mid;
					}
				}
			}
			// Exit if no module is found
			if(!$args->modules_info) return Context::get('msg_not_founded');
			$args->module_srl = implode(',',$module_srls);

			/**
			 * Method is separately made because content extraction, articles, comments and other elements exist
			 */
			// tab type
			if(empty($args->tab_type) || $args->tab_type == 'none' || $args->tab_type == 'tab_name')
			{
				switch($args->content_type)
				{
					case 'comment':
						$content_items = $this->_getCommentItems($args);
						break;
					case 'image':
						$content_items = $this->_getImageItems($args);
						break;
					default:
						$content_items = $this->_getDocumentItems($args);
						break;
				}
				// If not a tab type
			}
			else
			{
				$content_items = array();

				switch($args->content_type)
				{
					case 'comment':
						foreach($args->mid_lists as $module_srl => $mid)
						{
							$args->module_srl = $module_srl;
							$content_items[$module_srl] = $this->_getCommentItems($args);
						}
						break;
					case 'image':
						foreach($args->mid_lists as $module_srl => $mid)
						{
							$args->module_srl = $module_srl;
							$content_items[$module_srl] = $this->_getImageItems($args);
						}
						break;
					default:
						// 카테고리
						if (!empty($args->tab_target) && $args->tab_target == 'category')
						{
							/* 전체카테고리 표시 */
							if($args->tab_all !== 'N')
							{
								$module_srl = array_key_first($args->mid_lists);
								$args->category_srl = null;
								$args->module_srl = $module_srl;
								$content_items[$module_srl] = $this->_getDocumentItems($args);
							}
							/* 전체카테고리 표시 끝 */
							foreach($args->mid_lists as $module_srl => $mid)
							{
								$args->category_srl = null;
								$args->module_srl = $module_srl;
								$category_list = documentModel::getCategoryList($args->module_srl);
								if ($category_list)
								{
									foreach ($category_list as $key => $val)
									{
										// 최상위 분류만 표시
										if ($args->category_range == 'first' && $val->parent_srl) continue;
										// 특정 분류만 출력
										if ($args->category_specific && !in_array($val->category_srl, explode(',', $args->category_specific))) continue;

										$args->category_srl = $val->category_srl;
										// add subcategories
										if (isset($category_list[$args->category_srl]))
										{
											$categories = $category_list[$args->category_srl]->childs;
											if (is_array($categories) && count($categories)) $categories[] = $args->category_srl;
											$args->category_srls = $categories;
										}
										$content_items[$args->category_srl] = $this->_getDocumentItems($args);
									}
								}
							}

						// 모듈
						}
						else
						{
							foreach($args->mid_lists as $module_srl => $mid)
							{
								$args->module_srl = $module_srl;
								$content_items[$module_srl] = $this->_getDocumentItems($args);
							}
						}
						break;
				}
			}

			$output = $this->_compile($args,$content_items);
			return $output;
		}
		else
		{
			$form_obj = Context::getRequestVars();
			$document_srl = $form_obj->target_srl;

			$oDocumentModel = getModel('document');

			if (!$GLOBALS['XE_DOCUMENT_LIST'][$document_srl])
			{
				$oDocument = new documentItem($document_srl);
				if (!$oDocument->isExists())
				{
					return $oDocument;
				}
				$GLOBALS['XE_DOCUMENT_LIST'][$document_srl] = $oDocument;
				//$oDocumentModel->setToAllDocumentExtraVars();
			}

			$oDocument = $GLOBALS['XE_DOCUMENT_LIST'][$document_srl];
			Context::set('document_item', $oDocument);
			Context::set('slider_height_popup', $args->slider_height_popup);

			$tpl_path = sprintf('%sskins/%s', $this->widget_path, $args->skin);

			$oTemplate = TemplateHandler::getInstance();
			return $oTemplate->compile($tpl_path, "ajax");
		}
	}

	/**
	 * @brief Get a list of comments and return contentItem
	 */
	function _getCommentItems($args)
	{
		// List variables to use CommentModel::getCommentList()
		$obj = new stdClass();
		$obj->module_srl = $args->module_srl;
		$obj->sort_index = $args->order_target;
		$obj->is_index_rand = $obj->sort_index === 'rand()';
		$obj->is_index_default = !$obj->is_index_rand;
		$obj->list_count = $args->list_count * $args->page_count;
		$obj->statusList = [1];
		if(($args->view_secret ?? 'N') !== 'Y')
		{
			$obj->is_secret = 'N';
		}
		$obj->start_regdate = $args->start_regdate ?? null;

		$output = executeQuery('widgets.bh_gall_widget.getCommentList', $obj);
		if (!$output->toBool())
		{
			return;
		}

		$comment_list = $output->data;
		if ($comment_list)
		{
			if (!is_array($comment_list))
			{
				$comment_list = array($comment_list);
			}

			$comment_count = count($comment_list);

			foreach ($comment_list as $key => $attribute)
			{
				if (!$attribute->comment_srl)
				{
					continue;
				}

				$oComment = new commentItem();
				$oComment->setAttribute($attribute);

				$result[$key] = $oComment;
			}
			$output->data = $result;
		}

		if (!is_array($output->data) || !count($output->data)) return;

		$content_items = array();
		foreach($output->data as $key => $oComment)
		{
			$oDocument = getModel('document')->getDocument($oComment->get('document_srl'), false, false);
			if(!$oDocument->isExists() || $oDocument->isSecret() && ($args->view_secret ?? 'N') !== 'Y')
			{
				continue;
			}

			$attribute = $oComment->getObjectVars();
			$title = $oComment->getSummary($args->content_cut_size);
			$thumbnail = $oComment->getThumbnail($args->thumbnail_width,$args->thumbnail_height,$args->thumbnail_type);
			$thumbnail_x2 = $oComment->getThumbnail($args->thumbnail_width * 2, $args->thumbnail_height * 2, $args->thumbnail_type);

			$attribute->mid = $args->mid_lists[$attribute->module_srl];
			$browser_title = $args->module_srls_info[$attribute->module_srl]->browser_title;
			$domain = $args->module_srls_info[$attribute->module_srl]->domain;

			$content_item = new bhWidgetItem($browser_title);
			$content_item->adds($attribute);
			$content_item->setTitle($title);
			$content_item->setThumbnail($thumbnail);
			$content_item->setThumbnail($thumbnail_x2, 2);
			$content_item->setLink($oComment->getPermanentUrl());
			$content_item->setDomain($domain);
			$content_item->add('mid', $args->mid_lists[$attribute->module_srl]);
			$content_items[] = $content_item;
		}
		return $content_items;
	}

	function _getDocumentItems($args)
	{
		// Get model object from the document module
		$oDocumentModel = getModel('document');
		// Get categories
		$obj = new stdClass();
		$obj->module_srl = $args->module_srl;
		$output = executeQueryArray('widgets.bh_gall_widget.getCategories',$obj);
		if($output->toBool() && $output->data)
		{
			foreach($output->data as $key => $val)
			{
				$category_lists[$val->module_srl][$val->category_srl] = $val;
			}
			$args->category_list = $category_lists;
		}
		// Get a list of documents
		$obj->module_srl = $args->module_srl ?? null;
		if (!empty($args->category_srls))
		{
			$obj->category_srls = $args->category_srls ?? null;
		}
		else
		{
			$obj->category_srl = $args->category_srl ?? null;
		}
		$obj->sort_index = $args->order_target ?? null;
		$obj->is_index_rand = $obj->sort_index === 'rand()';
		$obj->is_index_default = !$obj->is_index_rand;
		if($args->order_target == 'list_order' || $args->order_target == 'update_order')
		{
			$obj->order_type = $args->order_type=="desc"?"asc":"desc";
		}
		else
		{
			$obj->order_type = $args->order_type=="desc"?"desc":"asc";
		}

		$obj->page = null;
		$obj->is_notice = $args->view_notice ?? null;
		$obj->title_bold = $args->view_best ?? null;
		if(($args->view_secret ?? 'N') == 'Y')
		{
			$obj->statusList = array('PUBLIC', 'SECRET');
		}
		else
		{
			$obj->statusList = array('PUBLIC');
		}
		$obj->start_regdate = $args->start_regdate ?? null;

		$obj->list_count = $args->list_count * $args->page_count;
		if (!empty($args->use_search) && $args->use_search === 'Y')
		{
			$obj->s_title = $args->search_keyword ?? null;
			$obj->s_content = $args->search_keyword ?? null;
			$obj->search_keyword_extra = $args->search_keyword_extra ?? null;
		}
		if (!empty($obj->search_keyword_extra))
		{
			$output = executeQueryArray('widgets.bh_gall_widget.getDocumentListWithinExtraVars', $obj);
		}
		else
		{
			$output = executeQueryArray('widgets.bh_gall_widget.getDocumentList', $obj);
		}
		if(!$output->toBool() || !$output->data) return;
		// If the result exists, make each document as an object
		$content_items = array();
		$first_thumbnail_idx = -1;
		if(is_array($output->data) && count($output->data))
		{
			foreach($output->data as $key => $attribute)
			{
				$oDocument = new documentItem();
				$oDocument->setAttribute($attribute, false);
				$GLOBALS['XE_DOCUMENT_LIST'][$oDocument->document_srl] = $oDocument;
				$document_srls[] = $oDocument->document_srl;
			}
			$oDocumentModel->setToAllDocumentExtraVars();

			foreach($document_srls as $i => $document_srl)
			{
				$oDocument = $GLOBALS['XE_DOCUMENT_LIST'][$document_srl];
				$document_srl = $oDocument->document_srl;
				$module_srl = $oDocument->get('module_srl');
				$category_srl = $oDocument->get('category_srl');

				if ($args->thumbnail_type !== 'original')
				{
					$thumbnail = $oDocument->getThumbnail($args->thumbnail_width,$args->thumbnail_height,$args->thumbnail_type);
				}
				else
				{
					// Find an iamge file among attached files if exists
					if ($oDocument->hasUploadedFiles())
					{
						$file_list = $oDocument->getUploadedFiles();
						$source_file = null;
						$first_image = null;
						foreach ($file_list as $file)
						{
							if ($file->direct_download !== 'Y') continue;
							if ($file->cover_image === 'Y' && file_exists($file->uploaded_filename))
							{
								$source_file = $file->uploaded_filename;
								break;
							}
							if ($first_image) continue;
							if (preg_match("/\.(jpe?g|png|gif|bmp)$/i", $file->source_filename))
							{
								if (file_exists($file->uploaded_filename))
								{
									$first_image = $file->uploaded_filename;
								}
							}
						}
						if (!$source_file && $first_image)
						{
							$source_file = $first_image;
						}
						$thumbnail = $source_file;
					}
				}
				$thumbnail_x2 = $oDocument->getThumbnail($args->thumbnail_width * 2,$args->thumbnail_height * 2,$args->thumbnail_type);

				$content_item = new bhWidgetItem($args->module_srls_info[$module_srl]->browser_title);
				$content_item->adds($oDocument->getObjectVars());
				$content_item->add('original_content', $oDocument->get('content'));
				$content_item->setTitle($oDocument->getTitleText());
				if(isset($category_lists[$module_srl]) && isset($category_lists[$module_srl][$category_srl]))
				{
					$content_item->setCategory($category_lists[$module_srl][$category_srl]->title);
				}
				if(isset($args->module_srls_info[$module_srl]))
				{
					$content_item->setDomain($args->module_srls_info[$module_srl]->domain);
				}
				$content_item->setContent($oDocument->getSummary($args->content_cut_size));
				// link
				$args->use_link = $args->use_link ?? '';
				if($args->use_link === 'e')
				{
					$target_link = $oDocument->getExtraEidValue('url');
				}
				else if($args->use_link === 'l')
				{
					$target_link = $args->widget_link;
				}
				else if($args->use_link === 'p')
				{
					$target_link = "#";
				}
				else
				{
					$target_link = $oDocument->getPermanentUrl();
				}
				$content_item->setLink($target_link);
				$content_item->setThumbnail($thumbnail);
				$content_item->setThumbnail($thumbnail_x2, 2);
				$content_item->setExtraImages($oDocument->printExtraImages($args->duration_new * 60 * 60));
				if (!empty($args->extravar_title)) $content_item->setExtraVar_title($oDocument->getExtraEidValueHTML($args->extravar_title));
				if (!empty($args->extravar_content)) $content_item->setExtraVar_content($oDocument->getExtraEidValueHTML($args->extravar_content));
				if (!empty($args->extravar_regdate)) $content_item->setExtraVar_regdate($oDocument->getExtraEidValue($args->extravar_regdate));
				if (!empty($args->extravar_nickname)) $content_item->setExtraVar_nickname($oDocument->getExtraEidValueHTML($args->extravar_nickname));

				if (!empty($args->extravar_1)) $content_item->setExtraVar_1($oDocument->getExtraEidValueHTML($args->extravar_1));
				if (!empty($args->extravar_2)) $content_item->setExtraVar_2($oDocument->getExtraEidValueHTML($args->extravar_2));
				if (!empty($args->extravar_3)) $content_item->setExtraVar_3($oDocument->getExtraEidValueHTML($args->extravar_3));
				if (!empty($args->extravar_4)) $content_item->setExtraVar_4($oDocument->getExtraEidValueHTML($args->extravar_4));
				if (!empty($args->extravar_5)) $content_item->setExtraVar_5($oDocument->getExtraEidValueHTML($args->extravar_5));
				if (!empty($args->extravar_6)) $content_item->setExtraVar_6($oDocument->getExtraEidValueHTML($args->extravar_6));
				if (in_array('label', $args->option_view_arr)) $content_item->setLabel($oDocument->getExtraEidValue('label'));

				$content_item->add('mid', $args->mid_lists[$module_srl]);
				if($first_thumbnail_idx==-1 && $thumbnail) $first_thumbnail_idx = $i;
				$content_items[] = $content_item;
			}

			$content_items[0]->setFirstThumbnailIdx($first_thumbnail_idx);
		}

		$oSecurity = new Security($content_items);
		$oSecurity->encodeHTML('..variables.content', '..variables.user_name', '..variables.nick_name');

		return $content_items;
	}

	function _getImageItems($args)
	{
		$oDocumentModel = getModel('document');

		$obj = new stdClass();
		$obj->module_srls = $obj->module_srl = $args->module_srl;
		$obj->direct_download = 'Y';
		$obj->isvalid = 'Y';
		// Get categories
		$output = executeQueryArray('widgets.bh_gall_widget.getCategories',$obj);
		if($output->toBool() && $output->data)
		{
			foreach($output->data as $key => $val)
			{
				$category_lists[$val->module_srl][$val->category_srl] = $val;
			}
		}
		// Get a file list in each document on the module
		$obj->list_count = $args->list_count * $args->page_count;
		$files_output = executeQueryArray("file.getOneFileInDocument", $obj);
		$files_count = count($files_output->data ?: []);
		if(!$files_count) return;

		$content_items = array();

		for($i=0;$i<$files_count;$i++) $document_srl_list[] = $files_output->data[$i]->document_srl;

		$tmp_document_list = $oDocumentModel->getDocuments($document_srl_list);

		if(!is_array($tmp_document_list) || !count($tmp_document_list)) return;

		foreach($tmp_document_list as $oDocument)
		{
			$attribute = $oDocument->getObjectVars();
			$browser_title = $args->module_srls_info[$attribute->module_srl]->browser_title;
			$domain = $args->module_srls_info[$attribute->module_srl]->domain;
			$category = isset($category_lists[$attribute->module_srl]) ? $category_lists[$attribute->module_srl]->text : '';
			$content = $oDocument->getSummary($args->content_cut_size);
			$url = sprintf('%s#%s', $oDocument->getPermanentUrl(), $oDocument->getCommentCount());
			$thumbnail = $oDocument->getThumbnail($args->thumbnail_width,$args->thumbnail_height,$args->thumbnail_type);
			$thumbnail_x2 = $oDocument->getThumbnail($args->thumbnail_width * 2, $args->thumbnail_height * 2, $args->thumbnail_type);
			$extra_images = $oDocument->printExtraImages($args->duration_new);

			$content_item = new bhWidgetItem($browser_title);
			$content_item->adds($attribute);
			$content_item->setCategory($category);
			$content_item->setContent($content);
			$content_item->setLink($url);
			$content_item->setThumbnail($thumbnail);
			$content_item->setThumbnail($thumbnail_x2, 2);
			$content_item->setExtraImages($extra_images);
			$content_item->setDomain($domain);
			$content_item->add('mid', $args->mid_lists[$attribute->module_srl]);
			$content_items[] = $content_item;
		}

		return $content_items;
	}

	function _getSummary($content, $str_size = 50)
	{
		// Remove tags
		$content = strip_tags($content);

		// Convert temporarily html entity for truncate
		$content = html_entity_decode($content, ENT_QUOTES);

		// Replace all whitespaces to single space
		$content = utf8_trim(utf8_normalize_spaces($content));

		// Truncate string
		$content = cut_str($content, $str_size, '...');

		return escape($content);
	}

	function _compile($args,$content_items)
	{
		// Set variables for widget
		$widget_info = new stdClass();
		$widget_info = $args;
		$widget_info->new_window = $args->new_window ?? null;

		if(!empty($args->tab_type) && $args->tab_type != 'none' && $args->tab_type != 'tab_name')
		{
			$tab = array();
			foreach($content_items as $module_srl => $val)
			{
				if(!is_array($content_items[$module_srl]) || !count($content_items[$module_srl])) continue;

				unset($tab_item);
				$tab_item = new stdClass();
				if (!empty($args->tab_target) && $args->tab_target == 'category')
				{
					$tab_item->title = $content_items[$module_srl][0]->getCategory();
				}
				else
				{
					$tab_item->title = $content_items[$module_srl][0]->getBrowserTitle();
				}
				$tab_item->content_items = $content_items[$module_srl];
				$tab_item->domain = $content_items[$module_srl][0]->getDomain();
				if (!empty($args->tab_target) && $args->tab_target == 'category')
				{
					$tab_item->url = getSiteUrl($tab_item->domain, '','mid',$content_items[$module_srl][0]->getMid(),'category',$module_srl);
				}
				else
				{
					$tab_item->url = $content_items[$module_srl][0]->getContentsLink();
					if(!$tab_item->url) $tab_item->url = getSiteUrl($tab_item->domain, '', 'mid', $content_items[$module_srl][0]->getMid());
				}
				if ($args->page_count > 1)
				{
					$list_count = $args->list_count;
					$page_count = $args->page_count;
					$total_count = is_array($tab_item->content_items) ? count($tab_item->content_items) : 1;
					$total_page = max(1, ceil($total_count / $list_count));
					$tab_item->page_navigation = new PageHandler($total_count, $total_page, 1, $page_count);
				}
				$tab[] = $tab_item;
			}
			$widget_info->tab = $tab;
			if (!empty($args->tab_all) && $args->tab_all !== 'N' && isset($widget_info->tab[0]))
			{
				// 전체카테고리 표시
				if (!empty($args->tab_target) && $args->tab_target == 'category')
				{
					$widget_info->tab[0]->title = "전체";
				}
			}
		}
		else if (!empty($args->tab_type) && $args->tab_type == 'tab_name')
		{
			$tab = array();
			$tab_item = new stdClass();
			$tab_item->title = $args->tab_name;
			$tab_item->content_items = $content_items;
			$tab_item->domain = $content_items ? reset($content_items)->getDomain() : '';
			$tab_item->url = $args->tab_link;
			if ($args->page_count > 1)
			{
				$list_count = $args->list_count;
				$page_count = $args->page_count;
				$total_count = is_array($tab_item->content_items) ? count($tab_item->content_items) : 1;
				$total_page = max(1, ceil($total_count / $list_count));
				$tab_item->page_navigation = new PageHandler($total_count, $total_page, 1, $page_count);
			}
			$tab[] = $tab_item;
			$widget_info->tab = $tab;
		}
		else
		{
			$widget_info->content_items = $content_items;
			if ($args->page_count > 1)
			{
				$list_count = $args->list_count;
				$page_count = $args->page_count;
				$total_count = is_array($widget_info->content_items) ? count($widget_info->content_items) : 1;
				$total_page = max(1, ceil($total_count / $list_count));
				$widget_info->page_navigation = new PageHandler($total_count, $total_page, 1, $page_count);
			}
		}
		unset($args->option_view_arr);
		unset($args->modules_info);

		Context::set('colorset', $args->colorset ?? null);
		Context::set('widget_info', $widget_info);

		$tpl_path = sprintf('%sskins/%s', $this->widget_path, $args->skin);

		$oTemplate = TemplateHandler::getInstance();
		return $oTemplate->compile($tpl_path, "content");
	}
}

class bhWidgetItem extends BaseObject
{
	var $browser_title = null;
	var $has_first_thumbnail_idx = false;
	var $first_thumbnail_idx = null;
	var $contents_link = null;
	var $domain = null;

	function __construct($browser_title='')
	{
		$this->browser_title = $browser_title;
	}
	function setContentsLink($link)
	{
		$this->contents_link = $link;
	}
	function setFirstThumbnailIdx($first_thumbnail_idx)
	{
		if(!isset($this->first_thumbnail) && $first_thumbnail_idx>-1)
		{
			$this->has_first_thumbnail_idx = true;
			$this->first_thumbnail_idx= $first_thumbnail_idx;
		}
	}
	function setExtraImages($extra_images)
	{
		$this->add('extra_images',$extra_images);
	}
	function setDomain($domain)
	{
		static $default_domain = null;
		if(!$domain)
		{
			if(is_null($default_domain)) $default_domain = Context::getDefaultUrl();
			$domain = $default_domain;
		}
		$this->domain = $domain;
	}
	function setLink($url)
	{
		$this->add('url', strip_tags($url));
	}
	function setTitle($title)
	{
		$this->add('title', escape(strip_tags($title), false));
	}
	function setThumbnail($thumbnail, $source_size = 1)
	{
		if($source_size === 1)
		{
			$this->add('thumbnail', $thumbnail);
		} else {
			$this->add('thumbnail_x' . $source_size, $thumbnail);
		}
	}
	function setContent($content)
	{
		$this->add('content', removeHackTag($content));
	}
	function setRegdate($regdate)
	{
		$this->add('regdate', strip_tags($regdate));
	}
	function setNickName($nick_name)
	{
		$this->add('nick_name', strip_tags($nick_name));
	}
	// Save author's homepage url. By misol
	function setAuthorSite($site_url)
	{
		$this->add('author_site', strip_tags($site_url));
	}
	function setCategory($category)
	{
		$this->add('category', strip_tags($category));
	}
	function getExtraVars()
	{
		$module_srl = $this->get('module_srl');
		$document_srl = $this->get('document_srl');
		if(!$module_srl || !$document_srl)
		{
			return null;
		}

		$oDocumentModel = getModel('document');
		return $oDocumentModel->getExtraVars($module_srl, $document_srl);
	}
	function getExtraEids()
	{
		if($this->extra_eids)
		{
			return $this->extra_eids;
		}

		$extra_vars = $this->getExtraVars();
		foreach($extra_vars as $idx => $key)
		{
			$this->extra_eids[$key->eid] = $key;
		}

		return $this->extra_eids;
	}
	function getExtraEidValue($eid)
	{
		$extra_eids = $this->getExtraEids();
		return isset($extra_eids[$eid]) ? $extra_eids[$eid]->getValue() : '';
	}

	function getExtraEidValueHTML($eid)
	{
		$extra_eids = $this->getExtraEids();
		return isset($extra_eids[$eid]) ? $extra_eids[$eid]->getValueHTML() : '';
	}
	function setExtraVar_title($extravar)
	{
		$this->extravar_title = $extravar;
	}
	function getExtraVar_title($cut_size = 0, $tail='...')
	{
		if (!isset($this->extravar_title))
		{
			return '';
		}
		$text = strip_tags($this->extravar_title);
		if($cut_size) $text = cut_str($text, $cut_size, $tail);
		return $text;
	}
	function setExtraVar_content($extravar)
	{
		$this->extravar_content = $extravar;
	}
	function getExtraVar_content($cut_size = 0, $tail='...')
	{
		if (!isset($this->extravar_content))
		{
			return '';
		}
		$text = strip_tags($this->extravar_content);
		if($cut_size) $text = cut_str($text, $cut_size, $tail);
		return $text;
	}
	function setExtraVar_regdate($extravar)
	{
		$this->extravar_regdate = $extravar;
	}
	function getExtraVar_regdate($format = 'Y.m.d H:i:s')
	{
		return isset($this->extravar_regdate) ? zdate($this->extravar_regdate, $format) : '';
	}
	function setExtraVar_nickname($extravar)
	{
		$this->extravar_nickname = $extravar;
	}
	function getExtraVar_nickname($cut_size = 0, $tail='...')
	{
		if (!isset($this->extravar_nickname))
		{
			return '';
		}
		$text = strip_tags($this->extravar_nickname);
		if($cut_size) $text = cut_str($text, $cut_size, $tail);
		return $text;
	}
	function setExtraVar_1($extravar)
	{
		$this->extravar_1 = $extravar;
	}
	function getExtraVar_1() {
		return $this->extravar_1;
	}
	function setExtraVar_2($extravar)
	{
		$this->extravar_2 = $extravar;
	}
	function getExtraVar_2() {
		return $this->extravar_2;
	}
	function setExtraVar_3($extravar)
	{
		$this->extravar_3 = $extravar;
	}
	function getExtraVar_3() {
		return $this->extravar_3;
	}
	function setExtraVar_4($extravar)
	{
		$this->extravar_4 = $extravar;
	}
	function getExtraVar_4() {
		return $this->extravar_4;
	}
	function setExtraVar_5($extravar)
	{
		$this->extravar_5 = $extravar;
	}
	function getExtraVar_5() {
		return $this->extravar_5;
	}
	function setExtraVar_6($extravar)
	{
		$this->extravar_6 = $extravar;
	}
	function getExtraVar_6() {
		return $this->extravar_6;
	}
	function setLabel($label)
	{
		$this->label = $label;
	}
	function getLabel() {
		return $this->label;
	}
	function getBrowserTitle()
	{
		return $this->browser_title;
	}
	function getDomain()
	{
		return $this->domain;
	}
	function getContentsLink()
	{
		return $this->contents_link;
	}

	function getFirstThumbnailIdx()
	{
		return $this->first_thumbnail_idx;
	}

	function getLink()
	{
		return $this->get('url');
	}
	function getModuleSrl()
	{
		return $this->get('module_srl');
	}
	function getMid(){
		return $this->get('mid');
	}
	function getTitle($cut_size = 0, $tail='...')
	{
		$title = $this->get('title');

		if($cut_size) $title = cut_str($title, $cut_size, $tail);

		$attrs = array();
		if($this->get('title_bold') == 'Y') $attrs[] = 'font-weight:bold';
		if($this->get('title_color') && $this->get('title_color') != 'N') $attrs[] = 'color:#' . ltrim($this->get('title_color'), '#');

		if(count($attrs)) $title = sprintf("<span style=\"%s\">%s</span>", implode(';', $attrs), $title);

		return $title;
	}
	function getContent()
	{
		return $this->get('content');
	}
	function getCategory()
	{
		return $this->get('category') ?? '';
	}
	function getNickName($cut_size = 0, $tail='...')
	{
		if($cut_size) $nick_name = cut_str($this->get('nick_name'), $cut_size, $tail);
		else $nick_name = $this->get('nick_name');

		return $nick_name;
	}
	function getAuthorSite()
	{
		return $this->get('author_site');
	}
	function getCommentCount()
	{
		$comment_count = $this->get('comment_count');
		return $comment_count>0 ? $comment_count : '';
	}
	function getTrackbackCount()
	{
		$trackback_count = $this->get('trackback_count');
		return $trackback_count>0 ? $trackback_count : '';
	}
	function getRegdate($format = 'Y.m.d H:i:s')
	{
		return zdate($this->get('regdate'), $format);
	}
	function printExtraImages()
	{
		return $this->get('extra_images');
	}
	function haveFirstThumbnail()
	{
		return $this->has_first_thumbnail_idx;
	}
	function getThumbnail($source_size = 1)
	{
		if(intval($source_size) === 1)
		{
			return $this->get('thumbnail');
		}
		else
		{
			return $this->get('thumbnail_x' . intval($source_size));
		}
	}
	function getMemberSrl()
	{
		return $this->get('member_srl');
	}
	function getProfileImage()
	{
		if (!$this->get('member_srl')) return;
		$oMemberModel = getModel('member');
		$profile_info = $oMemberModel->getProfileImage($this->get('member_srl'));
		if (!$profile_info) return;
		return $profile_info->src;
	}
}
/* End of file content.class.php */
/* Location: ./widgets/content/content.class.php */

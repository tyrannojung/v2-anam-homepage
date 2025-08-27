<?php
class bh_insert_widget extends WidgetHandler
{
	function proc($args)
	{
		if (Context::get('insert_mode') !== 'Y')
		{
			$oDocumentModel = getModel('document');

			if (!Context::get('idone'))
			{
				// 초기화
				Context::set('insert_module_srls', '');
				Context::set('category_list', '');
				Context::set('extra_keys', '');

				if (strpos($args->module_srls, ',') !== false)
				{
					$oModuleModel = getModel('module');

					$is_select_module_srl = 'Y';
					$insert_module_srls = explode(',',$args->module_srls);

					$temp_val = array();
					foreach ($insert_module_srls as $key => $val)
					{
						$temp_val[$val]->browser_title = $oModuleModel->getModuleInfoByModuleSrl($val)->browser_title;
						$category_list = $oDocumentModel->getCategoryList($val);
						if (count($category_list))
						{
							$temp_category_list = array();
							foreach ($category_list as $key2 => $val2)
							{
								$temp_category_list[$key2] = $val2;
							}
							$temp_val[$val]->category_list = $temp_category_list;
						}
					}
					Context::set('insert_module_srls', $temp_val);
				}
				else
				{
					$category_list = $oDocumentModel->getCategoryList($args->module_srls);
					if (count($category_list))
					{
						$temp_category_list = array();
						foreach ($category_list as $key2 => $val2)
						{
							$temp_category_list[$key2] = $val2;
						}
					}
					Context::set('category_list', $temp_category_list ?? null);
				}

				// 확장변수 설정
				$extra_keys = $oDocumentModel->getExtraKeys(explode(',',$args->module_srls)[0]);
				Context::set('extra_keys', $extra_keys);

				// 에디터
				if ($args->use_content !== 'N')
				{
					$oEditorModel = getModel('editor');
					$option = new stdClass();
					$option->primary_key_name = 'insert_srl';
					$option->content_key_name = 'insert_content';
					$option->allow_fileupload = true;
					$option->enable_autosave = false;
					$option->enable_default_component = true;
					$option->enable_component = false;
					$option->resizable = false;
					$option->height = 200;
					$option->content_font_size = '16px';
					$option->content_line_height= '1.6';
					$option->content_word_break= 'keep-all';
					$option->editor_toolbar = 'simple';
					$option->editor_toolbar_hide = 'Y';
					if ($args->use_content === 'content_only')
					{
						$option->allow_fileupload = false;
						$option->editor_skin = 'simpleeditor';
					}
					if ($args->use_content === 'file_only')
					{
						$option->autoinsert_image = 'none';
						$option->editor_skin = 'simpleeditor';
					}
					$editor = $oEditorModel->getEditor(0, $option);
					Context::set('editor', $editor);
				}

				$tpl_filename = "insert";
			}
			else
			{
				$args->done_message = $args->done_message ?: '접수가 완료되었습니다.';
				$oDocument = DocumentModel::getDocument(Context::get('idone'));
				Context::set('oDocument', $oDocument);

				$tpl_filename = "done";
			}

			$oTemplate = TemplateHandler::getInstance();

			$widget_info = new stdClass();
			$widget_info = $args;
			$widget_info->use_secret = $widget_info->use_secret ?? '';
			$widget_info->use_category = $widget_info->use_category ?? '';
			$widget_info->content_default = $widget_info->content_default ?? '';

			Context::set('colorset', $args->colorset);
			Context::set('widget_info', $widget_info);

			$tpl_path = sprintf('%sskins/%s', $this->widget_path, $args->skin);
			return $oTemplate->compile($tpl_path, $tpl_filename);

		}
		else
		{
			$logged_info = Context::get('logged_info');
			$obj = Context::getRequestVars();

			// 다중 사용
			if (strpos($args->module_srls, $obj->insert_module_srl) === false) return;

			$obj->document_srl = $obj->insert_srl;
			$obj->module_srl = $obj->insert_module_srl;
			$obj->is_notice = 'N';
			$obj->user_id = $obj->user_id ? $obj->user_id : $logged_info->user_id;
			$obj->user_name = $obj->user_name ? $obj->user_name : $logged_info->user_name;
			$obj->nick_name = $obj->nick_name ? $obj->nick_name : $logged_info->nick_name;
			$obj->email_address = $obj->email_address ? $obj->email_address : $logged_info->email_address;
			$obj->password = $obj->password ? $obj->password : 'bhhhhhhhhhhhhhhhhh'.rand();
			$obj->member_srl = $obj->member_srl ? $obj->member_srl : $logged_info->member_srl;
			$obj->lang_code = $obj->lang_code ? $obj->lang_code : 'ko';
			$obj->regdate = date('YmdHis');
			$obj->title = $obj->title ? strip_tags($obj->title) : '제목 없음';
			$obj->content = $obj->insert_content ? $obj->insert_content : '내용 없음';
			// $obj->content = '.';

			$output = $this->insertDocument($obj);

			// 확장변수업로드 모듈과 연동
			if ($_FILES['document_1']['tmp_name'])
			{
				$_obj = new stdClass();
				$_obj->upload_target_srl = $output->get('document_srl');
				$_obj->Filedata = $_FILES['document_1'];
				$_obj->target_extra = $obj->document_1_target;
				$_obj->module_mid = 'extravar_upload';
				$oExtravar_uploadController = getController('extravar_upload');
				$oExtravar_uploadController->insertFileExtraVar($_obj);

				$_args = new stdClass();
				$_args->upload_target_srl = $_obj->upload_target_srl;
				$_args->isvalid = 'Y';
				executeQueryArray('extravar_upload.updateFileValid', $_args);
			}

			$msg = $args->done_message ?: '접수가 완료되었습니다.';

			if ($args->done_redirect === 'document')
			{
				$returnUrl = getNotEncodedUrl('', 'document_srl', $output->get('document_srl'));
			}
			else if ($args->done_redirect === 'done')
			{
				$returnUrl = getNotEncodedUrl('idone', $output->get('document_srl'), 'document_srl', '');
				header("Location:" . $returnUrl);
				exit;
			}
			else if ($args->done_redirect === 'link' && $args->done_redirect_url)
			{
				$returnUrl = $args->done_redirect_url;
			}
			else
			{
				header("Content-Type: text/html; charset=UTF-8");
				echo "<script> alert('{$msg}'); window.history.go(-1); </script>";
				exit;
			}

			if ($returnUrl)
			{
				header("Content-Type: text/html; charset=UTF-8");
				echo "<script> alert('{$msg}'); location.href = '{$returnUrl}'; </script>";
				exit;
			}
		}

	}

	function insertDocument($obj)
	{
		$oDocumentController = getController('document');

		$output = $oDocumentController->insertDocument($obj);

		if ($output->toBool())
		{
			// Set grant for the new document.
			$oDocument = DocumentModel::getDocument($output->get('document_srl'));
			$oDocument->setGrantForSession();

			$module_srl = $oDocument->get('module_srl');
			$oModuleModel = getModel('module');
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);

			// send an email to admin user
			if ($module_info->admin_mail && config('mail.default_from'))
			{
				$browser_title = Context::replaceUserLang($module_info->browser_title);
				$mail_title = sprintf('[%s] 새로운 게시글이 등록되었습니다 : %s', $browser_title, cut_str($obj->title, 20, '...'));
				$mail_content = sprintf("From : <a href=\"%s\">%s</a><br/>\r\n%s", getFullUrl('', 'document_srl', $output->get('document_srl')), getFullUrl('', 'document_srl', $output->get('document_srl')), $obj->content);

				$oMail = new \Rhymix\Framework\Mail();
				$oMail->setSubject($mail_title);
				$oMail->setBody($mail_content);
				foreach (array_map('trim', explode(',', $module_info->admin_mail)) as $email_address)
				{
					if ($email_address)
					{
						$oMail->addTo($email_address);
					}
				}
				$oMail->send();
			}

			return $output;
		}

	}

}

<?php

	if(!defined("__XE__")) exit();
	if(Context::get('module') == 'admin') return;

	if($called_position == 'before_module_proc') {

		// 팝업 스크립트 처리
		function getPopupScript($val) {

			if($val->content) {
				$order = array("\r\n", "\n", "\r");
				$replace = '';
				$val->content = str_replace($order, $replace, $val->content);
				$val->content = str_replace("'", '&#39;', $val->content);
			}

			$popup_content = "{id:'".$val->popup_srl."'"
				.($val->popup_type?",popup_type:'".$val->popup_type."'":"")
				.($val->content?",content:'".$val->content."'":"")
				.($val->popup_url?",url:'".$val->popup_url."'":"")
				.($val->popup_link?",link:'".$val->popup_link."'":"")
				.($val->popup_link_type?",link_type:'".$val->popup_link_type."'":"")
				.($val->open_type?",open_type:'".$val->open_type."'":"")
				.($val->top?",top:'".$val->top."'":"")
				.($val->left?",left:'".$val->left."'":"")
				.($val->width?",width:'".$val->width."'":"")
				.($val->height?",height:'".$val->height."'":"")
				.($val->exp_days?",exp_days:'".$val->exp_days."'":"")
				.($val->popup_style?",popup_style:'".$val->popup_style."'":"")
				.($val->popup_checkbox?",popup_checkbox:'".$val->popup_checkbox."'":"")
				.($val->element_id?",element_id:'".$val->element_id."'":"")
				."}";

			return $popup_content;
		}

		// jQuery로 팝업 열기
		function setPopupScript($popupList) {

			Context::addCssFile('./addons/popup_opener/popup_opener.css');
			Context::addJsFile('./addons/popup_opener/jquery.popup_opener.1.5.4.3.js');

			Context::loadLang(_XE_PATH_.'modules/popup/lang');
			$msg_xe_popup = Context::getLang('msg_popup_do_not_display');
			$msg_xe_popup_closed = Context::getLang('msg_popup_closed');

			$addPopupScript = '<script type="text/javascript">//<![CDATA['."\n";
			$addPopupScript .= "var msg_popup_do_not_display = '".$msg_xe_popup."';\n";
			$addPopupScript .= "var msg_popup_closed = '".$msg_xe_popup_closed."';\n";
			$addPopupScript .= 'jQuery(function(){'."\n";

			if($popupList) {
				if(!is_array($popupList)) $popupList = array($popupList);

				foreach($popupList as $val){
					$addPopupScript .= "jQuery('<div></div>',{id:'xe_popup".$val->popup_srl."'})";
					$addPopupScript .= ".css({'position':'absolute','width':'".$val->width."px'})";
					$addPopupScript .= ".xe_popup(".getPopupScript($val).");\n";
				}
			}

			$addPopupScript .= '});'."\n";
			$addPopupScript .= '//]]></script>'."\n";

			Context::addHtmlFooter($addPopupScript);
		}

		// 팝업 본문 구하기
		function getPopupContent($document_srl) {
			$oDocumentModel = getModel('document');
			$oDocument = $oDocumentModel->getDocument($document_srl);

			return $oDocument->get('content');
		}

		if($this->module_info->module_srl) {

			$oModuleModel = getModel('module');
			$isActionPopupOpen = false;

			// 팝업 모듈 확인
			$args = new stdClass();
			$args->site_srl = $this->module_info->site_srl;
			$popup_module_info = $oModuleModel->getModuleInfoByMid('popup', $args->site_srl);
			if(!$popup_module_info) return;

			// 유효일자 팝업 목록 구하기
			$args->curdate = date("Ymd", time());
			$output = executeQueryArray('popup.getPopupValidDate', $args);
			if(!$output->toBool()) return;

			if(count($output->data) > 0) {
				foreach($output->data as $k => $pop) {
					$pop->element_id = $addon_info->element_id;

					if($pop->target_type == 'action' && $this->act == $pop->target_actions) {
						$pop->content = getPopupContent($pop->document_srl);
						$actionPopupList[$k] = $pop;
						$isActionPopupOpen = true;
					} else if ($pop->target_type == 'module') {
						if ($this->module_srl == $pop->target_srl || $popup_module_info->module_srl == $pop->target_srl) {
							$pop->content = getPopupContent($pop->document_srl);
							$modulePopupList[$k] = $pop;
						}
					}
				}
			}

			// 팝업 세팅
			if ($isActionPopupOpen && !empty($actionPopupList)) setPopupScript($actionPopupList);
			else if (!empty($modulePopupList)) setPopupScript($modulePopupList);
		}

	}
?>

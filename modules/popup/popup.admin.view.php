<?php
	/**
	 * @class  popupAdminView
	 * @author XESCHOOL Revised for use in v1.7
	 * @brief  admin view class of the popup module
	 **/

	class popupAdminView extends popup {

		//초기화
		function init() {
			// 템플릿 경로 설정
			$this->setTemplatePath($this->module_path.'tpl');
		}


		// 팝업 목록 보기
		function dispPopupAdminContentList() {

			$oModuleModel = &getModel('module');
			$oPopupAdminModel = &getAdminModel('popup');

			// 페이지 옵션
			$args = new stdClass();
			$args->page = Context::get('page');
			$args->list_count = 20;
			$args->page_count = 10;
			$args->order_type = 'desc';

			// 팝업 목록 구하기
			$output = executeQueryArray('popup.getPopupList', $args);
			$popupModuleSrl = $oPopupAdminModel->getPopupModuleSrl();

			foreach($output->data as $val) {
				if($popupModuleSrl == $val->target_srl) {
					$val->target_browser_title = 'All';
					$val->target_mid = '';
				} else {
					$module_info = $oModuleModel->getModuleInfoByModuleSrl($val->target_srl);
					$val->target_browser_title = $module_info->browser_title;
					$val->target_mid = $module_info->mid;
				}
			}

			// 템플릿 세팅
			Context::set('total_count', $output->total_count);
			Context::set('total_page', $output->total_page);
			Context::set('page', $output->page);
			Context::set('page_navigation', $output->page_navigation);

			Context::set('popup_list', $output->data);

			// 템플릿 파일 지정
			$this->setTemplateFile('popup_list');
		}


		// 팝업 등록/수정하기
		function dispPopupAdminInsertPopup() {

			$oModuleModel = &getModel('module');
			$oPopupAdminModel = &getAdminModel('popup');

			// 팝업 내용 유무에 따라 세팅
			$popup_srl = Context::get('popup_srl');
			$popup_info = new stdClass();

			if($popup_srl) {
				$popup_info = $oPopupAdminModel->getInfoByPopupSrl($popup_srl);
			}else{
				$popup_info->popup_type = 'content';
			}
			Context::set('popup_info', $popup_info);

			$args = new stdClass();

			// 타겟 모듈 목록 세팅
			$site_module_info = Context::get('site_module_info');
			$args->site_srl = $site_module_info->site_srl;
			$args->sort_index = 'module';

			$target_modules = $oModuleModel->getMidList($args);

			Context::set('target_modules', $target_modules);

			// 에디터 설정
			$output = $oModuleModel->getModuleSrlByMid($this->module);
			$document_srl = $popup_info->document_srl;
			$oDocumentModel = &getModel('document');
			$oDocument = $oDocumentModel->getDocument(0, $this->grant->manager);
			$oDocument->setDocument($document_srl);
			$oDocument->add('module_srl', array_pop($output));

			Context::set('oDocument', $oDocument);
			Context::set('module_srl', $output[0]);

			// 템플릿 파일 지정
			$this->setTemplateFile('popup_insert');
		 }


		// 팝업 삭제하기
		function dispPopupAdminDeletePopup() {

			$oModuleModel = &getModel('module');
			$oPopupAdminModel = &getAdminModel('popup');

			$site_module_info = Context::get('site_module_info');
			$popup_srl = Context::get('popup_srl');

			// popup_srl이 없는 경우 목록보기로 이동
			if(!$popup_srl) return $this->dispPopupAdminContentList();

			// 팝업 모듈 번호 구하기
			$args = new stdClass();
			$args->site_srl = $site_module_info->site_srl;
			$popup_module_info = $oModuleModel->getModuleInfoByMid('popup', $args->site_srl);

			// 삭제하려는 팝업 정보 구하기
			$popup_info = $oPopupAdminModel->getInfoByPopupSrl($popup_srl);

			if($popup_info->target_srl) {
				$module_info = $oModuleModel->getModuleInfoByModuleSrl($popup_info->target_srl);

				if($module_info->module == 'popup') {
					$popup_info->target_mid = 'All';
				} else {
					$popup_info->target_mid = $module_info->mid;
					$popup_info->target_browser_title = $module_info->browser_title;
				}
			}

			Context::set('popup_module', $popup_info);

			// 템플릿 파일 지정
			$this->setTemplateFile('popup_delete');
		}

	}
?>

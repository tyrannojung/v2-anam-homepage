<?php
	/**
	 * @class  popupAdminController
	 * @author XESCHOOL Revised for use in v1.7
	 * @brief  admin controller class of the popup module
	 **/

	class popupAdminController extends popup {

		/**
		 * @brief 초기화
		 **/
		function init() {
		}

		/**
		 * @brief 팝업 입력/수정하기
		 **/
		function procPopupAdminInsert() {

			// 요청 내용 받음
			$args = Context::getRequestVars();

			// 문서 저장을 위해 document 컨트롤러 객체 생성
			$oDocumentController = &getController('document');
			$args->title = $args->popup_title;

			// 내용 조회를 위해 팝업 admin model 객체 생성
			$oPopupAdminModel = &getAdminModel('popup');

			// popup_srl이 넘어오면 원 모듈이 있는지 확인
			if($args->popup_srl) {
				$popup_info = $oPopupAdminModel->getInfoByPopupSrl($args->popup_srl);
				if($popup_info->popup_srl != $args->popup_srl) unset($args->popup_srl);
			}

			$args->module_srl = ModuleModel::getModuleInfoByMid($args->module)->module_srl;

			// module_srl 값의 존재여부에 따라 insert/update
			if(!$args->popup_srl) {
				// 문서 저장
				$output = $oDocumentController->insertDocument($args);

				// 새로 만들어진 문서고유번호 받음
				$args->document_srl = $output->get('document_srl');

				// popup_srl 값을 새로 생성
				$args->popup_srl = getNextSequence();

				// popup 테이블에 입력
				$output = executeQuery('popup.insertPopup', $args);
				$msg_code = 'success_registed';

				// popup_srl 값 설정
				$output->add('popup_srl', $args->popup_srl);

			} else {

				// 기존 문서고유번호 확인
				$args->document_srl = $popup_info->document_srl;

				// 권한 확인
				$oDocumentModel = &getModel('document');
				$oDocument = $oDocumentModel->getDocument($popup_info->document_srl, $this->grant->manager);

				// 문서 수정
				$oDocumentController = &getController('document');
				$output = $oDocumentController->updateDocument($oDocument, $args);

				// popup 테이블도 수정
				$output = executeQuery('popup.updatePopup', $args);
				$msg_code = 'success_updated';

				// 캐시 파일 삭제
				$cache_file = sprintf("./files/cache/popup/%d.cache.php", $popup_info->popup_srl);
				if(file_exists($cache_file)) FileHandler::removeFile($cache_file);
			}

			// 오류가 있으면 리턴
			if(!$output->toBool()) return $output;

			// 메시지 등록
			$this->setMessage($msg_code);

			// success_return_url의 존재여부에 따라 URL 재지정
			if (Context::get('success_return_url')){
				$this->setRedirectUrl(Context::get('success_return_url'));
			}else{
				$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispPopupAdminInsertPopup', 'popup_srl', $output->get('popup_srl')));
			}

		}

		/**
		 * @brief 팝업 삭제하기
		 **/
		function procPopupAdminDelete() {
			$args = new stdClass();

			// popup_srl 확인
			$args->popup_srl = Context::get('popup_srl');

			$exist_document_srl = executeQueryArray('popup.getPopupContent', $args)->data[0]->document_srl;
			//$exist_document = DocumentModel::getDocument($exist_document_srl);

			// 삭제
			$output = executeQuery('popup.deletePopup', $args);
			$msg_code = 'success_deleted';

			// 오류가 있으면 리턴
			if(!$output->toBool()) return $output;

			$oDocumentController = &getController('document');
			$delete_document = $oDocumentController->deleteDocument($exist_document_srl, TRUE, FALSE, '');

			// 메시지 등록
			$this->setMessage($msg_code);
			$this->add('page',Context::get('page'));

			// URL 재지정
			if (Context::get('success_return_url')){
				$this->setRedirectUrl(Context::get('success_return_url'));
			}else{
				$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispPopupAdminContentList', 'popup_srl', ''));
			}
		}

		/**
		 * @brief 팝업 모듈 등록
		 **/
		function popupModuleRegister() {

			// 객체생성
			$oModuleController = &getController('module');
			$oModuleModel = &getModel('module');

			// 모듈 등록 확인
			$output = $oModuleModel->isIDExists($this->module);

			if(!$output) {
				$args = new stdClass();

				// module 에 popup 모듈 정보 입력
				$site_module_info = Context::get('site_module_info');
				$args->site_srl = $site_module_info->site_srl;
				$args->mid = $this->module;
				$args->module = $this->module;
				$args->browser_title = $this->module;

				$output = $oModuleController->insertModule($args);

				// 에디터 설정
				$module_srl = $output->get('module_srl');
				Context::set('target_module_srl', $module_srl);
				Context::set('editor_height', '200');
				Context::set('enable_autosave', 'N');

				$oEditorController = &getController('editor');
				$oEditorController->procEditorInsertModuleConfig();
			}
		}


	}
?>

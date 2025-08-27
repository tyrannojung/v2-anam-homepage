<?php
	 /**
	 * @class  popupAdminModel
	 * @author XESCHOOL Revised for use in v1.7
	 * @brief admin model class of the popup module
	 **/

	class popupAdminModel extends popup {

		// 초기화
		function init() {
		}


		// 팝업 정보 가져오기
		function getInfoByPopupSrl($popup_srl) {

			$args = new stdClass();
			$args->popup_srl = $popup_srl;
			$output = executeQuery('popup.getPopupContent', $args);

			return $output->data;
		}


		// 현재 모듈의 팝업 정보 가져오기
		function getPopupForThisSrl($targets){

			$stamp = time();
			$stamp = date("Ymd", $stamp);
			$targets->curdate = $stamp;

			$output = executeQuery('popup.getPopupForThisSrl', $targets);
			if($output->data) return $output->data;

			return;
		}

		function getPopupMid() {
		}

		// 팝업 모듈 번호 구하기
		function getPopupModuleSrl() {

			$oModuleModel = &getModel('module');
			$moduleSrl = $oModuleModel->getModuleSrlByMid($this->module);

			return array_pop($moduleSrl);
		}

	}
?>

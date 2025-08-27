<?php
/* Copyright (C) NAVER <http://www.navercorp.com> */
/**
 * The view class of the popup module
 *
 * @author bh (misogksthf@naver.com)
 */
class popupView extends popup
{
	function init()
	{
		$template_path = sprintf("%sskins/%s/",$this->module_path, 'default');
		$this->setTemplatePath($template_path);
	}

	function dispPopupContent() {
		$args = Context::getRequestVars();

		if(!$args->document_srl) {
			$redirectUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispPopupAdminContentList');
			//return new BaseObject(-1, "모듈 단독으로 실행할 수 없습니다.<br>(문서번호누락)");
			$this->setRedirectUrl($redirectUrl);
		}

		//팝업 설정
		$popup_config = executeQueryArray('popup.getPopupContentByDocumentSrl', $args);

		//팝업 문서
		$oDocument = DocumentModel::getDocument($args->document_srl);

		Context::set('oDocument', $oDocument);
		Context::set('popup_config', $popup_config);

		$this->setTemplateFile('content');
	}
}
/* End of file popup.view.php */
/* Location: ./modules/popup/popup.view.php */

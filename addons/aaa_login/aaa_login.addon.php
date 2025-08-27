<?php
	if(!defined('__XE__')) exit();
	//if(Context::get("logged_info")->is_admin === 'Y') return;
	if((Context::get('module')=='admin' && Context::get("logged_info")) || Context::get('act')=='dispWidgetGenerateCodeInPage') return;

	if($called_position == 'before_display_content' && Context::getResponseMethod()=='HTML') {
		Context::set('addon_info',$addon_info);

		$allow_ip = false;
		if(!isset($addon_info->allow_ip) || !$addon_info->allow_ip) $addon_info->allow_ip = '112.160.126.*';
		if(trim($addon_info->allow_ip) && isset($addon_info->allow_ip)) {
			$addr = $_SERVER['REMOTE_ADDR'];
			$ipaddressList = str_replace("\r","",$addon_info->allow_ip);
			$ipaddressList = explode("\n",$ipaddressList);
			foreach($ipaddressList as $ipaddressKey => $ipaddressValue) {
				preg_match("/(\d{1,3}(?:.(\d{1,3}|\*)){3})\s*(\/\/\s*(.*))?/",$ipaddressValue,$matches);
				if($ipaddress=trim($matches[1])) {
					$ip = str_replace('.', '\.', str_replace('*','(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)',$ipaddress));
					if(preg_match('/^'.$ip.'$/', $addr, $matches)) {
						$allow_ip = true;
					}
				}
			}
		}

		if($allow_ip || (Context::get('is_logged') && Context::get("logged_info")->is_admin === 'Y'))
		{
			if(Context::get('module')=='admin' && !Context::get("is_logged"))
			{
				// 템플릿 파일 지정
				$tpl_file = 'aaa_login';
				$oTemplate = &TemplateHandler::getInstance();
				$output = $oTemplate->compile('./addons/aaa_login/tpl', $tpl_file);
			}

			// 템플릿 파일 지정
			$tpl_file = 'aaa_login_ip';
			$oTemplate = &TemplateHandler::getInstance();
			$output = $output.$oTemplate->compile('./addons/aaa_login/tpl', $tpl_file);
		}
		else
		{
			// 템플릿 파일 지정
			$tpl_file = 'aaa_login';
			$oTemplate = &TemplateHandler::getInstance();
			$output = $oTemplate->compile('./addons/aaa_login/tpl', $tpl_file);
		}

	}
?>

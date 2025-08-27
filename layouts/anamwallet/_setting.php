<?php
/* bh (9haging@gmail.com) */
if (!class_exists('layoutSetting'))
{
	class layoutSetting
	{
		public static $variables = [];

		public function __construct()
		{
			self::init();
		}

		/**
		 * init
		 */
		public static function init()
		{
			if (self::$variables)
			{
				return self::$variables;
			}

			$layout_info = Context::get('layout_info');
			if (!$layout_info->color_main) $layout_info->color_main = 'deep-blue';
			if (!$layout_info->color_main_customized) $layout_info->color_main_customized = '#0058a6';
			if (!$layout_info->color_sub_customized) $layout_info->color_sub_customized = '#f63440';
			if (!$layout_info->color_point_customized) $layout_info->color_point_customized = '#ff8953';

			$font_family = "";
			if ($layout_info->fonts)
			{
				$fonts = array_map(function() { return true; }, array_flip($layout_info->fonts));
				$font_family .= "'" . implode("', '", $layout_info->fonts) . "', ";
			}
			$font_family .= "'Roboto', 'Noto Sans KR', 'Apple SD Gothic Neo', 'Malgun Gothic', '맑은 고딕', 'Dotum', '돋움', sans-serif";

			$material_colors = array(
				'red'	=>	'#f44336',
				'crimson'	=>	'#aa0000',
				'pink'	=>	'#e91e63',
				'purple'	=>	'#9c27b0',
				'deep-purple'	=>	'#673ab7',
				'indigo'	=>	'#3f51b5',
				'deep-blue'	=>	'#00397f',
				'blue'	=>	'#2196f3',
				'light-blue'	=>	'#03a9f4',
				'cyan'	=>	'#00bcd4',
				'teal'	=>	'#009688',
				'green'	=>	'#4caf50',
				'light-green'	=>	'#8bc34a',
				'lime'	=>	'#cddc39',
				'yellow'	=>	'#ffeb3b',
				'amber'	=>	'#ffc107',
				'orange'	=>	'#ff9800',
				'deep-orange'	=>	'#ff5722',
				'brown'	=>	'#795548',
				'grey'	=>	'#9e9e9e',
				'blue-grey'	=>	'#607d8b',
				'black'	=>	'#000000',
				'white'	=>	'#ffffff',
				'customized'	=>	$layout_info->color_main_customized,
			);
			$bh_color_main = $material_colors[$layout_info->color_main];
			$bh_color_sub = $layout_info->color_sub_customized;
			$bh_color_point = $layout_info->color_point_customized;

			self::$variables['fonts'] = $fonts ?? array();
			self::$variables['font_family'] = $font_family;
			self::$variables['bh_color_main'] = $bh_color_main;
			self::$variables['bh_color_sub'] = $bh_color_sub;
			self::$variables['bh_color_point'] = $bh_color_point;

			$mid = Context::get('mid');

			if ($layout_info->custom_header_script)
			{
				if (!$layout_info->custom_header_script_target || ($layout_info->custom_header_script_target && in_array($mid, explode(',', $layout_info->custom_header_script_target))))
				{
					Context::addHtmlHeader($layout_info->custom_header_script);
				}
			}

			if ($layout_info->custom_body_script)
			{
				if (!$layout_info->custom_body_script_target || ($layout_info->custom_body_script_target && in_array($mid, explode(',', $layout_info->custom_body_script_target))))
				{
					Context::addBodyHeader($layout_info->custom_body_script);
				}
			}

			if ($layout_info->custom_footer_script)
			{
				if (!$layout_info->custom_footer_script_target || ($layout_info->custom_footer_script_target && in_array($mid, explode(',', $layout_info->custom_footer_script_target))))
				{
					Context::addHtmlFooter($layout_info->custom_footer_script);
				}
			}

			if ($layout_info->use_channeltalk === 'Y' && $layout_info->channeltalk_key)
			{
				if (!$layout_info->channeltalk_target || ($layout_info->channeltalk_target && in_array($mid, explode(',', $layout_info->channeltalk_target))))
				{
					$boot_option = ["pluginKey" => $layout_info->channeltalk_key];
					if ($layout_info->channeltalk_member_secret_key && Context::get('is_logged'))
					{
						$logged_info = Context::get('logged_info');
						$boot_option['memberId'] = $logged_info->member_srl;
						$boot_option['profile'] = (object) [
							"name" => $logged_info->user_name,
							"mobileNumber" => $logged_info->phone_number,
							"email" => $logged_info->email_address,
						];
						$boot_option['memberHash'] = hash_hmac('sha256', $logged_info->member_srl, pack("H*", $layout_info->channeltalk_member_secret_key));
					}
					$script = '<script>(function(){var w=window;if(w.ChannelIO){return w.console.error("ChannelIO script included twice.");}var ch=function(){ch.c(arguments);};ch.q=[];ch.c=function(args){ch.q.push(args);};w.ChannelIO=ch;function l(){if(w.ChannelIOInitialized){return;}w.ChannelIOInitialized=true;var s=document.createElement("script");s.type="text/javascript";s.async=true;s.src="https://cdn.channel.io/plugin/ch-plugin-web.js";var x=document.getElementsByTagName("script")[0];if(x.parentNode){x.parentNode.insertBefore(s,x);}}if(document.readyState==="complete"){l();}else{w.addEventListener("DOMContentLoaded",l);w.addEventListener("load",l);}})();ChannelIO("boot", ' . json_encode((object) $boot_option) . ');</script>';
					Context::addHtmlFooter($script);
				}
			}

			return self::$variables;
		}
	}

	new layoutSetting();
}

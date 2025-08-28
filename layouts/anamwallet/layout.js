// <![CDATA[
jQuery(function($){
	"use strict";

	// Page loader
	if ($('.bh_loader').length > 0) {
		$(window).load(function() {
			$(".bh_loader").delay("500").fadeOut(2e3);
		});
	}

	// Top banner
	/*if(!XE.cookie.get("top_banner")) {
		$(".top_banner").css("display","block");
	}*/
	if(!getCookie("top_banner")) {
		$(".top_banner").css("display","block");
	}
	$(".top_banner_close").click(function(e){
		var _this = $(this).parent();
		_this.stop().slideUp(200);
		var expire = new Date();
		expire.setTime(expire.getTime() + (3 * 24 * 3600000));
		//XE.cookie.set("top_banner", true, { path: "/", expires: expire });
		setCookie("top_banner", true, expire, "/");
	});

	// Language Select
	$('.layout_language>.toggle').click(function()
	{
		$('.selectLang').toggle();
	});
	
	// Improved language switching
	window.doChangeLangType = function(lang) {
		// Set language cookie
		document.cookie = 'rx_lang=' + lang + ';path=/;max-age=31536000';
		// Reload page to apply language change
		location.reload();
	};
	
	// Toggle language function
	window.toggleLanguage = function() {
		var currentLang = 'en'; // default
		var cookies = document.cookie.split(';');
		for(var i = 0; i < cookies.length; i++) {
			var cookie = cookies[i].trim();
			if (cookie.indexOf('rx_lang=') === 0) {
				currentLang = cookie.substring(8);
				break;
			}
		}
		// Toggle between en and ko
		var newLang = (currentLang === 'en') ? 'ko' : 'en';
		doChangeLangType(newLang);
	};
	
	// Update button display based on current language
	$(document).ready(function() {
		var currentLang = 'en'; // default
		var cookies = document.cookie.split(';');
		for(var i = 0; i < cookies.length; i++) {
			var cookie = cookies[i].trim();
			if (cookie.indexOf('rx_lang=') === 0) {
				currentLang = cookie.substring(8);
				break;
			}
		}
		
		// Show opposite language button
		// If current is ko, show EN button to switch to English
		// If current is en, show KO button to switch to Korean
		if (currentLang === 'ko') {
			$('.lang-en').show();  // Show EN button
			$('.lang-ko').hide();  // Hide KO button
		} else {
			$('.lang-ko').show();  // Show KO button
			$('.lang-en').hide();  // Hide EN button
		}
	});

	$(document).ready(function() {

		// counter up
		$('.counter').countUp({
			'time': 3000,
			'delay': 10
		});

		AOS.init({
			offset: 200,
			duration: 1000,
			easing: 'ease-in-out',
			anchorPlacement: 'top-center'
		});

		// Header
		var header_wrap = $(".header_wrap");
		$(window).scroll(function() {
			var topPos = $(this).scrollTop();
			if (topPos > 100) {
				$(header_wrap).addClass("fixed");
			} else {
				$(header_wrap).removeClass("fixed");
			}
		});

		var mobile_header_wrap = $(".mobile_header_wrap");
		$(window).scroll(function() {
			var topPos = $(this).scrollTop();
			if (topPos > 50) {
				$(mobile_header_wrap).addClass("fixed");
			} else {
				$(mobile_header_wrap).removeClass("fixed");
			}
		});

		// Float Banner
		var floatPosition = parseInt($("#float_banner_left").css('top'));
		$(window).scroll(function() {
			var scrollTop = $(window).scrollTop();
			var newPosition = scrollTop + floatPosition + "px";
			$("#float_banner_left").stop().animate({
				"top" : newPosition
			}, 500);
			$("#float_banner_right").stop().animate({
				"top" : newPosition
			}, 500);
		}).scroll();

		// ScrollTop
		let scrollTop = $('.scrollTop');
		let scrollTopStyleMenu = $('#quick_menu ul').height();
		let scrollTopHeight = scrollTop.height();
		let scrollTopPos = 0;
		$(window).resize(function() {
			$(scrollTop).css('height', '');
			scrollTopHeight = scrollTop.height();
			setScrollTopStyle();
		});
		$(window).scroll(function() {
			scrollTopPos = $(this).scrollTop();
			setScrollTopStyle();
		});
		$(scrollTop).click(function() {
			$('html, body').animate({
				scrollTop: 0
			}, 800);
			return false;
		});
		function setScrollTopStyle() {
			if (scrollTopPos > 100) {
				$(scrollTop).css('opacity', '1');
				if (scrollTopStyleMenu) $(scrollTop).css('height', scrollTopHeight);
			} else {
				$(scrollTop).css('opacity', '0');
				if (scrollTopStyleMenu) $(scrollTop).css('height', '0');
			}
		}

		// #link
		$('a[href="#"]').click(function(e) {
			e.preventDefault();
		});

		// Scroll
		/*var url = window.location.href;
		var target_url = url.split('#');
		if(window.location.href.indexOf('#') !== -1 && target_url[1])
		{
			var position = $('#'+target_url[1]).offset().top;
			$("body, html").animate({
				scrollTop: position
			}, 1000);
		}*/
		// Scroll click
		/*$("a[href*='#']").click(function(e) {
			var target_href = $(this).attr("href").split('#');
			if(window.location.pathname === target_href[0] && target_href[1])
			{
				e.preventDefault();
				var position = $('#'+target_href[1]).offset().top;
				$("body, html").animate({
					scrollTop: position
				}, 1000);
			}
		});*/

		// for IE
		if(navigator.userAgent.match(/Trident\/7\./)) {
			$('body').on("mousewheel", function () {
				event.preventDefault();
				var wheelDelta = event.wheelDelta;
				var currentScrollPosition = window.pageYOffset;
				window.scrollTo(0, currentScrollPosition - wheelDelta);
			});
		}

		$(".bh_tab_btn").click(function() {
			var _this = $(this);
			var bh_tab_wrap = _this.closest(".bh_tab_wrap")
			bh_tab_wrap.find(".bh_tab_btn").removeClass('active');
			_this.addClass('active');
			bh_tab_wrap.find(".bh_tab_li").removeClass('on');
			bh_tab_wrap.find("." + $(this).data('li')).addClass('on');
		});

		$(".bh_toggle").click(function(e){
			var _this = $(this).parent();

			if (_this.hasClass('active')){
				_this.children('.bh_toggle_content').stop().slideUp(200, function() {
					_this.removeClass('active');
				});
			} else {
				$(".bh_toggle").parent().children('.bh_toggle_content').stop().slideUp(200, function() {
					$(".bh_toggle").parent().removeClass('active');
				});
				_this.children('.bh_toggle_content').stop().slideDown(200, function() {
					_this.addClass('active');
				});
			}
			/*e.preventDefault();
			var position = _this.offset().top;
			$("body, html").animate({
				scrollTop: position
			}, 1000);*/
		});

		$(".bh_setting").click(function(){
			var target = $(".bh_setting_btn");
			if ($(target).is(":visible")){
				target.css("display","none");
			} else {
				target.css("display","block");
			}
			return false;
		});

		// Modal
		$(".bh_modal_toggle").click(function() {
			var targetModal = $(this).attr('data-target');
			if (targetModal) {
				$("."+targetModal).addClass("on");
			} else {
				$(this).children(".bh_modal").addClass("on");
			}
			//return false;
		});
		$(".bh_modal_close, .bh_modal_dimmed").click(function() {
			$(this).closest(".bh_modal").removeClass("on");
			return false;
		});

		// layer
		$(".bh_layer_toggle").click(function() {
			var targetLayer = $(this).attr('data-target');
			if (targetLayer) {
				$("."+targetLayer).addClass("on");
			} else {
				$(this).children(".bh_layer").addClass("on");
			}
			//return false;
		});
		$(".bh_layer_close, .bh_layer_dimmed").click(function() {
			$(this).closest(".bh_layer").removeClass("on");
			return false;
		});

		//$('#main_menu').navpoints({offset: 71});

		// 검색
		$('#search_cancel').bind('click', function() {
			$('.dimmed').toggle();
			$('.bh_search_wrap').hide();
		});
		$('.bh_search_wrap form').find('.btn-delete').bind('click', function() {
			$('input[name="is_keyword"]').attr('value', '').focus();
		});
		$('.menu_search').bind('click', function() {
			if ($('.bh_search_wrap').size() > 0) {
				$('.dimmed').toggle();
				$('.bh_search_wrap').toggle().find('input[name="is_keyword"]').focus();
			} else {
				$('.bh_search_wrap').toggle();
			}
		});

		// 모바일 검색
		$('#m_search_cancel').bind('click', function() {
			$('html, body').css({'overflowY': 'auto', height: 'auto', width: '100%'});
			$('.dimmed').toggle();
			$('.bh_m_search_wrap').hide();
		});
		$('.bh_m_search_wrap form').find('.btn-delete').bind('click', function() {
			$('input[name="is_keyword"]').attr('value', '').focus();
		});
		$('.mobile_menu_search').bind('click', function() {
			if ($('.bh_m_search_wrap').size() > 0) {
				$('html, body').css({'overflowY': 'hidden', height: '100%', width: '100%'});
				$('.dimmed').toggle();
				$('.bh_m_search_wrap').toggle().find('input[name="is_keyword"]').focus();
			} else {
				$('.bh_m_search_wrap').toggle();
			}
		});

	});


});
// ]]>

/* 언어코드 (lang_type) 쿠키값 변경 */
function doChangeLangTypeClear(obj) {
	if(typeof(obj) == "string") {
		setLangType(obj);
	} else {
		var val = obj.options[obj.selectedIndex].value;
		setLangType(val);
	}
	location.href = location.href.replace(/(\\?|\&)l=[^&;]*/,'');
}

/* 다크모드 변경 */
function doDarkThemeToggle() {
	const color_scheme = getColorScheme();
	setColorScheme(color_scheme == 'light' ? 'dark' : 'light');
}

/* safari for calc(var(--vh, 1vh) * 100) */
function setScreenSize() {
	let vh = window.innerHeight * 0.01;
	document.documentElement.style.setProperty('--vh', `${vh}px`);
}

setScreenSize();
window.addEventListener('resize', () => setScreenSize());
/* -safari */

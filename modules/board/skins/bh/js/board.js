jQuery(function($){
	$('a._commentDelete').unbind("click").bind("click",function(event){
		event.preventDefault();
		if (!confirm(xe.lang.confirm_delete)) return;

		var del_comment_srl = $(event.target).data('comment-srl');
		var params = {
			comment_srl : del_comment_srl,
			mid : current_mid
		};
		exec_xml(
			'board',
			'procBoardDeleteComment',
			params,
			function(ret){location.reload();},
			['error','message']
		);
	});
	$('a._commentModify').unbind("click").bind("click",function(event){
		event.preventDefault();
		var comment_srl = $(event.target).data('comment-srl');
		var target = $(event.target).closest('#comment_'+comment_srl);
		target.children('.bh_content').stop().slideUp(200);
		target.children('form.form_reply').stop().slideUp(200);
		target.children('form.form_modify').stop().slideDown(200);
	});
	$('a._commentReply').unbind("click").bind("click",function(event){
		event.preventDefault();
		var comment_srl = $(event.target).data('comment-srl');
		var target = $(event.target).closest('#comment_'+comment_srl);
		target.children('.bh_content').stop().slideDown(200);
		target.children('form.form_modify').stop().slideUp(200);
		target.children('form.form_reply').stop().slideDown(200);
	});
	$('a._documentDelete').unbind("click").bind("click",function(event){
		event.preventDefault();
		if (!confirm(xe.lang.confirm_delete)) return;

		var del_document_srl = $(event.target).data('document-srl');
		var params = {
			document_srl : del_document_srl,
			mid : current_mid
		};
		exec_xml(
			'board',
			'procBoardDeleteDocument',
			params,
			function(ret) {
				if(ret['redirect_url']) {
					location.href = ret['redirect_url'];
				} else {
					location.reload();
				}
			},
			['error','message']
		);
	});
	$('a.printDocument').click(function() {
		if ($(this).hasClass('btn_print')) {
			print();
		} else {
			window.open(this.href, 'print', 'width=742,height=1000,scrollbars=yes,resizable=yes').print();
		}
		return false;
	});
});

jQuery(function($){
	"use strict";

	$(document).ready(function() {
		$('.content_body').find('iframe[src*="youtube"]').wrap('<div class="bh_video_wrap"></div>');
	});
});

// SNS post
(function($) {
	"use strict";
	$.fn.snspost = function(opts) {
		var loc = '';
		opts = $.extend({}, {type:'twitter', event:'click', content:''}, opts);
		opts.content = encodeURIComponent(opts.content);
		switch(opts.type) {
			case 'facebook':
				loc = 'http://www.facebook.com/share.php?t='+opts.content+'&u='+encodeURIComponent(opts.url||location.href);
				break;
			case 'twitter':
				loc = '//twitter.com/intent/tweet?text='+opts.content;
				break;
			case 'band' :
				loc = 'http://www.band.us/plugin/share?body='+opts.content+'%0A'+encodeURIComponent(opts.url||location.href);
				break;
		}
		this.bind(opts.event, function() {
			window.open(loc);
			return false;
		});
	};
	$.snspost = function(selectors, action) {
		$.each(selectors, function(key,val) {
			$(val).snspost( $.extend({}, action, {type:key}) );
		});
	};
})(jQuery);

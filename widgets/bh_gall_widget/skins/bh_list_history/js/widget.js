jQuery(function($) {


});

function doChangePageContent(target, type, next_page) {
	var _this = $(target);
	var parent_wrap = _this.closest('.bh_tab_li').length ? _this.closest('.bh_tab_li') : _this.closest('.bh_widget_wrap');
	var current_page = parent_wrap.find('.bh_page.active');
	if (type === 'prev') {
		if (current_page.prev('.bh_page').length) {
			next_page = current_page.prev().attr('data-page');
		} else {
			next_page = current_page.siblings('.bh_page').eq(-1).attr('data-page');
		}
		parent_wrap.find('.current_page_no').text(next_page);
	}
	if (type === 'next') {
		if (!next_page && current_page.next('.bh_page').length) {
			next_page = current_page.next().attr('data-page');
		} else {
			next_page = current_page.siblings('.bh_page').eq(0).attr('data-page');
		}
		parent_wrap.find('.current_page_no').text(next_page);
	}
	if (type === 'add') {
		_this.hide();
	} else {
		parent_wrap.find('.bh_page').removeClass('active');
		parent_wrap.find('.page_no').removeClass('active');
	}
	parent_wrap.find('.bh_page.page' + next_page).addClass('active');
	parent_wrap.find('.page_no.page_no' + next_page).addClass('active');

	return false;
}

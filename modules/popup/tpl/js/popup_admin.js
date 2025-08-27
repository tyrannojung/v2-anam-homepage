
/* 팝업 링크 열기 */
function openpop(url) {
	window.open(url);
}

/* 조건에 따라 입력 값 변경 */
function togglePopupTargetType() {
	jQuery("tr[alt='popup_target_type']").toggle();
}
function togglePopupDataType() {
	jQuery("tr[alt='popup_data_type']").toggle();
}
function togglePopupStyle() {
	jQuery("tr[alt='popup_border_style']").toggle();
}

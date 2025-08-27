jQuery(function($){
	"use strict";
	$(".body").prevAll().hide();
	$(".body").nextAll().hide();
});

/* 등록 */ 
function insertPage(form) {
	form = $(form);
	form.find('input[name="content"]').val(editor.getSession().getValue());
	
	jQuery.exec_json('raw', form.serialize(), function(data) {
		if (data.error != 0) {
			alert(data.message);
		} else {
			alert(data.message);
			//location.reload();
			if(opener) opener.location.href = opener.location.href;
			window.close();
		}
	}, function(data) {
		if (data.error != 0) {
			//alert(data.message);
		}
	});
	return false;
}
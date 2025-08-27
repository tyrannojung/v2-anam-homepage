(function($) {

	$.fn.xe_popup = function(options) {

		var options = $.extend({}, $.fn.xe_popup.defaults, options);

		return this.each(function() {

			var popup = $(this);
			var popupID = options.id;
			var popupCookieValue = getCookie(popupID);

			if (!popupCookieValue || popupCookieValue != "no") {

				var popupbody = $("<div></div>").addClass('popupbody');
				var popupcloser = $("<div></div>").addClass('popupcloser');
				var popuplabel = $("<label>")
				var popupcheckbox = $("<input type='checkbox' />");
				var popup_message = msg_popup_do_not_display.replace('%d', options.exp_days).replace('%s', options.exp_days>1?'s':'');
				var popupcloserhtml = $("<span>"+popup_message+"</span>");
				var popupclosebutton = $('<button type="button">'+msg_popup_closed+'</button>');

				var elementID = options.element_id ? '#'+options.element_id : '';

				if ($(elementID).length>0) var targetBody = elementID;
				else var targetBody = document.body;

				popup.appendTo(targetBody).addClass('xe_popup_'+options.popup_style).css({'top' : options.top+"px", 'left' : options.left+"px"});
				popupbody.css({'height' : options.height+"px"});

				if(options.popup_type == 'content') {
					popupbody.html(options.content);
					popup.show("fast");
				} else if (options.popup_type == 'url') {
					popupbody.load(options.url, function(){popup.show("fast")});
				}

				if (options.link) {
					popupbody.css('cursor','pointer');
					if (options.link_type == 'true')
						popupbody.click(function(){window.open(options.link); popup.hide("fast");});
					else
						popupbody.click(function(){document.location.href=options.link;});
				}

				popuplabel.attr('for', popupID);
				popupcheckbox.attr('id', popupID);

				popuplabel.click(function(){
					setCookie(popupID, "no", options.exp_days);
					popup.hide("fast", function(){$(this).remove();});
				})

				popupclosebutton.click(function(){
					popup.hide("fast", function(){$(this).remove();});
				});

				if(options.popup_style == 'border' || options.popup_checkbox != 'N') {
					popupcloser.append(popuplabel)
					popuplabel.append(popupcheckbox);
					popuplabel.append(popupcloserhtml);
				}
				popupcloser.append(popupclosebutton);
				popup.append(popupbody);
				popup.append(popupcloser);
				//popup.draggable();
			}

			function setCookie( id, value, exp_days ) {
				 var todayDate = new Date();
				 todayDate.setDate( parseInt(todayDate.getDate()) + parseInt(exp_days) );
				 document.cookie = id + "=" + escape( value ) + "; path=/; expires=" + todayDate.toGMTString() + ";"
			}

			function getCookie(name) {
				var value=null, search=name+"=";
				if (document.cookie.length > 0) {
					var offset = document.cookie.indexOf(search);
					if (offset != -1) {
						offset += search.length;
						var end = document.cookie.indexOf(";", offset);
						if (end == -1) end = document.cookie.length;
						value = unescape(document.cookie.substring(offset, end));
					}
				}
				return value;
			}

		});
	};

	$.fn.xe_popup.defaults = {
		id: 'xe_popup',
		popup_type: 'content',
		content: 'No content!',
		open_type: 'inner',
		exp_days: '1',
		width: 300,
		height: 200,
		top: 0,
		left: 0,
		url: '/xe/',
		link: '',
		link_type: '',
		popup_title: 'xe_popup',
		popup_style: 'border',
		popup_checkbox: 'Y'
	};

})(jQuery);

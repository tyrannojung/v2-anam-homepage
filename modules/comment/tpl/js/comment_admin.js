
function doCancelDeclare() {
    var comment_srl = new Array();
    $('#fo_list input[name="cart[]"]:checked').each(function() {
        comment_srl.push($(this).val());
    });
    if (comment_srl.length < 1) {
		return;
	}

    var params = { comment_srl: comment_srl.join(',') };
	Rhymix.ajax('comment.procCommentAdminCancelDeclare', params, function() {
		location.reload();
	});
}

function insertSelectedModule(id, module_srl, mid, browser_title) {
    location.href = current_url.setQuery('module_srl',module_srl);
}

function completeAddCart(ret_obj, response_tags)
{
}

function getCommentList()
{
	var commentListTable = jQuery('#commentListTable');
	var cartList = [];
	commentListTable.find(':checkbox[name=cart]').each(function(){
		if(this.checked) cartList.push(this.value);
	});

    var params = new Array();
    var response_tags = ['error','message', 'comment_list'];
	params["comment_srls"] = cartList.join(",");

    exec_xml('comment','procCommentGetList',params, completeGetCommentList, response_tags);
}

function completeGetCommentList(ret_obj, response_tags)
{
	var htmlListBuffer = '';
	if(ret_obj['comment_list'] == null)
	{
		htmlListBuffer = '<tr>' +
							'<td colspan="4" style="text-align:center">'+ret_obj['message']+'</td>' +
						'</tr>';
	}
	else
	{
		var comment_list = ret_obj['comment_list']['item'];
		if(!jQuery.isArray(comment_list)) comment_list = [comment_list];
		for(var x in comment_list)
		{
			var objComment = comment_list[x];
			htmlListBuffer += '<tr>' +
								'<td class="title">'+ objComment.content +'</td>' +
								'<td class="nowr">'+ objComment.nick_name +'</td>' +
								'<td class="nowr">'+ secret_name_list[objComment.is_secret] +'</td>' +
								'<td>'+ published_name_list[String(objComment.status)] + '<input type="hidden" name="cart[]" value="'+objComment.comment_srl+'" />' + '</td>' +
							'</tr>';
		}
		jQuery('#selectedCommentCount').html(comment_list.length);
	}
	jQuery('#commentManageListTable>tbody').html(htmlListBuffer);
}

function doChangePublishedStatus(new_status)
{
	container_div = jQuery("#listManager");
	var act = container_div.find("input[name=act]");
	var will_publish = container_div.find("input[name=will_publish]");
	var action = "procCommentAdminChangePublishedStatusChecked";
	will_publish.val(new_status);
	act.val(action);
}

function checkSearch(form)
{
	if(form.search_target.value == '')
	{
		alert(xe.lang.msg_empty_search_target);
		return false;
	}
	/*
	if(form.search_keyword.value == '')
	{
		alert(xe.lang.msg_empty_search_keyword);
		return false;
	}
	*/
}

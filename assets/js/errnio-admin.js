jQuery('.errnio-notice.is-dismissible').on('click', '.notice-dismiss', function(e){
	postMessageToWPClosed();
});

function postMessageToWPClosed() {
	jQuery.ajax({
		url : window.errniowp.ajax_url,
		type : 'post',
		data : {
			'action': 'nextarticle_by_errnio_close_notification'
		},
		success : function( response ) {
		}
	});
}
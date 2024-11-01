/**
 * contains functions for uploading documents to wemalo and working with orders
 */
(function($){
	$(document).ready(function(){
		/** 
		 * Attachment upload start
		 */
		$("#_wemalo_attachment").on("change", function(){
			if ($("#_wemalo_attachment").val() == "") {
				$('#_wemalo_attachment_file').hide();
			}
			else {
				$('#_wemalo_attachment_file').show();
				$('#_wemalo_attachment_file').click(function(event) {
					event.stopPropagation();
	      		});
				$('#_wemalo_attachment_file').click();
			}
	    });
		$('#wemalo_upload_order_refresh').on('click', function() {
			location.href = location.href;
		});
		$('#_wemalo_attachment_file').on('change', prepareUpload);
		
		/**
		 * celebrity mail
		 */
		$('#_wemalo_celeb_active').on('change', function() {
			if ($('#_wemalo_celeb_active').prop('checked')) {
				$('#wemalo-celeb-div').show();
			}
			else {
				$('#wemalo-celeb-div').hide();
			}
		});
		//event handler for sending email notifications for celebrity deliveries
		$('#wemalo_send_celeb_mail').on('click', function() {
			if ($('#_wemalo_celeb').val() == "") {
				$('#_wemalo_celeb').focus().select();
			}
			else {
				var data = new FormData();
				data.append("action", "wemalo_celeb_mail");
				data.append("post_id", $('#wemalo_order_id').val());
				data.append("msg", $('#_wemalo_celeb').val());
				$('#wemalo-celeb-div').fadeOut(750);
				executeAjax(data, function(data, textStatus, jqXHR) {
					$('#wemalo-celeb-div').show();
					if(data.response == "SUCCESS"){
						$('#wemalo-celeb-div').addClass('wemalo-alert');
		        		$('#wemalo-celeb-div').addClass('wemalo-alert-success');
		        		$('#wemalo-celeb-div').html('ok!');
		        	}
					else {
						alert(data.error);
						$('#wemalo-celeb-div').show();
					}
				});
			}
		});
		//announce return
		$('#wemalo-announce-return').on('click', function(e) {
			if (confirm("Retoure anmelden?")) {
				$('#order_status').val('wc-return-announced');
			}
			else {
				e.preventDefault();
			}
		});
		$('#wemalo-reclamation-return').on('click', function(e) {
			if (confirm("Reklamation anmelden?")) {
				$('#order_status').val('wc-reclam-announced');
			}
			else {
				e.preventDefault();
			}
		});
		
		/**
		 * dispatcher loading
		 */
		//load available dispatcher profiles
		$('#wemalo-dispatcher-div').hide();
		var data = new FormData();
		data.append("action", "wemalo_load_dispatchers");
		data.append("post_id", $('#wemalo_order_id').val());
		var profiles = {};
		if ($('#wemalo_order_id') && $('#wemalo_order_id').val() > 0) {
			executeAjax(data, function(data, textStatus, jqXHR) {
				$('#wemalo-dispatcher-div').show();
				if(data.response == "SUCCESS") {
					$('#_wemalo_dispatcher').html('');
					$.each(data.items, function (i, item) {
					    $('#_wemalo_dispatcher').append($('<option>', { 
					        value: item.value,
					        text : item.text 
					    }));
					});
					$('#_wemalo_dispatcher').val(data.selectedDispatcher);
					profiles = data.items;
					dispatcherSelected($('#_wemalo_dispatcher').val(), data.selectedDispatcherProduct);
				}
				else {
					// 2018-01-19, Patric Eid: don't show an error if profiles could not be loaded
					//alert(data.error);
				}
			});
		}
		$('#_wemalo_dispatcher').on('change', function() {
			dispatcherSelected($('#_wemalo_dispatcher').val(), "");
		});
		/**
		 * called when a dispatcher was selected and adds dispatcher products to second select box
		 */
		function dispatcherSelected(selectedId, selectedProfile) {
			var result = $.grep(profiles, function(e){ return e.value == selectedId; });
			$('#_wemalo_dispatcher_product').html('');
			if (result.length > 0) {
				$.each(result, function (i, item) {
					$.each(item.subarray, function (j, sub) {
						$('#_wemalo_dispatcher_product').append($('<option>', { 
					        value: sub.value,
					        text : sub.text 
						}));
					});
				});
			}
			$('#_wemalo_dispatcher_product').val(selectedProfile);
		}
		
		/**
		 * executes an ajax call
		 */
		function executeAjax(data, cb) {
			$.ajax({
				url: wemaloOrder.ajax_url,
		        type: 'POST',
		        data: data,
		        cache: false,
		        dataType: 'json',
		        processData: false, // Don't process the files
		        contentType: false, // Set content type to false as jQuery will tell the server its a query string request
		        success: function(data, textStatus, jqXHR) {
		          cb(data, textStatus, jqXHR);
		        }
			});
		}
		
		/**
		 * prepares file upload and executes ajax call
		 */
		function prepareUpload(event) { 
			$('#wemalo_upload_message').html('uploading...');
			$('#wemalo_upload_message').removeClass('wemalo-alert-danger');
			$('#wemalo_upload_message').removeClass('wemalo-alert-success');
			$('#wemalo_upload_message').show();
			$('#wemalo_upload_order_refresh').hide();
			var file = event.target.files;
			var data = new FormData();
			data.append("action", "wemalo_file_upload");
			data.append("post_id", $('#wemalo_order_id').val());
			data.append("wemalo_attachment_type", $('#_wemalo_attachment').val());
			$.each(file, function(key, value)
  			{
    			data.append("wemalo_attachment_file", value);
  			});
			executeAjax(data, function(data, textStatus, jqXHR) {
				if(data.response == "SUCCESS"){
	        		  $('#wemalo_upload_message').html('ok!').delay(6000).fadeOut();
	        		  $('#wemalo_upload_order_refresh').show();
	        		  $('#_wemalo_attachment_file').hide();
	        		  $('#wemalo_upload_message').addClass('wemalo-alert-success');
	        	  }
	        	  else {
	        		  $('#wemalo_upload_message').html(data.error);
	        		  $('#wemalo_upload_message').addClass('wemalo-alert-danger');
	        	  }
			});
		}
	});
})(jQuery);
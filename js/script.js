$(document).ready(function(){
	
	var sitename = location.protocol + '//' + location.hostname;

	$('body #preloader_button').hide();
	
/****************************************************/
/***** PARCE ALL PRODUCTS FROM CATEGORY**************/
/****************************************************/
	$(document).on("click", '#parcebutton', function(e){
	
		e.preventDefault();

		var catid = $(this).attr('rel');
		var form_data = {catid:catid};

		$.ajax({
			url : sitename + "/index.php/chinavasioncontroller/parcecategoryproducts",
			type : 'POST',
			data : form_data,
			beforeSend: function() {
				$('#trcatid_' + catid + ' img').show();
				$(this).attr('disabled', true);
			},	
			complete: function() {
				$('#trcatid_' + catid + ' img').hide();
			},	
			success : function(msg)
			{
				$('#tdcatid_' + catid).html('<span class="text-success">PARCED</span>');
				// alert(msg);
			},
			error: function(msg)
			{
				alert(msg);
			}
		});		
	});
/****************************************************/
});
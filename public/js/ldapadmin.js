$(document).ready(function() {
    $('#tree').append('<div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>');
    $.ajax({
    	url:"/ldapadmin/tree",
    	async:true
   	}).done(function(asyncContent ){
        $('#tree').html(asyncContent);
    })
    
    $('#asyncModal').on('show.bs.modal', function (event) {
        $.ajax(event.relatedTarget.dataset['href'])
        .done(function(asyncContent ){
            $('#asyncModal .modal-body').html(asyncContent);
            
            $('#asyncModal .modal-body form').on('submit',function(e){
            	var form = e.currentTarget;
            	var formData = new FormData($(this)[0]);
            	if( form.name == "ldap_user_group_update"){
	            	e.preventDefault();
            	    
		            $.ajax({
		                url: "/ldapadmin/usergroupupdate",
		                method: 'POST',
                        processData: false,
            			contentType: false,
		                data: formData
		            });
            	}
            });
        });
      });
    
});

'use strict';

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
			var $wrapper = $('#asyncModal .modal-body');
			var formApp = new FormAjax($wrapper);
        });
      });
    
});

(function(window, $) {
    window.FormAjax = function ($wrapper) {
        this.$wrapper = $wrapper;
        //this.helper = new Helper(this.$wrapper);

        this.$wrapper.find('form').on(
            'submit',
            this.handleFormSubmit.bind(this)
        );
    };
    $.extend(window.FormAjax.prototype, {
        handleFormSubmit: function(e) {
            e.preventDefault();
            var form = $(e.currentTarget);
			var formData = new FormData(form[0]);

            $.ajax({
                url: "/ldapadmin/usergroupupdate",
                method: 'POST',
				processData: false,
				contentType: false,
                data: formData
            }).done(function(asyncContent ){
				try {
					var json = JSON.parse(asyncContent);
					if(json.type != "danger"){
						window.location.reload();
					}
				} catch (e) {
					$('#asyncModal .modal-body').html(asyncContent);
					var $wrapper = $('#asyncModal .modal-body');
					var formApp = new FormAjax($wrapper);
				}
			});
        }
    });

})(window, jQuery);
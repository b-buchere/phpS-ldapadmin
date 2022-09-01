'use strict';

$(document).ready(function() {
    $('#tree').append('<div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>');
    $.ajax({
    	url:"/ldapadmin/tree",
    	async:true
   	}).done(function(asyncContent ){
        $('#tree').html(asyncContent);
        var oTree = new treeAjax($('#tree'));
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
    window.treeAjax = function ($wrapper) {
        this.$wrapper = $wrapper;
        this.$wrapper.find('[id^="item-"]').on(
            'click',
            this.treeRequest.bind(this)
        );
    };
    window.FormAjax = function ($wrapper) {
        this.$wrapper = $wrapper;

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
    $.extend(window.treeAjax.prototype, {
        treeRequest: function(e) {
            var data = "dn="+$(e.currentTarget)[0].dataset['href'];
            $($(e.currentTarget)[0].dataset['bsTarget']).html('<div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>');
            $.ajax({
                url: "/ldapadmin/tree",
                method: 'GET',
				processData: false,
				contentType: false,
                data: data
            }).done(function(asyncContent ){
                
                $($(e.currentTarget)[0].dataset['bsTarget']).html($(asyncContent).find('#tree')[0].innerHTML);
                var oTree = new treeAjax($($(e.currentTarget)[0].dataset['bsTarget']));
			});
        }
    });
})(window, jQuery);
'use strict';

$(document).ready(function() {

    var $wrapper = $('#form');
    var formAjax = new FormAjax($wrapper);
    /*$('[name="ldap_userbulk"]').on('submit', function(e){
        e.preventDefault();
        console.log(e.currentTarget);
    });*/

    /*$('#ldap_userbulk_fileimport').on('change', function(e){
        console.log(e.currentTarget);
        var form = $(e.currentTarget.form);
        var formData = new FormData(form);

    });*/
    
});

(function(window, $) {
    window.FormAjax = function ($wrapper) {
        this.$wrapper = $wrapper;

        this.$wrapper.find('form').on(
            'submit',
            this.handleFormSubmit.bind(this)
        );

        this.$wrapper.find('[id$=_fileimport]').on(
            'change',
            this.verifyFile.bind(this)
        );
    };
    $.extend(window.FormAjax.prototype, {
        handleFormSubmit: function(e) {
            e.preventDefault();
            var form = $(e.currentTarget);
			var formData = new FormData(form[0]);

            $('#importProgress').removeClass('d-none');
            $('#reportProgress').removeClass('d-none');
            this.checkProgress();
            $.ajax({
                url: form[0].action,
                method: 'POST',
				processData: false,
				contentType: false,
				async:true,
                data: formData
            }).done(function(asyncContent ){
				/*try {
					var json = JSON.parse(asyncContent);
					if(json.type != "danger"){
						window.location.reload();
					}
				} catch (e) {
					$('#asyncModal .modal-body').html(asyncContent);
					var $wrapper = $('#asyncModal .modal-body');
					var formApp = new FormAjax($wrapper);
				}*/
			});
        },
        checkProgress: function(){
            const that = this;
            $.ajax({
                url: "/ldapadmin/userbulk/progress",
                async:true,
                type: "POST"
            }).done(function (data) {
                console.log(data);
                var progress = data['progress'];
                
                $('#importProgress .progress-bar').attr('aria-valuenow', progress);
                $('#importProgress .progress-bar').css('width', progress+'%');
                $('.reportBody').html(data['dataRender']);
                //Si l'avancement n'est pas à 100%, cette fonction est relancée
                if(progress != 100) {
                    setTimeout(that.checkProgress(), 2000);
                }
            });
        },
        verifyFile: function(e) {
            var form = $(e.currentTarget.closest('form'));
            
			var formData = new FormData(form[0]);
 
            $('[id$=_help]').removeClass('invisible');

            $.ajax({
                url: $('[id$=_verifyUrl]').val(),
                method: 'POST',
                processData: false,
				contentType: false,
                data: formData
            }).done(function( json ){                
                $('[id$=_help]').html(json['message']);
            });
        }
    });

})(window, jQuery);
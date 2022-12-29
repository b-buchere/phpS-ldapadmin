'use strict';

$(document).ready(function() {

    $('#ldap_user_create_nom').on('change', function(e){
        $('#ldap_user_create_fullname').val($('#ldap_user_create_prenom').val()+' '+$(this).val());
    });

    $('#ldap_user_create_prenom').on('change', function(e){
        $('#ldap_user_create_fullname').val($(this).val()+' '+$('#ldap_user_create_nom').val());
    });
	$('#ldap_user_create_region').on('change', function(e){
        
        $.ajax({
            url: "/ldapadmin/getstructureoptions",
            method: 'POST',
            data: {
                dn:$(this)[0].value
            }
        }).done(function(asyncContent ){
            $('#ldap_user_create_structure').html(asyncContent);
        });
    });
    
    $('#groupAdd').on('click', function (e){
        e.preventDefault();
        $.ajax({
            url: $(this).attr('href'),
            method: 'POST',
            data: {
                dn:$(this)[0].value
            }
        }).done(function(asyncContent ){
            $('#ldap_user_create_structure').html(asyncContent);
        });
    });

    $('#ldap_user_create_Groupes').select2({
        tags:true,
        tokenSeparators: [',',' '],
        theme: 'bootstrap-5'
    });
    $('#ldap_user_create_Groupes').parent().find('.select2-selection').addClass(
        $('#ldap_user_create_Groupes').attr('class')
    );
    $('#ldap_user_create_Groupes').parent().find('.select2-selection').removeClass('select2-hidden-accessible');
    $('#ldap_user_create_Groupes').parent().find('.select2-selection').removeClass('w-75');
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
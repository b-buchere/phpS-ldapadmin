$(document).ready(function() {
    $('#tree').append('<div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>');
    $.ajax("/ldapadmin/tree")
    .done(function(asyncContent ){
        $('#tree').html(asyncContent);
    })
    
    $('#asyncModal').on('show.bs.modal', function (event) {
        $.ajax(event.relatedTarget.dataset['href'])
        .done(function(asyncContent ){
            $('#asyncModal .modal-body').html(asyncContent);
        });
      });
    
});

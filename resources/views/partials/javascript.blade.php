            function openModalIframe(url){

                jQuery.ajax({
                    method: 'GET',
                    url: url,
                }).done(function(html){
                    $('#modal-content').html(html);
                    $('#modal-container').modal({show:true})
                });
            }

            function deleteFile(url){

                jQuery.ajax({
                    method: 'DELETE',
                    url: url,
                }).done(function(html){
                    location.reload();
                });
            }

            jQuery('button.delete').click(function(e){
                var fileId = jQuery(this).closest('tr').find('a').data('id');
                var fileRoute = jQuery(this).data('delete-confirm');
                openModalIframe(fileRoute);
            }); 

jQuery(function($) {
    var inProgress = false;
    var started = null;
    
    var WPBDP_ZIPDBImport_step = function() {
        if (!inProgress) {
            $('.import-status-text').removeClass('working');
            return;
        }

        $.ajax( {
            url: ajaxurl,
            data: { action: 'wpbdp-zipcodesearch-import' },
            dataType: 'json',
            success: function(res) {
                var pcg = res.progress;
                
                $('td.progress .progress-bar .progress-bar-inner').attr('style', 'width: ' + pcg + '%;');
                $('td.progress .progress-text').text(pcg + '%');
                $('td.progress .import-status-text').text(res.statusText);
                
                if ( res.error ) {
                    inProgress = false;
                    alert( res.error );
                    return;
                }

                if ( res.finished ) {
                    inProgress = false;
                    
                    $('.import-step-2 tr.actions').fadeOut();

                    WPBDP_ZIPDBImportRebuildCache_step();
                } else {
                    WPBDP_ZIPDBImport_step();
                }
            }
        } );
    };
    
    $('.wpbdp-page-zip-db-import a.resume-import').click(function(e) {
        e.preventDefault();
        
        if (inProgress) {
            inProgress = false;
            
            $(this).text(wpbdpL10n.resume_import);
            $(this).addClass('button-primary');
            $('.import-status-text').removeClass('working');
        } else {
            inProgress = true;
            
            started = new Date().getTime();
            
            $(this).text(wpbdpL10n.pause_import);
            $(this).removeClass('button-primary');
            $('.import-status-text').addClass('working');
            
            WPBDP_ZIPDBImport_step();
        }
    });

    var WPBDP_ZIPDBImportRebuildCache_step = function() {
        $.ajax({
           url: ajaxurl,
           data: { 'action': 'wpbdp-zipcodesearch-rebuildcache' },
           dataType: 'json',
           success: function(res) {
               $('.import-status-text').text(res.statusText);
               
               if (res.done) {
                   $('.import-step-2').fadeOut( function() { $('.import-step-3').fadeIn(); $(this).remove() } );                   
               } else {
                   WPBDP_ZIPDBImportRebuildCache_step();
               }
           }
        });
    };

    var WPBDP_ZIPDBRebuildCache_step = function(url) {
        var progress_text = ['.', '..', '...'];
        var $status = $('.zipcodesearch-cache .status');        
        var $progress = $('.progress', $status);
        
        $('.msg', $status).text('Working');
        $progress.text(progress_text[ $progress.text().length % 3 ]);

        $.ajax({
            url: url,
            data: {},
            dataType: 'json',
            success: function(res) {
                if (res.done) {
                    $status.removeClass('notok').addClass('ok');
                    $('.msg', $status).text('OK');
                    $progress.text('');
                } else {
                    WPBDP_ZIPDBRebuildCache_step(url);
                }
            }
        });
    };
    
    $('.wpbdp-page-admin-settings .zipcodesearch-cache a.rebuild-cache').click(function(e) {
        e.preventDefault();
        WPBDP_ZIPDBRebuildCache_step($(this).attr('href'));
    });    
    
});

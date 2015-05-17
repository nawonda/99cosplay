jQuery(document).ready(function(){
    jQuery('.the-slideshow').hover(function() {
        jQuery(".slide").addClass('transition');

    }, function() {
        jQuery(".slide").removeClass('transition');
    });
});
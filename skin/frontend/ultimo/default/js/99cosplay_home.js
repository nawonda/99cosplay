jQuery(document).ready(function(){
    jQuery('.slide_0').hover(function() {
        jQuery(".slide_0").addClass('transition');

    }, function() {
        jQuery(".slide_0").removeClass('transition');
    });

    jQuery('.slide_1').hover(function() {
        jQuery(".slide_1").addClass('transition');

    }, function() {
        jQuery(".slide_1").removeClass('transition');
    });
});
$('li.dropdown').mouseover(function() {   
    $(this).addClass('open');
}).mouseout(function() {
    $(this).removeClass('open');
}); 

$(function () {
    $('#account').popover('show');
})


jQuery(document).ready(function($) {

    var vars = JSON.parse(gpVarsJson);

    function setCookie(name,value,days) {
        var expires = "";
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days*24*60*60*1000));
            expires = "; expires=" + date.toUTCString();
        }
        document.cookie = name + "=" + (value || "")  + expires + "; path=/";
    }

    function getCookie(name) {
        var nameEQ = name + "=";
        var ca = document.cookie.split(';');
        for(var i=0;i < ca.length;i++) {
            var c = ca[i];
            while (c.charAt(0)==' ') c = c.substring(1,c.length);
            if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
        }
        return null;
    }

    if (vars.user) {
        var gpuser = getCookie('lienhard.gpuser');
        if (gpuser === null) {
            setCookie('lienhard.gpuser', vars.user, 3650);
        } else {
            vars.user = gpuser;
        }
    }

    function gratePostDoAjax(theAction, theArgs, theCallback) {

        jQuery.ajax({
            type: 'post',
            url:  vars.url,
            data: { 
                action: 'gratePostAjax', 
                _ajax_nonce: vars.nonce, 
                args: theArgs,
                userAction: theAction
            },
            success: function(response){
                theCallback(response);			  
            }
        });
    }

    var rating = vars.rating;

    if (rating !== 0) {
        $("input[name='rating'][value="+rating+"]").prop('checked', true);
    }

    $("input[name='rating']").click(function(){
        if ($(this).is(':checked')) {
            var rating = $(this).val();
            var postid = vars.postid;
            var user = vars.user;
            var args = {'rating' : rating, 'postid' : postid, 'user' : user};
            gratePostDoAjax('logPostRating', args, function(response){
                $('.grate-post-rating').html(response);
            });
        }
    });
});

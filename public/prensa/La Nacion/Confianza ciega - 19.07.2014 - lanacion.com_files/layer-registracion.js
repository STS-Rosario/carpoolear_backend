
//----------------------------------------------------------
//Funciones Responsive para manejar el layer de registracion
//----------------------------------------------------------

document.write("<link rel=\"stylesheet\" type=\"text/css\" href=\""+window.location.protocol+"//registracion.lanacion.com.ar/_ui/desktop/css/layer-registracion.css?v=52\" />");

function Tayer(caption, url, imageGroup) {

    var ancho = 0;
    var alto = 0;
    if ($(window).width() <= 639) {
        ancho = 310;
        alto = 350;
    }

    if ($(window).width() > 639) {
        ancho = 500;
        alto = 500;
    }
    


    if (url.indexOf("?") >= 0)
        url = url + "&amp;width=" + ancho + "&height=" + alto;
    else
        url = url + "?width=" + ancho + "&height=" + alto;
    

    urlLayer = url;
    try {

        if (typeof document.body.style.maxHeight === "undefined") {//if IE 6
            //$("body", "html").css({ height: "100%", width: "100%" });
           // $("html").css("overflow", "hidden");
            if (document.getElementById("LN_HideSelect") === null) {//iframe to hide select elements in ie6
                $("body").append("<iframe id='LN_HideSelect'></iframe><div id='LN_overlay'></div><div id='LN_modal'></div>");
                $("#LN_overlay").click(tb_remove);
            }
        } else {//all others
            if (document.getElementById("LN_overlay") === null) {
                $("body").append("<div id='LN_overlay'></div><div id='LN_modal'></div>");
                $("#LN_overlay").click(tb_remove);
            }
        }

        if (tb_detectMacXFF()) {
            $("#LN_overlay").addClass("LN_overlayMacFFBGHack"); //use png overlay so hide flash
        } else {
            $("#LN_overlay").addClass("LN_background"); //use background and opacity
        }
        
        if (url.indexOf("?") > 0) {
            queryString = url.replace(/^[^\?]+\??/, '');
            var params = tb_parseQuery(queryString);
        }
        else {
            queryString = url.replace(/^[^-]+\??/, '');
            var params = tb_parseQueryNew(queryString);
        }

        TB_WIDTH = (params['width'] * 1) || 420; //defaults to 630 if no paramaters were added to URL
        TB_HEIGHT = (params['height'] * 1) || 360; //defaults to 440 if no paramaters were added to URL

        ajaxContentW = TB_WIDTH - 30;
        ajaxContentH = TB_HEIGHT - 45;


        urlNoQuery = url.split('ln_');
        $("#LN_iframe").remove();

        $("#LN_modal").append("<div id='LN_top'><div id='LN_close'><a href='#' id='LN_closeButton' title='Close'>&#xa0;</a> </div></div><iframe frameborder='0' hspace='0' src='" + urlNoQuery[0] + "' id='LN_iframe' name='LN_iframe" + Math.round(Math.random() * 1000) + "' onload='tb_showIframe()'> </iframe>");


        $("#LN_closeButton").click(tb_remove);

        tb_position();
        if ($.browser.safari) {//safari needs help because it will not fire iframe onload
            $("#loader").remove();
            $("#LN_modal").css({ display: "block" });
        }

        document.onkeyup = function (e) {
            if (e == null) { // ie
                keycode = event.keyCode;
            } else { // mozilla
                keycode = e.which;
            }
            if (keycode == 27) { // close
                tb_remove();
            }
        }


    }
    catch (e) {
        alert(e);
    }

}

function Todal(elem) {
    var t = elem.title || elem.name || null;
    var a = elem.href || elem.alt;
    var g = elem.rel || false;
    Tayer(t, a, g);

    return false;
}

function tb_parseQueryNew(query) {
    var Params = {};
    if (!query) { return Params; } // return empty object
    var Pairs = query.split(/[|-]/);
    for (var i = 0; i < Pairs.length; i++) {
        var KeyVal = Pairs[i].split('=');
        if (!KeyVal || KeyVal.length != 2) { continue; }
        var key = unescape(KeyVal[0]);
        var val = unescape(KeyVal[1]);
        val = val.replace(/\+/g, ' ');
        Params[key] = val;
    }
    //console.log("tb_parseQuery.Params = " + Params);
    return Params;
}

function tb_parseQuery(query) {
    var Params = {};
    if (!query) { return Params; } // return empty object
    var Pairs = query.split(/[;&]/);
    for (var i = 0; i < Pairs.length; i++) {
        var KeyVal = Pairs[i].split('=');
        if (!KeyVal || KeyVal.length != 2) { continue; }
        var key = unescape(KeyVal[0]);
        var val = unescape(KeyVal[1]);
        val = val.replace(/\+/g, ' ');
        Params[key] = val;
    }
    //console.log("tb_parseQueryOld.Params = " + Params);
    return Params;
}

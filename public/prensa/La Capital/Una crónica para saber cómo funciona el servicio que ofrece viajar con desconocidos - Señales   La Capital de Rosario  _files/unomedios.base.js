/**
* 
* Javascript Base - Uno Medios
* unomedios.base.js
* (c) 2011
*
* 
*/

/**
* Extiende la funcionalidad del objeto Array 
* para que permita el método indexOf
*
*/
Array.prototype.indexOf = function(s) {
	for (var x=0;x<this.length;x++) if(this[x] === s) return x;
	return -1;
}

/**
  * Verifica si un m�dulo est� cargado o no.
  * @param {string} moduleName nombre del m�dulo
  * 
*/
unomedios.isLoaded = function(moduleName) {
	return (unomedios.modules_loaded.indexOf(moduleName) !== -1);
};

/**
  * Carga m�dulos javascript din�micamente 
  * @param {Array} moduleList Lista de m�dulos a cargar
  * 
*/ 
unomedios.includeJs = function(moduleList) {
	if (!moduleList) { return false; }
	
	for (var i = 0, l = moduleList.length; i < l ; i++) {
		if (moduleList[i] && !unomedios.isLoaded(moduleList[i])) {
			
			$.getScript(moduleList[i]);
			unomedios.modules_loaded.push(moduleList[i]);
		}
	}
};

/**
  * Carga estilos css din�micamente 
  * @param {Array} styleList Lista de estilos a cargar
  * 
*/ 
unomedios.includeCss = function(styleList) {
	if (!styleList) { return false; }
	
	for (var i = 0, l = styleList.length; i < l ; i++) {
		
		if (styleList[i] && unomedios.styles_loaded.indexOf(styleList[i]) === -1) {
			
			$("head").append("<link>");
			var css = $("head").children(":last");
			css.attr({
				rel:  "stylesheet",
				type: "text/css",
				href: styleList[i]
			});
			unomedios.styles_loaded.push(styleList[i]);
		}
	}
};

/**
  * Espera a que determinado objeto de Javascript est� disponible
  * @param {Object} fn Nombre del objeto a esperar
  * @param {function} successCallback Callback a disparar cuando el objeto est� disponible
  * @param {function} errorCallback Callback a disparar si el objeto nunca est� disponible
  * 
*/ 
unomedios.waitFor = function(fn, successCallback, errorCallback) {
	
	var max_requests = 10;
	var polling_interval = 1000; // ms
	var t = 1;
	var x;
	function verificar() {
		try {
			if ( eval ('typeof ' + fn + ' == "function" ') || eval ('typeof ' + fn + ' == "object" ') ) {
				clearTimeout(x);
				if (jQuery.isFunction(successCallback)) { 
					try { successCallback(); } catch(ex1) { /*if(console.log) console.log (ex1.message);*/ }
				}
				return;
			} else {
				throw("OBJETO_O_FUNCION_NO_DEFINIDO");
			}
		} catch(excep) {
			/*if(console.log) console.log('unomedios.waitFor: esperando a ' +  fn + ' (' + t + ')');*/
			t++;
			if (t > max_requests) {
				//if(console.log) console.log('unomedios.waitFor: ' + fn + ' timeout');
				clearTimeout(x);
				if (jQuery.isFunction(errorCallback)) {
					try { errorCallback(); } catch(ex2) { /*if(console.log) console.log (ex2.message);*/ }
				}
				return false;
			}
			x = setTimeout(verificar, polling_interval );
		}
	}
	verificar();	
};

unomedios.removeFullPath = function (filePath){
	/*var fileNoPath = filePath.replace(/^\/.*\/([^\/]+)$/, "");
	return fileNoPath;*/
};

unomedios.setBookmarks = function(){

	// add a "rel" attrib if Opera 7+
	if(window.opera) {
		if ($("a.jqbookmark").attr("rel") != ""){ // don't overwrite the rel attrib if already set
		    $("a.jqbookmark").attr("rel","sidebar");
		}
	}
	$("a.jqbookmark").click(function(event){
		event.preventDefault(); // prevent the anchor tag from sending the user off to the link
		var url = this.href;
		var title = this.title;
		
		if (window.sidebar) { // Mozilla Firefox Bookmark
		   	window.sidebar.addPanel(title, url,"");
		} else if( window.external.AddFavorite ) { // IE Favorite
		    	window.external.AddFavorite( url, title);
		} else if(window.opera) { // Opera 7+
		   	return false; // do nothing - the rel="sidebar" should do the trick
		} else { // for Safari, Konq etc - browsers who do not support bookmarking scripts (that i could find anyway)
			alert("Para agregar a favoritos, hagalo manualmente");
		}
	});
};


/**
 * ----------------------------------------------------------------------------------------
 * Aumenta el tamaño de fuente de los elementos que posean la clase pasada como parámetro
 * @param classAumento {string} Clase de los elementos a quien aumentar la fuente
 */
unomedios.aumentarTexto = function(classAumento){
	var minimo = 8;
	var maximo = 20;
	var fuente = 13;
	
	
	var $elem = $("." + classAumento);
	var curSize = parseFloat($elem.css("font-size"), 2);
	
        fuente = (curSize >= maximo) ? maximo : curSize + 1;
	
	$elem.css("font-size", fuente+ "px");
	
};

/**
 * ----------------------------------------------------------------------------------------
 * Muestra el audio correspondiente al hacer click en los links de audio
 */
unomedios.setAudio = function(){
	$(".audio a.show_audio").click(function(e) {
		e.preventDefault();
		
		
		$(this).parents(".audio").find(".audio_player").slideToggle();
	
	});
	
};


/**
 * ----------------------------------------------------------------------------------------
 * Disminuye el tamaño de fuente de los elementos que posean la clase pasada como parámetro
 * @param classAumento {string} Clase de los elementos a quien disminuir la fuente
 */
unomedios.achicarTexto = function(classAumento){
	var minimo = 8;
	var maximo = 20;
	var fuente = 13;
	
	
	var $elem = $("." + classAumento);
	var curSize = parseFloat($elem.css("font-size"), 2);
	
        fuente = (curSize <= minimo) ? minimo : curSize - 1;
	
	$elem.css("font-size", fuente+ "px");
}


/**
 * ----------------------------------------------------------------------------------------
 * Abre una ventana pop con las propiedades especificadas como parámetros
 * 
 */
unomedios.openWinScroll = function(url,fwidth,fheight,scroll){
	window.open(url , "","width="+fwidth+",height="+fheight+",scrollbars="+scroll);
}

unomedios.validarCampos = function ($form) {

	var checksFlag = false;
	var textsFlag = false;
	
		
	var $checks = $('[type="checkbox"].mandatory', $form);
	
	if ($checks.length == 0) {
		checksFlag = true;
	} else {
		$.each($checks, function(x, i) {
			
			if (i.checked) { 
				checksFlag = true;
				$("#a_seccion").val(1);
			}
		
		});
	}
	
	var $texts = $('[type="text"].mandatory', $form);
        
        if ($texts.length == 0) {
		textsFlag = true;
	} else {
	        $.each($texts, function(x, i) {
			if ($(this).val() != "") { 
				textsFlag = true;
			}
		
		}); 
	}
      	
        if (checksFlag || textsFlag) {
        	return true;
        } else {
        	return false;
        }
};

unomedios.showMessage = function (texto, tipo, container) { 
	// TODO: Hacer una bonita caja de mensajes
	alert(texto);

};

/**
*
* Tracking extraordinario de page views
* @param {String} path Ruta a trackear
*
**/
unomedios.registrarPageView = function (path) {
	
	// Tracking de Certifica (función definitida en includeEstadisticaCertifica.jsp)
	unomedios.waitFor("trackCertificaPageEvent", function() {
		trackCertificaPageEvent(path);
	});
	
	// Tracking de Google Analytics
	unomedios.waitFor("_gaq", function() {
		window._gaq.push(['_trackPageview', '/' + path]);
	});

};

/**
 * ----------------------------------------------------------------------------------------
 * Inicialización de la interfaz
 * 
 */
unomedios.setUpUI = function() {

	//DropDown del menú superior
	$('.more').click(function () {
     		$('ul.more-items').slideToggle('fast');
     	});
     	
     	//Click en el icono de imprimir
	$('.imprimir').click(function (ev) {
		ev.preventDefault();
     		window.print();
     	});
     	
     	unomedios.setAudio();

}

/*
 * Date Format 1.2.3
 * (c) 2007-2009 Steven Levithan <stevenlevithan.com>
 * MIT license
 *
 * Includes enhancements by Scott Trenda <scott.trenda.net>
 * and Kris Kowal <cixar.com/~kris.kowal/>
 *
 * Accepts a date, a mask, or a date and a mask.
 * Returns a formatted version of the given date.
 * The date defaults to the current date/time.
 * The mask defaults to dateFormat.masks.default.
 */

var dateFormat = function () {
	var	token = /d{1,4}|m{1,4}|yy(?:yy)?|([HhMsTt])\1?|[LloSZ]|"[^"]*"|'[^']*'/g,
		timezone = /\b(?:[PMCEA][SDP]T|(?:Pacific|Mountain|Central|Eastern|Atlantic) (?:Standard|Daylight|Prevailing) Time|(?:GMT|UTC)(?:[-+]\d{4})?)\b/g,
		timezoneClip = /[^-+\dA-Z]/g,
		pad = function (val, len) {
			val = String(val);
			len = len || 2;
			while (val.length < len) val = "0" + val;
			return val;
		};

	// Regexes and supporting functions are cached through closure
	return function (date, mask, utc) {
		var dF = dateFormat;

		// You can't provide utc if you skip other args (use the "UTC:" mask prefix)
		if (arguments.length == 1 && Object.prototype.toString.call(date) == "[object String]" && !/\d/.test(date)) {
			mask = date;
			date = undefined;
		}

		// Passing date through Date applies Date.parse, if necessary
		date = date ? new Date(date) : new Date;
		if (isNaN(date)) throw SyntaxError("invalid date");

		mask = String(dF.masks[mask] || mask || dF.masks["default"]);

		// Allow setting the utc argument via the mask
		if (mask.slice(0, 4) == "UTC:") {
			mask = mask.slice(4);
			utc = true;
		}

		var	_ = utc ? "getUTC" : "get",
			d = date[_ + "Date"](),
			D = date[_ + "Day"](),
			m = date[_ + "Month"](),
			y = date[_ + "FullYear"](),
			H = date[_ + "Hours"](),
			M = date[_ + "Minutes"](),
			s = date[_ + "Seconds"](),
			L = date[_ + "Milliseconds"](),
			o = utc ? 0 : date.getTimezoneOffset(),
			flags = {
				d:    d,
				dd:   pad(d),
				ddd:  dF.i18n.dayNames[D],
				dddd: dF.i18n.dayNames[D + 7],
				m:    m + 1,
				mm:   pad(m + 1),
				mmm:  dF.i18n.monthNames[m],
				mmmm: dF.i18n.monthNames[m + 12],
				yy:   String(y).slice(2),
				yyyy: y,
				h:    H % 12 || 12,
				hh:   pad(H % 12 || 12),
				H:    H,
				HH:   pad(H),
				M:    M,
				MM:   pad(M),
				s:    s,
				ss:   pad(s),
				l:    pad(L, 3),
				L:    pad(L > 99 ? Math.round(L / 10) : L),
				t:    H < 12 ? "a"  : "p",
				tt:   H < 12 ? "am" : "pm",
				T:    H < 12 ? "A"  : "P",
				TT:   H < 12 ? "AM" : "PM",
				Z:    utc ? "UTC" : (String(date).match(timezone) || [""]).pop().replace(timezoneClip, ""),
				o:    (o > 0 ? "-" : "+") + pad(Math.floor(Math.abs(o) / 60) * 100 + Math.abs(o) % 60, 4),
				S:    ["th", "st", "nd", "rd"][d % 10 > 3 ? 0 : (d % 100 - d % 10 != 10) * d % 10]
			};

		return mask.replace(token, function ($0) {
			return $0 in flags ? flags[$0] : $0.slice(1, $0.length - 1);
		});
	};
}();

// Some common format strings
dateFormat.masks = {
	"default":      "ddd mmm dd yyyy HH:MM:ss",
	shortDate:      "m/d/yy",
	mediumDate:     "mmm d, yyyy",
	longDate:       "mmmm d, yyyy",
	fullDate:       "dddd, mmmm d, yyyy",
	shortTime:      "h:MM TT",
	mediumTime:     "h:MM:ss TT",
	longTime:       "h:MM:ss TT Z",
	isoDate:        "yyyy-mm-dd",
	isoTime:        "HH:MM:ss",
	isoDateTime:    "yyyy-mm-dd'T'HH:MM:ss",
	isoUtcDateTime: "UTC:yyyy-mm-dd'T'HH:MM:ss'Z'"
};

// Internationalization strings
dateFormat.i18n = {
	dayNames: [
		"Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat",
		"Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"
	],
	monthNames: [
		"Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec",
		"January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"
	]
};

// For convenience...
Date.prototype.format = function (mask, utc) {
	return dateFormat(this, mask, utc);
};

/**
 * ----------------------------------------------------------------------------------------
 * Inicializaci�n del entorno Javascript com�n a todas las p�ginas.
 * 
 */
unomedios.init = function() {
	
	//if(console.log) console.log("Inicializando unomedios.base.js ...");
	unomedios.cssFolder = "../css/";
	unomedios.modules_loaded = [];
	unomedios.styles_loaded = [];
	
	// Incluir los archivos css necesarios
	unomedios.includeCss(unomedios.requiredCss);
	
	// Incluir los archivos javascript necesarios
	unomedios.includeJs(unomedios.requiredJs);
	
	// Seteo los links de Agregar a Favoritos
	unomedios.setBookmarks();
	
	// Inicializar interfaz
	unomedios.setUpUI();
	
};

$(document).ready(unomedios.init);
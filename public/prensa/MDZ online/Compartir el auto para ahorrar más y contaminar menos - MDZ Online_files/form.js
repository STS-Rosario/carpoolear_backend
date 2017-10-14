// Javascript Form Validator
var vsmlang;						// Define el idioma en que se muestran los mensajes de error.
var vsmfSubmitted = false;			// Flag que indica si fue ejecutado el submit.
var vsmfOnSubmitHandle = 'alert';	// Función llamada cuando se produce un error en un elemento del formulario al hacer submit.
var vsmfOnBlurHandle = null;		// Función llamada cuando se produce un error en un elemento del formulario al perder el foco. 
var vsmfOnFocusError = null;		// Función llamada cuando se produce un error al querer enfocar el campo que produjo el error. 
var vsmfProcesses = 0;				// Flag que indica la cantidad de procesos de validación pendiente. Incluye los llamados AJAX + vsmSubmit;
var vsmfErrorDisplayed = false;		// Flag que indica si ya se mostró un error.
var vsmfForm = null;				// Contiene el objeto tipo FORM con el formulario validado. En caso de que el submit deba hacerse asincronico al finalizar un llamado AJAX.
var vsmfElement = null;				// Contiene el objeto tipo INPUT con el último elemento validado.

function vsmRequireValidation() {
	// Llamado en los inputs que requieren validación
	if(!vsmfSubmitted) vsmfErrorDisplayed = false;
}

function vsmSubmit(frm, lang, onSubmitHandle, onBlurHandle) {
	// Define el idioma
	lang = lang.toLowerCase();
	if (lang != 'en' && lang !='pt' && lang !='es') {vsmlang='en';} else {vsmlang=lang;}
	// Resetea las variables
	vsmfForm = frm;
	vsmfErrorDisplayed = '';
	vsmfProcesses = 1;
	vsmfSubmitted = true;
	// Define los handlers
	if(onSubmitHandle != undefined) vsmfOnSubmitHandle = onSubmitHandle;
	if(onBlurHandle != undefined) vsmfOnBlurHandle = onBlurHandle;
	// Desactiva el botón de submit
	vsmSubmitButtonEnabled(false);
	// Recorre los elementos del formulario y los valida
	for(var index=0;index<frm.elements.length;index++) {
		var el= frm.elements[index];
		if(!el.type) continue;
		if(el.onblur && el.onblur !== "") el.onblur();
		if(vsmfErrorDisplayed) return false;
	}
	vsmfProcesses-=1;
	if(vsmfProcesses==0 && vsmfSubmitted) {return true;} else {return false;}
}

function vsmSubmitButtonEnabled(value) {
	// Activa o desactiva el botón de submit
	var frm = vsmfForm;
	for(var index= 0; index < frm.elements.length;index++)
		if(frm.elements[index].type == 'submit' || frm.elements[index].type == 'image') frm.elements[index].disabled= !value;
}

function vsmFocusElement(element) {
	// Intenta hacer foco en el elemento del formulario
	try {
		element.focus(); 
	} catch (error) {
		if(vsmfOnFocusError) eval(vsmfOnFocusError+'()');
	}
	// Determina si el elemento informa que el destaque de error debe hacerse sobre otro elemento a través del atributo highlight
	var obj;
	var highlight = element.getAttribute('highlight');
	if(highlight) obj = document.getElementById(highlight);
	if(!obj) obj = element;
	if(obj.className.indexOf('vsmformerror') == -1) obj.className+= ' vsmformerror';
}

function vsmFieldDescription(fld) { 
	// Devuelve la descripción del campo
	if(fld.title) return fld.title;
	if(fld.id && document.getElementsByTagName) {
		for(var i= 0, lbl= document.getElementsByTagName('LABEL'); i < lbl.length; i++)
			if(lbl[i].htmlFor==fld.id) return lbl[i].nodeValue||lbl[i].textContent||lbl[i].innerText;
		for(var i= 0, lbl= document.getElementsByTagName('label'); i < lbl.length; i++)
			if(lbl[i].htmlFor==fld.id) return lbl[i].nodeValue||lbl[i].textContent||lbl[i].innerText;
	}
	return fld.name;
}

function vsmTrim(str) {
	return str.replace(/^\s*|\s*$/g,"");
}

function vsmAjaxValidation(fld, url) {
	// Dispara una validación ajax
	if(vsmfSubmitted) vsmfProcesses+=1;
	if(!vsmfErrorDisplayed && (vsmfSubmitted || vsmfOnBlurHandle)) vsm.ajaxCall(url, 'vsmAjaxValidationOK("'+fld.id+'")', 'vsmAjaxValidationError("'+fld.id+'")');
}

function vsmAjaxValidationOK(response, fldid) {
    if(response.length) document.getElementById(fldid).value = response;
	if(vsmfSubmitted) {
		vsmfProcesses-=1;
		// Si no quedan llamados ajax pendientes envia el formulario
		if(vsmfProcesses==0) vsmfForm.submit();
	} 
}

function vsmAjaxValidationError(response, fldid) {
	var fld = document.getElementById(fldid);
	if(vsmfSubmitted) vsmfProcesses-=1;
    // response devuelve un array json con value (nuevo valor del campo) y error mensaje de error
    var e= eval('(' + response + ')');
    if(e['value']) fld.value = e['value'];
    if(e['error']) vsmfShowError(fld, e['error']);
}

function vsmfShowError(fld,errorMsg) {
	if(!vsmfErrorDisplayed) {
		vsmfErrorDisplayed = true;
		vsmfElement = fld;
		if(vsmfSubmitted) {
			if(vsmfOnSubmitHandle) eval(vsmfOnSubmitHandle+"(errorMsg)");
			vsmFocusElement(vsmfElement);
			vsmSubmitButtonEnabled(true);
			vsmfSubmitted=false;
		} else {
			if(vsmfOnBlurHandle) eval(vsmfOnBlurHandle+"(errorMsg)");
		}
	}
}

/* Funciones de validación */
function vsmRequired(fld) {
	if(fld.disabled) return;
	switch(fld.type) {
		case 'checkbox': 
			if(fld.checked) return true;
			break;
		case 'radio':
			var radios = fld.form[fld.name];
			for(var i=0;i<radios.length;i++) {
				if (radios[i].checked) return true;
			}
			break;
		case 'select-one':
			if(fld.selectedIndex!=-1 && fld.options[fld.selectedIndex].value!='') return true;
			break;
		default:
			if(fld.value.length) return true;
			break;
	}
	switch (vsmlang) {
		case 'es': var errorMsg= 'El campo '+vsmFieldDescription(fld)+' no puede ser omitido.'; break;
		case 'pt': var errorMsg= 'Deve preencher o campo '+vsmFieldDescription(fld)+'.'; break;
		default:   var errorMsg= 'The '+vsmFieldDescription(fld)+' field cannot be left blank.'; break;
	}
	vsmfShowError(fld, errorMsg);
}

function vsmConfirmation(fld, confirmfld) {
	if(fld.disabled) return;
	if(fld.value != confirmfld.value) {
		switch (vsmlang) {
			case 'es': var errorMsg= 'Los campos '+vsmFieldDescription(fld)+' y '+vsmFieldDescription(confirmfld)+' deben ser idénticos.'; break; 
			case 'pt': var errorMsg= 'Os campos '+vsmFieldDescription(fld)+' e '+vsmFieldDescription(confirmfld)+' devem ser iguais.'; break; 
			default:   var errorMsg= 'The '+vsmFieldDescription(fld)+' field does not match the '+vsmFieldDescription(confirmfld)+' field.'; break; 
		}
		vsmfShowError(fld, errorMsg);
	}
}

function vsmLength(fld, min, max) { 
	if(fld.disabled) return;
	var len= fld.value.length;
	if(min > -1 && len < min) {
		switch (vsmlang) {
			case 'es': var errorMsg= 'El campo '+vsmFieldDescription(fld)+' debe tener al menos '+min+ ' caracteres; Actualmente tiene '+len+'.'; break;
			case 'pt': var errorMsg= 'O campo '+vsmFieldDescription(fld)+' deve conter no mínimo '+min+ ' caracteres; Atualmente contem '+len+'.'; break;
			default:   var errorMsg= 'The '+vsmFieldDescription(fld)+' field must be at least '+min+ ' characters long; it is currently '+len+' characters long.'; break;
		}
		vsmfShowError(fld, errorMsg);
		return;
	}

  	if(max > -1 && len > max) { 
		switch (vsmlang) {
			case 'es': var errorMsg= 'El campo '+vsmFieldDescription(fld)+' debe tener un máximo de '+max+ ' caracteres; Actualmente tiene '+len+'.'; break;
			case 'pt': var errorMsg= 'O campo '+vsmFieldDescription(fld)+' deve conter no máximo '+max+ ' caracteres; Atualmente contem '+len+'.'; break;
			default:   var errorMsg= 'The '+vsmFieldDescription(fld)+' field must be no more than '+max+' characters long; it is currently '+len+' characters long.'; break; 
		}
		vsmfShowError(fld, errorMsg);
		return;
	}
}

function vsmInteger(fld, min, max) { 
	// Controla que el campo sea entero entre los valores min y max
	if(fld.disabled) return true;
	if(fld.value && fld.value != parseInt(fld.value)) {
		switch (vsmlang) {
			case 'es': var errorMsg= 'El campo '+vsmFieldDescription(fld)+' debe ser numérico.'; break;
			case 'pt': var errorMsg= 'O campo '+vsmFieldDescription(fld)+' deve ser numérico.'; break;
			default:   var errorMsg= 'The '+vsmFieldDescription(fld)+' field must be a number.'; break;
		}
		vsmfShowError(fld, errorMsg);
		return;
	}
	
	if(fld.value < min || fld.value > max) {
		switch (vsmlang) {
			case 'es': var errorMsg= 'El campo '+vsmFieldDescription(fld)+' debe ser numérico entre '+min+ ' y '+max+'.'; break;
			case 'pt': var errorMsg= 'O campo '+vsmFieldDescription(fld)+' deve ser numérico entre '+min+ ' y '+max+'.'; break;
			default:   var errorMsg= 'The '+vsmFieldDescription(fld)+' field must be a number between '+min+ ' and '+max+'.'; break;
		}
		vsmfShowError(fld, errorMsg);
		return;
	}
}


function vsmAllowChars(fld, chars) { 
	// provide a string of acceptable chars for a field
	if(fld.disabled) return true;
	var length = fld.value.length;
	for(var i=0;i<length;i++) {
		if(chars.indexOf(fld.value.charAt(i)) == -1) {
			switch (vsmlang) {
				case 'es': var errorMsg= 'El campo '+vsmFieldDescription(fld)+' contiene caracteres no permitidos.\r\n" '+fld.value.charAt(i)+' " no es un caracter permitido.\r\nLos caracteres permitidos son:\r\n'+chars; break;
				case 'pt': var errorMsg= 'O campo '+vsmFieldDescription(fld)+' contem caracteres não permitidos.\r\n" '+fld.value.charAt(i)+' " não é um caracter permitido.\r\nOs caracteres permitidos são:\r\n'+chars; break;
				default:   var errorMsg= 'The '+vsmFieldDescription(fld)+' contain invalid characters. It may only contain the following characters:\r\n'+chars; break; 
			}
			vsmfShowError(fld, errorMsg);
			return;
		}
	}
}

function vsmDisallowChars(fld, chars){ 
	// provide a string of unacceptable chars for a field
	if(fld.disabled) return true;
	var length = fld.value.length;
	for(var i=0;i<length;i++) {
    	if(chars.indexOf(fld.value.charAt(i)) != -1) {
	 		switch (vsmlang) {
				case 'es': var errorMsg= 'El campo '+vsmFieldDescription(fld)+' no puede contener ninguno de los siguientes caracteres:\r\n'+chars; break; 
				case 'pt': var errorMsg= 'O campo '+vsmFieldDescription(fld)+' não pode conter nenhum dos seguintes caracteres:\r\n'+chars; break; 
				default:   var errorMsg= 'The '+vsmFieldDescription(fld)+' field cannot contain any of the following characteres:\r\n'+chars; break; 
			}
			vsmfShowError(fld, errorMsg);
			return;
  		}
	}
}

function vsmEmail(fld) { 
	if(!fld.value.length||fld.disabled) return true; // blank fields are the domain of requireValue 
	fld.value = vsmTrim(fld.value.toLowerCase());
	var filter  = /^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,8})+$/;
	if (!filter.test(fld.value)) {
		switch (vsmlang) {
			case 'es': var errorMsg= 'El campo '+vsmFieldDescription(fld)+' no es una dirección de correo válida.'; break;
			case 'pt': var errorMsg= 'O campo '+vsmFieldDescription(fld)+' não é um endereço válido'; break;
			default:   var errorMsg= 'The '+vsmFieldDescription(fld)+' field must contain a valid email address.'; break;
		}
		vsmfShowError(fld, errorMsg);
		return;
	}
}

function vsmFile(fld, allowedfiletypes) {
	var ok = true;
	if(!fld.value.length||fld.disabled) return true; 
    if(typeof allowedfiletypes == 'undefined') allowedfiletypes = fld.getAttribute('data-vsm-file-types');
	// Obtiene la extension el archivo
	var filepath = fld.value;
	if(filepath.lastIndexOf(".") == -1) ok = false;
	if (ok) {
		var fileext = (filepath.substring(filepath.lastIndexOf(".")+1)).toLowerCase();
		if (allowedfiletypes && (allowedfiletypes+',').indexOf(fileext+',') == -1) ok = false;
	} 
    
	if (!ok) {
		switch(vsmlang) {
			case 'es': var errorMsg= 'El campo '+vsmFieldDescription(fld)+' no contiene un archivo válido.\r\n\r\nTipos de archivos válidos:\r\n' + allowedfiletypes.toUpperCase(); break;
			case 'pt': var errorMsg= 'O campo '+vsmFieldDescription(fld)+' não é um arquivo válido.\r\n\r\nTipos de arquivos validos:\r\n' + allowedfiletypes.toUpperCase(); break;
			default:   var errorMsg= 'The '+vsmFieldDescription(fld)+' field must contain a valid file type.\r\n\r\nValid file types:\r\n' + allowedfiletypes.toUpperCase(); break;
		}
		vsmfShowError(fld, errorMsg);
		return;
	} else {
        return true;
    }
}

function vsmLabel(fld, objectType, objectID, settings, fieldlabel) {
	// Valida si la clave única de un objeto es válida
    /*
	var newValue = '';
	fld.value = vsmTrim(fld.value);
	for(var i=0;i<fld.value.length;i++) {
		var car = fld.value.charAt(i).toLowerCase();
		if(String('abcdefghijklmnopqrstuvwxyz0123456789').indexOf(car) != -1) {
			newValue+= car;
		} else if (String(' -_;:,.').indexOf(car) != -1) {
			newValue+= '-';
		} else if (String('áàâä').indexOf(car) != -1) {
			newValue+= 'a';
		} else if (String('éèêë').indexOf(car) != -1) {
			newValue+= 'e';
		} else if (String('íìîï').indexOf(car) != -1) {
			newValue+= 'i';
		} else if (String('óòôö').indexOf(car) != -1) {
			newValue+= 'o';
		} else if (String('úùûü').indexOf(car) != -1) {
			newValue+= 'u';
		} else if (car == 'ñ') {
			newValue+= 'n';
		} else if (car == 'ç') {
			newValue+= 'c';
		}
	}
	fld.value = newValue;
    */
    fld.value = vsmTrim(fld.value);
    if(fld.value) {
	    var url = '/tools/ajax/validLabel.php?ot='+objectType+'&oid='+objectID+'&s='+settings+'&v='+escape(fld.value)+'&c='+fieldlabel+'&l='+vsmlang;
	    vsmAjaxValidation(fld, url);
    }
}


function vsmDate(fld, format) {
	// Esta ok si el campo está vacio o disabled
	if(!fld.value.length||fld.disabled) return true; 
	// Normaliza la fecha quitando los espacios y convirtiendo cualquier separador en /
	fld.value = vsmTrim(fld.value);
	var d = fld.value.replace(/\D/g, '/');
	// Obtiene los valores separados por /
	dateArray = d.split('/');
	if (dateArray.length == 3) {
		format = format.toLowerCase();
		switch(format) {
			case 'ymd': year=dateArray[0]; month = dateArray[1]; day = dateArray[2]; break;
			case 'dmy': year=dateArray[2]; month = dateArray[1]; day = dateArray[0]; break;
			case 'mdy': year=dateArray[2]; month = dateArray[0]; day = dateArray[1]; break;
		}
		ok = vsmValidDate(year, month, day);
		if (ok) {
			fld.value = d;
			return;
		}
		
	}
	switch (vsmlang) {
		case 'es': var errorMsg= 'El campo '+vsmFieldDescription(fld)+' no es una fecha válida.'; break;
		case 'pt': var errorMsg= 'O campo '+vsmFieldDescription(fld)+' não é uma data válida'; break;
		default:   var errorMsg= 'The '+vsmFieldDescription(fld)+' field must contain a valid date.'; break;
	}
	vsmfShowError(fld, errorMsg);
	return;
}

function vsmComboDate(yearfld, monthfld, dayfld, required) {
	if (typeof(required)=='undefined') required = false;
	if (yearfld.disabled || monthfld.disabled || dayfld.disabled) return true;
	if (!yearfld.value.length && !monthfld.value.length && !dayfld.value.length) {
		if (required) {
			switch (vsmlang) {
			case 'es': var errorMsg= 'El campo '+vsmFieldDescription(yearfld)+' no puede quedar vacío.'; break; 
			case 'pt': var errorMsg= 'Deve preencher o campo '+vsmFieldDescription(yearfld)+'.'; break; 
			default:   var errorMsg= 'The '+vsmFieldDescription(yearfld)+' field cannot be left blank.'; break; 
			}
			vsmfShowError(dayfld, errorMsg);
			return;
		} else {
			return;
		}
	}
	ok =  vsmValidDate(yearfld.value, monthfld.value, dayfld.value);
	if (!ok) {
		switch (vsmlang) {
			case 'es': var errorMsg= 'El campo '+vsmFieldDescription(yearfld)+' no es una fecha válida.'; break;
			case 'pt': var errorMsg= 'O campo '+vsmFieldDescription(yearfld)+' não é uma data válida'; break;
			default:   var errorMsg= 'The '+vsmFieldDescription(yearfld)+' field must contain a valid date.'; break;
		}
		vsmfShowError(dayfld, errorMsg);
		return;
	}
}

function vsmValidDate(year, month, day) {
	if (!isNaN(day) && !isNaN(month) && !isNaN(year)) {
		month = month-1;
		var dteDate=new Date(year,month,day);
		if ((day==dteDate.getDate()) && (month==dteDate.getMonth()) && (year==dteDate.getFullYear())) return true;
	}
	return false;
}

function vsmDependants(enabled, elements) { 
	// Para Activar/Desactivar campos
	// convenience function to enable/disable dependant fields, passed in as an array 
	if(!elements.length) return true;
	for(var i= 0; i < elements.length; i++) {
    	elements[i].disabled= !enabled;
	}
}


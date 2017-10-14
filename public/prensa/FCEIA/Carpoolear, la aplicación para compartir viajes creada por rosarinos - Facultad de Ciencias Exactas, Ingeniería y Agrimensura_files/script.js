function refreshCaptcha(componentId, captchaPath)
{
	if(!captchaPath) captchaPath = 'index.php?option=com_rsform&task=captcha&componentId=' + componentId;
	document.getElementById('captcha' + componentId).src = captchaPath + '&' + Math.random();
	document.getElementById('captchaTxt' + componentId).value='';
	document.getElementById('captchaTxt' + componentId).focus();
}

function number_format(number, decimals, dec_point, thousands_sep)
{
    var n = number, prec = decimals;
    n = !isFinite(+n) ? 0 : +n;
    prec = !isFinite(+prec) ? 0 : Math.abs(prec);
    var sep = (typeof thousands_sep == "undefined") ? ',' : thousands_sep;
    var dec = (typeof dec_point == "undefined") ? '.' : dec_point;
 
    var s = (prec > 0) ? n.toFixed(prec) : Math.round(n).toFixed(prec); //fix for IE parseFloat(0.55).toFixed(0) = 0;
 
    var abs = Math.abs(n).toFixed(prec);
    var _, i;
 
    if (abs >= 1000) {
        _ = abs.split(/\D/);
        i = _[0].length % 3 || 3;
 
        _[0] = s.slice(0,i + (n < 0)) +
              _[0].slice(i).replace(/(\d{3})/g, sep+'$1');
 
        s = _.join(dec);
    } else {
        s = s.replace('.', dec);
    }
 
    return s;
}

function buildXmlHttp()
{
	var xmlHttp;
	try
	{
		xmlHttp=new XMLHttpRequest();
	}
	catch (e)
	{
		try
		{
			xmlHttp=new ActiveXObject("Msxml2.XMLHTTP");
		}
		catch (e)
		{
			try
			{
				xmlHttp=new ActiveXObject("Microsoft.XMLHTTP");
			}
			catch (e)
			{
				alert("Your browser does not support AJAX!");
				return false;
			}
		}
	}
	return xmlHttp;
}

function ajaxValidation(form, page)
{
	if (typeof(form.elements) == 'undefined')
		form = this;
		
	var xmlHttp = buildXmlHttp();
	var url = 'index.php?option=com_rsform&task=ajaxValidate';
	
	if (page)
		url += '&page=' + page;
	
	var params = new Array();
	var submits = new Array();
	var success = false;
	for (i=0; i<form.elements.length; i++)
	{
		// don't send an empty value
		if (!form.elements[i].name) continue;
		if (form.elements[i].name.length == 0) continue;
		// check if the checkbox is checked
		if (form.elements[i].type == 'checkbox' && form.elements[i].checked == false) continue;
		// check if the radio is selected
		if (form.elements[i].type == 'radio' && form.elements[i].checked == false) continue;
		
		if (form.elements[i].type == 'submit')
		{
			submits.push(form.elements[i]);
			form.elements[i].disabled = true;
		}
		
		// check if form is a dropdown with multiple selections
		if (form.elements[i].type == 'select-multiple')
		{
			for (var j=0; j<form.elements[i].options.length; j++)
				if (form.elements[i].options[j].selected)
					params.push(form.elements[i].name + '=' + encodeURIComponent(form.elements[i].options[j].value));
			
			continue;
		}
		
		params.push(form.elements[i].name + '=' + encodeURIComponent(form.elements[i].value));
	}
	
	params = params.join('&');
	
	xmlHttp.open("POST", url, false);

	//Send the proper header information along with the request
	xmlHttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
	xmlHttp.setRequestHeader("Content-length", params.length);
	xmlHttp.setRequestHeader("Connection", "close");
	xmlHttp.send(params);
	var success = true;
	
	if (xmlHttp.responseText.indexOf("\n") != -1)
	{
		var response = xmlHttp.responseText.split("\n");
		// All spans set to no error
		var ids = response[0].split(',');
		for (var i=0; i<ids.length; i++)
			if (document.getElementById('component'+ids[i]))
				document.getElementById('component'+ids[i]).className = 'formNoError';
			
		// Show errors
		var ids = response[1].split(',');
		for (var i=0; i<ids.length; i++)
			if (document.getElementById('component'+ids[i]))
			{
				document.getElementById('component'+ids[i]).className = 'formError';
				success = false;
			}
			
		for (var i=0; i<submits.length; i++)
			submits[i].disabled = false;
	}
	
	return success;
}

function rsfp_addEvent(obj, evType, fn){ 
 if (obj.addEventListener){ 
   obj.addEventListener(evType, fn, false); 
   return true; 
 } else if (obj.attachEvent){ 
   var r = obj.attachEvent("on"+evType, fn); 
   return r; 
 } else { 
   return false; 
 } 
}

function rsfp_getForm(formId)
{
	var formIds = document.getElementsByName('form[formId]');
	for (var i=0; i<formIds.length; i++)
	{
		if (parseInt(formIds[i].value) != parseInt(formId))
			continue;
		
		var form = formIds[i].parentNode;
		return form;
	}
}
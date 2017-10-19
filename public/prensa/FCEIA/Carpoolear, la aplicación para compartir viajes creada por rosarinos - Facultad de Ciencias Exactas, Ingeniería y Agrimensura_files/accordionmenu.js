/*
*Mod_Accordionmenu by James Frank
*
* License: GNU/GPL
*
*Based off of  v1.0 - by ah72, July 2008
*
*/


function accordionMenu(menuId, srcExpandImage, srcCollapseImage, accOptions, accHoverDelay, bDoHover) {
if($(menuId)){

    // getting accordion parent items ("li" tags with class "parent")
    $(menuId).accParentItems = [];

    for(var i = 0; i < $(menuId).childNodes.length; i++) {

        if($(menuId).childNodes[i].className.indexOf('parent') >= 0){
            $(menuId).accParentItems.push($(menuId).childNodes[i]);
        }
    }


    $(menuId).accTogglers = [];
    $(menuId).accElements = [];
    var startItem = -1;

    for(var i = 0; i < $(menuId).accParentItems.length; i++) {

		if(srcExpandImage.length > 0){

			// creating accordion togglers
			var accToggler = document.createElement("img");
	
			accToggler.setAttribute("title","Expand");
			accToggler.setAttribute("src",srcExpandImage);
		}
		else
		{
			var accToggler = document.createElement("span");	
		}
			$(menuId).accParentItems[i].insertBefore(accToggler, $(menuId).accParentItems[i].firstChild);
		
			$(menuId).accTogglers.push(accToggler);
		

        // accordion elements
        $(menuId).accElements.push($(menuId).accParentItems[i].getElementsByTagName('ul')[0]);

        // searching for active menu item to make the accordion show its sub-items when page loads
        if ( $(menuId).accParentItems[i].className.indexOf('active') >= 0 ) {
            startItem = i;
        }
   }

    //create our Accordion instance
    if ( $(menuId).accParentItems.length > 0 ){
        $(menuId).Accordion = new Accordion($(menuId).accTogglers, $(menuId).accElements, $merge({
            opacity: false,
            alwaysHide: true,
            show: startItem,
            duration: 600,
            transition: Fx.Transitions.Bounce.easeOut,

            onActive: function(toggler, element){
                element.parentNode.parentNode.setStyle('height', 'auto');
                toggler.setAttribute("src", srcCollapseImage);
                toggler.setAttribute("title","Collapse");
            },
            onBackground: function(toggler, element){
                element.parentNode.parentNode.setStyle('height', 'auto');
                element.setStyle('height', element.offsetHeight+'px');
                toggler.setAttribute("src", srcExpandImage);
                toggler.setAttribute("title","Expand");
            }

            }, accOptions)

        );
    }


    accTimer = null;
    if (!accHoverDelay) var accHoverDelay = 200;
	
    for(var i = 0; i < $(menuId).accParentItems.length; i++) {

        eval("function accOnclickFunc(){return function(){ if( $('"+menuId+"').accElements["+i+"].style.height == '0px' ) { $('"+menuId+"').Accordion.display("+i+") }}}");
		eval("function accOnMouseoverFunc(){return function(){if( $('"+menuId+"').accElements["+i+"].style.height == '0px' ){accTimer = $('"+menuId+"').Accordion.display.delay("+accHoverDelay+", $('"+menuId+"').Accordion, "+i+");}}}");
		eval("function accOnmouseoutFunc(){return function(){if($defined(accTimer)){$clear(accTimer);}}}");

        $(menuId).accParentItems[i].firstChild.nextSibling.onclick = accOnclickFunc();
		if (bDoHover==1) {
			$(menuId).accParentItems[i].firstChild.nextSibling.onmouseover = accOnMouseoverFunc();
        }
		$(menuId).accParentItems[i].firstChild.nextSibling.onmouseout = accOnmouseoutFunc();
    }


    for(var i = 0; i < $(menuId).accElements.length; i++) {
        $(menuId).accElements[i].setAttribute('id', menuId+'_'+i);
        accordionMenu(menuId+'_'+i, srcExpandImage, srcCollapseImage, accOptions, accHoverDelay, bDoHover)
    }

}
}

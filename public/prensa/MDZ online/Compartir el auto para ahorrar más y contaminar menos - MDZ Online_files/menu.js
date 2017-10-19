vsm.menu = {
    loaded:false,
    forcedopen:false,
    init:function(e){
        if (e) vsm.menu.eam(e);
        if (vsm.menu.loaded) return;
        vsm.menu.loaded=true;                    
        vsm.attachEvent(document, 'click', vsm.menu.hideAll);
        vsm.dom.ready(function(){vsm.menu.eam()});

    },
    eam : function (d){//Función para habilitar todos los hijos de un elemento
        for(var i=0,m=vsm.menu.el(d),s=m.length;i<s;i++) {
            if (vsm.menu.isMenu(m[i]) && m[i].getAttribute('data-submenu') != 'css' && !m[i].getAttribute('data-menu-enabled')){
                m[i].setAttribute('data-menu-enabled',1);
                vsm.attachEvent(m[i],'click',vsm.menu.activate);
                vsm.attachEvent(m[i],'mouseover',vsm.menu.activate);
            }
        }
    },
    el:function(d){d=d||document;return (d.querySelectorAll) ? d.querySelectorAll('[data-vsmel]'): d.getElementsByTagName('div');},
    hideAll:function(skip){
        
        var returnValue=false;
        for(var i=0,m=vsm.menu.el(),s=m.length;i<s;i++) {
            if (vsm.menu.isMenu(m[i]) && skip!=m[i].parentNode) {  
                var mitem= m[i].parentNode;
                if (vsm.menu.isItem(mitem) && (!skip || !mitem.contains(skip))){ //Desactiva los menues que no pertenzcan al nodo de skip, siempre y cuando haya skip
                    if (!returnValue && vsm.hasClass(mitem,'visible')) returnValue=true;
                    vsm.removeClass(mitem, 'visible');
                    
                }
            
            }
        }
        if (vsm.browser.msie <= 8)return true;  //En IE8 el returnValue es siempre true. Si no para los click de "a href" comunes.
        return returnValue;
    },
    isItem:function(o){return (o && o.getAttribute('data-vsmel') == 'mitem');},
    isMenu:function(o){return (o && o.getAttribute('data-vsmel') == 'menu');},
    activate : function(e) {
        var el = e.target || window.event.srcElement;
        for(var mnu=el;mnu && mnu.getAttribute('data-vsmel') != 'menu';mnu=mnu.parentNode);
        if (!mnu || mnu.getAttribute('data-onopen')) return cb(); //Si no hay mnu o el menu padre es un onopen lo dejo interactuar con el panel.
        var type= mnu.getAttribute('data-submenu'),stop;
        if(e.type == type || (type=='mouseover' && e.type =='click')) {
            for(var itm=el;itm && itm.nodeType==1 && !vsm.menu.isItem(itm);itm=itm.parentNode);
            var sm = vsm.menu.hasMenu(itm);
            if(sm) {
                if(itm.className.indexOf('button') == -1 || vsm.hasClass(el,'ddb') || vsm.menu.forcedopen) { //Habilita si no es button o si elemento es 'ddb' o si fue forzada una apertura (data-onopen)
                    stop = true;
                    itm.className = itm.className.replace(/ visible/g,'')+((type=='click' && itm.className.indexOf(' visible') != -1)?'':' visible');
                }
                if (type=='mouseover' && e.type =='click') stop = (!itm.childNodes[0].href.length);
                var jsfunc = sm.getAttribute('data-onopen');
                if (jsfunc) {
                    vsm.addClass(itm, 'onopen');
                    eval(jsfunc);
                }
            }
            vsm.menu.hideAll(itm);
            vsm.menu.deactivate(itm.parentNode, itm);
        }else if (e.type == 'click'){//Se fija si hizo click en algun nodo del submenu y el click no ocurrio en un algun item. Si ocurrio en algun item lo deja fluir, si no quiere decir que esta haciendo click en algun elemento no item del submenu abierto.
            for(var itm=el;itm && itm.nodeType==1 && !vsm.menu.isItem(itm);itm=itm.parentNode);
            if (vsm.hasClass(itm,'visible')){ //Si el item que encontro es el elemento que habría el menu no oculta los menues.
                stop = true;
            }
            
        } 
        
        if(stop) cb();
        function cb(){
            if (e.stopPropagation) e.stopPropagation();
            e.cancelBubble=true;
        }
        vsm.menu.forcedopen=false;
    },
    hasMenu : function (itm){
        return (itm && itm.tagName=='SPAN' && itm.childNodes.length && itm.childNodes[itm.childNodes.length-1].nodeType==1 && itm.childNodes[itm.childNodes.length-1].getAttribute('data-vsmel') == 'menu') ? itm.childNodes[itm.childNodes.length-1]: false;
    },
    deactivate : function (root, itm) {
        if(!root || (!root.childNodes && !root.childNodes.length)) return;
        for(var oitm= root.childNodes[0];oitm;oitm=oitm.nextSibling) {
            if (vsm.menu.hasMenu(oitm)){
                if(oitm != itm) vsm.replaceClass(oitm,' visible','');
                vsm.menu.deactivate(oitm.childNodes[oitm.childNodes.length-1], itm);
            }
        }
    }
}

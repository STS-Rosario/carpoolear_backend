vplfgal = function(id,values,pi,ths,scrll,onchange){
    this.p = 0; //Position;
    this.e = (typeof values == 'string') ?  eval("("+values+")"):values; //Json Elements
    pi = pi||0; 
    if (pi) pi = (pi>this.e.length) ? this.e.length: pi;
    this.pi = pi; //Amount of preload Images
    if(pi) this.api = new Array(); //array of preloaded images
    this.id=id;
    this.ths = ths;
    this.scroll = scrll;
    this.z= false; //Zoom in page
    this.autosize= true; //Autosize
    this.onchange = onchange;
    var _temp = this;
    var h =document.location.hash.replace("#","");
    var s= h.split('.');
    if (_temp.id == s[0] ){
        vsm.dom.ready(function(){
            var h = vsm.object('vplfgz_'+s[0]);
            if (!h) return;
            setTimeout(function (){
            _temp.zoom(h.getAttribute('data-mcolor'),h.getAttribute('data-mopacity'),s[1],((h.getAttribute('data-autosize')==0)?false:true))
            },500);
        });
        
    }
};
vplfgal.prototype.getValue = function(idx,pos,defaultv){    
    pos = (pos == undefined || pos <0) ? this.p: pos;
    return this.e[pos][idx];
};
vplfgal.prototype.prev = function(){
    if (!this.e[0]["i"]) return; //Check if it has elements;
    this.p= (this.p > 0) ? this.p-1:this.e.length-1;
    this.preload(true);
    this.change();
};
vplfgal.prototype.thumbs = function(idx){
    var th = vsm.object('vplfgth_'+idx+'_'+this.id);
    if (!th)return;
    var o= th.parentNode;
    var thx = 0;
    var ax=0;
    var c = o.childNodes;
    
    for (var i=0;i<c.length;i++){
        if (c[i].className.indexOf('selected')!=-1) ax =i;
        c[i].className = c[i].className.replace(/ selected/g,"");
        if (c[i]==th) thx= i;
        
    }
    th.className += ' selected';
    if (this.scroll){
        for(;o.className.indexOf(this.scroll)==-1;o=o.parentNode);
        var d = (parseInt(vsm.currentStyle(o,'marginLeft'))+th.offsetLeft+(th.offsetWidth/2));
        if (thx>ax) d = d*-1;
        vpl.scroll(o,d ,(thx>ax),'marginLeft');
    }
};
vplfgal.prototype.maskValue = function(o,e,dv){
    dv= dv||this.getValue(e);
    return (dv) ? ((o.getAttribute("msk")) ?  o.getAttribute("msk").replace("%%v%%",dv):dv): ""; 
};

vplfgal.prototype.adjustSize = function(i){
    var obj = document["vplfgalobj"];
    if(!obj) return;
    console.log('obj.id:'+obj.id);
    var o = vsm.object('vplfgi_'+obj.id); //div de la imagen que debe mostrarse
    var p = vsm.object('vplfgp_'+ obj.id); //div de la página que representa la gallería
    var canvas = vsm.object('vplfgcanvas');  //Canvas donde es ubicada la imagen
    if(!i.nodeType) i = o;  
    
    //Tamaño real de la image
    var ni = new Image();
    ni.src = i.src;
    
    //Defino el ancho y el alto del canvas
    if(canvas){
        var pw = canvas.offsetWidth;
        var ph = canvas.offsetHeight;
    } else {
        var pw = document.documentElement.clientWidth;
        var ph = document.documentElement.clientHeight;
    }
    
    //obtengo la imagen adaptada para que quede dentro del canvas
    var ai = vsm.scaleToFit(ni.width,ni.height,pw,ph,false);
    
    if(obj.z){
       if (obj.autosize){
            p.style.marginLeft= "-"+(w/2)+"px";       
            p.style.marginTop= "-"+(h/2)+"px";
        }else{
            //Seteo los nuevos valores de la imagen
            o.setAttribute('width',ai.width);
            o.setAttribute('height',ai.height);
            o.style.position = 'absolute';
            o.style.top = ai.top+"px";
            o.style.left = ai.left+"px";
        }
    }      
};

/*
vplfgal.prototype.adjustSize = function(i){
    var obj = document["vplfgalobj"];
    if (!obj) return;
    var o = vsm.object('vplfgi_'+obj.id);
    var p = vsm.object('vplfgp_'+ obj.id);
    var canvas = vsm.object('vplfgcanvas'); 
    if(!i.nodeType) i = o;  
    
    var pw =  (canvas) ?canvas.offsetWidth:document.documentElement.clientWidth; //ancho del canvas 
    var ph = (canvas) ?canvas.offsetHeight:document.documentElement.clientHeight; //alto del canvas
    
    // Ancho del canvas < a la imagen y Alto del canvas > a la imagen
    if (pw < i.width && ph >= i.height){
        i.height = (pw*i.height)/i.width; // Calculo el height para que se ajuste al nuevo width
        i.width = pw;
    }
    
    //Ancho del canvas < a la imagen y Alto del canvas < a la imagen
    if (pw < i.width && ph < i.height){
        if(pw<ph){
            i.height = (pw*i.height)/i.width;
            i.width = pw;
        } else {
            i.width = (ph*i.width)/i.height;
            i.height = ph;
        }
    }
    //Ancho del canvas > a la imagen y Alto del canvas < a la imagen
    if (pw >= i.width && ph < i.height){
        i.width = (ph*i.width)/i.height; // Calculo el width para que se ajuste al nuevo height
        i.height = ph;
    }    
    //Ancho del canvas > a la imagen y Alto del canvas > a la imagen
    if (pw >= i.width && ph >= i.height){
        //No tiene que realizar cambios en los tamaños ya que el espacio disponible es mas grande que la imagen
    }
    
    if(i.width) {o.removeAttribute('width'); o.setAttribute('width',i.width);} else o.removeAttribute('width');                                     
    if(i.height){o.removeAttribute('height'); o.setAttribute('height',i.height);} else o.removeAttribute('height');
    
    i.width= o.clientWidth;        
    i.height = o.clientHeight;        
    
    if(obj.z){
        var w = (i.width > p.offsetWidth) ? i.width:p.offsetWidth; 
        var h = (i.height > p.offsetHeight) ? i.height:p.offsetHeight; 
        if (obj.autosize){
            p.style.marginLeft= "-"+(w/2)+"px";       
            p.style.marginTop= "-"+(h/2)+"px";
        }else{
            o.style.position='absolute';
            o.style.top='50%';
            o.style.left='50%';
            o.style.marginLeft= "-"+(i.width/2)+"px";
            o.style.marginTop= "-"+(i.height/2)+"px";
        }
    }    
};
*/


vplfgal.prototype.change = function(idx){
    
     var o= vsm.object('vplfgi_'+this.id);
     var b= vsm.object('vplfgb_'+this.id);
     var p = vsm.object('vplfgp_'+ this.id);     
     if(b) b.style.display ='';
     var w = o.width, h = o.height;
     
     if (typeof(idx) != "undefined") this.p= parseInt(idx);
     if (this.ths) this.thumbs(this.p);
  /*   o.src ='/lib/1x1.gif';
     o.width = w; o.height=h;*/
     
     var cr= vsm.object('vplfgcr_'+this.id);     
     var c= vsm.object('vplfgc_'+this.id);          
     var co= vsm.object('vplfgco_'+this.id);    
     var to= vsm.object('vplfgto_'+this.id);    
     var s = vsm.object('vplfgs_'+this.id);    
     var t = vsm.object('vplfgt_'+this.id);  
     var a = vsm.object('vplfga_'+this.id);  
     var cidx = vsm.object('vplfgcidxs_'+this.id);  
     if (cr) cr.innerHTML = unescape(this.getValue("cr")); 
     if (c) c.innerHTML = this.maskValue(c,"c");
     if (co) co.innerHTML =  this.maskValue(co,null,this.p+1);
     if (to) to.innerHTML =  this.maskValue(to,null,this.e.length);
     if (s) s.innerHTML = this.maskValue(s,"s");
     if (t) t.innerHTML = this.maskValue(t,"t");
     if (a) a.innerHTML = this.maskValue(a,"a");
     if (this.pi){
        var i = (typeof(this.api[this.p]) != "undefined") ? this.api[this.p]: new Image();
        for (var j=0,  len=this.e.length; j<len-1;j++){
            if (j!=this.p && (typeof(this.api[j]) != "undefined")) {this.api[j].onload =null};
        }
     }else{
         var i = new Image();
     }
     var _temp = this;
     i.onload = function(){
        if (p){
         p.className = p.className.replace(/ trans-end/g,'');
         p.className += ' trans-in'; 
        }
        
        
        if (o.filters && o.filters.length)o.filters[0].apply();
        if (p){
                timeout = (p.getAttribute('data-transition-time')) ? p.getAttribute('data-transition-time') : 500;
                setTimeout(function(){
                p.className = p.className.replace(/ trans-in/g,' trans-end');
                if (o.filters && o.filters.length) {o.filters[0].play();}
                o.src = i.src;
                if (_temp.z)_temp.adjustSize(i);
                },timeout);
        }else{
            o.src = i.src;
        }
        if(b) b.style.display ='none';        
     } 
     i.src = this.getValue("i");
     
     if (i.complete)i.onload();
     if (cidx){
         for (var j=0;j<cidx.childNodes.length;j++){
             vsm.replaceClass(cidx.childNodes[j],' selected','');
         }
         cidx.childNodes[idx].className += ' selected';
     }
     
     if(this.z)window.location.hash = this.id+'.'+this.p;
     
     if (this.onchange)vsm.eval(this.onchange,this);
};
vplfgal.prototype.zoom = function(c,o,idx,autosize){
    var p = vsm.object('vplfgp_'+this.id);
    
    var tmp = p.parentNode;
    console.log(tmp);
    if (tmp.tagName != 'BODY'){
        tmp.removeChild(p);
        document.body.appendChild(p);
    }
    delete tmp;
    var i = vsm.object('vplfgi_'+this.id);
    i.src = '/lib/1x1.gif';
    this.z= true;
    this.autosize = autosize;
    document["vplfgalobj"] = this;
    this.preload(false);  
    p.style.position ='fixed';
    
    if (autosize){ 
        p.style.marginLeft = "-10000px" ;//Mueve el elmento fuera de la pantalla para poder hacerlo visible y calcular el ancho
        p.style.display = '';
        var w = (i.width > p.offsetWidth) ? i.width:p.offsetWidth; 
        var h = (i.height > p.offsetHeight) ? i.height:p.offsetHeight; 
        p.style.marginLeft= "-"+(w/2)+"px";
        p.style.marginTop= "-"+(h/2)+"px";
        p.style.left = '50%';
        p.style.top = '50%';
        
    }else{
        p.style.top = '0';
        p.style.left = '0';
        p.style.bottom= '0';
        p.style.right = '0';
        var id = this.id;
        document.body.style.overflow = 'hidden';

    }
    p.style.display = '';
    if (idx===false) {idx = this.p;}
    document.location.hash = this.id+'.'+idx;
    if (o!=false){
        vpl.modal(p,{closefunction:function(){ document["vplfgalobj"].close();},opacity:o,color:c,notatcenter:true});
    }   
    vsm.attachEvent(document, 'keydown', this.keyhandle);
    vsm.attachEvent(window, 'resize', this.adjustSize);
    
    if (typeof _gaq == 'object' && typeof _gaq.push == 'function'){
        _gaq.push(['_trackEvent', 'Gallery Images', 'Zoom']); 
    }
    var next = vsm.object('vplfgnext');
    var prev = vsm.object('vplfgprev');
    if (this.e.length == 1) {//Si es igual quita next y prev
        if (next) next.className+=' hidden';
        if (prev) prev.className+=' hidden';
    }else{
        vsm.replaceClass(next, ' hidden','');
        vsm.replaceClass(prev, ' hidden','');
        
    }
    this.change(idx);
};

vplfgal.prototype.keyhandle = function(e,obj){
    e = (window.event) ? window.event:e;
    if (e.keyCode == 39) document["vplfgalobj"].next(); 
    if (e.keyCode == 37) document["vplfgalobj"].prev(); 
};
vplfgal.prototype.close = function(){
    vplSwitchVisible('vplfgp_'+this.id,'none');
    vsm.detachEvent(document, 'keydown', this.keyhandle);
    document["vplfgalobj"] = null;
    vsm.detachEvent(window, 'resize', this.adjustSize);
    document.location.hash = vsm.randomID(this.id);
    document.body.style.overflow = '';
};
vplfgal.prototype.next = function(){
    if (!this.e[0]["i"]) return; //Check if it has elements;
    this.p = (this.p ==this.e.length-1) ? 0:this.p+1;
    this.preload(false);
    this.change();
};
vplfgal.prototype.preload = function(rewind){
    for (var i=0;i<=this.pi;i++){
        if (rewind){
            var p = (this.p-i <= 0) ? ((this.p-i)+(this.e.length-1)): this.p-i;
        }else{
            var p = (this.p+i >= this.e.length-1) ?((this.p+i)-(this.e.length-1)): this.p+i;
        }
        if (this.pi && typeof(this.api[p]) == "undefined" && p < this.e.length){
            this.api[p] = new Image();
            this.api[p].src = this.getValue("i",p);
        }
    }
};
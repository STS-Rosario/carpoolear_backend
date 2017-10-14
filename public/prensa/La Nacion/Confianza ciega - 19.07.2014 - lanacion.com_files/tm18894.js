var nvg18894 = new function(){

    this.version= 7;
    this.url=false;
    this.tuple=false;
    this.domain=false;
    this.userId=false;
    this.userSync='';
    this.segmentKey=false;
    this.segmentValue=false;
    this.control=false;
    this.segmentKey=false;
    this.segmentValue=false;
    this.wantString=true;
    this.wantCustom=false;
    this.navdmp=false;
    this.qry=false;
    this.cokCache={};
    this.coknm='navdmp';
    this.parameter='/req?v=' + this.version;


    this.account = 18894;
    this.leave = 0;
    this.wantString = false;
    this.wantCustom = true;
    this.maxCriteria = 10;
    this.wantCookie = true;
    this.domain = 'www.lanacion.com.ar';
    this.tagCode = 'var NVG_QRY={};nvg18894.makeQry=function(){this.cokCache={};var resultado={};var nvg_parms=new Array("connection","device","os","browser","career","region","brand","city","cluster","age","gender","education","interest","product","income","marital","custom");for(nvg_i in nvg_parms){var nvg_tmp_nme=nvg_parms[nvg_i];var nvg_tmp=this.getSegment(nvg_parms[nvg_i]);if(nvg_tmp.search("-")!=-1)nvg_tmp=nvg_tmp.replace(/-/g,",");if(nvg_tmp!="")resultado[nvg_tmp_nme]=nvg_tmp}return resultado};NVG_QRY=nvg18894.makeQry();nvg18894.setCustomTargeting(Array(Array("leonardo-pisculichi","teo-gutierrez","marcelo-gallardo","river","enzo-francescoli","funes-mori")),877);nvg18894.setCustomTargeting(Array(Array("bmw","mercedes-benz","audi","porsche","volvo","ferrari","lamborghini")),862);nvg18894.setCustomTargeting(Array(Array("lionel-messi","barcelona","messi")),876);nvg18894.setCustomTargeting(Array(Array("boca","arruabarrena","carlos-bianchi","fernando-gago","agustin-orion")),878);nvg18894.setCustomTargeting(Array(Array("chevrolet","peugeot","ford","volkswagen","fiat","renault")),879);nvg18894.setCustomTargeting(Array(Array("maiz","soja","cultivo","rural","campo","cosecha","trigo","agropecuario","campos","fertilizantes","agricultura","granos","granos-gruesos","hacienda","oleaginosa","mesa-de-enlace","usda","productores-rurales","campo","entidades-agropecuarias","campaña","cosecha","gran-campeon","retenciones","maquinaria-agricola","cereal","siembra","agricultura","hectareas","etchevere","buzzi","malezas-resistentes","malezas")),880);nvg18894.setCustomTargeting(Array(Array("ecologia","sustentabilidad","reciclado","contaminacion","basura-cero","separacion-basura","urbanizacion","ballenas","greenpeace","fauna","flora")),886);nvg18894.setCustomTargeting(Array(Array("humedad","milimetros","sequia","rindes","vaca","novillo","ternero","zona-nucleo","preñez","ojo-de-rana","roya","amaranthus","rama-negra","glisofato","monsanto","nidera","donmario","cosecha","jhon-deere","granos","soja","maiz","trigo","campo","maquinaria-agricola","retenciones","fertilizantes")),927);';
    this.tag = 'city:product:career:gender:age:custom:brand:marital:country:cluster:connection:interest:income:education:region';
    this.HighGranularity = true;

    this.coknm='nav'+this.account.toString();




    this.server = Array('navdmp.com','navdmp.com');

    this.segments = Array('','gender','age','education',
                      'marital','income','prolook',
                      'connection','city','region',
                      'country','cluster','custom',
                      'brand','interest','product','career');




    this.preLoad = function ()    
    {
        if(window.location.hostname.search(this.domain)==-1) this.domain = '';
        this.navdmp = this.getCookie(this.coknm) || false;
        if (this.navdmp) {
            var arr = this.navdmp.split('_');
            this.userId=arr[0];
            if(this.userId.indexOf('|')>=0){this.userId=this.userId.split('|');this.userSync='|'+this.userId[1];this.userId=this.userId[0];};
            this.control=arr[1];
            if(typeof(window.localStorage)=="object")
            {
                localnav = window.localStorage.getItem(this.coknm);
                if(localnav)
                {
                    try
                    {
                        localnav = localnav.split('_');
                        this.segmentKey=localnav[1].split(':');
                        this.segmentValue=localnav[2].split(':');
                    }catch(e){};
                }
            }
            if(!this.segmentKey)
            {
                if (arr[2]) this.segmentKey=arr[2].split(':');
                if (arr[3]) this.segmentValue=arr[3].split(':');
            }

        };
        if (this.tagCode) this.include('','script',this.tagCode);    
    };
    
    this.load = function ()
    {
        if(!this.navdmp)
            this.preLoad();

         if ( !this.userId || this.tagManagerCode || this.control!=this.datestr() || this.getParameter('navegg_debug')=='1' ) 
        {
            var url = '/usr?v=' + this.version;
            url += '&acc=' + this.account;
            if ( (!this.control) || (this.control != this.datestr() ) ) { url+='&upd=1';this.parameter +='&upd=1'; }
            if (this.userId) url+='&id=' + this.userId; else { url += '&new=1'; this.parameter+='&new=1';}
            if (!this.wantString) url += '&wst=0';
            if (this.wantCustom) url += '&wct=1';
            if (this.getParameter('navegg_debug')=='1') url+='&rdn='+parseInt(Math.random()*1e8);
    
             if (!(this.leave&1)) this.include('//' + this.server[0] + url,'script');
                        else if(this.tagManagerCode) this.include('','script',this.tagManagerCode);
        };
        if(this.navdmp) 
                {
                       if (!(this.leave&2) && this.getParameter('navegg_debug')!='1') this.saveRequest(this.userId);
                };
                if( typeof(this.tagSync) == "function" ) this.tagSync();
    
    };

    this.start = function (id,keys,values)
    {
        if ( ((this.userId!=id)  || (this.control != this.datestr())) && id!='' )  {
            this.setCookie(this.coknm,id +this.userSync+ '_' + this.datestr() );
        };
        if(keys && values) this.tuple = keys + '_' + values;
                if(this.tuple) this.saveLocal(this.coknm,id+'_'+this.tuple);
        if (keys) this.segmentKey=keys.split(':');
        if (values) this.segmentValue=values.split(':');
        if (this.wantCookie && keys && values) this.cokCustom(id+this.userSync);
        if( typeof(this.cokCustomOld) == "function" ) this.cokCustomOld(id);
        if (this.tagManagerCode) this.include('','script',this.tagManagerCode);    
                if (!this.navdmp){if (!(this.leave&2)  && this.getParameter('navegg_debug')!='1') this.saveRequest(id);};
        if( typeof(this.dataCustom) == "function" ) this.dataCustom();
        if(typeof navegg_callback=="function" && (this.control != this.datestr()))
            try{ navegg_callback(); } catch(e) {};
    
    };

    this.setCookie = function (fld,vle,ttl)
    {
    var ltd='';
    if (this.domain) ltd = ';domain=' + this.domain;
    var d = new Date();
    if(ttl!=ttl || !ttl) ttl=365;
    d.setTime(d.getTime()+(ttl*24*60*60*1000));
    var ttl = d.toGMTString();
    document.cookie = fld + "=" + vle + ";expires=" + ttl + ";path=/" + ltd;
    };

    this.include = function (src, inctype, html, nvgasync)
    {
        if (inctype == '' || inctype == undefined) inctype="script";
        if (nvgasync === '' || nvgasync === undefined) nvgasync=true;
        var c=document.createElement(inctype);
        if (inctype == 'script')   c.type="text/javascript";
        if(html) c.text = html;
        else     c.src = src;
        c.async = nvgasync;

        var p = document.getElementsByTagName('script')[0];
        p.parentNode.insertBefore(c, p);
    };
    
    this.getCookie = function (name)
    {
        var start = document.cookie.indexOf( name + "=" );
        var len = start + name.length + 1;
        if ( ( !start ) && ( name != document.cookie.substring( 0, name.length ) ) ) return null;
        if ( start == -1 ) return null;
        var end = document.cookie.indexOf( ";", len );
        if ( end == -1 ) end = document.cookie.length;
        return unescape( document.cookie.substring( len, end ) );
    };

    this.getSegment = function ( fld )
    {
    if(fld in this.cokCache) return this.cokCache[fld]||'';
    var cpos = new Array(),segpa,segpb,rtn='',x=0;
    if (!this.segmentValue) {
        var ckcnt;
        cpos[0]=0;cpos[1]=1;
        if(!(ckcnt=this.tuple)) {
                cpos[0]=2;cpos[1]=3;
                if (!(ckcnt = this.navdmp)) { return ''; };
        };
        ckcnt = ckcnt.split('_');
        try {
        this.segmentKey = ckcnt[cpos[0]].split(':');
        this.segmentValue = ckcnt[cpos[1]].split(':'); 
        } catch(e) {return ''};
    };
    segpa = this.findOf(fld,this.segments);
    if (segpa) segpb = this.findOf(segpa.toString(),this.segmentKey );
    if (segpb>=0) rtn = this.segmentValue[ segpb ];
    if(rtn==undefined)return '';
    rtn = rtn.indexOf(';')>=0 ? rtn.split(';').join('-') : rtn;
    if(rtn.indexOf('-')>=0){
            rtnt = rtn.split('-');
            rtnf = new Array();
    for(x=0;x in rtnt;x++)
                    if(rtnt[x]!='' && rtnt[x]!='undefined')
                            rtnf.push(rtnt[x]);
            rtn = rtnf.join('-');
    };
        this.cokCache[fld] = rtn;
        return rtn;
    };
    
    this.datestr = function ()
    {
        var d = new Date();
        return (d.getMonth().toString() + d.getDate().toString()) ;
    };
    
    this.getParameter=function(fld){
      if(!this.qry){
        this.qry = {};
        prmstr = window.location.search.substr(1);
        prmarr = prmstr.split ("&");
        for(var i = 0; i < prmarr.length; i++){
          tmparr = prmarr[i].split("=");
          this.qry[tmparr[0]] = tmparr[1];
        };
      };
      return this.qry[fld] || '';
    };

    this.cokCustom = function (id)
    {
                var ckc = ':'+this.tag+':';
                var cok = new Array();
                cok[0]  = new Array();
                cok[1]  = new Array();
                var str,paA,paB,cokPos,y;str=paA=paB = '';y=0;
                cokPos = this.HighGranularity ? 1 : 0;
                for (x=0;this.segmentKey[x];x++)
                {
                    if(ckc.search(':'+this.segments[this.segmentKey[x]]+':') == -1) continue;
                    paA = this.segmentKey[x];
                    paB =  this.segmentValue[x] || '';
                    if(paB=='') continue;
                    cok[0][y]   = paA;
                    cok[1][y]   = paB;
                    if(paB.search('-')>=0 || paB.search(';')>=0)
                    {
                        if(paB.search(';')>0) paB = paB.split(';')[cokPos];
                        var ncok = new Array();
                        var nmac = paB.split('-');
                        for(h=0;nmac[h]&&h<this.maxCriteria;h++)
                            ncok[h] = nmac[h];
                        cok[1][y] = ncok.join('-');
                    };
                    y++;
                };
                str = cok[0].join(':').replace(/;/g,'') + '_' + cok[1].join(':').replace(/;/g,'');
                this.setCookie(this.coknm,id + '_' + this.datestr() + '_' + str);

    };

    this.saveRequest = function(profile)
    {
            var a;
            this.parameter += '&id=' + profile + this.userSync;
            if (this.account) this.parameter += '&acc=' + this.account;
            if (this.product) this.parameter += '&prd=' + this.product;
            if (this.category) this.parameter += '&cat=' + this.category;
            if (this.url) this.parameter += '&url=' + escape(this.url);
            if (document.referrer) this.parameter += '&ref=' + escape(document.referrer);
            this.parameter += '&tit=' + escape(document.title);
            if(a=this.getCookie('__utmz')) this.parameter += '&utm=' + escape(a);
            this.include('//' + this.server[1] + this.parameter);
            if(typeof navegg_callback=="function" && (this.control == this.datestr()))
                try{ navegg_callback(); } catch(e) {};
    };

    this.setCustom = function(custom)
    {
        var toCus = '/req';
        toCus     += '?acc=' + this.account;
        if(this.userId)toCus    += '&usr=' + this.userId;
        toCus    += '&cus=' + custom;
        this.include('//' + this.server[1] + toCus);
    };

    this.doSync = function(version)
    {
        var cok = this.getCookie(this.coknm)||'';
        cok = cok.split('_');
        if(cok[0].search(/\|/)>=0)
        {
            cok[0] = cok[0].split('|');
            cok[0] = cok[0][0];
        };
        cok[0] +='|'+version;
        cok = cok.join('_');
        this.setCookie(this.coknm,cok);
    };

    this.saveLocal = function(id,data)
    {
        window.localStorage.setItem(id,data);
    };

    this.findOf = function(val,ar)
    {
    if(typeof(ar)!='object') return -1;
    for(x in ar) if(ar[x]==val) return x;
    return -1;
    };

    this.setCustomTargeting = function(rules,cusId){
     var nvg_pos_x,nvg_pos_y,nvg_or_arr,nvg_flag;
     for( nvg_pos_x=0;nvg_pos_x<rules.length;nvg_pos_x++){
         nvg_or_arr = rules[ nvg_pos_x ];
         nvg_flag = false;
         for(nvg_pos_y=0;nvg_pos_y<nvg_or_arr.length;nvg_pos_y++){
             if( window.location.href.search( nvg_or_arr[ nvg_pos_y ] )>=0 )
                 nvg_flag = true;
         }
         if(!nvg_flag) return false;
     }
     this.setCustom(cusId);
     return true;
    }

};
function nvgGetSegment (f)
{
    return nvg18894.getSegment(f);
};

function ltgc(s)
{
    return nvg18894.getSegment(s);
};

nvg18894.load();

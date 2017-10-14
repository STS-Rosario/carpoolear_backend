;(function($){var allImages={};var imageCounter=0;$.galleriffic={version:'2.0.1',normalizeHash:function(hash){return hash.replace(/^.*#/,'').replace(/\?.*$/,'');},getImage:function(hash){if(!hash)
return undefined;hash=$.galleriffic.normalizeHash(hash);return allImages[hash];},gotoImage:function(hash){var imageData=$.galleriffic.getImage(hash);if(!imageData)
return false;var gallery=imageData.gallery;gallery.gotoImage(imageData);return true;},removeImageByHash:function(hash,ownerGallery){var imageData=$.galleriffic.getImage(hash);if(!imageData)
return false;var gallery=imageData.gallery;if(ownerGallery&&ownerGallery!=gallery)
return false;return gallery.removeImageByIndex(imageData.index);}};var defaults={delay:3000,numThumbs:20,preloadAhead:40,enableTopPager:false,enableBottomPager:true,maxPagesToShow:7,imageContainerSel:'',captionContainerSel:'',controlsContainerSel:'',loadingContainerSel:'',renderSSControls:true,renderNavControls:true,playLinkText:'Play',pauseLinkText:'Pause',prevLinkText:'Previous',nextLinkText:'Next',nextPageLinkText:'Next &rsaquo;',prevPageLinkText:'&lsaquo; Prev',enableHistory:false,enableKeyboardNavigation:true,autoStart:false,syncTransitions:false,defaultTransitionDuration:1000,onSlideChange:undefined,onTransitionOut:undefined,onTransitionIn:undefined,onPageTransitionOut:undefined,onPageTransitionIn:undefined,onImageAdded:undefined,onImageRemoved:undefined};$.fn.galleriffic=function(settings){$.extend(this,{version:$.galleriffic.version,isSlideshowRunning:false,slideshowTimeout:undefined,clickHandler:function(e,link){this.pause();if(!this.enableHistory){var hash=$.galleriffic.normalizeHash($(link).attr('href'));$.galleriffic.gotoImage(hash);e.preventDefault();}},appendImage:function(listItem){this.addImage(listItem,false,false);return this;},insertImage:function(listItem,position){this.addImage(listItem,false,true,position);return this;},addImage:function(listItem,thumbExists,insert,position){var $li=(typeof listItem==="string")?$(listItem):listItem;var $aThumb=$li.find('a.thumb');var slideUrl=$aThumb.attr('href');var title=$aThumb.attr('title');var $caption=$li.find('.caption').remove();var hash=$aThumb.attr('name');imageCounter++;if(!hash||allImages[''+hash]){hash=imageCounter;}
if(!insert)
position=this.data.length;var imageData={title:title,slideUrl:slideUrl,caption:$caption,hash:hash,gallery:this,index:position};if(insert){this.data.splice(position,0,imageData);this.updateIndices(position);}
else{this.data.push(imageData);}
var gallery=this;if(!thumbExists){this.updateThumbs(function(){var $thumbsUl=gallery.find('ul.thumbs');if(insert)
$thumbsUl.children(':eq('+position+')').before($li);else
$thumbsUl.append($li);if(gallery.onImageAdded)
gallery.onImageAdded(imageData,$li);});}
allImages[''+hash]=imageData;$aThumb.attr('rel','history')
.attr('href','#'+hash)
.removeAttr('name')
.click(function(e){gallery.clickHandler(e,this);});return this;},removeImageByIndex:function(index){if(index<0||index>=this.data.length)
return false;var imageData=this.data[index];if(!imageData)
return false;this.removeImage(imageData);return true;},removeImageByHash:function(hash){return $.galleriffic.removeImageByHash(hash,this);},removeImage:function(imageData){var index=imageData.index;this.data.splice(index,1);delete allImages[''+imageData.hash];this.updateThumbs(function(){var $li=gallery.find('ul.thumbs')
.children(':eq('+index+')')
.remove();if(gallery.onImageRemoved)
gallery.onImageRemoved(imageData,$li);});this.updateIndices(index);return this;},updateIndices:function(startIndex){for(i=startIndex;i<this.data.length;i++){this.data[i].index=i;}
return this;},initializeThumbs:function(){this.data=[];var gallery=this;this.find('ul.thumbs > li').each(function(i){gallery.addImage($(this),true,false);});return this;},isPreloadComplete:false,preloadInit:function(){if(this.preloadAhead==0)return this;this.preloadStartIndex=this.currentImage.index;var nextIndex=this.getNextIndex(this.preloadStartIndex);return this.preloadRecursive(this.preloadStartIndex,nextIndex);},preloadRelocate:function(index){this.preloadStartIndex=index;return this;},preloadRecursive:function(startIndex,currentIndex){if(startIndex!=this.preloadStartIndex){var nextIndex=this.getNextIndex(this.preloadStartIndex);return this.preloadRecursive(this.preloadStartIndex,nextIndex);}
var gallery=this;var preloadCount=currentIndex-startIndex;if(preloadCount<0)
preloadCount=this.data.length-1-startIndex+currentIndex;if(this.preloadAhead>=0&&preloadCount>this.preloadAhead){setTimeout(function(){gallery.preloadRecursive(startIndex,currentIndex);},500);return this;}
var imageData=this.data[currentIndex];if(!imageData)
return this;if(imageData.image)
return this.preloadNext(startIndex,currentIndex);var image=new Image();image.onload=function(){imageData.image=this;gallery.preloadNext(startIndex,currentIndex);};image.alt=imageData.title;image.src=imageData.slideUrl;return this;},preloadNext:function(startIndex,currentIndex){var nextIndex=this.getNextIndex(currentIndex);if(nextIndex==startIndex){this.isPreloadComplete=true;}else{var gallery=this;setTimeout(function(){gallery.preloadRecursive(startIndex,nextIndex);},100);}
return this;},getNextIndex:function(index){var nextIndex=index+1;if(nextIndex>=this.data.length)
nextIndex=0;return nextIndex;},getPrevIndex:function(index){var prevIndex=index-1;if(prevIndex<0)
prevIndex=this.data.length-1;return prevIndex;},pause:function(){this.isSlideshowRunning=false;if(this.slideshowTimeout){clearTimeout(this.slideshowTimeout);this.slideshowTimeout=undefined;}
if(this.$controlsContainer){this.$controlsContainer
.find('div.ss-controls a').removeClass().addClass('play')
.attr('title',this.playLinkText)
.attr('href','#play')
.html(this.playLinkText);}
return this;},play:function(){this.isSlideshowRunning=true;if(this.$controlsContainer){this.$controlsContainer
.find('div.ss-controls a').removeClass().addClass('pause')
.attr('title',this.pauseLinkText)
.attr('href','#pause')
.html(this.pauseLinkText);}
if(!this.slideshowTimeout){var gallery=this;this.slideshowTimeout=setTimeout(function(){gallery.ssAdvance();},this.delay);}
return this;},toggleSlideshow:function(){if(this.isSlideshowRunning)
this.pause();else
this.play();return this;},ssAdvance:function(){if(this.isSlideshowRunning)
this.next(true);return this;},next:function(dontPause,bypassHistory){this.gotoIndex(this.getNextIndex(this.currentImage.index),dontPause,bypassHistory);return this;},previous:function(dontPause,bypassHistory){this.gotoIndex(this.getPrevIndex(this.currentImage.index),dontPause,bypassHistory);return this;},nextPage:function(dontPause,bypassHistory){var page=this.getCurrentPage();var lastPage=this.getNumPages()-1;if(page<lastPage){var startIndex=page*this.numThumbs;var nextPage=startIndex+this.numThumbs;this.gotoIndex(nextPage,dontPause,bypassHistory);}
return this;},previousPage:function(dontPause,bypassHistory){var page=this.getCurrentPage();if(page>0){var startIndex=page*this.numThumbs;var prevPage=startIndex-this.numThumbs;this.gotoIndex(prevPage,dontPause,bypassHistory);}
return this;},gotoIndex:function(index,dontPause,bypassHistory){if(!dontPause)
this.pause();if(index<0)index=0;else if(index>=this.data.length)index=this.data.length-1;var imageData=this.data[index];if(!bypassHistory&&this.enableHistory)
$.historyLoad(String(imageData.hash));else
this.gotoImage(imageData);return this;},gotoImage:function(imageData){var index=imageData.index;if(this.onSlideChange)
this.onSlideChange(this.currentImage.index,index);this.currentImage=imageData;this.preloadRelocate(index);this.refresh();return this;},getDefaultTransitionDuration:function(isSync){if(isSync)
return this.defaultTransitionDuration;return this.defaultTransitionDuration/2;},refresh:function(){var imageData=this.currentImage;if(!imageData)
return this;var index=imageData.index;if(this.$controlsContainer){this.$controlsContainer
.find('div.nav-controls a.prev').attr('href','#'+this.data[this.getPrevIndex(index)].hash).end()
.find('div.nav-controls a.next').attr('href','#'+this.data[this.getNextIndex(index)].hash);}
var previousSlide=this.$imageContainer.find('span.current').addClass('previous').removeClass('current');var previousCaption=0;if(this.$captionContainer){previousCaption=this.$captionContainer.find('span.current').addClass('previous').removeClass('current');}
var isSync=this.syncTransitions&&imageData.image;var isTransitioning=true;var gallery=this;var transitionOutCallback=function(){isTransitioning=false;previousSlide.remove();if(previousCaption)
previousCaption.remove();if(!isSync){if(imageData.image&&imageData.hash==gallery.data[gallery.currentImage.index].hash){gallery.buildImage(imageData,isSync);}else{if(gallery.$loadingContainer){gallery.$loadingContainer.show();}}}};if(previousSlide.length==0){transitionOutCallback();}else{if(this.onTransitionOut){this.onTransitionOut(previousSlide,previousCaption,isSync,transitionOutCallback);}else{previousSlide.fadeTo(this.getDefaultTransitionDuration(isSync),0.0,transitionOutCallback);if(previousCaption)
previousCaption.fadeTo(this.getDefaultTransitionDuration(isSync),0.0);}}
if(isSync)
this.buildImage(imageData,isSync);if(!imageData.image){var image=new Image();image.onload=function(){imageData.image=this;if(!isTransitioning&&imageData.hash==gallery.data[gallery.currentImage.index].hash){gallery.buildImage(imageData,isSync);}};image.alt=imageData.title;image.src=imageData.slideUrl;}
this.relocatePreload=true;return this.syncThumbs();},buildImage:function(imageData,isSync){var gallery=this;var nextIndex=this.getNextIndex(imageData.index);var newSlide=this.$imageContainer
.append('<span class="image-wrapper current"><a class="advance-link" rel="history" href="#'+this.data[nextIndex].hash+'" title="'+imageData.title+'">&nbsp;</a></span>')
.find('span.current').css('opacity','0');newSlide.find('a')
.append(imageData.image)
.click(function(e){gallery.clickHandler(e,this);});var newCaption=0;if(this.$captionContainer){newCaption=this.$captionContainer
.append('<span class="image-caption current"></span>')
.find('span.current').css('opacity','0')
.append(imageData.caption);}
if(this.$loadingContainer){this.$loadingContainer.hide();}
if(this.onTransitionIn){this.onTransitionIn(newSlide,newCaption,isSync);}else{newSlide.fadeTo(this.getDefaultTransitionDuration(isSync),1.0);if(newCaption)
newCaption.fadeTo(this.getDefaultTransitionDuration(isSync),1.0);}
if(this.isSlideshowRunning){if(this.slideshowTimeout)
clearTimeout(this.slideshowTimeout);this.slideshowTimeout=setTimeout(function(){gallery.ssAdvance();},this.delay);}
return this;},getCurrentPage:function(){return Math.floor(this.currentImage.index/this.numThumbs);},syncThumbs:function(){var page=this.getCurrentPage();if(page!=this.displayedPage)
this.updateThumbs();var $thumbs=this.find('ul.thumbs').children();$thumbs.filter('.selected').removeClass('selected');$thumbs.eq(this.currentImage.index).addClass('selected');return this;},updateThumbs:function(postTransitionOutHandler){var gallery=this;var transitionOutCallback=function(){if(postTransitionOutHandler)
postTransitionOutHandler();gallery.rebuildThumbs();if(gallery.onPageTransitionIn)
gallery.onPageTransitionIn();else
gallery.show();};if(this.onPageTransitionOut){this.onPageTransitionOut(transitionOutCallback);}else{this.hide();transitionOutCallback();}
return this;},rebuildThumbs:function(){var needsPagination=this.data.length>this.numThumbs;if(this.enableTopPager){var $topPager=this.find('div.top');if($topPager.length==0)
$topPager=this.prepend('<div class="top pagination"></div>').find('div.top');else
$topPager.empty();if(needsPagination)
this.buildPager($topPager);}
if(this.enableBottomPager){var $bottomPager=this.find('div.bottom');if($bottomPager.length==0)
$bottomPager=this.append('<div class="bottom pagination"></div>').find('div.bottom');else
$bottomPager.empty();if(needsPagination)
this.buildPager($bottomPager);}
var page=this.getCurrentPage();var startIndex=page*this.numThumbs;var stopIndex=startIndex+this.numThumbs-1;if(stopIndex>=this.data.length)
stopIndex=this.data.length-1;var $thumbsUl=this.find('ul.thumbs');$thumbsUl.find('li').each(function(i){var $li=$(this);if(i>=startIndex&&i<=stopIndex){$li.show();}else{$li.hide();}});this.displayedPage=page;$thumbsUl.removeClass('noscript');return this;},getNumPages:function(){return Math.ceil(this.data.length/this.numThumbs);},buildPager:function(pager){var gallery=this;var numPages=this.getNumPages();var page=this.getCurrentPage();var startIndex=page*this.numThumbs;var pagesRemaining=this.maxPagesToShow-1;var pageNum=page-Math.floor((this.maxPagesToShow-1)/2)+1;if(pageNum>0){var remainingPageCount=numPages-pageNum;if(remainingPageCount<pagesRemaining){pageNum=pageNum-(pagesRemaining-remainingPageCount);}}
if(pageNum<0){pageNum=0;}
if(page>0){var prevPage=startIndex-this.numThumbs;pager.append('<a rel="history" href="#'+this.data[prevPage].hash+'" title="'+this.prevPageLinkText+'">'+this.prevPageLinkText+'</a>');}
if(pageNum>0){this.buildPageLink(pager,0,numPages);if(pageNum>1)
pager.append('<span class="ellipsis">&hellip;</span>');pagesRemaining--;}
while(pagesRemaining>0){this.buildPageLink(pager,pageNum,numPages);pagesRemaining--;pageNum++;}
if(pageNum<numPages){var lastPageNum=numPages-1;if(pageNum<lastPageNum)
pager.append('<span class="ellipsis">&hellip;</span>');this.buildPageLink(pager,lastPageNum,numPages);}
var nextPage=startIndex+this.numThumbs;if(nextPage<this.data.length){pager.append('<a rel="history" href="#'+this.data[nextPage].hash+'" title="'+this.nextPageLinkText+'">'+this.nextPageLinkText+'</a>');}
pager.find('a').click(function(e){gallery.clickHandler(e,this);});return this;},buildPageLink:function(pager,pageNum,numPages){var pageLabel=pageNum+1;var currentPage=this.getCurrentPage();if(pageNum==currentPage)
pager.append('<span class="current">'+pageLabel+'</span>');else if(pageNum<numPages){var imageIndex=pageNum*this.numThumbs;pager.append('<a rel="history" href="#'+this.data[imageIndex].hash+'" title="'+pageLabel+'">'+pageLabel+'</a>');}
return this;}});$.extend(this,defaults,settings);if(this.enableHistory&&!$.historyInit)
this.enableHistory=false;if(this.imageContainerSel)this.$imageContainer=$(this.imageContainerSel);if(this.captionContainerSel)this.$captionContainer=$(this.captionContainerSel);if(this.loadingContainerSel)this.$loadingContainer=$(this.loadingContainerSel);this.initializeThumbs();if(this.maxPagesToShow<3)
this.maxPagesToShow=3;this.displayedPage=-1;this.currentImage=this.data[0];var gallery=this;if(this.$loadingContainer)
this.$loadingContainer.hide();if(this.controlsContainerSel){this.$controlsContainer=$(this.controlsContainerSel).empty();if(this.renderSSControls){if(this.autoStart){this.$controlsContainer
.append('<div class="ss-controls"><a href="#pause" class="pause" title="'+this.pauseLinkText+'">'+this.pauseLinkText+'</a></div>');}else{this.$controlsContainer
.append('<div class="ss-controls"><a href="#play" class="play" title="'+this.playLinkText+'">'+this.playLinkText+'</a></div>');}
this.$controlsContainer.find('div.ss-controls a')
.click(function(e){gallery.toggleSlideshow();e.preventDefault();return false;});}
if(this.renderNavControls){this.$controlsContainer
.append('<div class="nav-controls"><a class="prev" rel="history" title="'+this.prevLinkText+'">'+this.prevLinkText+'</a><a class="next" rel="history" title="'+this.nextLinkText+'">'+this.nextLinkText+'</a></div>')
.find('div.nav-controls a')
.click(function(e){gallery.clickHandler(e,this);});}}
var initFirstImage=!this.enableHistory||!location.hash;if(this.enableHistory&&location.hash){var hash=$.galleriffic.normalizeHash(location.hash);var imageData=allImages[hash];if(!imageData)
initFirstImage=true;}
if(initFirstImage)
this.gotoIndex(0,false,true);if(this.enableKeyboardNavigation){$(document).keydown(function(e){var key=e.charCode?e.charCode:e.keyCode?e.keyCode:0;switch(key){case 32:gallery.next();e.preventDefault();break;case 33:gallery.previousPage();e.preventDefault();break;case 34:gallery.nextPage();e.preventDefault();break;case 35:gallery.gotoIndex(gallery.data.length-1);e.preventDefault();break;case 36:gallery.gotoIndex(0);e.preventDefault();break;case 37:gallery.previous();e.preventDefault();break;case 39:gallery.next();e.preventDefault();break;}});}
if(this.autoStart)
this.play();setTimeout(function(){gallery.preloadInit();},1000);return this;};})(jQuery);
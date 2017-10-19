/*
  Simple slideshow using prototype and scriptaculous.
  
  Usage:
  
    <script src="prototype.js"></script>
    <script src="effects.js"></script>
    <script src="slideshow.js"></script>
    <style type="text/css">
      #slideshow { position: relative; width: 100px; height: 100px; }
      #slideshow div { position: absolute; left: 0; top: 0; }
    </style>
    <div class="slideshow" id="slideshow">
      <div class="slide"><img src="slide1.jpg"></div>
      <div class="slide"><img src="slide2.jpg"></div>
      <div class="slide"><img src="slide3.jpg"></div>
    </div>
    <script type="text/javascript">new Slideshow('slideshow', 3000);</script>
  
  See also: http://blog.remvee.net/post/17
  
  Copyright (c) 2006 - R.W. van 't Veer
*/

function Slideshow(slideshow, timeout) {
  this.slides = [];
  var nl = $(slideshow).getElementsByTagName('div');
  for (var i = 0; i < nl.length; i++) {
    if (Element.hasClassName(nl[i], 'slide')) {
      this.slides.push(nl[i]);
    }
  }
	this.timeout = timeout;
	this.current = 0;
	this.pause = false;
	this.sense = this.next;

  for (var i = 0; i < this.slides.length; i++) {
    this.slides[i].style.zIndex = this.slides.length - i;
  }

  Element.show(slideshow);
}
Slideshow.prototype = {
	init: function() {
		setTimeout((function(){this.start();}).bind(this), this.timeout);
	},
	start: function() {
		if (this.pause == false) {
			this.sense();
		}
		setTimeout((function(){this.start();}).bind(this), this.timeout + 850);
	},
	stop: function() {
		this.pause = true;
	},
  next: function() {
    for (var i = 0; i < this.slides.length; i++) {
      var slide = this.slides[(this.current + i) % this.slides.length];
      slide.style.zIndex = this.slides.length - i;
    }

    Effect.Fade(this.slides[this.current], {
      afterFinish: function(effect) {
        effect.element.style.zIndex = 0;
        Element.show(effect.element);
        Element.setOpacity(effect.element, 1);
      }
    });
    
    this.current = (this.current + 1) % this.slides.length;
	this.sense = this.next;
//     setTimeout((function(){this.sense();}).bind(this), this.timeout + 850);
  },
  prev: function() {
    for (var i = this.slides.length; i > 0; i--) {
      var slide = this.slides[(this.current + i) % this.slides.length];
      slide.style.zIndex = this.slides.length + i;
    }

    Effect.Fade(this.slides[this.current], {
      afterFinish: function(effect) {
        effect.element.style.zIndex = 0;
        Element.show(effect.element);
        Element.setOpacity(effect.element, 1);
      }
    });
	if (this.current != 0) {
	    this.current = (this.current - 1);
	}
	else {
 		this.current = (this.slides.length - 1);
	}
	this.sense = this.prev;
//     setTimeout((function(){this.sense();}).bind(this), this.timeout + 850);
  }
}

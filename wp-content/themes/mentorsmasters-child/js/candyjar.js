candyjar = {
	
	debugMode : false,
	
	api : {
		
		/*
			*
			*	DOM
			*
			*
		*/
		
		classify : function( element, _class_ ){
			pre_class = element.className;
			if( !pre_class.match( _class_ )){
				pre_class = pre_class + " " + _class_;
			}
			element.className = pre_class.trim();
		},
		
		declassify : function( element, _class_ ){
			pre_class = element.className;
			var pattern = new RegExp( _class_, 'g');
			if( pre_class.match( pattern )){
				pre_class = pre_class.replace( pattern, "");
			}
			element.className = pre_class.trim();
		},
		
		forLoop : function( _array_, fnc ){
			for( var i = 0, n = _array_.length; i < n; i++ ){
				// 
			}
		},
		
		/*
			*
			*	Events
			*
			*
		*/
		
		evBind : function( fnc, context ){
			return function(){
				return fnc.apply( context, arguments ); 
			}
		},
		
		evThrottle : function( method, context, interval ){
			if( typeof interval !== 'number' ){
				interval = 100;
			}
		
			clearTimeout( method.timerId );
			method.timerId = setTimeout( function(){
				method.call( context );
			} , interval );
		},
		
		evLoad : function( callback ){
			var i = 0;
			var load = setInterval( function(){
				if ( document.readyState === "complete" || i > 1000 ){
					clearInterval( load );
					callback.apply();				
				}
				i++;
			} , 100);			
		},
	
		evScroll : function( callback ){
			document.addEventListener( 'scroll' , function(){
				candyjar.api.evThrottle( callback, this, 10 );
			});
		},
	
		evResize : function( callback ){
			window.addEventListener( 'resize' , function(){
				candyjar.api.evThrottle( callback, this, 100 );
			})
		},

		evTransitionEnd : function() {
			var el = document.createElement('slipperydick');

			var transEndEventNames = {
				'WebkitTransition' : 'webkitTransitionEnd',
				'MozTransition'    : 'transitionend',
				'OTransition'      : 'oTransitionEnd otransitionend',
				'transition'       : 'transitionend'
			}
			for (var name in transEndEventNames) {
				if (el.style[name] !== undefined) {
					
					if( candyjar.debugMode === true ){ console.log( 'candyjar.api.evTransitionEnd: ' + transEndEventNames[name] ); }
					
					return { end : transEndEventNames[name] }
				}
			}
			return false // explicit for ie8 (  ._.)
		},
	
		evOnTransitionEnd : function( callback ){
			
			var tranType = candyjar.api.evTransitionEnd();
			
		},
		
		/*
			*
			*	HTTP
			*
			*
		*/
	
		ajaxReq : function( action, request, async, ajaxreq_before, ajaxreq_after, ajaxreq_error, ajaxreq_formdata ){
			if( typeof ajaxreq_before == 'function' ){
				ajaxreq_before.call();
			}
			
			var ajaxreq = new XMLHttpRequest();
			ajaxreq.open( action, request, async);
			if( !ajaxreq_formdata || typeof ajaxreq_formdata != 'object'){
				ajaxreq_formdata == null;
			}
			
			ajaxreq.onreadystatechange = function(){
			
				if( ajaxreq.readyState == 4 ){
					if( ajaxreq.status >= 200 && ajaxreq.status < 300 || ajaxreq.status == 304 ){
						if( typeof ajaxreq_after == 'function' ){
							ajaxreq_after( ajaxreq.response );
						}
					}
					else if( ajaxreq.status > 400 ){
						if( typeof ajaxreq_error == 'function' ){
							ajaxreq_error( ajaxreq.response );
						}
					}
				}
			}
			ajaxreq.send( ajaxreq_formdata );
		},
	
		urlVars : function(){
			var map = {};
			var parts = window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, function(m,key,value) {
				map[key] = value;
			});
			return map;
		},
		
		/*
			*
			*	client storage
			*
			*
		*/
	
		setCookie: function( name, subname, value, expire, path, domain, secure ){
			var final_collect = new Array();
		
			// check for pre-existing cookie
			if( document.cookie.match( name )){
				values_collect = candyjar.api.getCookie( name );
			
				// check if value already exists & remove
				for( var i = 0, n = values_collect.length; i < n; i++ ){
					if( values_collect[i] != subname +"="+ value ){
						final_collect.push( values_collect[i] );
					}
				}
				values_collect = null;
				// console.log( values_collect );
			}
		
			// write the .cookie function
			var cookie_output = encodeURIComponent( name ) +"=";
			cookie_output += encodeURIComponent( subname ) +"=";
			cookie_output += encodeURIComponent( value );
		
			if( final_collect.length > 0 ){
				i = 0
				while( i < 10 ){
					cookie_output += "&";
					for( var i = 0, n = final_collect.length; i < n; i++ ){
						cookie_output += final_collect[i];
						if( i < final_collect.length - 1 ){
							cookie_output += "&";
						}
						i++;
					}
				}
			}
			if( expire instanceof Date ){
				cookie_output += ";expires=" + expire.toGMTString();
			}
			if( path){
				cookie_output += ";path=" + path;
			}
			if( domain ){
				cookie_output += ";domain=" + domain;
			}
			if( secure ){
				cookie_output += ";secure";
			}
			document.cookie = cookie_output;
		},
	
		getCookie: function( name ){
			var values_collect = new Array();
			if( document.cookie.match( name )){
				name = decodeURIComponent( name );
			
				// mark beginning of specified name in string
				cookie_start = document.cookie.indexOf( name );
			
				/* 
					*	mark first occurance of semi-colon after cookie start
					*	or set it to the cookie length if no semi-colon exists
				*/
				cookie_end = document.cookie.indexOf( ";", cookie_start );
				if( cookie_end == -1 ){
					cookie_end = document.cookie.length;
				}
			
				// store the values between cookie_start (var name) & cookie_end (;)
				var pre_cookie_val = document.cookie.substring( cookie_start + name.length, cookie_end );
			
				// dump pre_cookie_val values into array breaking on &
				if( pre_cookie_val.indexOf( '&' ) > -1){
					values_collect = pre_cookie_val.split( '&' );
				}
				else{
					values_collect.push( pre_cookie_val );
				}
			
				// remove any = signs preceeding the subcookie value
				for( var i = 0, n = values_collect.length; i < n; i++ ){
					if( values_collect[i].match( /^=/ )){
						values_collect[i] = values_collect[i].replace( /^=/, '' );
					}
					// console.log( values_collect[i] );
				}
				return values_collect;
			}
			else{
				return null;
			}
		},
	},
	
	ui : {
		
		fastButton : function( ele, handler ){
			this.ele = ele;
			this.handler = handler;
			if ( 'ontouchstart' in ele ) {
				this.ele.addEventListener( 'touchstart', this, false );
				document.addEventListener( 'click', candyjar.ui.fastButton.prototype.ghostBusterClick, true );
			}
			else{
				this.ele.addEventListener( 'click', this, false );
			}
			
			if( candyjar.debugMode === true ){
				console.log( 'candyjar.ui.fastButton.ele: ' + this.ele );
				console.log( 'candyjar.ui.fastButton.handler: ' + this.handler );	
			}
		},
	},
};

candyjar.ui.fastButton.prototype = {
	constructor : candyjar.ui.fastButton,
	coordinates : [],
	
	handleEvent : function( ev ){
		if ( 'ontouchstart' in this.ele ) {
			switch ( ev.type ){
				case 'touchstart' : this.onTouchStart( ev );
				case 'touchmove' : 	this.onTouchMove( ev );
				case 'touchend' : 	this.evFire( ev );
			}
		}
		else{
			this.evFire( ev );
		}
		
		if( candyjar.debugMode === true ){
			console.log( 'candyjar.ui.fastButton.prototype.handleEvent() ev.type: ' + ev.type );
		}
	},
	
	evFire : function( ev ){
		ev.preventDefault();
		ev.stopPropagation();
		this.handler();
		this.reset();

		if( ev.type == 'touchend' ){
			this.ghostBuster( this.startX, this.startY );
		}
		
		if( candyjar.debugMode === true ){
			console.log( 'candyjar.ui.fastButton.prototype.evFire() ev.type: ' + ev.type );
		}
	},
	
	onTouchStart : function( ev ){
		ev.stopPropagation();
		
		this.ele.addEventListener( 'touchend' , this, false );
		document.body.addEventListener( 'touchmove', this, false );
		
		this.startX = ev.touches[0].clientX;
		this.startY = ev.touches[0].clientY;
		
		if( candyjar.debugMode === true ){
			console.log( 'candyjar.ui.fastButton.prototype.onTouchStart() startX, startY: ' + this.startx +" X "+ this.starty );
		}
	},
	
	onTouchMove : function( ev ){
		if( Math.abs( ev.touches[0].clientX - this.startX ) > 10 || Math.abs( ev.touches[0].clientY - this.startY ) > 10 ){
			this.reset();
		}
	},
	
	reset : function(){
		this.ele.removeEventListener( 'touchend', this, false );
		document.body.removeEventListener( 'touchmove', this, false );
	},
	
	ghostBuster : function( x, y ){
		this.coordinates.push( x, y );
		window.setTimeout( this.ghostBusterPop, 2500 );
	},
	
	ghostBusterPop : function(){
		this.coordinates.splice( 0, 2 );
	},
	
	ghostBusterClick : function( ev ){
		if( this.coordinates.length ){
			for( var i = 0; i < this.coordinates.length; i += 2 ){
				var x = this.coordinates[i];
				var y = this.coordinates[i + 1];
				if( Math.abs( ev.clientX - x ) < 25 && Math.abs( ev.clientY - y) < 25 ){
					ev.stopPropagation();
					ev.preventDefault();
				}
			}
		}
		// console.log( 'ghostBusterClick' );
	},
};
var wpbdp = window.wpbdp || {};

(function($) {
    var googlemaps = wpbdp.googlemaps = wpbdp.googlemaps || {};
    googlemaps.listeners = { 'map_created': [], 'map_rendered': [] };

    googlemaps.Map = function( htmlID, settings ) {
        this.MAP_TYPES = {
            'roadmap': google.maps.MapTypeId.ROADMAP,
            'satellite': google.maps.MapTypeId.SATELLITE,
            'hybrid': google.maps.MapTypeId.HYBRID,
            'terrain': google.maps.MapTypeId.TERRAIN
        };

        this.$map = $( '#' + htmlID );
        this.locations = [];
        this.settings = settings;
        this.settings.removeEmpty = true;
        this.bounds = new google.maps.LatLngBounds();
        
        this.GoogleMap = new google.maps.Map( this.$map[0], { mapTypeId: this.MAP_TYPES[ this.settings.map_type ] } );
        this.infoWindow = new google.maps.InfoWindow();        
        this.oms = new OverlappingMarkerSpiderfier( this.GoogleMap,
                                                    { markersWontMove: true,
                                                      markersWontHide: true,
                                                      keepSpiderfied: true } );

        for ( i = 0; i < googlemaps.listeners['map_created'].length; i++ )
            googlemaps.listeners['map_created'][i]( this );
    };

    $.extend( googlemaps.Map.prototype, {
        _addMarker: function( place ) {
            if ( 'undefined' === typeof( place ) || ! place )
                return;

            if ( 'undefined' === typeof( place.geolocation) || ! place.geolocation ||
                 'undefined' === typeof( place.geolocation.lat ) || ! place.geolocation.lat ||
                 'undefined' === typeof( place.geolocation.lng ) || ! place.geolocation.lng )
                return;

            var position = new google.maps.LatLng( place.geolocation.lat, place.geolocation.lng );
            this.bounds.extend( position );

            var marker = new google.maps.Marker({
                map: this.GoogleMap,
                position: position,
                animation: this.settings.animate_markers ? google.maps.Animation.DROP : null
            });
            marker.descriptionHTML = '<small><a href="' + place.url + '"><b>' + place.title + '</b></a><br />' + place.content.replace( "\n", "<br />" ) + '</small>';
            this.oms.addMarker( marker );
        },

        setLocations: function( locations ) {
            this.locations = locations;
        },

        fitContainer: function(stretch, enlarge) {
            if ( ! this.settings.auto_resize || "auto" === this.settings.map_size )
                return;

            var parent_width = this.$map.parent().innerWidth();
            var current_width = this.$map.outerWidth();

            if ( parent_width < current_width ) {
                this.$map.width( parent_width - 2 );
            } else if ( parent_width >= this.orig_width ) {
                this.$map.width(map.orig_width - 2);
            }

            google.maps.event.trigger( this.GoogleMap, "resize" );
        },

        render: function() {
            var map = this;
            this.orig_width = this.$map.width();

            var listingsContainer = this.$map.parent().siblings().find('div.listings');

            if ( 'top' == this.settings.position ) {
                this.$map.prependTo( listingsContainer );
            } else {
                if ( $( 'div.listings .wpbdp-pagination' ).length > 0 ) {
                    this.$map.insertBefore( $( '.wpbdp-pagination', listingsContainer ) );
                }
            }

            // Add markers to map.
            if ( this.locations ) {
                for( var i = 0; i < this.locations.length; i++ ) {
                    this._addMarker( this.locations[i] );
                }
            }

            this.oms.addListener( 'click', function( marker, event ) {
                map.infoWindow.setContent( marker.descriptionHTML );
                map.infoWindow.open( map.GoogleMap, marker );
            });

            this.oms.addListener( 'spiderfy' , function( markers ) {
              map.infoWindow.close();
            });

            for ( var i = 0; i < googlemaps.listeners['map_rendered'].length; i++ )
                googlemaps.listeners['map_rendered'][i]( map );

            google.maps.event.addListenerOnce( this.GoogleMap, 'idle', function() {
                if ( map.settings.removeEmpty && ! map.locations )
                    map.$map.remove();

                if ( map.locations.length == 1 ) {
                    map.GoogleMap.setZoom( 15 );
                } else {
                    map.GoogleMap.fitBounds( map.bounds );
                }

                map.GoogleMap.setCenter( map.bounds.getCenter() );
            });

            $(window).resize(function() {
                map.fitContainer( true, false );
            });

            map.fitContainer( true, false );
        }
    });

    /**
     * @since 3.6
     */
    googlemaps.DirectionsHandler = function( map, $form, $display ) {
        if ( 0 == $form.length )
            return;

        if ( ! map.settings.listingID || map.locations.length != 1 )
            return;

        this._map = map;
        this._$form = $form;
        this._$display = null;

        this.from = null;
        this.to = [map.locations[0].geolocation.lat, map.locations[0].geolocation.lng];
        this.travelMode = null;

        this._working = false;
        this._error = '';

        var t = this;
        t._$form.find( '.find-route-btn' ).click(function(e) {
            e.preventDefault();
            t.startRouting();
        });

        t._$form.find( 'input[name="from_mode"]' ).change(function(e) {
            if ( 'address' == $(this).val() )
                t._$form.find( 'input[name="from_address"]' ).show().focus();
            else
                t._$form.find( 'input[name="from_address"]' ).hide();
        });
    }

    $.extend( googlemaps.DirectionsHandler.prototype, {
        HTML_TEMPLATE : '<div id="wpbdp-map-directions-wrapper" style="display: none;">' +
                        '<div id="wpbdp-map-directions" class="cf">' +
                        '<div class="wpbdp-google-map route-map"></div>' + 
                        '<div class="directions-panel"></div>' +
                        '</div>' +
                        '</div>',

        TRAVEL_MODES : {
            'driving': google.maps.TravelMode.DRIVING,
            'cycling': google.maps.TravelMode.BICYCLING,
            'transit': google.maps.TravelMode.TRANSIT,
            'walking': google.maps.TravelMode.WALKING
        },

/*an API key. Each TransitMode specifies a preferred mode of transit. The following values are permitted:
google.maps.TransitMode.BUS indicates that the calculated route should prefer travel by bus.
google.maps.TransitMode.RAIL indicates that the calculated route should prefer travel by train, tram, light rail, and subway.
google.maps.TransitMode.SUBWAY indicates that the calculated route should prefer travel by subway.
google.maps.TransitMode.TRAIN indicates that the calculated route should prefer travel by train.
google.maps.TransitMode.TRAM indicates that the calculated route should prefer travel by tram and light rail.*/        

        error: function( msg ) {
            var t = this;
            t._error = msg;
            t._working = false;

            alert( t._error );

            t._$form.find( '.find-route-btn' ).removeAttr( 'disabled' )
                                              .val( WPBDP_googlemaps_directions_l10n.submit_normal );
        },

        startRouting: function() {
            var t = this;

            if ( t._working )
                return;

            t._working = true;
            t._$form.find( '.find-route-btn' ).attr( 'disabled', 'disabled' )
                                              .val( WPBDP_googlemaps_directions_l10n.submit_working );

            // Reset everything.
            $( '#wpbdp-map-directions-wrapper' ).remove();
            t._$display = $( t.HTML_TEMPLATE ).appendTo( 'body' );

            var fromMode = t._$form.find( 'input[name="from_mode"]:checked' ).val();
            var address = $.trim( this._$form.find( 'input[name="from_address"]' ).val() );
            var travelMode = t._$form.find( 'select[name="travel_mode"]' ).val();

            if ( 'current' != fromMode && 'address' != fromMode ) {
                t.error('XXX');
                return;
            }

            if ( 'address' == fromMode && ! address ) {
                t.error('XXX');
                return;
            }

            if ( 'driving' != travelMode && 'cycling' != travelMode && 'walking' != travelMode && 'transit' != travelMode ) {
                t.error('XX');
                return;
            }

            t.travelMode = travelMode;

            if ( 'current' == fromMode ) {
                t.geolocate();
            } else {
                // TODO: maybe we can do away with passing the string directly? Does running the geocoding service work
                // better?
                t.geocode( address );
            }
        },

        geolocate: function() {
            var t = this;

            if ( ! t._working )
                return;

            if ( ! navigator.geolocation )
                t.error('XX');

            navigator.geolocation.getCurrentPosition(
                function(pos) {
                    t.from = [ pos.coords.latitude, pos.coords.longitude ];
                    t.displayRoute();
                },
                function(err) {
                    t.error('XX');
                }
            );
        },

        geocode: function( address ) {
            var t = this;

            if ( ! t._working )
                return;

            if ( 'undefined' == typeof t._geocoderService )
                t._geocoderService = new google.maps.Geocoder();

            t._geocoderService.geocode( { 'address': address }, function( results, status_ ) {

                if ( ! google.maps.GeocoderStatus.OK == status_ )
                    t.error('XX');

                var pos = results[0].geometry.location;
                t.from = [ pos.lat(), pos.lng() ];
                t.displayRoute();
            } );
        },

        displayRoute: function() {
            var t = this;

            if ( ! t._working )
                return;

            var directionsDisplay, directionsService;
            directionsDisplay = new google.maps.DirectionsRenderer();
            directionsService = new google.maps.DirectionsService();

            var request = {
                origin: new google.maps.LatLng( t.from[0], t.from[1] ),
                destination: new google.maps.LatLng( t.to[0], t.to[1] ),
                travelMode: t.TRAVEL_MODES[ t.travelMode ]
            };

            directionsService.route( request, function( res, status_ ) {
                if ( google.maps.DirectionsStatus.OK != status_ ) {
                    t.error( WPBDP_googlemaps_directions_l10n['errors_no_route'] );
                    return;
                }

                var mapOptions = {
                    zoom: 7,
                    center: new google.maps.LatLng( t.from[0], t.from[1] ),
                    mapTypeId: google.maps.MapTypeId.ROADMAP
                };
                var map = new google.maps.Map( t._$display.find( '.route-map' ).get( 0 ), mapOptions );

                directionsDisplay.setMap( map );
                directionsDisplay.setPanel( t._$display.find( '.directions-panel' ).get( 0 ) );
                directionsDisplay.setDirections( res );

                t._routeMap = map;

                t.showThickbox();
            } );
        },

        showThickbox: function() {
            var t = this;

            if ( ! t._working )
                return;

            // Figure out a resasonable size for the TB window (80% of browser window).
            var width = Math.floor( $(window).width() * 0.8 ) - 5;
            var height = Math.floor( $(window).height() * 0.8 ) - 5;

            t._$display.find( '.route-map' ).width( ( width * 0.7 ) + 'px' );
            t._$display.find( '.route-map' ).height( height + 'px' );
            t._$display.find( '.directions-panel' ).css( 'max-height', height + 'px' );

            var listingTitle = t._$form.find( 'input[name="listing_title"]' ).val();
            var title = WPBDP_googlemaps_directions_l10n[ 'titles_' + t.travelMode ].replace( /%s/g, listingTitle );

            $('#wpbdp-map-directions-wrapper').show();
            google.maps.event.trigger( t._routeMap, 'resize');
            tb_show( title, '#TB_inline?width=' + width + '&height=' + height + '&inlineId=wpbdp-map-directions-wrapper' );
            $( '#wpbdp-map-directions-wrapper' ).remove();

            t._working = false;
            t._$form.find( '.find-route-btn' ).removeAttr( 'disabled' )
                                              .val( WPBDP_googlemaps_directions_l10n.submit_normal );
        }
    } );

})(jQuery);

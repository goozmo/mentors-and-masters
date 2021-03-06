(function($) {
    var googlemaps = wpbdp.googlemaps = wpbdp.googlemaps || {};

    googlemaps.PlaceChooser = function( container, settings ) {
        var t = this;
        var TEMPLATE = '<div class="wpbdp-widget-place-chooser">' + 
/*                       '<div class="search-place">' +
                       '<input type="text" />' +
                       '<input type="button" value="Search" />' +
                       '</div>' +*/
                       '<div class="map"></div>' +
                       '<div class="actions">' +
                       '<input type="button" value="Search nearby place" class="search-nearby-toggle" />' +
                       '<input type="button" value="Enter coordinates" class="enter-coordinates-toggle" />' +
                       '<input type="button" value="Done" class="done" />' +
                       '</div>' +
                       '<div class="action-area-wrapper"><input type="button" class="close" value="← Return" /><div class="action-area"></div></div>' +
                       '</div>';

        this.$container = $(container);
        this.$container.html(TEMPLATE);
        this.settings = $.extend({
            initial_value: {},
            context: '',
            debug: false,
            done_after_drag: false,
            show_done_button: true
        }, settings);

        this._listeners = [];

//        this.$search = this.$container.find('.search-place');
        this.$map = this.$container.find('.map');
        this.$actions = this.$container.find('.actions');
        this.$action_area = this.$container.find('.action-area');

        if ( this.settings.show_done_button ) {
            t.$actions.find( '.done' ).click(function(e) {
                e.preventDefault();
                t._notify_listeners();
            });
        } else {
            t.$actions.find( '.done' ).remove();
        }

        t.$container.find('.action-area-wrapper .close').click(function(e) {
            e.preventDefault();
            t._toggle_action_area();
        });

        this.$container.find('.actions .search-nearby-toggle').click(function(e) {
            e.preventDefault();
            t.search_nearby();
        });
        this.$container.find('.actions .enter-coordinates-toggle').click(function(e) {
            e.preventDefault();
            t.enter_coordinates();
        });
    };

    $.extend( googlemaps.PlaceChooser.prototype, {
        init: function() {
            this.init_map();
        },

        get_value: function() {
            var pos = this.marker.getPosition();
            return { lat: pos.lat(), lng: pos.lng() };
        },

        set_value: function(lat, lng) {
            this.debug('set_value()', lat, lng);
            this.marker.setPosition(new google.maps.LatLng( lat, lng ));
            this.google_map.setCenter(this.marker.getPosition());
            this._notify_listeners();
        },

        init_map: function() {
            var t = this;

            // Initialize map with default values.
            var def_value = new google.maps.LatLng( 0.0, 0.0 );
            t.google_map = new google.maps.Map( this.$map.get(0), {
                center: def_value,
                zoom: 5,
                mapTypeId: google.maps.MapTypeId.ROADMAP
            } );
            t.marker = new google.maps.Marker({
                position: def_value,
                map: this.google_map,
                draggable: true
            });

            if ( t.settings.done_after_drag ) {
                google.maps.event.addListener( t.marker, 'dragend', function() {
                    t._notify_listeners();
                } );
            }

            // Try to set initial location based on settings, context and browser geolocation.
            if ( t.settings.initial_value && 'undefined' != typeof( t.settings.initial_value.lat ) && 'undefined' != typeof( t.settings.initial_value.lng ) ) {
                t.debug('Setting initial value based on settings.', t.settings.initial_value);
                t.set_value( t.settings.initial_value.lat, t.settings.initial_value.lng );
            } else if ( t.settings.context ) {
                t.debug('Setting initial value based on context.', t.settings.context);
            } else {
                t.debug('Setting initial value based on geolocation API.');
                t.geolocate();
            }
        },

        geolocate: function() {
            var t = this;

            if ( navigator.geolocation ) {
                navigator.geolocation.getCurrentPosition(function(pos) {
                    t.set_value( pos.coords.latitude, pos.coords.longitude );
                });
            }
        },

        debug: function(v) {
            if ( ! this.settings.debug )
                return;

            for ( var i = 0; i < arguments.length; i++ ) {
                console.log( '[PlaceChooser] ' + arguments[i].toString());
            }
        },

        _notify_listeners: function() {
                var pos = this.marker.getPosition();
                var res = { 'success': true, 'lat': pos.lat(), 'lng': pos.lng() };

                $.each( this._listeners, function(i, f) {
                    f(res);
                } );
        },

        when_done: function(cb) {
            this._listeners.push(cb);
        },

        _toggle_action_area: function() {
            var t = this;

            if ( t.$actions.is( ':visible' ) ) {
                t.$actions.hide();
                t.$container.find('.action-area-wrapper').show();
            } else {
                t.$container.find('.action-area-wrapper').hide();
                t.$actions.show();
                t.$action_area.html('');
            }
        },

        /* {{ Actions. */
        enter_coordinates: function() {
            var t = this;

            var $form = $( '<form class="enter-coordinates">' +
                           '<label>Lat.: <input type="text" name="lat" class="coords-lat" /></label>' +
                           '<label>Long.: <input type="text" name="lng" class="coords-lng" /></label>' +
                           '<input type="submit" value="Set Location" class="locate-point" />' + 
                           '</form>' );
            var $lat = $form.find('input[name="lat"]');
            var $lng = $form.find('input[name="lng"]');

            $form.submit(function(e) {
                e.preventDefault();

                var lat = parseFloat( $.trim( $lat.val() ) );
                var lng = parseFloat( $.trim( $lng.val() ) );

                if ( isNaN( lat ) || isNaN( lng ) )
                    return;

                t.set_value( lat, lng );
            });

            t.$action_area.html($form);
            t._toggle_action_area();

            $lat.focus();
        },

        search_nearby: function() {
            var t = this;

            var $search_form = $('<form class="search-nearby"><label>Address: <input type="text" class="search-term" /></label><input type="submit" value="Search" class="do-search" /></form>');
            $search_form.find('input.search-term').focus();
            $search_form.submit(function(e) {
                e.preventDefault();

                var address = $.trim( $search_form.find('.search-term').val() );

                if ( ! address )
                    return;

                if ( 'undefined' == typeof( t.google_geocoder ) )
                    t.google_geocoder = new google.maps.Geocoder();

                t.debug('Geocoding address: ' + address);
                t.google_geocoder.geocode( { 'address': address }, function( results, status ) {
                    if ( google.maps.GeocoderStatus.OK != status ) {
                        return;
                    }

                    var res = results[0].geometry.location;
                    t.set_value( res.lat(), res.lng() );
//                    t._toggle_action_area();
                } );
            });

            t.$action_area.html($search_form);
            t._toggle_action_area();

            $search_form.find('input.search-term').focus();
        },

        /* }} */
    } );

/*    $(document).ready(function() {
        var chooser = new googlemaps.PlaceChooser( $('.wpbdp-googlemaps-place-chooser-container').get(0) );
        chooser.when_done(function(res) {
            alert('Done. Location = (' + res.lat + ', ' + res.lng + ')' );
        });
    });*/

})(jQuery);

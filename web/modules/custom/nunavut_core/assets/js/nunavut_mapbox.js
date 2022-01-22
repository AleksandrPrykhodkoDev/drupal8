/**
 * @file
 * Nunavut map behaviors.
 */

(function ($, Drupal) {

  'use strict';

  let initialized;

  /**
   * Behavior description.
   */
  Drupal.behaviors.nunavutMapbox = {
    attach: function (context, settings) {
      this.init(context, settings);
    },
    init: function (context, settings) {
      if (!initialized) {
        initialized = true;
        this.bindEvents(context, settings);
      }
    },
    bindEvents: function (context, settings) {
      $(document).ready(function () {
        mapboxgl.accessToken = settings.mapboxGl.access_token;

        let map = new mapboxgl.Map({
          container: settings.mapboxGl.map_settings.container, // container ID
          style: settings.mapboxGl.map_settings.style, // style URL
          center: settings.mapboxGl.map_settings.center, // starting position [lng, lat]
          zoom: settings.mapboxGl.map_settings.zoom// starting zoom
        });

        let el = document.createElement('div');

        el.className = 'marker nunavut-marker';

        let popup = new mapboxgl.Popup({offset: 25}).setText(
          settings.mapboxGl.point.tooltip
        );

        new mapboxgl
          .Marker(el)
          .setLngLat(settings.mapboxGl.map_settings.center)
          .setOffset([0, -36])
          .setPopup(popup)
          .addTo(map);
      });
    }
  };
}(jQuery, Drupal));

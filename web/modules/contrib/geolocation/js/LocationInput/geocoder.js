import { GeolocationLocationInputBase } from "./GeolocationLocationInputBase.js";

/**
 * @typedef {Object} GeocoderLocationInputSettings
 *
 * @extends GeolocationLocationInputSettings
 *
 * @property {Boolean} auto_submit
 * @property {Boolean} hide_form
 * @property {Object} geocoder_settings
 * @property {String} geocoder_settings.import_path
 * @property {GeolocationGeocoderSettings} geocoder_settings.settings
 */

/**
 * @property {GeocoderLocationInputSettings} settings
 */
export default class Geocoder extends GeolocationLocationInputBase {
  constructor(form, settings = {}) {
    super(form, settings);

    import(this.settings.geocoder_settings.import_path)
      /** @param {GeolocationGeocoder} geocoder */
      .then((geocoder) => {
        this.geocoder = new geocoder.default(this.settings.geocoder_settings);

        if (!this.geocoder) {
          console.error(this.geocoder, "Could not instantiate Geocoder. No Geocoding feature support.");
        }

        this.geocoder.addResultCallback((result) => {
          if (result.coordinates) {
            this.setCoordinates(result.coordinates);
          }

          if (this.settings.auto_submit) {
            this.submit();
          }
        });

        let geocoderInput = this.form.querySelector(".geolocation-geocoder-address");

        if (!geocoderInput) {
          console.error("No Geocoder input found");
          return false;
        }

        this.geocoder.attachToElement(geocoderInput);
      });
  }
}

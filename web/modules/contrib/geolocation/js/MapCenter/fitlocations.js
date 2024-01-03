import { GeolocationMapCenterBase } from "./GeolocationMapCenterBase.js";

/**
 * @property {boolean} settings.reset_zoom
 */
export default class FitLocations extends GeolocationMapCenterBase {
  setCenter() {
    super.setCenter();

    if (this.map.dataLayers.get('default').markers.length === 0) {
      return false;
    }

    this.map.fitMapToMarkers();

    if (this.settings.min_zoom) {
      this.map.getZoom().then((zoom) => {
        if (this.settings.min_zoom < zoom) {
          this.map.setZoom(this.settings.min_zoom);
        }
      });
    }

    return true;
  }
}

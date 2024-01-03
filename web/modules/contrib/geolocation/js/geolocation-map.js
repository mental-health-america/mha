/**
 * @file
 * Javascript for the Geolocation map formatter.
 */

(function (Drupal) {
  "use strict";

  Drupal.geolocation.maps = {
    /**
     * @type {Map<string, GeolocationMapBase>}
     */
    initializedMaps: new Map(),
    mapPromises: new Map(),
    delayedInitialization: new Map(),
    mapProviderSettings: new Map(),
    callback() {},
    addInitializedMap(map) {
      this.initializedMaps.set(map.id, map);
      if (this.mapPromises.has(map.id)) {
        for (const resolve of this.mapPromises.get(map.id)) {
          resolve(map);
        }
        this.mapPromises.delete(map.id);
      }
    },
    getMap(mapId) {
      return new Promise((resolve) => {
        if (this.initializedMaps.has(mapId)) {
          resolve(this.initializedMaps.get(mapId));
        } else {
          let promises = this.mapPromises.get(mapId) ?? [];
          promises.push(resolve);
          this.mapPromises.set(mapId, promises);
        }
      });
    },
    addMapProviderCallback(mapProviderId, callback) {
      let mapProviderSettings;
      if (this.mapProviderSettings.has(mapProviderId)) {
        mapProviderSettings = this.mapProviderSettings.get(mapProviderId);
      } else {
        mapProviderSettings = {
          ready: false,
          callbacks: [],
        };
      }

      if (mapProviderSettings.ready) {
        callback();
      } else {
        mapProviderSettings.callbacks.push(callback);
        this.mapProviderSettings.set(mapProviderId, mapProviderSettings);
      }
    },
    mapProviderCallback(mapProviderId) {
      if (!this.mapProviderSettings.has(mapProviderId)) {
        return false;
      }
      let mapProviderSettings = this.mapProviderSettings.get(mapProviderId);
      mapProviderSettings.ready = true;

      while (mapProviderSettings.callbacks.length) {
        mapProviderSettings.callbacks.shift()();
      }
      this.mapProviderSettings.set(mapProviderId, mapProviderSettings);
    },
    delayInitialization(mapId, callback) {
      let callbacks = this.delayedInitialization.get(mapId) ?? [];
      callbacks.push(callback);
      this.delayedInitialization.set(mapId, callbacks);
    },
    initializeDelayed(mapId = null) {
      for (const callback of this.delayedInitialization.get(mapId) ?? []) {
        callback();
      }
      this.delayedInitialization.delete(mapId);
    },
  };

  Drupal.AjaxCommands.prototype.geolocation = function (ajax, response) {
    if (response.method !== "replaceCommonMapView") {
      return;
    }

    let oldContent = document.querySelector(response.selector);
    if (!oldContent) {
      return;
    }

    let template = document.createElement("template");
    template.innerHTML = response.data.trim();
    let newContent = template.content.firstElementChild;

    Drupal.detachBehaviors(oldContent, response.settings);
    oldContent.insertAdjacentElement("afterend", newContent);

    new Promise((resolve) => {
      let mapPromises = [];
      oldContent.querySelectorAll(".geolocation-map-wrapper").forEach((oldMapWrapper) => {
        let mapId = oldMapWrapper.getAttribute("id");
        if (!mapId) {
          return;
        }

        if (!Drupal.geolocation.maps.initializedMaps.has(mapId)) {
          return;
        }

        let oldMapContainer = oldMapWrapper.querySelector(".geolocation-map-container");
        if (!oldMapContainer) {
          return;
        }

        let newMapWrapper = newContent.querySelector(".geolocation-map-wrapper#" + mapId);
        if (!newMapWrapper) {
          Drupal.geolocation.maps.initializedMaps.delete(mapId);
          return;
        }

        let newMapContainer = newMapWrapper.querySelector(".geolocation-map-container");
        if (!newMapContainer) {
          return;
        }

        mapPromises.push(
          new Promise((resolve) => {
            let map = Drupal.geolocation.maps.initializedMaps.get(mapId);
            map.wrapper = newMapWrapper;
            let newContainerParent = newMapContainer.parentNode;
            newMapContainer.remove();
            newContainerParent.appendChild(oldMapContainer);
            resolve();
          })
        );
      });

      resolve(Promise.all(mapPromises));
    }).then(() => {
      oldContent.remove();
      Drupal.attachBehaviors(newContent, response.settings);
    });
  };

  /**
   * Find and display all maps.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches Geolocation Maps formatter functionality to relevant elements.
   */
  Drupal.behaviors.geolocationMap = {
    /**
     * @param context
     * @param drupalSettings
     * @param {Object} drupalSettings.geolocation
     * @param {GeolocationMapSettings[]} drupalSettings.geolocation.maps
     */
    attach: function (context, drupalSettings) {
      context.querySelectorAll(".geolocation-map-wrapper").forEach(function (mapWrapper) {
        if (typeof drupalSettings.geolocation === "undefined") {
          throw "Bailing out for lack of settings.";
          return;
        }

        if (mapWrapper.classList.contains("geolocation-map-processed")) {
          return;
        }
        mapWrapper.classList.add("geolocation-map-processed");

        if (mapWrapper.length === 0) {
          return;
        }

        let mapSettings = {};
        mapSettings.id = mapWrapper.getAttribute("id");
        mapSettings.wrapper = mapWrapper;

        mapSettings.lat = 0;
        mapSettings.lng = 0;

        for (const mapId in drupalSettings.geolocation.maps) {
          if (mapId === mapSettings.id) {
            Object.assign(mapSettings, drupalSettings.geolocation.maps[mapId]);
          }
        }

        if (mapWrapper.getAttribute("data-lat") && mapWrapper.getAttribute("data-lng")) {
          mapSettings.lat = Number(mapWrapper.getAttribute("data-lat"));
          mapSettings.lng = Number(mapWrapper.getAttribute("data-lng"));
        }

        if (mapWrapper.getAttribute("map-type")) {
          mapSettings.type = mapWrapper.getAttribute("map-type");
        }

        new Promise((resolve, reject) => {
          let map = Drupal.geolocation.maps.initializedMaps.get(mapSettings.id);
          if (map) {
            resolve(map);
          } else {
            reject();
          }
        })
          .catch(() => {
            return new Promise((resolve) => {
              switch (mapSettings.conditional_initialization ?? "no") {
                case "programmatically":
                  Drupal.geolocation.maps.delay(mapSettings.id, () => {
                    resolve();
                  });
                  break;

                case "button": {
                  Drupal.geolocation.maps.delay(mapSettings.id, () => {
                    resolve();
                  });

                  let mapContainer = mapWrapper.querySelector(".geolocation-map-container");
                  if (!mapContainer) {
                    return;
                  }
                  let conditionalInitContainer = document.createElement("div");
                  conditionalInitContainer.classList.add("geolocation-map-conditional");

                  let conditionalInitDescription = document.createElement("div");
                  conditionalInitDescription.textContent = mapSettings.conditional_description ?? Drupal.t("Clicking this button will embed a map.");
                  conditionalInitContainer.appendChild(conditionalInitDescription);

                  let button = document.createElement("button");
                  button.innerHTML = mapSettings.conditional_label ?? Drupal.t("Show map");
                  button.classList.add("button");
                  button.onclick = (event) => {
                    event.preventDefault();
                    Drupal.geolocation.maps.initializeDelayed(mapSettings.id);
                    conditionalInitContainer.remove();
                  };
                  conditionalInitContainer.appendChild(button);

                  mapContainer.appendChild(conditionalInitContainer);
                  break;
                }

                default:
                  resolve();
              }
            })
              .then(() => {
                return import(mapSettings.import_path);
              })
              .then((mapProviderImport) => {
                /** @type {GeolocationMapBase} */
                let map = new mapProviderImport.default(mapSettings);
                return map.initialize();
              })
              .then((map) => {
                Drupal.geolocation.maps.initializedMaps.set(map.id, map);
                return map.loadCenterOptions();
              })
              .then((map) => {
                return map.loadFeatures();
              })
              .then((map) => {
                map.removeControls();
                map.wrapper.querySelectorAll(".geolocation-map-controls > *").forEach((control) => {
                  map.addControl(control);
                });
                return map;
              });
          })
          .then((map) => {
            map.removeMapMarkers();
            return map.loadDataLayers();
          })
          .then((map) => {
            if (!map.dataLayers.has('default')) {
              throw "No default layer defined. Breaking";
            }
            return map;
          })
          .then((map) => {
            // Only _after_ markers are loaded.
            map.setCenterByOptions();

            // Just in case.
            map.wrapper.classList.remove("ajax-loading");

            map.wrapper.querySelectorAll(".geolocation-location").forEach((element) => {
              element.style.display = "none";
            });

            map.readyFeatures();

            return map;
          })
          .then((map) => {
            Drupal.geolocation.maps.addInitializedMap(map);
          })
          .catch((e) => {
            console.error(e, "Something failed during map init.");
            return false;
          });
      });
    },
    detach: function (context, drupalSettings) {},
  };

})(Drupal);

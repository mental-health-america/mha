/**
 * @file
 * Contains the Siteimprove Plugin methods.
 */

 (function (Drupal, $, once, drupalSettings, cookies) {
  'use strict';

  // Add forEach function to NodeList if it doesn't exist.
  if (typeof NodeList !== 'undefined' && NodeList.prototype && !NodeList.prototype.forEach) {
    NodeList.prototype.forEach = Array.prototype.forEach;
  }

   function convertRelativeToAbsolute(baseUrl, relativeUrl) {
     // Create an anchor element to parse URLs
     const anchor = document.createElement('a')
     anchor.href = relativeUrl

     // If the URL is already absolute, return it
     if (anchor.protocol && anchor.hostname) {
       return relativeUrl
     }

     // Otherwise, create the absolute URL
     return new URL(relativeUrl, baseUrl).href
   }

   // Function to update all image src attributes to absolute URLs
   function updateImageUrlsToAbsolute(dom = document, win = window) {
     // Get all image elements
     const images = dom.querySelectorAll('img')

     // Iterate through each image element
     images.forEach(function (img) {
       // Convert the src to an absolute URL
       img.src = convertRelativeToAbsolute(win.location.href, img.src)
     })
   }

   const getDom = async function (url, nonce) {
     const iframeContainer = document.createElement('div')
     iframeContainer.setAttribute('id', 'div_iframe')
     document.body.appendChild(iframeContainer)
     const separator = url.includes('?') ? '&' : '?'
     iframeContainer.innerHTML = `<iframe id='domIframe' src=${url}${separator}si_preview_nonce=${nonce} style='height:100vh; width:100%'></iframe>`
     const iframe = document.getElementById('domIframe')
     const promise = new Promise(function (resolve, reject) {
       iframe.addEventListener(
         'load',
         () => {
           iframe.contentWindow.document.querySelector(
             '#toolbar-administration'
           ).innerHTML = ''
           iframe.contentWindow.document
             .querySelectorAll('iframe')
             .forEach(function (item) {
               item.innerHTML = ''
               item.src = ''
             })
           iframe.contentWindow.document
             .querySelectorAll('.contextual')
             .forEach(function (item) {
               item.innerHTML = ''
             })
           iframe.contentWindow.document
             .querySelectorAll('.contextual-links')
             .forEach(function (item) {
               item.innerHTML = ''
             })
           iframe.contentWindow.document
             .querySelectorAll('.node-preview-container')
             .forEach(function (item) {
               item.innerHTML = ''
             })
           iframe.contentWindow.document
             .querySelectorAll('.si-toggle-container')
             .forEach(function (item) {
               item.innerHTML = ''
             })
           iframe.contentWindow.document
             .querySelectorAll('.si-iframe-container')
             .forEach(function (item) {
               item.innerHTML = ''
             })

           updateImageUrlsToAbsolute(
             iframe.contentWindow.document,
             iframe.contentWindow
           )
           const cleanDom = iframe.contentWindow.document
           document.body.removeChild(iframeContainer)
           resolve(cleanDom)
         },
         {once: true}
       )
     })

     const documentReturned = await promise
     $('.si-overlay').remove()
     return documentReturned
   }

  var siteimprove = {
    input: function () {
      this.url = drupalSettings.siteimprove.input.url;
      this.method = 'input';
      this.common();
    },

    domain: function () {
      this.url = drupalSettings.siteimprove.domain.url;
      this.method = 'domain';
      this.common();
    },
    clear: function () {
      this.method = 'clear';
      var _si = window._si || [];
      _si.push([this.method, function() { }, drupalSettings.siteimprove.token]);
    },
    recheck: function () {
      this.url = drupalSettings.siteimprove.recheck.url;
      this.method = 'recheck';
      this.common();
    },
    recrawl: function () {
      this.url = drupalSettings.siteimprove.recrawl.url;
      this.method = 'recrawl';
      this.common();
    },
    contentcheck: function () {
      this.urls = drupalSettings.siteimprove.input && drupalSettings.siteimprove.input.url ? drupalSettings.siteimprove.input.url : drupalSettings.siteimprove.domain.url;
      if (!Array.isArray(this.urls)) {
        this.urls = [this.urls];
      }
      this.method = 'contentcheck-flat-dom';
      var _si = window._si || [];
      var self = this;
      this.urls.forEach(function (url) {
        let document_ = document.cloneNode(true);
        _si.push([self.method, self.cleanHtml(document_), url, drupalSettings.siteimprove.token, function () { console.log('Pre-publish check ordered (flat dom version)'); }]);
      });
    },
    registerPrepublishCallback: function () {
      this.urls = drupalSettings.siteimprove.input && drupalSettings.siteimprove.input.url ? drupalSettings.siteimprove.input.url : drupalSettings.siteimprove.domain.url;
      if (!Array.isArray(this.urls)) {
        this.urls = [this.urls];
      }
      this.method = 'registerPrepublishCallback';
      var _si = window._si || [];
      var self = this;
      this.urls.forEach(function (url) {
        const getDomCallback = function () {
          return getDom(url);
        }
        _si.push([self.method, getDomCallback]);
      });
    },
    onhighlight: function () {
      // Creating "highlight" event for other modules / themes to react on.
      let event;
      event = new CustomEvent('siteimproveContentcheckHighlight');

      this.method = 'onHighlight';
      var _si = window._si || [];
      _si.push([this.method, function (highlightInfo) {
        _si.push(['applyDefaultHighlighting', highlightInfo, document, window]);

        // Dispatch highlight event.
        event.highlightSelector = highlightSelector;
        document.dispatchEvent(event);
      }]);
    },
    common: function () {
      this.urls = this.url;
      if (!Array.isArray(this.urls)) {
        this.urls = [this.urls];
      }
      var _si = window._si || [];
      var self = this;
      this.urls.forEach(function (url) {
        _si.push([self.method, url, drupalSettings.siteimprove.token]);
        self.onhighlight();
        self.registerPrepublishCallback();
      });
    },
    // Clean html for contextual links, toolbar, etc.
    cleanHtml: function (origHtml) {
      if (origHtml.querySelector('#toolbar-administration')) {
        origHtml.querySelector('#toolbar-administration').innerHTML = '';
      }
      origHtml.querySelectorAll('iframe').forEach(function (item) { item.innerHTML = ''; item.src = ''; });
      origHtml.querySelectorAll('.contextual').forEach(function (item) { item.innerHTML = ''; });
      origHtml.querySelectorAll('.contextual-links').forEach(function (item) { item.innerHTML = ''; });
      origHtml.querySelectorAll('.node-preview-container').forEach(function (item) { item.innerHTML = ''; });

      return origHtml;
    },
    events: {
      recheck: function () {
        $('.siteimprove-recheck-button').click(function () {
          siteimprove.recheck();
          return false;
        });
      },
      contentcheck: function () {
        $('.siteimprove-contentcheck-button').click(function () {
          siteimprove.contentcheck();
          siteimprove.onhighlight();
          return false;
        });
      }
    }
  };

  Drupal.behaviors.siteimprove = {
    attach: function (context, settings) {
      $(once('siteimprove', 'body', context)).each(function () {

        // If exist recheck, call recheck Siteimprove method.
        if (typeof settings.siteimprove.recheck !== 'undefined') {
          if (settings.siteimprove.recheck.auto) {
            siteimprove.recheck();
          }
          siteimprove.events.recheck();
        }

        // If contentcheck exists, call contentcheck Siteimprove method.
        if (typeof settings.siteimprove.contentcheck !== 'undefined') {
          setTimeout(function () {
            if (settings.siteimprove.contentcheck.auto) {
              siteimprove.contentcheck();
              siteimprove.onhighlight();
            }
            siteimprove.events.contentcheck();
          }, 200);
        }

        let $buttons = $('.contextual-region .siteimprove-contentcheck-button, .contextual-region .siteimprove-recheck-button');
        $buttons.removeClass('is-active');
        $buttons.parent().removeClass('is-active');

        $('.siteimprove-contentcheck-button').click(function (event) {
          event.preventDefault();
          siteimprove.contentcheck();
          siteimprove.onhighlight();
        });

        // If exist onhighlight, call onhighlight Siteimprove method.
        if (typeof settings.siteimprove.onhighlight !== 'undefined') {
          if (settings.siteimprove.onhighlight.auto) {
            siteimprove.onhighlight();
          }
        }

        // If exist input, call input Siteimprove method.
        if (typeof settings.siteimprove.input !== 'undefined') {
          if (settings.siteimprove.input.auto) {
            siteimprove.input();
          }
        }

        // If exist domain, call domain Siteimprove method.
        if (typeof settings.siteimprove.domain !== 'undefined') {
          if (settings.siteimprove.domain.auto) {
            siteimprove.domain();
          }
        }

        // If exist clear, call clear Siteimprove method.
        if (typeof settings.siteimprove.clear !== 'undefined') {
          if (settings.siteimprove.clear.auto) {
            siteimprove.clear();
          }
        }

        // If exist recrawl, call input Siteimprove method.
        if (typeof settings.siteimprove.recrawl !== 'undefined') {
          if (settings.siteimprove.recrawl.auto) {
            siteimprove.recrawl();
          }
        }
      });

      // We check if the cookie is already set to collapse or open.
      // If it's not set, we check if the setting is set to collapsed or open
      // and set the cookie accordingly.
      var OVERLAY_COLLAPSED = '6';
      var OVERLAY_OPEN = '48';
      var sicmsplugin = cookies.get('sicmsplugin');

      if (!sicmsplugin) {
        let siOverlaySettings = null;
        let secure = false;

        if (drupalSettings && drupalSettings.siteimprove) {
          if ('overlay_default_collapse' in drupalSettings.siteimprove) {
            siOverlaySettings = drupalSettings.siteimprove.overlay_default_collapse;
          }
          if ('overlay_cookie_secure' in drupalSettings.siteimprove) {
            secure = drupalSettings.siteimprove.overlay_cookie_secure;
          }
        }

        // let siOverlaySettings = drupalSettings?.siteimprove?.overlay_default_collapse ?? null;
        // let secure = drupalSettings?.siteimprove?.overlay_cookie_secure ?? false;
        if (siOverlaySettings) {
          cookies.set('sicmsplugin', OVERLAY_COLLAPSED, {
            domain: document.domain,
            path: '/',
            secure: secure
          });
        } else {
          cookies.set('sicmsplugin', OVERLAY_OPEN, {
            domain: document.domain,
            path: '/',
            secure: secure
          });
        }
      }
    }
  };

})(Drupal, jQuery, once, drupalSettings, window.Cookies);

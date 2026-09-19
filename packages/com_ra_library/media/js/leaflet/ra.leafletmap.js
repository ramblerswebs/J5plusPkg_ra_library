var L, ra, OsGridRef, Element;
if (typeof (ra) === "undefined") {
    ra = {};
}
ra.leafletmap = function (tag, options) {
    this.tag = tag;
    this.options = options;
    var tabOptions = {
        style: {side: 'right', // left or right
            vert: 'below',
            size: 'small'}, // normal or small
        tabs: {map: {title: 'Map', staticContainer: true},
            help: {title: 'Help', staticContainer: true},
            settings: {title: 'Settings', staticContainer: true}}
    };
    var tags = [
        {name: 'preMapDiv', parent: 'root', tag: 'div', attribs: {class: 'preMapDiv'}},
    ];
    var maptags = [
        {name: 'map', parent: 'root', tag: 'div', style: {height: options.mapHeight, width: options.mapWidth}},
        {name: 'copyright', parent: 'root', tag: 'div'}
    ];
    this.elements = ra.html.generateTags(tag, tags);

    this.tabs = new ra.tabs(tag, tabOptions);
    this.tabs.display();
    this._mapTab = this.tabs.getStaticContainer('map');
    this._mapTab.classList.add('ra-map-container');
    this.elements2 = ra.html.generateTags(this._mapTab, maptags);
    this.lmap = new ra._leafletmap(this.elements2.map, this.elements2.copyright, this.options);

    this.display = function () {
        var first = {help: true,
            settings: true
        };
        var _this = this;

        this.tag.addEventListener("displayTabContents", function (e) {
            if (e.tabDisplay.tab === 'map') {
                _this.lmap.map.invalidateSize();
            }
            if (e.tabDisplay.tab === 'help') {
                if (first.help) {
                    first.help = false;
                    _this._displayHelp(_this.tabs.getStaticContainer('help'));
                }
            }
            if (e.tabDisplay.tab === 'settings') {
                if (first.settings) {
                    first.settings = false;
                    _this._displaySettings(_this.tabs.getStaticContainer('settings'));
                }
            }
        });
    };
    this._displayHelp = function (tag) {
        var iframe = document.createElement('iframe');
        var page = this.options.helpPage;
        page = ra.map.helpBase + page;
        iframe.setAttribute('src', page);
        iframe.innerHTML = "Loading";
        tag.appendChild(iframe);
    };
    this._displaySettings = function (tag) {
        var setting = new ra.map.Settings();
        setting.add(tag, this);
    };
    this.map = function () {
        return this.lmap.map;
    };
    this.SetPlotUserControl = function (value) {
        this.lmap.controls.plotroute = value;
    };
    this.plotControl = function () {
        return this.lmap.controls.plotroute;
    };
    this.layersControl = function () {
        return this.lmap.controls.layers;
    };
    this.zoomControl = function () {
        return this.lmap.controls.zoom;
    };
    this.mylocationControl = function () {
        return this.lmap.controls.mylocation;
    };
    this.elevationControl = function () {
        return this.lmap.controls.elevation;
    };
    this.fullscreenControl = function () {
        return this.lmap.controls.fullscreen;
    };
    this.printControl = function () {
        return this.lmap.controls.print;
    };
    this.mouseControl = function () {
        return this.lmap.controls.mouse;
    };
    this.scaleControl = function () {
        return this.lmap.controls.scale;
    };
    this.rightclickControl = function () {
        return this.lmap.controls.rightclick;
    };
    this.osInfoControl = function () {
        return this.lmap.controls.osinfo;
    };
    this.errorDivControl = function () {
        return this.lmap.controls.errorDiv;
    };
    this.preMapDiv = function () {
        return this.elements.preMapDiv;
    };

};



ra._leafletmap = function (tag, copyrightTag, options) {

    ra.logger.toServer(["createMap", window.location.href]);
    this.options = options;
    this.controls = {layers: null,
        settins: null,
        zoom: null,
        zoomAll: null,
        elevation: null,
        errorDiv: null,
        print: null,
        mylocation: null,
        scale: null,
        mouse: null,
        rightclick: null,
        search: null,
        plotroute: null,
        osinfo: null};
    this._mapDiv = tag;
    this.baseLayerChanging = false;
    var _this = this;

    var self = this;
    var mapOptions = {
        center: new L.LatLng(54.221592, -3.355007),
        zoom: 5,
        maxzoom: 18,
        zoomSnap: 0.1,
        zoomControl: false
    };
    var ukBounds = L.latLngBounds(L.latLng(49.5, -10.7), L.latLng(61.33, 1.91));
    this.minHeight = this._mapDiv.clientHeight; // save initial map height
    if (options.copyright) {
        var tagcopy = [{parent: 'root', tag: 'p', attrs: {class: 'mapcopyright'}, textContent: '© openrouteservice.org by HeiGIT | Map data © OpenStreetMap contributors'},
            {parent: 'root', tag: 'p', attrs: {class: 'mapcopyright'}, textContent: 'OS data © Crown copyright and database 2021;   Royal Mail data © Royal Mail copyright and Database 2021'},
            {parent: 'root', tag: 'p', attrs: {class: 'mapcopyright'}, textContent: 'Maps Icons Collection https://mapicons.mapsmarker.com'}
        ];
        ra.html.generateTags(copyrightTag, tagcopy);
    }
    this.map = new L.Map(this._mapDiv, mapOptions);
    this.mapLayers = new Object();
    this.overlayLayers = new Object(); // optional layers drawn over the basemap (checkbox, not radio, in the layers control)
    // map types
    this.mapLayers["Open Street Map"] = new L.TileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        referrerPolicy: 'strict-origin-when-cross-origin',
        attribution: "Map data &copy; <a href=\"https://openstreetmap.org\" target=\"_blank\">OpenStreetMap</a>"
    }).addTo(this.map);
    this.mapLayers["Open Topo Map"] = new L.TileLayer("https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png", {
        maxNativeZoom: 15,
        referrerPolicy: 'strict-origin-when-cross-origin',
        attribution: 'Kartendaten: &copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap</a>-Mitwirkende, <a href="http://viewfinderpanoramas.org">SRTM</a> | Kartendarstellung: &copy; <a href="https://opentopomap.org" target=\"_blank\">OpenTopoMap</a> (<a href="https://creativecommons.org/licenses/by-sa/3.0/">CC-BY-SA</a>)'
    });
    if (options.licenseKeys.ESRIkey !== null) {
        this.mapLayers["Aerial View, Esri"] = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community'
        });
    }
    const   crs27700 = new L.Proj.CRS('EPSG:27700', '+proj=tmerc +lat_0=49 +lon_0=-2 +k=0.9996012717 +x_0=400000 +y_0=-100000 +ellps=airy +towgs84=446.448,-125.157,542.06,0.15,0.247,0.842,-20.489 +units=m +no_defs', {
// resolutions: [ 896.0, 448.0, 224.0, 112.0, 56.0, 28.0, 14.0, 7.0, 3.5, 1.75 ],
        resolutions: [114688.0, 57344.0, 28672.0, 14336.0, 7168.0, 3584.0, 1792.0, 896.0, 448.0, 224.0, 112.0, 56.0, 28.0, 14.0, 7.0, 3.5, 1.75],
        origin: [-238375.0, 1376256.0]
    });
    if (options.licenseKeys.OSkey !== null) {
        this.mapLayers["OS Explorer/Landranger"] = L.tileLayer('https://api.os.uk/maps/raster/v1/zxy/Leisure_27700/{z}/{x}/{y}.png?key=' + options.licenseKeys.OSkey, {
            attribution: 'Map &copy; Ordnance Survey',
            zoomOffset: -7,
            minZoom: 6.5,
            maxZoom: 16,
            crs: crs27700,
            bounds: ukBounds,
            ukBounds: ukBounds
        });
    }


    if (options.licenseKeys.OSkey !== null && L.maplibreGL) {
// Load and display vector tile layer on the map.
        this.mapLayers["Ordnance Survey"] = L.maplibreGL({
            style: ra.baseDirectory() + 'media/com_ra_library/js/leaflet/mapStyles/osRamblersStyle.json',
            attribution: 'Map &copy; Ordnance Survey',
            maxZoom: 18,
            ukBounds: ukBounds,
            //  bounds: ukBounds, // causes code to fail!
            transformRequest: (url, resourceType) => {
                if (resourceType !== 'Style' && url.startsWith('https://api.os.uk')) {
                    url = new URL(url);
                    if (!url.searchParams.has('key'))
                        url.searchParams.append('key', options.licenseKeys.OSkey);
                    if (!url.searchParams.has('srs'))
                        url.searchParams.append('srs', 3857);
                    return {
                        url: new Request(url).url
                    };
                }
            }
        });
    }

    if (options.licenseKeys.OSTestkey !== null && L.maplibreGL) {
        const customStyleJson = 'https://raw.githubusercontent.com/OrdnanceSurvey/OS-Vector-Tile-API-Stylesheets/master/OS_VTS_3857_Outdoor.json';
        this.mapLayers["Ordnance Survey Outdoor Test"] = L.maplibreGL({
            style: customStyleJson,
            attribution: 'Map &copy; Ordnance Survey',
            maxZoom: 18,
            ukBounds: ukBounds,
            transformRequest: (url, resourceType) => {
                if (resourceType !== 'Style' && url.startsWith('https://api.os.uk')) {
                    url = new URL(url);
                    if (!url.searchParams.has('key'))
                        url.searchParams.append('key', options.licenseKeys.OSTestkey);
                    if (!url.searchParams.has('srs'))
                        url.searchParams.append('srs', 3857);
                    return {
                        url: new Request(url).url
                    };
                }
            }
        });
    }

    if (options.licenseKeys.OSTestkey !== null && L.maplibreGL) {
// Load and display vector tile layer on the map.
        this.mapLayers["Ordnance Survey Ramblers Test"] = L.maplibreGL({
            style: ra.baseDirectory() + 'media/com_ra_library/js/leaflet/mapStyles/osTestStyle.json',
            attribution: 'Map &copy; Ordnance Survey',
            maxZoom: 18,
            ukBounds: ukBounds,
            transformRequest: (url, resourceType) => {
                if (resourceType !== 'Style' && url.startsWith('https://api.os.uk')) {
                    url = new URL(url);
                    if (!url.searchParams.has('key'))
                        url.searchParams.append('key', options.licenseKeys.OSTestStyle);
                    if (!url.searchParams.has('srs'))
                        url.searchParams.append('srs', 3857);
                    return {
                        url: new Request(url).url
                    };
                }
            }
        });
    }

    if (options.licenseKeys.OSMVectorStyle !== null && L.maplibreGL) {
        this.mapLayers["OSM Vector - Test"] = L.maplibreGL({
            style: ra.baseDirectory() + 'media/com_ra_library/js/leaflet/mapStyles/osmliberty.json',
            maxZoom: 18,
            ukBounds: ukBounds
        });
    }

    // Footpath / PRoW network overlay (OS NGD API - Features: trn-rami-highwaydedication-1).
    // Requires footpath-network-layer.js (defines L.RA.FootpathNetworkLayer) to be loaded
    // before this file, and the OSkey's OS Data Hub project to have the
    // "OS NGD API - Features" product enabled (separate from OS Maps/Vector Tile API).
    // This is an OVERLAY, not a base layer, so it goes into overlayLayers, not mapLayers -
    // otherwise selecting it in the layers control would replace the basemap instead of
    // drawing on top of it.
    if (options.licenseKeys.OSMNGD !== null && L.RA && L.RA.FootpathNetworkLayer) {
        this.overlayLayers["Footpath Network (OS NGD)"] = new L.RA.FootpathNetworkLayer({
            apiKey: options.licenseKeys.OSMNGD,
            minZoom: 13,
            onlyPublicRightOfWay: true,
            attribution: 'Highway dedication (indicative PRoW) &copy; Ordnance Survey'
        });
    }

// get my location for directions
    this.map.locate();
    this.map.on('locationerror', function () {
        ra.loc.setPositionError();
    });
    this.map.on('locationfound', function (e) {
        ra.loc.setPosition(e);
    });
// top right control for error messages
    this.controls.errorDiv = L.control.racontainer({position: 'topright'}).addTo(this.map);
// top left controls
    if (options.displayElevation) {
        this.controls.elevation = L.control.elevation({
            position: "topleft",
            theme: "steelblue-theme", //default: lime-theme
            width: 600,
            height: 125,
            margins: {
                top: 10,
                right: 20,
                bottom: 30,
                left: 50
            },
            useHeightIndicator: true, //if false a marker is drawn at map position
            interpolation: "linear", //see https://github.com/mbostock/d3/wiki/SVG-Shapes#wiki-area_interpolate
            hoverNumber: {
                decimalsX: 1, //decimals on distance (always in km)
                decimalsY: 0, //deciamls on hehttps://www.npmjs.com/package/leaflet.coordinatesight (always in m)
                formatter: undefined //custom formatter function may be injected
            },
            xTicks: undefined, //number of ticks in x axis, calculated by default according to width
            yTicks: undefined, //number of ticks on y axis, calculated by default according to height
            collapsed: true, //collapsed mode, show chart on click or mouseover
            imperial: false    //display imperial units instead of metric
        });
        this.controls.elevation.addTo(this.map);
    }
    this.controls.zoom = L.control.zoom({position: 'topleft'}).addTo(this.map);
    this.controls.fullscreen = L.control.fullscreen({position: 'topleft'}).addTo(this.map);
    this.controls.print = L.control.browserPrint({
        title: 'Print',
        documentTitle: 'The Ramblers - working for walkers',
        printModes: [L.control.browserPrint.mode.portrait("Protrait", "A4"),
            L.control.browserPrint.mode.landscape("Landscape", "A4"),
            L.control.browserPrint.mode.custom("Select Area", "A4")],
        contentSelector: "[leaflet-browser-print-content]",
        pagesSelector: "[leaflet-browser-print-pages]",
        manualMode: false,
        closePopupsOnPrint: false
    }).addTo(this.map);
    this.controls.search = L.control.search({position: 'topleft'}).addTo(this.map);
    this.controls.mylocation = L.control.mylocation().addTo(this.map);
    this.controls.zoomAll = L.control.zoomall().addTo(this.map);
    this.controls.osinfo = L.control.osinfo().addTo(this.map);
    this.controls.rightclick = L.control.rightclick().addTo(this.map);
    var tools = L.control.tools().addTo(this.map);
    tools.moveMapControl(this.controls.zoom);
    tools.moveMapControl(this.controls.zoomAll);
    tools.moveMapControl(this.controls.print);
    tools.moveMapControl(this.controls.mylocation);
    tools.moveMapControl(this.controls.search);
    tools.moveMapControl(this.controls.fullscreen);
    tools.moveMapControl(this.controls.rightclick);
    tools.moveMapControl(this.controls.osinfo);
    if (this.options.resizer) {
        this.controls.resizer = L.control.resizer({onlyOnHover: false, direction: 's'}).addTo(this.map);
        this.controls.resizer.addEventListener('drag', function () {
            var newHeight = self._mapDiv.clientHeight;
            if (newHeight < self.minHeight) {
                self._mapDiv.style.height = self.minHeight + "px";
                //  only allow map to be larger
            }
        });
    }
// bottom left controls
    if (options.mouseposition !== null && ra.hasMouse()) {
        this.controls.mouse = L.control.mouse().addTo(this.map);
        this.controls.mouse.addSearch(this.controls.search);
    }

    this.controls.scale = L.control.scale().addTo(this.map);
    this.map.on('baselayerchange', function (e) {
        var options = e.layer.options;
        var map = _this.map;
        var bounds = _this.map.getBounds();
        if (options.ukBounds) {
            if (!bounds.intersects(ukBounds)) {
                bounds = options.ukBounds;
            }
        }
        if (options.crs) {
            map.options.crs = options.crs;
        } else {
            delete _this.map.options.crs;
        }
        if (options.maxZoom) {
            map.fitBounds(bounds, {maxZoom: options.maxZoom});
        } else {
            map.fitBounds(bounds);
        }
    });
// bottom right controls
    if (options.controlcontainer) {
        // used by walks editor
        L.control.racontainer().addTo(this.map);
    }

    if (options.initialview) {
        var pt = L.latLng(options.initialview.latitude, options.initialview.longitude);
        this.map.setView(pt, options.initialview.zoom);
    }

// top right controls
    this.controls.layers = L.control.layers(this.mapLayers, this.overlayLayers).addTo(this.map);
    if (options.topoMapDefault) {
        this.map.addLayer(this.mapLayers["Open Topo Map"]);
    } else {
        this.map.addLayer(this.mapLayers["Open Street Map"]);
        //    this.map.addLayer(this.mapLayers["OS Explorer/Landranger"]);
    }

    var _this = this;
    if (options.rightclick !== null) {
        this.controls.rightclick.mapControl(this.controls.layers);
    }

    this.map.on('browser-print-end', function (e) {
        tag.scrollIntoView();
    });
    this.map.on('browser-print-end', function (e) {
        tag.scrollIntoView();
    });
};
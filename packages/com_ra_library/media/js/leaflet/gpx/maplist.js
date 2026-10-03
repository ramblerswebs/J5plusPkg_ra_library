var L, ra, OsGridRef;
if (typeof (ra) === "undefined") {
    ra = {};
}
if (typeof (ra.display) === "undefined") {
    ra.display = {};
}
ra.display.gpxSingle = function (options, data) {

    this.data = data;
    var masterdiv = document.getElementById(options.divId);
    this.lmap = new ra.leafletmap(masterdiv, options);
    this.lmap.display();
    this.load = function () {
        var data = this.data;
        var file = data.gpxfile;
        var linecolour = data.linecolour;
        var imperial = data.imperial;
        var detailsDivId = data.detailsDivId;
        /////////////////////////
        var _map = this.lmap.map();
        var el = this.lmap.elevationControl();
        var g = new L.GPX(ra.baseDirectory() + file, {async: true,
            polyline_options: {color: linecolour},
            marker_options: {
                startIconUrl: ra.baseDirectory() + 'media/com_ra_library/js/leaflet/images/route-start.png',
                endIconUrl: ra.baseDirectory() + 'media/com_ra_library/js/leaflet/images/route-end.png',
                shadowUrl: null,
                iconSize: [20, 20], // size of the icon
                iconAnchor: [10, 10]
            }});
        g.on('addline', function (e) {
            el.addData(e.line);
        });
        g.on('addpoint', function (e) {
            var marker = e.point;
            switch (e.point_type) {
                case "waypoint":
                    var icon = L.icon({
                        iconUrl: ra.baseDirectory() + 'media/com_ra_library/js/leaflet/images/redmarker.png',
                        iconSize: [36, 41], // size of the icon
                        iconAnchor: [18, 41],
                        popupAnchor: [0, -20]
                    });
                    marker.setIcon(icon);
                    var sSymbol = marker.options.iconkey;
                    ra.map.icon.setMarker(marker, sSymbol);
                    break;
                case "start":
                    L.setOptions(marker, {title: "Start location"});
                    break;
                case "end":
                    L.setOptions(marker, {title: "End location"});
                    break;
            }
            L.setOptions(marker, {riseOnHover: true});
        });
        var _this = this;
        g.on('loaded', function (e) {
            _map.fitBounds(e.target.getBounds(), {padding: [20, 20]});
            _this.displayGpxdetails(g, detailsDivId);
            var clear = document.createElement('div');
            clear.classList.add("clearBoth");
            _this.pageDiv.appendChild(clear);
            // Scoped to this map's own container (masterdiv), not the whole
            // document - document.getElementsByClassName(...)[0] picked
            // whichever Leaflet map's control-container happened to be
            // FIRST in the page, which is only ever this one when it's the
            // only map on the page (a normal single-item page). Open the
            // same item in the "view in a popup" modal (ra.itempreview.js)
            // or the {gpx_map_list} lightbox (ra.gpxlightbox.js) from a
            // page that already has its own map (e.g. a Map/Table display)
            // and [0] silently grabbed that OTHER map's control-container
            // instead - whose "elevation" control is absent, so `ele[0]`
            // was undefined (not null, so the check below never caught it)
            // and the next line threw.
            const ele = masterdiv.getElementsByClassName("elevation");
            var elevation = ele[0];
            if (elevation) {
                var svg = elevation.childNodes[1];
                var clone = svg.cloneNode(true);
                var holder = document.createElement('div');
                holder.classList.add("elevation");
                holder.classList.add("steelblue-theme");
                holder.classList.add("leaflet-control");
                _this.pageDiv.appendChild(holder);
                holder.appendChild(clone);
            }
            var clear = document.createElement('div');
            clear.classList.add("clearBoth");
            _this.pageDiv.appendChild(clear);
        });
        g.addTo(_map);

    };
    this.displayGpxdetails = function (g, divId) {
        var container = document.getElementById(divId);

        if (container !== null) {
            var titleDiv = document.createElement('div');
            titleDiv.setAttribute("leaflet-browser-print-content", true);
            titleDiv.innerHTML = "";
            container.appendChild(titleDiv);
            var pageDiv = document.createElement('div');
            pageDiv.setAttribute("leaflet-browser-print-pages", true);
            container.appendChild(pageDiv);
            var info = g._info;
            var header = "";
            if (info !== "undefined" && info !== null) {
                if (info.name !== "undefined" && info.name !== null) {
                    header = '<b>Name:</b> ' + info.name + "<br/>";
                }
                header += '<b>Distance:</b> ' + ra.map.getGPXDistance(info.length) + '<br/>';
                if (info.desc) {
                    header += '<b>Description:</b> ' + info.desc + '<br/>';
                }
                if (info.elevation.gain === 0) {
                    header += "No elevation data<br/>";
                } else {
                    header += '<b>Min Altitude:</b> ' + info.elevation.min.toFixed(0) + ' m<br/>';
                    header += '<b>Max Altitude:</b> ' + info.elevation.max.toFixed(0) + ' m<br/>';
                    header += '<b>Elevation Gain:</b> ' + info.elevation.gain.toFixed(0) + ' m<br/>';
                }
                header += "<b>Est time:</b> " + ra.math.naismith(info.length, info.elevation.gain) + '<br/>';
                if (typeof info.duration !== "undefined") {
                    var duration = info.duration;
                    if (typeof duration.total !== "undefined") {
                        if (duration.total !== null) {
                            var actual = duration.total / 1000;
                            header += "<b>Actual Time:</b> " + ra.time.secsToHrsMins(actual) + '<br/>';
                        }
                    }
                }
                pageDiv.innerHTML = header;
                this.pageDiv = pageDiv;
            }
        }
    };
};
// container is optional (defaults to document, the old behaviour) - pass
// the specific map's own masterdiv when more than one Leaflet map/control-
// container can be on the page at once (see the same fix in the 'loaded'
// handler above), otherwise this can pick up the wrong map's elevation
// control.
ra.display.gpxGetElevationSVG = function (container) {
    const scope = container || document;
    const ele = scope.getElementsByClassName("elevation");
    var elevation = ele[0];

    if (!elevation) {
        // No elevation control in this container yet (e.g. displayElevation
        // wasn't enabled, or the GPX hasn't finished loading) - callers
        // already need to handle a route with no elevation data (see
        // gpxSingle's own "No elevation data" case), so return null rather
        // than throwing on elevation.childNodes below.
        return null;
    }

    var svg = elevation.childNodes[1];
    return svg;
};
ra.display.gpxGetElevationSVGCopy = function (svg, tag) {
    var clear = document.createElement('div');
    clear.classList.add("clearBoth");
    tag.appendChild(clear);
    var clone = svg.cloneNode(true);
    var holder = document.createElement('div');
    holder.classList.add("elevation");
    holder.classList.add("steelblue-theme");
    holder.classList.add("leaflet-control");
    tag.appendChild(holder);
    holder.appendChild(clone);
    var clear = document.createElement('div');
    clear.classList.add("clearBoth");
    tag.appendChild(clear);
};

ra.display.gpxFolder = function (options, data) {

    this._map = null;
    this.options = options;
    this.base = ra.baseDirectory();
    this.controls = {
        folder: null,
        linecolour: "#782327",
        dateorder: false,
        authororder: false,
        distorder: false,
        titleorder: false,
        gainorder: false,
        download: 0,
        elevation: null,
        description: true,
        displayAsPreviousWalks: false,
        gpx: null,
        searchtext: ''
    };
    this.routes = null;
    this.masterdiv = document.getElementById(options.divId);
    var tabOptions = {tabClass: 'tableDisplay',
        tabs: {map: {title: 'Map', staticContainer: true},
            list: {title: 'List', staticContainer: true}}};


    this.tabs = new ra.tabs(this.masterdiv, tabOptions);

    this.tabs.display();
    this.data = data;
    this.elements = {};

    this.load = function () {
        var _this = this;
        this.setData(this.data);
        var mapDiv = this.tabs.getStaticContainer('map');
        this.loadMap(mapDiv);
        var listDiv = this.tabs.getStaticContainer('list');
        this.loadTable(listDiv);

        this.masterdiv.addEventListener("displayTabContents", function (e) {
            if (e.tabDisplay.tab === 'map') {
                _this._map.invalidateSize();
            }
        });

    };
    this.loadMap = function (tag) {
        var _this = this;
        var tags = [
            {name: 'routeDiv', parent: 'root', tag: 'div', attrs: {class: 'ra routeinfo'}},
            {name: 'mapDiv', parent: 'root', tag: 'div'}
        ];
        this.elements = ra.html.generateTags(tag, tags);

        this.elements.routeDiv.innerHTML = 'Click on any walk to see summary, click on title to display route';
        this.lmap = new ra.leafletmap(this.elements.mapDiv, this.options);
        this.lmap.display();
        this._map = this.lmap.map();
        this.el = this.lmap.elevationControl();
        this.gpx = null;
        this.cluster = new ra.map.cluster(this._map);
        this._map.on('popupopen', function () {
            var tabs = document.querySelectorAll('.leaflet-popup-content div[data-route-id]');
            for (var i = 0; i < tabs.length; ++i) {
                tabs[i].addEventListener("click", function () {
                    var id = this.getAttribute('data-route-id');
                    _this.updateGPXid(id);
                });
            }
        });
        this.addGPXMarkers();

    };
//    this.loadList = function () {
//
//    };
    this.setData = function (data) {
        this.routes = data.items;
        this.controls.folder = data.folder;
        this.controls.linecolour = data.linecolour;
        this.controls.displayAsPreviousWalks = data.displayAsPreviousWalks;
        this.controls.download = data.download;
    };
    this.displayGPXName = function (route) {
        var link = '<b><div data-route-id="' + route.id + '" class="pointer">' + route.title + '</div></b>';
        return link;
    };
    this.loadTable = function (tag) {
        var format = [{"title": "Date", "options": {"align": "right"}, "field": {"type": "text", "filter": false, "sort": true}},
            {"title": "Leader", "options": {"align": "right", view: 'notMobile'}, "field": {"type": "text", "filter": false, "sort": true}},
            {"title": "Title", "options": {"align": "right"}, "field": {"type": "text", "filter": true, "sort": true}},
            {"title": "Distance Km", "options": {"align": "right"}, "field": {"type": "number", "filter": true, "sort": true}},
            {"title": "Miles", "options": {"align": "right", view: 'notTablet'}, "field": {"type": "number", "filter": false, "sort": false}},
            {"title": "min Altitude(m)", "options": {"align": "right", view: 'notTablet'}, "field": {"type": "number", "filter": false, "sort": true}},
            {"title": "max Altitude(m)", "options": {"align": "right", view: 'notTablet'}, "field": {"type": "number", "filter": false, "sort": false}},
            {"title": "Elevation Gain(m)", "options": {"align": "right", view: 'notMobile'}, "field": {"type": "number", "filter": false, "sort": true}},
            {"title": "GPX", "options": {"align": "right", view: 'notTablet'}, "field": {"type": "text", "filter": false, "sort": false}}
        ];
        if (!this.controls.displayAsPreviousWalks) {
            format.shift();
            format.shift();
        }
        var table = new ra.paginatedTable(tag);
        table.tableHeading(format);
        this._addTableRows(table, format);
        table.tableEnd();
        table.sort('Date', 'Desc');
        if (this.controls.download === 1) {
            var ele = document.createElement('p');
            tag.appendChild(ele);
            ele.textContent = "* To be able to download GPX Routes, you need to log on to our web site.";
        }
    };
    this._addTableRows = function (table, format) {
        for (var index = 0; index < this.routes.length; ++index) {
            var route = this.routes[index];
            if (this.displayRoute(route)) {
                this.addTableRow(table, route, format);
            }
        }
    };
    this.addTableRow = function (table, route, format) {
        var _this = this;
        table.tableRowStart();
        var i = 0;
        if (this.controls.displayAsPreviousWalks) {
            table.tableRowItem('<b>' + route.date + '</b>', format[i]);
            i += 1;
            table.tableRowItem(route.author, format[i]);
            i += 1;
        }
        var td = table.tableRowItem(this.displayGPXName(route), format[i]);
        td.addEventListener("click", function (e) {
            var tag = e.target;
            var id = tag.getAttribute('data-route-id');
            _this.updateGPXid(id);
        });
        i += 1;
        table.tableRowItem((route.distance / 1000).toFixed(1), format[i]);
        i += 1;
        table.tableRowItem(ra.units.metresToMi(route.distance).toFixed(2), format[i]);
        i += 1;
        table.tableRowItem(route.minAltitude.toFixed(0), format[i]);
        i += 1;
        table.tableRowItem(route.maxAltitude.toFixed(0), format[i]);
        i += 1;
        table.tableRowItem(route.cumulativeElevationGain.toFixed(0), format[i]);
        i += 1;

        if (this.controls.download > 0) {
            table.tableRowItem(this.getGPXdownloadLink(route), format[i]);
        }
        table.tableRowEnd();

    };
    this.updateGPXid = function (sid) {
        var id = parseInt(sid);
        this.tabs.clickToTab('map');
        var header, path;
        var route = this.getRoutefromID(id);
        header = "<h2>" + route.title + "</h2>";
        header += "<button style='float:right' class=\"link-button sunset small white\" onclick=\"javascript:ra.html.showhide(event, 'gpxDetails')\">Show/Hide Details</button>";
        header += "<div class='clear'></div>";
        header += "<div id='gpxDetails'><span>";


        if (this.controls.displayAsPreviousWalks) {
            header += '<b>Date:</b> ' + route.date + '<br/>';
            header += '<b>Leader:</b> ' + route.author + '<br/>';
        }
        header += '<b>Distance:</b> ' + ra.map.getGPXDistance(route.distance) + '<br/>';
        if (route.description !== '') {
            header += '<b>Description:</b> ' + route.description + '<br/>';
        }
        var p = new LatLon(route.latitude, route.longitude);
        var grid = OsGridRef.latLonToOsGrid(p);
        header += '<b>Start:</b> ' + grid.toString(6) + '<br/>';
        if (route.endLatitude !== undefined && route.endLongitude !== undefined) {
            if (route.endLatitude !== 0 && route.endLongitude !== 0) {
                var p = new LatLon(route.endLatitude, route.endLongitude);
                var grid = OsGridRef.latLonToOsGrid(p);
                header += '<b>End:</b> ' + grid.toString(6) + '<br/>';
            }
        }
        header += this.formatAltitude(route);
        header += "<b>Est time <a href=\"" + ra.map.helpNaismith + "\" target='_blank'>(Naismith)</a>:</b> " + ra.math.naismith(route.distance, route.cumulativeElevationGain) + '<br/>';
        if (route.duration !== 0) {
            header += "<b>Actual Time:</b> " + ra.time.secsToHrsMins(route.duration) + '<br/>';
        }
        if (this.controls.download > 0) {
            header += "<b>Download route:</b> " + this.getGPXdownloadLink(route) + '<br/>';
            if (this.controls.download === 1) {
                header += "* To be able to download GPX Routes, you need to log on to our web site.<br/>";
            }
        }
        if (route.tracks > 0) {
            header += "<b>Tracks:</b> " + route.tracks.toFixed(0);
        }

        if (route.routes > 0) {
            header += "<b>Routes:</b> " + route.routes.toFixed(0);
        }

        header += "</div>";
        if (route.links !== undefined) {
            if (route.links.length > 0) {
                header += '<b>Links</b><ul>';
                for (var index = 0; index < route.links.length; ++index) {
                    var link = route.links[index];
                    var text = link.text;
                    if (text === "") {
                        text = link.href;
                    }
                    header += '<li><a href="' + link.href + '" target="_blank" >' + text + '</a>';
                }
                header += '</ul>';
            }
        }
        header += "</div>";
        path = this.controls.folder + "/" + route.filename;
        this.elements.routeDiv.innerHTML = header;
        var data = {};
        data.gpxfile = path;
        data.linecolour = this.controls.linecolour;
        data.imperial = 0;
        this.displayGPX(data);
        this.elements.routeDiv.scrollIntoView();
    };
    this.displayGPX = function (data) {
        var file = data.gpxfile;
        var linecolour = data.linecolour;
        var _this = this;
        // remove old gpx route and elevation
        this.el.clear();
        if (this.gpx !== null) {
            this.gpx.remove();
            this.gpx = null;
        }

        this.gpx = new L.GPX(this.base + file, {async: true,
            polyline_options: {color: linecolour},
            marker_options: {
                startIconUrl: this.base + 'media/com_ra_library/js/leaflet/images/route-start.png',
                endIconUrl: this.base + 'media/com_ra_library/js/leaflet/images/route-end.png',
                shadowUrl: null,
                iconSize: [20, 20], // size of the icon
                iconAnchor: [10, 10]
            }});
        this.gpx.on('addline', function (e) {
            _this.el.addData(e.line);
        });
        this.gpx.on('addpoint', function (e) {
            var marker = e.point;
            switch (e.point_type) {
                case "waypoint":
                    var icon = L.icon({
                        iconUrl: ra.baseDirectory() + 'media/com_ra_library/js/leaflet/images/redmarker.png',
                        iconSize: [36, 41], // size of the icon
                        iconAnchor: [18, 41],
                        popupAnchor: [0, -20]
                    });
                    marker.setIcon(icon);
                    var sSymbol = marker.options.iconkey;
                    ra.map.icon.setMarker(marker, sSymbol);
                    break;
                case "start":
                    L.setOptions(marker, {title: "Start location"});
                    break;
                case "end":
                    L.setOptions(marker, {title: "End location"});
                    break;
            }
            L.setOptions(marker, {riseOnHover: true});
        });
        this.gpx.on('loaded', function (e) {
            _this._map.fitBounds(e.target.getBounds(), {padding: [20, 20]});
            _this._map.closePopup();

            // Same elevation-chart clone gpxSingle's own 'loaded' handler
            // appends next to its route details - missing here before, so
            // the folder/table display's route panel only ever showed the
            // min/max/gain text (formatAltitude(), already in `header`
            // above) with no chart. Scoped to this._map's own container
            // (elements.mapDiv), not the whole document, for the same
            // reason as gpxSingle's fix - this map is one of potentially
            // several Leaflet maps on the page at once (e.g. behind an
            // item-preview popup). routeDiv's innerHTML was already reset
            // for this route in updateGPXid() before displayGPX() was
            // called, so there's no stale previous clone to remove first.
            var svg = ra.display.gpxGetElevationSVG(_this.elements.mapDiv);

            if (svg) {
                ra.display.gpxGetElevationSVGCopy(svg, _this.elements.routeDiv);
            }
        });
        this.gpx.addTo(this._map);
    };
    this.getRoutefromID = function (id) {
        for (var index = 0; index < this.routes.length; ++index) {
            var route = this.routes[index];
            if (route.id === id) {
                return route;
            }
        }
        return null;
    };
    this.addGPXMarkers = function () {
        for (var index = 0; index < this.routes.length; ++index) {
            var route = this.routes[index];
            if (this.displayRoute(route)) {
                this.addGPXMarker(route);
            }
        }
        this.cluster.addClusterMarkers();
        this.cluster.zoomAll();
    };
    this.addGPXMarker = function (route) {
        var $popup, $lat, $long, title;
        title = route.title;
        $popup = "<div style='font-size:120%'>" + this.displayGPXName(route) + "</div>";
        $popup += '<b>Distance</b> - ' + ra.map.getGPXDistance(route.distance) + '<br/>';
        $popup += this.formatAltitude(route);
        $lat = route.latitude;
        $long = route.longitude;
        this.cluster.addMarker($popup, $lat, $long, {icon: ra.map.icon.markerRoute(), riseOnHover: true, title: title});
    };
    this.formatAltitude = function (route) {
        var text;
        text = "";
        if (route.cumulativeElevationGain === 0) {
            return "No elevation data<br/>";
        } else {
            text += '<b>Min Altitude:</b> ' + route.minAltitude.toFixed(0) + ' m<br/>';
            text += '<b>Max Altitude:</b> ' + route.maxAltitude.toFixed(0) + ' m<br/>';
            text += '<b>Elevation Gain:</b> ' + route.cumulativeElevationGain.toFixed(0) + ' m<br/>';
        }
        return text;
    };
    this.getGPXdownloadLink = function (route) {
        var path, link;
        link = "";
        if (this.controls.download === 1) {
            link = "*";
        }
        if (this.controls.download === 2) {
            path = ra.baseDirectory() + this.controls.folder + "/" + route.filename;
            link = "<a href='" + path + "'><img  alt='gpx' src='" + ra.baseDirectory() + "media/com_ra_library/images/orange-gpx-32.png' width='20' height='20'></a>";
        }
        return link;
    };
    this.displayRoute = function (route) {
        if (this.controls.searchtext === '') {
            return true;
        }
        if (route.title.toLowerCase().includes(this.controls.searchtext)) {
            return true;
        }
        return false;
    };

};
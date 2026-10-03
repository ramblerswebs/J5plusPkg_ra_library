/**
 * Footpath / PRoW network overlay for Leaflet, backed by OS NGD API – Features.
 *
 * WHY THIS SHAPE:
 * Unlike your existing "Ordnance Survey" / "OS Explorer" layers, there is no
 * vector-TILE collection for paths or rights of way — OS NGD API Tiles only
 * exposes 5 collections (ngd-base, asu-bdy, trn-ntwk-railway, wtr-ctch,
 * wtr-tidalboundary), none of which carry path/PRoW data. The relevant data
 * (trn-rami-highwaydedication-1) is only queryable via OS NGD API – FEATURES,
 * which returns paginated GeoJSON for a bbox rather than zxy tiles. So this
 * is a viewport-driven L.LayerGroup that fetches on pan/zoom and caches what
 * it has already drawn, rather than an L.maplibreGL style layer.
 *
 * DATA CAVEAT (surfaced to the user via the info modal below, not just here):
 * `publicrightofway` on trn-rami-highwaydedication-1 is described by OS as
 * "only an inference of where a Public Right of Way exists" — it is NOT the
 * definitive record (that remains the Definitive Map held by each of the
 * 120+ individual highway authorities, per your own pkg_ra_pathnetwork
 * research). Treat this layer as indicative, not authoritative.
 *
 * ATTRIBUTES: trn-rami-highwaydedication-1 does NOT carry a path/road name
 * (that lives on the separate trn-ntwk-path-1 / trn-ntwk-road-1 collections,
 * cross-referenced via networkreferenceid + networkfeaturetype). So most
 * segments will only ever show their dedication type plus whatever of
 * authorityid/effectivestartdate/effectiveenddate/the boolean flags OS has
 * populated for that segment - that's a property of the data, not a bug.
 * If you want names, we'd need a second lookup against the network
 * collection per click - flag it if you want that built.
 *
 * DEPENDENCIES: needs `L` (Leaflet) and `ra.modals`/`ra.modal` (your shared
 * modal component) loaded before this file runs.
 */
(function (global) {
    "use strict";
    const FEATURES_BASE = "https://api.os.uk/features/ngd/ofa/v1";
    const COLLECTION = "trn-rami-highwaydedication-1";
    const PAGE_LIMIT = 100;        // OS NGD API Features max per page
    const MAX_PAGES_PER_CELL = 20; // safety cap: 2000 features per grid cell

    // dedicationvalue codelist -> style. The four classic PRoW categories get
    // distinct, deliberately different treatments (colour + dash pattern, so
    // they're still distinguishable for colour-blind users and in print).
    // Non-PRoW dedication types are included too since onlyPublicRightOfWay
    // can be switched off to see the fuller network.
    // Colours are from the Okabe-Ito palette (chosen for staying distinguishable
    // under protanopia/deuteranopia/tritanopia, not just for looking nice), with
    // a clearly different dash rhythm per type too as a non-colour backup cue.
    const DEDICATION_STYLES = {
        "Pedestrian Way Or Footpath": {color: "#D55E00", weight: 3, dashArray: "6,4"}, // vermillion, short dashes
        "Bridleway": {color: "#0072B2", weight: 3, dashArray: "12,4,2,4"}, // blue, dash-dot
        "Restricted Byway": {color: "#CC79A7", weight: 3, dashArray: "2,3"}, // reddish purple, fine dots
        "Byway Open To All Traffic": {color: "#009E73", weight: 3, dashArray: "16,6"}, // bluish green, long dashes
        "Cycle Track Or Cycle Way": {color: "#56B4E9", weight: 2, dashArray: null}, // sky blue, solid
        "All Vehicles": {color: "#7f8c8d", weight: 1, dashArray: null},
        "Motorway": {color: "#2c3e50", weight: 3, dashArray: null},
        "No Dedication Or Dedication Unknown": {color: "#bdc3c7", weight: 1, dashArray: "1,5", opacity: 0.6}
    };
    const DEFAULT_STYLE = {color: "#95a5a6", weight: 1, dashArray: "1,5"};

    function styleFor(props) {
        return DEDICATION_STYLES[props.description] || DEFAULT_STYLE;
    }

    function escapeHtml(str) {
        return String(str == null ? "" : str).replace(/[&<>"']/g, ch => ({
                "&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "'": "&#39;"
            }[ch]));
    }

    // Real trn-rami-highwaydedication-1 attributes worth surfacing, per OS's
    // own data-structure docs - deliberately NOT including a "name" field,
    // because this collection doesn't have one (see header comment above).
    const PROPERTY_LABELS = {
        authorityid: "Highway authority ref",
        effectivestartdate: "Recorded from",
        effectiveenddate: "Recorded to",
        highwaydedicationid: "Dedication ID"
    };
    const BOOLEAN_FLAGS = {
        nationalcycleroute: "Part of a National Cycle Route",
        quietroute: "Quiet Route",
        obstruction: "⚠ Recorded obstruction on this section",
        planningorder: "⚠ Subject to a planning order",
        worksprohibited: "⚠ Works prohibited on this section"
    };

    // Coarse cache grid so we don't re-request the same ground on small pans.
    const CELL_SIZE_DEG = 0.03; // ~2-3km depending on latitude; tune to taste

    const INFO_MODAL_STORAGE_KEY = "ra.footpathNetworkLayer.infoSeen";
    const INFO_MODAL_VERSION = "4"; // bump to re-show the disclaimer to everyone after a text/feature change

    // Compact colour key for the four PRoW categories - used both in every
    // click popup and in the always-visible top-left legend control.
    // Motorway/All Vehicles/Cycle Track/Unknown are left out of this compact
    // key - they're only ever drawn when onlyPublicRightOfWay is switched
    // off, and would clutter a legend that's normally only relevant for the
    // 4 PRoW types.
    const CORE_LEGEND = [
        {label: "Footpath", key: "Pedestrian Way Or Footpath"},
        {label: "Bridleway", key: "Bridleway"},
        {label: "Restricted Byway", key: "Restricted Byway"},
        {label: "BOAT", key: "Byway Open To All Traffic"}
    ];

    /**
     * Renders the line sample as real SVG using the feature's actual dashArray,
     * rather than approximating it with CSS border-style (which only offers
     * solid/dashed/dotted) - so the legend is a pixel-accurate match for what's
     * drawn on the map, not just a rough impression of it.
     */
    function legendSwatchSvg(style, width) {
        const dash = style.dashArray ? ` stroke-dasharray="${style.dashArray}"` : "";
        return `<svg width="${width}" height="8" style="display:block;overflow:visible">` +
                `<line x1="0" y1="4" x2="${width}" y2="4" stroke="${style.color}" stroke-width="4"${dash} stroke-linecap="round" /></svg>`;
    }

    /** One legend row: a line sample with its label underneath. `barWidth` caps the sample's length (default: fill the container). */
    function legendBlockHtml(entry, barWidth) {
        const style = DEDICATION_STYLES[entry.key];
        return '<div style="margin:8px 0">' +
                legendSwatchSvg(style, barWidth || "100%") +
                `<div style="font-size:11.5px;color:#333;margin-top:3px;">${escapeHtml(entry.label)}</div>` +
                "</div>";
    }

    /** Colour key for the top-left map control - samples fill the (narrow) control box. */
    function controlLegendHtml() {
        const rows = CORE_LEGEND.map(entry => legendBlockHtml(entry)).join("");
        return '<div style="font-weight:bold;margin-bottom:2px">Rights of way</div>' + rows;
    }

    /** Colour key for the info modal - samples capped to a fixed length rather than stretching across the modal. */
    function modalLegendHtml() {
        const rows = CORE_LEGEND.map(entry => legendBlockHtml(entry, "70")).join("");
        return '<div style="margin-top:12px"><strong>Key</strong>' + rows + "</div>";
    }

    L.RA = L.RA || {};

    L.RA.FootpathNetworkLayer = L.LayerGroup.extend({

        options: {
            apiKey: null,
            minZoom: 13, // don't fetch at country/region scale
            onlyPublicRightOfWay: true,
            attribution: 'Highway dedication (indicative PRoW) &copy; Ordnance Survey',
            debounceMs: 400,
            showInfoModalOnFirstUse: true
        },

        initialize: function (options) {
            L.LayerGroup.prototype.initialize.call(this, [], options);
            L.Util.setOptions(this, options);
            this._loadedCells = new Set();
            this._debounceTimer = null;
            // SVG (Leaflet's default renderer) only registers clicks on the actual
            // rendered stroke, which is unusably thin at typical weights (1-2px).
            // Canvas's `tolerance` option pads the hit-test area in pixels without
            // changing how anything looks, so lines stay thin but are easy to hit.
            this._renderer = L.canvas({padding: 0.5, tolerance: 8});
        },

        onAdd: function (map) {
            L.LayerGroup.prototype.onAdd.call(this, map);
            this._map = map;
            this._boundRefresh = this._scheduleRefresh.bind(this);
            map.on("moveend zoomend", this._boundRefresh);
            this._scheduleRefresh();
            this._maybeShowInfoModal();
            this._addLegendControl();
            return this;
        },

        onRemove: function (map) {
            map.off("moveend zoomend", this._boundRefresh);
            this._removeLegendControl();
            L.LayerGroup.prototype.onRemove.call(this, map);
            return this;
        },

        /** Adds the always-visible top-left colour key while this layer is switched on. */
        _addLegendControl: function () {
            if (this._legendControl)
                return;
            const legend = L.control({position: "topleft"});
            legend.onAdd = () => {
                const div = L.DomUtil.create("div", "ra-footpath-legend leaflet-bar");
                div.style.cssText = "background:#fff;padding:8px 10px;font-size:12px;min-width:150px;" +
                        "line-height:1.4;box-shadow:0 1px 4px rgba(0,0,0,0.4);border-radius:4px;";
                div.innerHTML = controlLegendHtml();
                // Stop map pan/zoom from hijacking clicks or scroll over the legend box.
                L.DomEvent.disableClickPropagation(div);
                L.DomEvent.disableScrollPropagation(div);
                return div;
            };
            legend.addTo(this._map);
            this._legendControl = legend;
        },

        _removeLegendControl: function () {
            if (this._legendControl) {
                this._legendControl.remove();
                this._legendControl = null;
            }
        },

        _scheduleRefresh: function () {
            clearTimeout(this._debounceTimer);
            this._debounceTimer = setTimeout(() => this._refresh(), this.options.debounceMs);
        },

        _refresh: function () {
            const map = this._map;
            if (!map || map.getZoom() < this.options.minZoom)
                return;

            const bounds = map.getBounds().pad(0.1); // small buffer so panning feels smooth
            for (const cellKey of this._cellsCovering(bounds)) {
                if (this._loadedCells.has(cellKey))
                    continue;
                this._loadedCells.add(cellKey);
                this._fetchCell(cellKey);
            }
        },

        _cellsCovering: function (bounds) {
            const keys = [];
            const minLng = Math.floor(bounds.getWest() / CELL_SIZE_DEG);
            const maxLng = Math.ceil(bounds.getEast() / CELL_SIZE_DEG);
            const minLat = Math.floor(bounds.getSouth() / CELL_SIZE_DEG);
            const maxLat = Math.ceil(bounds.getNorth() / CELL_SIZE_DEG);
            for (let x = minLng; x < maxLng; x++) {
                for (let y = minLat; y < maxLat; y++) {
                    keys.push(x + "_" + y);
                }
            }
            return keys;
        },

        _fetchCell: function (cellKey, offset) {
            offset = offset || 0;
            const [cx, cy] = cellKey.split("_").map(Number);
            const bbox = [
                cx * CELL_SIZE_DEG,
                cy * CELL_SIZE_DEG,
                (cx + 1) * CELL_SIZE_DEG,
                (cy + 1) * CELL_SIZE_DEG
            ].join(",");

            const params = new URLSearchParams({
                key: this.options.apiKey,
                bbox: bbox,
                limit: PAGE_LIMIT,
                offset: offset
            });
            if (this.options.onlyPublicRightOfWay) {
                params.set("filter", "publicrightofway=true");
                params.set("filter-lang", "cql-text"); // this project's endpoint only accepts cql-text, not cql2-text
            }

            const url = `${FEATURES_BASE}/collections/${COLLECTION}/items?${params.toString()}`;

            fetch(url, {referrerPolicy: "strict-origin-when-cross-origin"})
                    .then(r => {
                        if (!r.ok)
                            throw new Error("OS NGD Features request failed: " + r.status);
                        return r.json();
                    })
                    .then(fc => {
                        if (!this._map)
                            return; // layer removed while in flight
                        L.geoJSON(fc, {
                            renderer: this._renderer,
                            style: f => styleFor(f.properties || {}),
                            onEachFeature: (f, layer) => {
                                const p = f.properties || {};
                                layer.bindPopup(this._popupContentFor(p), {maxWidth: 320, minWidth: 240});
                                layer.on("popupopen", e => {
                                    const el = e.popup.getElement();
                                    const link = el && el.querySelector(".ra-footpath-popup-info-link");
                                    if (link) {
                                        L.DomEvent.on(link, "click", evt => {
                                            L.DomEvent.preventDefault(evt);
                                            this._openInfoModal();
                                        });
                                    }
                                });
                            }
                        }).addTo(this);

                        const returned = (fc.features || []).length;
                        if (returned === PAGE_LIMIT && (offset / PAGE_LIMIT) < MAX_PAGES_PER_CELL) {
                            this._fetchCell(cellKey, offset + PAGE_LIMIT);
                        }
                    })
                    .catch(err => console.error("Footpath network fetch failed for cell", cellKey, err));
        },

        /** Builds the click-popup HTML for a single highway dedication feature. */
        _popupContentFor: function (p) {
            const rows = [`<strong>${escapeHtml(p.description || "Highway")}</strong>`];

            Object.keys(PROPERTY_LABELS).forEach(key => {
                const value = p[key];
                if (value !== undefined && value !== null && value !== "") {
                    rows.push(`${PROPERTY_LABELS[key]}: ${escapeHtml(value)}`);
                }
            });
            Object.keys(BOOLEAN_FLAGS).forEach(key => {
                if (p[key])
                    rows.push(BOOLEAN_FLAGS[key]);
            });

            rows.push(
                    '<span style="color:#777">Indicative only — not the definitive legal record.</span> ' +
                    '<a href="#" class="ra-footpath-popup-info-link">About this data</a>'
                    );

            return rows.join("<br>");
        },

        /** Shows the one-time explanatory modal, unless already dismissed (per INFO_MODAL_VERSION). */
        _maybeShowInfoModal: function () {
            if (!this.options.showInfoModalOnFirstUse)
                return;
            try {
                if (window.localStorage && localStorage.getItem(INFO_MODAL_STORAGE_KEY) === INFO_MODAL_VERSION) {
                    return;
                }
            } catch (e) {
                // localStorage unavailable (private browsing etc) - fall through and show anyway
            }
            this._openInfoModal();
        },

        /** Opens the info modal via your shared ra.modals component. Safe to call repeatedly. */
        _openInfoModal: function () {
            const html =
                    '<h2>About the footpath &amp; rights of way layer</h2>' +
                    '<p>This layer shows Ordnance Survey’s Highway Dedication data, filtered to routes OS has ' +
                    'flagged as carrying a public right of way: footpaths, bridleways, restricted byways and ' +
                    'byways open to all traffic (BOATs).</p>' +
                    '<p><strong>This is indicative, not authoritative.</strong> OS describes this as an ' +
                    '<em>inference</em> of where a right of way exists, not the definitive legal record. The ' +
                    'definitive record for any given path remains the Definitive Map and Statement held by the ' +
                    'relevant highway authority &mdash; there are 120+ of these across England and Wales, each ' +
                    'maintaining their own. Always check with the local highway authority (or the Ramblers) ' +
                    'before relying on a specific route’s legal status.</p>' +
                    '<p style="font-size:12.5px;color:#555">Source: Ordnance Survey, OS NGD API &ndash; Features, ' +
                    'collection <code>trn-rami-highwaydedication-1</code> (Highway Dedication, Transport theme).</p>' +
                    modalLegendHtml() +
                    '<button type="button" class="link-button granite tiny" id="ra-footpath-info-ok" style="margin-top:12px">OK, got it</button>' +
                    '<div style="font-size:12px;color:#777;margin-top:10px">You can reopen this explanation anytime ' +
                    'via "About this data" on any path’s popup.</div>';

            const modal = ra.modals.createModal(html, false, true);
            const content = modal.getContent();
            const okBtn = content.querySelector("#ra-footpath-info-ok");
            if (okBtn) {
                okBtn.addEventListener("click", () => modal.close());
            }

            const onClosing = e => {
                if (e.raModal && e.raModal.id === modal.id) {
                    try {
                        if (window.localStorage)
                            localStorage.setItem(INFO_MODAL_STORAGE_KEY, INFO_MODAL_VERSION);
                    } catch (err) { /* ignore */
                    }
                    document.removeEventListener("ra-modal-closing", onClosing);
                }
            };
            document.addEventListener("ra-modal-closing", onClosing);
        },

        /** Call when licence key changes or you want to force a hard reload of the path data. */
        clearCache: function () {
            this._loadedCells.clear();
            this.clearLayers();
        }
    });

    global.L = global.L || L;
})(window);
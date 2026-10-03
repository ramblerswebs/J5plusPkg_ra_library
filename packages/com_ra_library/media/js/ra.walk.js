var ra, displayCustomValues, OsGridRef, LatLon;
var ra = ra || {};
if (typeof (ra.data) === "undefined") {
    ra.data = {};
}

ra.events = function () {
    this.events = [];
    this.filter = null;
    ra.events.filterOptions = {};
    // these filter options are also used by calendar subscribe.
    ra.events.filterOptions.grades = {order: [
            'Easy Access',
            'Easy',
            'Leisurely',
            'Moderate',
            'Strenuous',
            'Technical']};

    ra.events.filterOptions.dow = {order: ['Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday',
            'Sunday']};
    ra.events.filterOptions.distance = {displaySingle: true,
        order: ['Not defined: See description',
            'Up to 3 miles (5 km)',
            '3+ to 5 miles (5-8 km)',
            '5+ to 8 miles (8-13 km)',
            '8+ to 10 miles (13-16 km)',
            '10+ to 13 miles (16-21 km)',
            '13+ to 15 miles (21-24 km)',
            '15+ miles (24 km)']};
    this.registerEvent = function (event) {
        if (event.getIntValue('basics', 'future')) {
            var idx = this.events.findIndex(o => String(o.admin.id) === String(event.admin.id));
            if (idx >= 0) {
                this.events[idx] = event;   // same walk registered again: replace, don't duplicate
            } else {
                this.events.push(event);
            }
        }
    };
    this.getEvent = function (id) {
        var item = this.events.find(o => String(o.admin.id) === String(id));
        return (typeof item !== 'undefined') ? item : null;
    };
    this.forEachAll = function (fcn) {
        this.events.forEach(event => {
            fcn(event);
        });
    };
    this.forEachFiltered = function (fcn) {
        this.events.forEach(event => {
            if (event._displayFiltered) {
                fcn(event);
            }
        });
    };
    this.filteredEvents = function () {
        var evts = [];
        this.events.forEach(event => {
            if (event._displayFiltered) {
                evts.push(event);
            }
        });
        return evts;
    };
    this.setAllWalks = function () {
        this.events.forEach(event => {
            event._displayFiltered = true;
        });
    };
    this.setDisplayFilter = function () {
        this.events.forEach(event => {
            event.setDisplayFilter(this.filter);
        });
    };
    this.getNoEventsToDisplay = function () {
        var no = 0;
        this.events.forEach(event => {
            if (event._displayFiltered) {
                no += 1;
            }
        });
        return no;
    };
    this.hasBookings = function () {
        var has = false;
        this.events.forEach(event => {
            if (event.bookings.enabled) {
                has = true;
            }
        });
        return has;
    };
    this.length = function () {
        return this.events.length;
    };
    this.getGroups = function () {
        const groups = new Map();

        this.events.forEach(event => {
            var groupCode = event.admin.groupCode;
            var groupName = event.admin.groupName;
            groups.set(groupCode, {'code': groupCode, 'name': groupName});
        });
        return groups;
    };




    this.setFilters = function (tag) {
        var filter = new ra.filter(document, "reDisplayWalks");
        this.filter = filter;
        var groupOptions = {displaySingle: false};
        var typeOptions = {displaySingle: false};
        var statusOptions = {displaySingle: false};
        var shapeOptions = {displaySingle: false};
        var bookingOptions = {displaySingle: false};
        var updateOptions = {
            order: [{title: 'All walks', limit: 0},
                {title: 'In last 3 months', limit: 93},
                {title: 'In last month', limit: 31},
                {title: 'In last 2 weeks', limit: 14},
                {title: 'In last week', limit: 7}
            ]};
        filter.addGroup(new ra.filter.groupText("idGroup", "Group", groupOptions));
        filter.addGroup(new ra.filter.groupText("idType", "Type", typeOptions));
        filter.addGroup(new ra.filter.groupText("idBooking", "Booking", bookingOptions));
        filter.addGroup(new ra.filter.groupText("idDOW", "Day of the week", ra.events.filterOptions.dow));
        filter.addGroup(new ra.filter.groupText("idShape", "Walk Shape/Type", shapeOptions));
        filter.addGroup(new ra.filter.groupText("idDistance", "Distance", ra.events.filterOptions.distance));
        filter.addGroup(new ra.filter.groupText("idGrade", "Grade", ra.events.filterOptions.grades));
        filter.addGroup(new ra.filter.groupDate("idDate", "Dates"));
        filter.addGroup(new ra.filter.groupLimit("idUpdate", "Updated", updateOptions));
        filter.addGroup(new ra.filter.groupText("idStatus", "Status", statusOptions));
        filter.addGroup(new ra.filter.groupText("idFacilities", "Facilities"));
        filter.addGroup(new ra.filter.groupText("idTransport", "Transport"));
        filter.addGroup(new ra.filter.groupText("idAccessibility", "Accessibility"));

        this.events.forEach(event => {
            event.initialiseFilter(filter);
        });
        filter.display(tag);
    };
};
ra.event = function () {
    this.admin = new ra.event.admin();
    this.basics = new ra.event.basics();
    this.walks = new ra.event.items();
    this.meeting = new ra.event.items();
    this.start = new ra.event.items();
    this.finish = new ra.event.items();
    this.contacts = new ra.event.items();
    this.media = new ra.event.media();
    this.flags = new ra.event.flags();
    this.linkedWalks = [];
    this.linkedEvent = null;
    this.bookings = null;
    this.general = new ra.event.general();
    var mapSummary = {};
    mapSummary.template = new ra.SimpleTemplate("{dowddmm}{lf}{title}{, ?distance}");
    mapSummary.fields = mapSummary.template.getFields();
    var mapLinks = {};
    mapLinks.template = new ra.SimpleTemplate("{startOSMap}{startDirections}");
    mapLinks.fields = mapLinks.template.getFields();
    var mapGrade = {};
    mapGrade.template = new ra.SimpleTemplate("{mapGrade}");
    mapGrade.fields = mapGrade.template.getFields();
    var mapTitle = {};
    mapTitle.template = new ra.SimpleTemplate("{mapDowShortddmm}{, ?distance}");
    mapTitle.fields = mapTitle.template.getFields();
    let mapLayer = null;
    this.isCancelled = function () {
        return this.admin.isCancelled();
    };
    this.convertPHPWalk = function (phpwalk) {
        // admin
        this.admin.convertPHPAdmin(phpwalk);
        // basics
        this.basics.convertPHPBasics(phpwalk);
        // walks
        phpwalk.walks.forEach(item => {
            var walk = new ra.event.walk();
            this.walks.addItem(walk.convertPHPWalk(item));
        });
        // meeting
        phpwalk.meeting.forEach(item => {
            var meet = new ra.event.timelocation();
            this.meeting.addItem(meet.convertPHPLocation(item, this.isCancelled()));
        });
        // start
        phpwalk.start.forEach(item => {
            var start = new ra.event.timelocation();
            this.start.addItem(start.convertPHPLocation(item, this.isCancelled()));
        });
        // finish
        phpwalk.finish.forEach(item => {
            var fin = new ra.event.timelocation();
            this.finish.addItem(fin.convertPHPLocation(item, this.isCancelled()));
        });
        // contact
        phpwalk.contact.forEach(item => {
            var contact = new ra.event.contact(this.admin.id);
            this.contacts.addItem(contact.convertPHPContact(item));
        });
        // media
        this.media.addPHPMedia(phpwalk.media);
        // flags
        this.flags.addFlags(phpwalk.flags);
        // linked walks
        this.linkedWalks = phpwalk.linkedWalks;
        // linked Event
        this.linkedEvent = phpwalk.linkedEvent;
        // bookings
        this.bookings = new ra.event.bookings(this.admin.id, this);
        this.bookings.convertPHPBookings(phpwalk);
    };
    this.walkDiagnostics = function (tag) {
        var options = ["{lf}", "<b>ADMIN</b>", "{group}", "{eventType}", "<b>BASICS</b>", "{title}", "{description}", "{additionalNotes}",
            "{dowShortdd}", "{dowShortddmm}",
            "{dowShortddmmyyyy}", "{dowdd}", "{dowddmm}", "{dowddmmyyyy}",
            "<b>MEETING</b>", "{meet}", "{meetTime}", "{meetPlace}", "{meetGR}", "{meetPC}",
            "{meetw3w}", "{meetOSMap}", "{meetDirections}",
            "<b>START</b>", "{start}", "{startTime}", "{startPlace}", "{startGR}", "{startPC}", "{startw3w}", "{startOSMap}", "{startDirections}",
            "<b>FINISH</b>", "{finishTime}",
            "<b>WALK</b>", "{difficulty}", "{difficulty+}", "{type}", "{shape}",
            "{distance}", "{distanceMi}", "{distanceKm}", "{gradeimg}", "{gradeimgRight}",
            "{grade}", "{grade+}", "{nationalGrade}", "{nationalGradeAbbr}", "{localGrade}",
            "{grade}", "{ascent}", "{ascentMetres}", "{ascentFeet}",
            "<b>CONTACT</b>", "{contact}", "{contactname}", "{contactperson}", "{telephone}",
            "{telephone1}", "{telephone2}", "{email}", "{emailat}", "{emaillink}",
            "<b>MEDIA</b>", "{mediathumbr}",
            "{mediathumbl}"];
        var intValues = {admin: [],
            basics: ["dayofweek", "displayMonth"],
            walks: ["_filterDistance", "_nationalGradeCSS", "_nationalGrade",
                "_icsWalkDuration", "_icsWalkGrade", "_icsWalkDistance"],
            meeting: ["_latitude", "_longitude", "_icsDescription", "_icsTime"],
            start: ["_latitude", "_longitude", "_icsDescription", "_icsTime", "_type"],
            finish: ["_latitude", "_longitude", "_icsDescription", "_icsTime"],
            contacts: ["_icsrecord"]};
        var tags = [
            {name: 'table', parent: 'root', tag: 'table', attrs: {class: 'ra-diagnostics'}},
            {name: 'tr', parent: 'table', tag: 'tr'},
            {parent: 'tr', tag: 'th', attrs: {style: 'min-width: 130px;'}, textContent: 'Name'},
            {parent: 'tr', tag: 'th', textContent: 'Value'},
            {parent: 'tr', tag: 'th', textContent: 'HTML'},
            {parent: 'root', tag: 'h4', textContent: 'Walk Object:'},
            {name: 'pre', parent: 'root', tag: 'pre', attrs: {class: 'ra-diagnostics'}},
            {parent: 'root', tag: 'h4', textContent: 'Internal Values:'},
            {name: 'itable', parent: 'root', tag: 'table', attrs: {class: 'ra-diagnostics'}},
            {name: 'itr', parent: 'itable', tag: 'tr'},
            {parent: 'itr', tag: 'th', attrs: {style: 'min-width: 120px;'}, textContent: 'Name'},
            {parent: 'itr', tag: 'th', textContent: 'Value'},
            {parent: 'itr', tag: 'th', textContent: 'HTML'}
        ];
        var $value;
        var elements = ra.html.generateTags(tag, tags);
        options.forEach(option => {
            $value = this.getEventValue(option);
            if ($value === undefined) {
                $value = "undefined";
            }
            var cols = [option, $value, $value.replace(/</g, "&lt;").replace(/>/g, "&gt;")];
            ra.html.addTableRow(elements.table, cols);
        });
        var cols = ["<b>INTERNAL VALUES</b>", "<b>INTERNAL VALUES</b>", "INTERNAL VALUES"];
        ra.html.addTableRow(elements.itable, cols);
        for (let key in intValues) {
            var section = intValues[key];
            section.forEach(option => {
                $value = this.getIntValue(key, option);
                if ($value === null) {
                    $value = "undefined";
                }
                $value = $value.toString();
                var cols = [key + " / " + option, $value, $value.replace(/</g, "&lt;").replace(/>/g, "&gt;")];
                ra.html.addTableRow(elements.itable, cols);
            });
        }

        var t = JSON.stringify(this, null, 4);
        elements.pre.innerHTML = "Times are in UTC so may be 1 hr out<br/>" + ra.syntaxHighlight(t);
    };
    /**
     * Get walk text from template
     * @param {object} layout - Obj with templat and fields
     * @param {boolean} link - Whether to add walk link (default: true)
     * @returns {string} Rendered output
     */
    this.getWalkText = function (layout, link) {
        if (link === undefined) {
            link = true;
        }
        var values = this.fetchValues(layout.fields); // your fetch logic
        var out = layout.template.render(values);
        if (link) {
            return this.addWalkLink(out);
        } else {
            return out;
        }
    };
    /**
     * Fetch values for template fields
     * @param {Array} fields - Array of field names
     * @returns {Object} Object with field values
     */
    this.fetchValues = function (fields) {
        var values = {};
        var i, field;
        for (i = 0; i < fields.length; i++) {
            field = fields[i];
            values[field] = this.getEventValue("{" + field + "}");
        }

        return values;
    };
    this.getEventValuesDEL = function (items, link = true) {
        var out, lastItem, thisItem;
        var options;
        out = "";
        lastItem = '';
        items.forEach(item => {
            options = this.getPrefix(item);
            thisItem = this.getEventValue(options.walkValue);
            if (lastItem !== '' && thisItem !== '') {
                out += options.previousPrefix;
            }
            if (thisItem !== "") {
                out += options.prefix;
            }
            out += thisItem;
            lastItem = thisItem;
        });
        if (out === '') {
            return out;
        }
        if (link) {
            return this.addWalkLink(out);
        } else {
            return  out;
    }

    };
    this.getPrefix = function (walkOption) {
        var options = {};
        options.previousPrefix = '';
        options.prefix = "";
        options.walkValue = walkOption;
        var $loop = true;
        do {
            switch (options.walkValue.substr(0, 2)) {
                case "{;":
                    options.prefix += '<br/>';
                    options.walkValue = options.walkValue.replace("{;", "{");
                    break;
                case "{,":
                    options.prefix += ", ";
                    options.walkValue = options.walkValue.replace("{,", "{");
                    break;
                case "{[":
                    var $close = options.walkValue.indexOf("]");
                    if ($close > 0) {
                        options.prefix += options.walkValue.substr(2, $close - 2);
                        options.walkValue = "{" + options.walkValue.substr($close + 1);
                    } else {
                        options.prefix += options.walkValue;
                        options.walkValue = "{}";
                    }
                    break;
                case "{<":
                    var $close = options.walkValue.indexOf(">");
                    if ($close > 0) {
                        options.previousPrefix += options.walkValue.substr(2, $close - 2);
                        options.walkValue = "{" + options.walkValue.substr($close + 1);
                    } else {
                        options.previousPrefix += options.walkValue;
                        options.walkValue = "{}";
                    }
                    break;
                default:
                    $loop = false;
            }
        } while ($loop);
        return options;
    };
    this.getEventValue = function ($option) {
        var BR = '<br/>';
        var out = "";
        switch ($option) {
            case "{lf}":
                out = BR;
                break;
            case "{group}":
            case "{eventType}":
                out = this.admin.getValue($option);
                break;
            case "{dowShortdd}":
            case "{dowShortddmm}":
            case "{mapDowShortddmm}":
            case "{dowShortddyyyy}": // published in error
            case "{dowShortddmmyyyy}":
            case "{dowdd}":
            case "{dowddmm}":
            case "{dowddmmyyyy}":
            case "{title}":
            case "{description}":
            case "{additionalNotes}":
            case "{finishTime}":
                out = this.basics.getValue($option);
                break;
            case "{meet}":
            case "{meetTime}":
            case "{meetPlace}":
            case "{meetGR}":
            case "{meetPC}":
            case "{meetOLC}":
            case "{meetw3w}":
            case "{meetOSMap}":
            case "{meetDirections}":
                out = this.meeting.getValue($option.replace('meet', ''));
                break;
            case "{start}":
            case "{startTime}":
            case "{startPlace}":
            case "{startGR}":
            case "{startPC}":
            case "{startOLC}":
            case "{startw3w}":
            case "{startOSMap}":
            case "{startDirections}":
                out = this.start.getValue($option.replace('start', ''));
                break;
            case "{distance}":
            case "{distanceMi}":
            case "{distanceKm}":
            case "{difficulty}":
            case "{difficulty+}":
            case "{gradeimg}":
            case "{gradeimgRight}":
            case "{gradeimgMiddle}":
            case "{grade}":
            case "{grade+}":
            case "{nationalGrade}":
            case "{nationalGradeAbbr}":
            case "{localGrade}":
            case "{ascent}":
            case "{ascentMetres}":
            case "{ascentFeet}":
            case "{type}":
            case "{shape}":
            case "{mapGrade}":
                out = this.walks.getValue($option);
                break;
            case "{contact}":
            case "{contactname}":
            case "{contactperson}":
            case "{telephone}":
            case "{telephone1}":
            case "{telephone2}":
            case "{email}":
            case "{emailat}":
            case "{emaillink}":
                out = this.contacts.getValue($option);
                break;
            case "{mediathumbr}":
            case "{mediathumbl}":
                out = this.media.getValue($option.replace('media', ''));
                break;
            default:
                var $found = false;
                var $response = "";
                if (typeof displayCustomValues === 'function') {
                    if ($option.startsWith("{x")) {
                        $response = displayCustomValues($option, this);
                        $found = $response.found;
                    }
                }
                if ($found) {
                    out += $response.out;
                } else {
                    $option = $option.replace("{", "");
                    out = $option.replace("}", "");
                }
        }

        return out;
    };
    this.getIntValue = function ($section, $option) {

        switch ($section) {
            case "admin":
                return this.admin.getIntValue($option);
            case "basics":
                return this.basics.getIntValue($option);
            case "walks":
                return this.walks.getIntValue($option);
            case "meeting":
                return this.meeting.getIntValue($option);
            case "start":
                return this.start.getIntValue($option);
            case "finish":
                return this.finish.getIntValue($option);
            case "contacts":
                return this.contacts.getIntValue($option);
        }
    };
    this.displayInModal = function (event) {
        var div = document.createElement("div");
        div.style.display = "inline-block";
        var modal = ra.modals.createModal(div);
        if (event.ctrlKey && event.altKey) {
            this.walkDiagnostics(div);
        } else {
            this.walkDetailsDisplay(div);
            var tag = modal.headerDiv();
            if (tag !== null) {
                this._addDiaryButton(tag);
            }
        }
    };
    this._addDiaryButton = function (tag) {
        var diary = document.createElement('button');
        diary.setAttribute('class', 'link-button tiny cloudy');
        diary.title = 'Download an .ICS file, import to Diary';
        diary.textContent = 'Add to Calendar';
        tag.parentNode.insertBefore(diary, tag);
        var _this = this;
        diary.addEventListener('click', function () {
            var file = new ra.ics.file();
            _this.addWalktoIcs(file);
            file.download();
        });
    };
    this.initialiseFilter = function (filter) {
        var values = this.getFilterValues();
        filter.initialiseFilter(values);
    };
    this.setDisplayFilter = function (filter) {
        var values = this.getFilterValues();
        this._displayFiltered = filter.shouldDisplayItem(values);
    };
    this.getFilterValues = function () {
        var valueSet = new ra.filter.valueSet();
        valueSet.add("idDate", this.basics.walkDate);
        valueSet.add("idType", this.admin.eventType);
        var dsow = this.getIntValue("basics", "filterDaysofweek");
        dsow.forEach(dow => {
            valueSet.add("idDOW", dow);
        });
        var status = this.admin.status;
        valueSet.add("idStatus", status);
        var booking = this.bookings.enabled;
        if (booking) {
            valueSet.add("idBooking", "Required");
        } else {
            valueSet.add("idBooking", "Not required");
        }
        //   valueSet.add("idShape", null);
        this.walks.forEach(walk => {
            walk.setFilter(valueSet);
        });
        valueSet.add("idGroup", this.admin.groupName);
        valueSet.add("idUpdate", this.admin.filterUpdate());
        var flags = this.flags.getFlags();
        flags.forEach(flag => {
            valueSet.add("id" + flag.section, flag.name);
        });
        return valueSet;
    };
    this.addWalktoIcs = function (events) {
        var ev = new ra.ics.event();
        var $meetLocation, $startLocation, $before, $after, $summary, $description, $altDescription;
        $meetLocation = this.getIntValue("meeting", "_icsDescription");
        if ($meetLocation !== "") {
            $meetLocation += "; <br/>";
        }
        $startLocation = this.getIntValue("start", "_icsDescription");
        $before = $meetLocation + $startLocation + "<br/>Description: ";
        $after = "<br/>" + this.getIntValue("contacts", "_icsrecord");
        $after += this.getIntValue("walks", "_icsWalkGrade");
        if (this.admin.nationalUrl !== "") {
            $after += this.admin.nationalUrl;
        }

        $after += "<br/>Note: Finish times are very approximate!";
        if (this.basics.additionalNotes !== '') {
            $after += "<br/>Notes: " + this.basics.additionalNotes;
        }
        $summary = this.basics.title;
        $summary += this.getIntValue("walks", "_icsWalkDistance");
        if (this.isCancelled()) {
            ev.method("CANCEL");
            $summary = " CANCELLED " + $summary;
            $description = "CANCELLED - REASON: " + this.admin.cancellationReason + " (" + this.basics.description + ")";
        } else {
            $description = $before + this.basics.description + $after;
            $altDescription = $before + this.basics.descriptionHtml + $after;
        }

        var $time = this._ics_getFirstTime();
        //  var d = ra.date.getDateTime(this.basics.walkDate);
        if ($time !== null) {
            ev.startDate($time);
            $time = this._ics_getFinishTime();
            if ($time !== null) {
                ev.endDate(new Date($time));
            }
        } else {
            ev.startDate(ra.date.getDateTime(this.basics.walkDate));
        }
        ev.createdDate(ra.date.getDateTime(this.admin.dateCreated));
        ev.modifiedDate(ra.date.getDateTime(this.admin.dateUpdated));
        ev.uid('walk' + this.admin.id + '-isc@ramblers-webs.org.uk');
        ev.organiser(this.admin.groupName + ":mailto:ignore@ramblers-webs.org.uk");
        ev.summary($summary);
        ev.description($description);
        ev.altDescription($altDescription);
        ev.location($startLocation);
        ev.url(this.admin.nationalUrl);
        ev.categories("Walk," + this.admin.groupName);
        ev.class('PUBLIC');
        events.addEvent(ev);
    };
    this._ics_getFirstTime = function () {
        var time = null;
        this.meeting.forEach(loc => {
            if (time === null) {
                if (loc.time !== null) {
                    time = loc.time;
                    return time;
                }
            }
        });
        this.start.forEach(loc => {
            if (time === null) {
                if (loc.time !== null) {
                    time = loc.time;
                }
            }
        });
        return time;
    };
    this._ics_getLastTime = function () {
        var time = null;
        this.start.forEach(loc => {
            if (time === null) {
                if (loc.time !== null) {
                    time = loc.time;
                }
            }
        });
        this.meeting.forEach(loc => {
            if (time === null) {
                if (loc.time !== null) {
                    time = loc.time;
                }
            }
        });
        return time;
    };
    this._ics_getFinishTime = function () {
        var time = this.getIntValue("finish", "_icsTime");
        if (time !== "") {
            return time;
        }
        // calculate end time
        var $durationFullMins = 0;
        var $lasttime = this._ics_getLastTime();
        if ($lasttime !== "") {
            $durationFullMins = this.getIntValue("walks", "_icsWalkDuration");
            if ($durationFullMins === "") {
                $durationFullMins = 0;
            }
            if (this.getIntValue("start", "_type") === "Rough") {
                $durationFullMins += 60;
            }
            $lasttime = ra.time.addMinutes($lasttime, $durationFullMins);
        }
        return $lasttime;
    };
    this.getEventDatetime = function () {
        var earliest = this.basics.getIntValue('bookingdate');
        this.meeting.forEach(item => {
            var time = item.getIntValue('bookingtime');
            if (earliest === null) {
                earliest = time;
            } else {
                if (time < earliest) {
                    earliest = time;
                }
            }
        });
        this.start.forEach(item => {
            var time = item.getIntValue('bookingtime');
            if (earliest === null) {
                earliest = time;
            } else {
                if (time < earliest) {
                    earliest = time;
                }
            }
        });
        return  earliest;
    };
    this.addTitleSection = function (tag) {
        var nationalGradeCSS = this.getIntValue("walks", "_nationalGradeCSS");
        var content = document.createElement('div');
        content.setAttribute('class', 'walkitem group ' + nationalGradeCSS);
        var $html = "<b>Group</b>: " + this.admin.groupName;
        if (this.isCancelled()) {
            $html += "<div class='walkitem reason'>" + this.admin.eventType + " CANCELLED: " + this.admin.cancellationReason + "</div>";
        }

        content.innerHTML = $html;
        tag.appendChild(content);
    };
    this.addBasicSection = function (tag) {
        var content = document.createElement('div');
        content.setAttribute('class', 'walkitem basics');
        var $html = "";
        $html += "<div class='description'><b><span class='walktitle'>" + this.basics.title + "</span><br/>" + this.getEventValue('{dowddmm}');
        $html += "</b></div>";
        if (this.basics.description !== "") {
            $html += "<div class='description'> " + this.basics.descriptionHtml + "</div>";
        }
        if (this.basics.additionalNotes !== "") {
            $html += "<div class='additionalnotes'><b>Additional Notes</b>: " + this.basics.additionalNotes + "</div>";
        }
        var shape = this.walks.getValue("{shape}");
        if (shape !== "") {
            $html += "<b>" + shape + " Walk</b>";
        }

        var time = this.meeting.getValue("{Time}");
        if (time !== "") {
            $html += "<div><b>Meeting time " + time + "</b></div>";
        }
        time = this.start.getValue("{Time}");
        if (time !== "") {
            $html += "<div><b>Start time " + time + "</b></div>";
        }
        time = this.basics.getValue("{finishTime}");
        if (time !== "") {
            $html += "<div><b>Estimated finish time " + time + "</b></div>";
        }
        content.innerHTML = $html;
        tag.appendChild(content);
    };
    this.addMapSection = function (tag) {
        var osMapLayer;
        var mapdiv = document.createElement('div');
        mapdiv.setAttribute('class', 'walkitem map');
        tag.appendChild(mapdiv);
        var lmap = new ra.leafletmap(mapdiv, ra.defaultMapOptions);
        lmap.display();
        var map = lmap.map();
        mapLayer = L.featureGroup().addTo(map);
        osMapLayer = L.featureGroup().addTo(map);
        tag.addEventListener("display-os-map", function (e) {
            var items = e.items;
            osMapLayer.clearLayers();
            items.forEach(item => {
                ra.map.os.display(item, osMapLayer);
            });
            map.fitBounds(osMapLayer.getBounds());
        });
        this.meeting.forEach(loc => {
            loc._addLocationMarker(mapLayer, map);
        });
        this.start.forEach(loc => {
            loc._addLocationMarker(mapLayer, map);
        });
        this.finish.forEach(loc => {
            loc._addLocationMarker(mapLayer, map);
        });
        var bounds = mapLayer.getBounds();
        map.fitBounds(bounds, {maxZoom: 15, padding: [20, 20]});
        return;
//        fix contact link does not work if popup is underneath it and it is in coloumn two
//        var elems = document.getElementsByClassName("walkcontact");
//        elems[0].addEventListener('mouseover', function () {
//            map.closePopup();
//        });

    };
    this.addLinkedWalksSection = function (tag, walkids) {
        if (walkids.length === 0) {
            return;
        }
        var content = document.createElement('div');
        content.setAttribute('class', 'walkitem linkedWalks');
        var $html = "<div><b>Walks associated with this event</b></div><ul style='margin-top:5px;margin-bottom:5px;'>";
        walkids.forEach(walkid => {
            $html += '<li>';
            $html += this.getLinkedHtml(walkid);
            $html += '</li>';
        });
        $html += '</ul>';
        content.innerHTML = $html;
        tag.appendChild(content);
    };
    this.getLinkedHtml = function (id) {
        var html = '';
        var $class = '';
        var title = "Walk not found: " + id;
        var walk = ra.walk.getEventID(id);
        if (walk !== null) {
            title = walk.basics.getValue('{title}');
        }
        html += "<span class='pointer " + $class + "' onclick=\"javascript:" + ra.walk.DisplayWalkFunction + "(event,'" + id + "')\" title='Click to display walk details'>" + title + "</span>";
        return html;
    };
    this.addLinkedEventSection = function (tag, eventid) {
        if (eventid === null) {
            return;
        }
        var content = document.createElement('div');
        content.setAttribute('class', 'walkitem linkedWalks');
        var $html = "Part of event: ";
        $html += this.getLinkedHtml(eventid);
        content.innerHTML = $html;
        tag.appendChild(content);
    };
    this.addFooterSection = function (tag) {
        var content = document.createElement('div');
        content.setAttribute('class', 'walkitem walkdates');
        var $html = "";
        if (this.admin.nationalUrl !== '') {
            $html += "<div><img src=\"" + ra.baseDirectory() + "media/com_ra_library/images/ralogo.png\" alt=\"Ramblers\" width=\"17\" height=\"17\"> ";
            $html += "<a href='" + this.admin.nationalUrl + "' target='_blank' >View " + this.admin.eventType + " on Ramblers national web site</a></div>";
        }
        var text = escape(this.admin.localPopupUrl);
        $html += "<div>";
        $html += "<img src=\"" + ra.baseDirectory() + "media/com_ra_library/js/leaflet/images/share.png\" alt=\"Share\" width=\"17\" height=\"17\"> ";
        $html += "<a href=\"javascript:ra.clipboard.set(\'" + text + "')\" >Copy url of this popup to clipboard</a>";
        $html += "</div>";
        $html += "<div>Last update: " + ra.date.dowShortddmmyyyy(this.admin.dateUpdated) + "</div>";
        $html += "</div>";
        $html += "<div>Event ID " + this.admin.id + "</div><hr";
        content.innerHTML = $html;
        tag.appendChild(content);
    };
    this.addTooltip = function ($text) {
        if ($text === '') {
            return $text;
        }
        if (this.isCancelled()) {
            return "<span data-descr='Walk Cancelled' class=' walkCancelled'>" + $text + "</span>";
        }
        if (this.admin.flagNew) {
            return "<span class='new' data-descr='New walk/event created " + ra.date.dowShortddmmyyyy(this.admin.dateCreated) + "' class=' walkNew'>" + $text + "</span>";
        }
        if (this.admin.flagUpdated) {
            return "<span class='updated' data-descr='Walk/event updated " + ra.date.dowShortddmmyyyy(this.admin.dateUpdated) + "' class=' walkNew'>" + $text + "</span>";
        }
        return $text;
    };
// display walks 
    this.walkDetailsDisplay = function (tag) {

        var content = document.createElement('div');
        content.setAttribute('class', 'walkstdfulldetails stdfulldetails walk' + this.admin.status);
        ra.html.insertAfter(tag, content);
        // tag.appendChild(content);
        this.addTitleSection(content);
        this.addBasicSection(content);
        this.bookings.addBookingsSection(content);
        this.meeting.addSection(content, "meeting");
        this.start.addSection(content, "start");
        this.finish.addSection(content, "finish");
        this.walks.addHtmlSection(content, "difficulty");
        this.addLinkedWalksSection(content, this.linkedWalks);
        this.addLinkedEventSection(content, this.linkedEvent);
        this.contacts.addHtmlSection(content, "contact");
        if (!this.isCancelled()) {
            this.addMapSection(content);
            this.media.addMediaSection(content);
            this.flags.addFlagsSection(content);
            //  this.general.addSection(content);
            this.updatePostcodeInfo();
        }
        this.addFooterSection(content);
        return;
    };
    this.updatePostcodeInfo = function () {
        this.meeting.forEach(loc => {
            loc._addPostcodeMarker(mapLayer);
        });
        this.start.forEach(loc => {
            loc._addPostcodeMarker(mapLayer);
        });
        this.finish.forEach(loc => {
            loc._addPostcodeMarker(mapLayer);
        });
    };
    this.addWalkMarker = function (cluster, walkClass) {
        var $long, $lat, $icon, $class;
        var $popup;
        this.start.forEach(loc => {
            var id = this.admin.id;
            var isCancelled = this.isCancelled();
            var isEvent = this.admin.eventType === "Event";
            var summary = this.getWalkText(mapSummary, false);
            var link = this.getWalkText(mapLinks, false);
            var grade = this.getWalkText(mapGrade, false);
            var title = this.getWalkText(mapTitle, false);
            loc.addWalkMarker(cluster, walkClass, id, isEvent, isCancelled, summary, link, grade, title);
        });
    };
    this._addWalkLink = function ($text, $class = "") {
        if ($text !== '') {
            return  "<span class='pointer " + $class + "' onclick=\"javascript:" + ra.walk.DisplayWalkFunction + "(event,'" + this.admin.id + "')\" title='Click to display walk details'>" + $text + "</span>";
        }
        return $text;
    };
    this.addWalkLink = function ($text, $class = "") {
        // split into text before and after span
        var st, en;
        var start = '';
        st = $text.indexOf("<span");
        en = $text.indexOf("/span>") + 6;
        if (st > -1 && en > st) {
            if (st > 0) {
                //var a = $text;
                // var _start = $text.substring(0, st);
                start = this._addWalkLink($text.substring(0, st), $class);
            }
            var middle = $text.substring(st, en);
            //  var _end = $text.substr(en);
            var end = this.addWalkLink($text.substr(en), $class);
            return start + middle + end;
        } else {
            return   this._addWalkLink($text, $class);
    }

    };
};
// end of ra.event


ra.event.items = function () {
    this.items = [];
    this.addItem = function (newItem) {
        if (newItem !== null) {
            this.items.push(newItem);
        }
    };
    this.getValue = function ($option) {
        if (this.items.length > 0) {
            return  this.items[0].getValue($option);
        }
        return "";
    };
    this.getIntValue = function ($option) {
        if (this.items.length > 0) {
            return  this.items[0].getIntValue($option);
        }
        return "";
    };
    this.addHtmlSection = function (tag, sectionclass) {

        var html = "";
        this.items.forEach(item => {
            html += item.getHtmlSection();
        });
        if (html !== "") {
            var content = document.createElement('div');
            content.setAttribute('class', 'walkitem ' + sectionclass);
            content.innerHTML = html;
            tag.appendChild(content);
        }

    };
    this.addSection = function (tag, sectionclass) {
        this.items.forEach(item => {
            var content = document.createElement('div');
            content.setAttribute('class', 'walkitem ' + sectionclass);
            item.getSection(tag, content);
            tag.appendChild(content);
        });
    };
    this.forEach = function (fcn) {
        this.items.forEach(item => {
            fcn(item);
        });
    };
};
ra.event.admin = function () {
    this.source = null;
    this.id = null;
    this.status = null;
    this.eventType = null;
    this.groupCode = '';
    this.groupName = '';
    this.dateUpdated = null;
    this.dateCreated = null;
    this.cancellationReason = null;
    this.nationalUrl = '';
    this.localPopupUrl = '';
    this.convertPHPAdmin = function (phpwalk) {
        var admin = phpwalk.admin;
        this.source = admin.source;
        this.id = admin.id;
        this.eventType = admin.type;
        this.groupCode = admin.groupCode;
        this.groupName = admin.groupName;
        this.status = admin.status;
        this.flagNew = admin.flagNew;
        this.flagUpdated = admin.flagUpdated;
        this.cancellationReason = admin.cancellationReason;
        this.dateUpdated = ra.date.getDateTime(admin.dateUpdated.date);
        this.dateCreated = ra.date.getDateTime(admin.dateCreated.date);
        this.nationalUrl = admin.nationalUrl;
        var url = new URL(window.location.href);
        var params = new URLSearchParams(url.search);
        params.delete("walkid");
        params.append("walkid", this.id);
        var link = new URL(`${url.origin}${url.pathname}?${params}`);
        this.localPopupUrl = link.href;
    };
    this.isCancelled = function () {
        return this.status.toLowerCase() === "cancelled";
    };
    this.filterUpdate = function () {
        return ra.date.periodInDays(new Date(), this.dateUpdated);
    };
    this.getValue = function ($option) {
        var BR = '<br/>';
        var out = "";
        switch ($option) {
            case "{group}":
                out = this.groupName;
                break;
            case "{eventType}":
                out = this.eventType;
                break;
        }
        return out;
    };
    this.getIntValue = function ($option) {
        console.log("Invalid internal request: " + $option);
        return "";
    };
};
ra.event.basics = function () {
    this.walkDate = null;
    this.finishDate = null;
    this.multiDate = false;
    this.title = null;
    this.description = '';
    this.descriptionHtml = '';
    this.additionalNotes = null;
    this.convertPHPBasics = function (phpwalk) {
        var basics = phpwalk.basics;
        this.title = basics.title;
        this.description = ra.convert_mails(basics.description);
        this.descriptionHtml = ra.convert_mails(basics.descriptionHtml);
        this.additionalNotes = ra.convert_mails(basics.additionalNotes);
        this.walkDate = ra.date.getDateTime(basics.walkDate.date);
        if (basics.finishDate !== null) {
            this.multiDate = basics.multiDate;
            this.finishDate = ra.date.getDateTime(basics.finishDate.date);
        }
    };
    this.getValue = function ($option) {
        var BR = '<br/>';
        var out = "";
        switch ($option) {
            case "{title}":
                out = "<b>" + this.title + "</b>";
                break;
            case "{description}":
                out = this.descriptionHtml;
                break;
            case "{additionalNotes}":
                out = this.additionalNotes;
                break;
            case "{finishTime}":
                if (this.finishDate !== null) {
                    out = ra.time.HHMMshort(this.finishDate);
                    if (this.multiDate) {
                        out = out + " " + ra.date.dowdd(this.finishDate);
                    }
                }
                break;
            case "{dowShortdd}":
                out = this.dateRange(ra.date.dowShortdd);
                break;
            case "{dowShortddmm}":
                out = this.dateRange(ra.date.dowShortddmm, true);
                //out = "<b>" + ra.date.dowShortddmm(this.walkDate) + this.addYear() + "</b>";
                break;
            case "{mapDowShortddmm}":
                // out = this.dateRange(ra.date.dowShortddmm, true);
                out = ra.date.dowShortddmm(this.walkDate) + this.addYear();
                break;
            case "{dowShortddyyyy}": // published in error
            case "{dowShortddmmyyyy}":
                out = this.dateRange(ra.date.dowShortddmmyyyy);
                //out = "<b>" + ra.date.dowShortddmmyyyy(this.walkDate) + "</b>";
                break;
            case "{dowdd}":
                out = this.dateRange(ra.date.dowdd);
                //out = "<b>" + ra.date.dowdd(this.walkDate) + "</b>";
                break;
            case "{dowddmm}":
                out = this.dateRange(ra.date.dowddmm, true);
                //out = "<b>" + ra.date.dowddmm(this.walkDate) + this.addYear() + "</b>";
                break;
            case "{dowddmmyyyy}":
                out = this.dateRange(ra.date.dowddmmyyyy);
                //out = "<b>" + ra.date.dowddmmyyyy(this.walkDate) + "</b>";
                break;
            default:
        }
        return out;
        ;
    };
    this.dateRange = function (func, addYear = false) {
        var out = func(this.walkDate);
        if (addYear) {
            out = out + this.addYear();
        }
        if (this.multiDate) {
            out = out + " - " + func(this.finishDate);
        }
        return  "<b>" + out + "</b>";
    };
    this.getIntValue = function ($option) {
        switch ($option) {
            case "dayofweek":
                return ra.date.dow(this.walkDate);
            case "displayMonth":
                return ra.date.month(this.walkDate) + this.addYear();
            case "filterDaysofweek":
                var dsow = [];
                dsow.push(ra.date.dow(this.walkDate));
                if (this.multiDate) {
                    var endDate = this.walkDate;
                    do {
                        endDate = ra.date.addDays(endDate, 1);
                        dsow.push(ra.date.dow(endDate));
                    } while (ra.date.YYYYMMDD(this.finishDate) > ra.date.YYYYMMDD(endDate));
                }
                return dsow;
            case "future":
                const today = new Date();
                const yesterday = new Date(today);
                yesterday.setDate(today.getDate() - 1);
                return this.walkDate > yesterday;
            case "bookingdate":
                return this.walkDate;
        }
        console.log("Invalid internal request: " + $option);
        return "";
    };
    this.addYear = function () {
        var d = new Date();
        var newDate = new Date(d.getTime() + 300 * 24 * 60 * 60000);
        var walkDate = ra.date.getDateTime(this.walkDate);
        if (walkDate.getTime() < newDate.getTime()) {
            return '';
        } else {
            return ' ' + ra.date.YYYY(walkDate);
        }
    };
};
ra.event.walk = function () {

    this.distanceKm = 0;
    this.distanceMiles = 0;
    this.nationalGrade = null;
    this.localGrade = '';
    this.shape = '';
    this.ascentMetres = null;
    this.ascentFeet = null;
    this.convertPHPWalk = function (phpwalk) {
        this.distanceKm = phpwalk.distanceKm;
        this.distanceMiles = phpwalk.distanceMiles;
        this.nationalGrade = new ra.event.nationalGrade(phpwalk.nationalGrade);
        this.localGrade = phpwalk.localGrade;
        this.shape = phpwalk.shape;
        this.ascentMetres = phpwalk.ascentMetres;
        this.ascentFeet = phpwalk.ascentFeet;
        return this;
    };
    this.setFilter = function (valueSet) {
        if (this.nationalGrade.toText() !== "Event") {
            valueSet.add("idShape", this.shape);
            valueSet.add("idGrade", this.nationalGrade.toText());
            valueSet.add("idDistance", this.filterDistance());
        }
    };
    this.getValue = function ($option) {
        var BR = '<br/>';
        var out = "";
        switch ($option) {
            case "{distance}":
                if (this.distanceMiles > 0) {
                    out = this.distanceMiles + "mi / " + this.distanceKm + "km";
                }
                break;
            case "{distanceMi}":
                if (this.distanceMiles > 0) {
                    out = this.distanceMiles + "mi";
                }
                break;
            case "{distanceKm}":
                if (this.distanceMiles > 0) {
                    out = this.distanceKm + "km";
                }
                break;
            case "{difficulty}":
                out = this.getValue("{distance}");
                out += "<br/><span class='pointer' onclick='javascript:ra.walk.dGH()' title='Click to see grading system'>" + this.nationalGrade.toText() + "</span>";
                if (this.localGrade !== "") {
                    out += BR + this.localGrade;
                }
                break;
            case "{difficulty+}":
                out = this.getValue("{distance}");
                out += BR + this.nationalGrade.disp("middle") + BR;
                out += "<span class='pointer' onclick='javascript:ra.walk.dGH()' title='Click to see grading system'>" + this.nationalGrade.toText() + "</span>";
                if (this.localGrade !== "") {
                    out += BR + this.localGrade;
                }
                break;
            case "{gradeimg}":
                out = this.nationalGrade.disp('details');
                break;
            case "{gradeimgRight}":
                out = this.nationalGrade.disp('right');
                break;
            case "{gradeimgMiddle}":
                out = this.nationalGrade.disp('middle');
                break;
            case "{grade}":
                out = "<span class='pointer " + this.nationalGrade.key() + "' onclick='javascript:ra.walk.dGH()' title='Click to see grading system'>" + this.nationalGrade.toText() + "</span>";
                if (this.localGrade !== "") {
                    out += BR + this.localGrade;
                }
                break;
            case "{grade+}":
                out = "";
                out += this.nationalGrade.disp("middle");
                out += "<span class='pointer " + this.nationalGrade.key() + "' onclick='javascript:ra.walk.dGH()' title='Click to see grading system'>" + this.nationalGrade.toText() + "</span>";
                if (this.localGrade !== "") {
                    out += BR + this.localGrade;
                }
                break;
            case "{nationalGrade}":
                out = "<span class='pointer' onclick='javascript:ra.walk.dGH()'>" + this.nationalGrade.toText() + "</span>";
                break;
            case "{nationalGradeAbbr}":
                out = "<span class='pointer' onclick='javascript:ra.walk.dGH()'>" + this.nationalGrade.abbr() + "</span>";
                break;
            case "{localGrade}":
                out = this.localGrade;
                break;
            case "{type}": // deprecated
            case "{shape}":
                out = this.shape;
                break;
            case "{ascent}":
                if (this.ascentMetres !== null) {
                    out = this.ascentMetres + 'm / ' + this.ascentFeet + 'ft';
                }
                break;
            case "{ascentMetres}":
                if (this.ascentMetres !== null) {
                    out = this.ascentMetres.toString();
                }
                break;
            case "{ascentFeet}":
                if (this.ascentMetres !== null) {
                    out = this.ascentFeet.toString();
                }
                break;
            case "{mapGrade}":
                out = this.nationalGrade.image() + "<br/>" + this.nationalGrade.toText();
                break;
        }
        return out;
    };
    this.getIntValue = function ($option) {

        switch ($option) {
            case "shape":
                return this.shape;
                break;
            case "_filterDistance":
                return  this.filterDistance();
            case "_nationalGradeCSS":
                return  this.nationalGrade.gradeCSS();
            case "_nationalGrade":
                return this.nationalGrade.toText();
            case "_icsWalkDuration":
                var dist = this.distanceMiles;
                return Math.ceil(dist / 2) * 60;
            case "_icsWalkGrade":
                if (this.localGrade !== "") {
                    return "Grade: " + this.localGrade + "/" + this.nationalGrade.toText() + "; <br/> ";
                } else {
                    return "Grade: " + this.nationalGrade.toText() + "; <br/> ";
                }
            case "_icsWalkDistance":
                if (this.distanceMiles > 0) {
                    return  ", " + this.distanceMiles + "mi/" + this.distanceKm + "km";
                }
                return "";
                break;
        }
        console.log("Invalid internal request: " + $option);
        return "";
    };
    this.filterDistance = function () {
        var distanceOrder = ra.events.filterOptions.distance.order;
        var dist = this.distanceMiles;
        switch (true) {
            case (dist === 0):
                return distanceOrder[0];
            case (dist <= 3):
                return distanceOrder[1];
            case (dist <= 5):
                return distanceOrder[2];
            case (dist <= 8):
                return distanceOrder[3];
            case (dist <= 10):
                return distanceOrder[4];
            case (dist <= 13):
                return distanceOrder[5];
            case (dist <= 15):
                return distanceOrder[6];
            default:
                return distanceOrder[7];
        }
    };
    this.getHtmlSection = function () {
        if (this.nationalGrade.toText() === "Event") {
            return ""; // ignore dummy walk
        }
        var $html;
        var shape = this.getValue("{shape}");
        if (shape !== "") {
            $html = "<b>" + shape + " Walk</b>";
        } else {
            $html = "<b>Walk</b>";
        }

        if (this.distanceMiles > 0) {
            $html += ra.html.addDiv("distance", "<b>Distance</b>: " + this.distanceMiles + "mi / " + this.distanceKm + "km");
        }
        $html += ra.html.addDiv("nationalgrade", "<b>National Grade</b>: " + this.nationalGrade.toText() + "  " + this.nationalGrade.disp('popup'));
        if (this.localGrade !== "") {
            $link = this.localGrade;
            $html += ra.html.addDiv("localgrade", "<b>Local Grade</b>: " + $link);
        }
        if (this.ascentMetres !== null) {
            var ascent = this.ascentMetres + 'm / ' + this.ascentFeet + 'ft'
            $html += ra.html.addDiv("ascent", "<b>Ascent</b>: " + ascent);
        }

        return $html;
    };
};
ra.event.timelocation = function () {
    this.type = "";
    this.description = '';
    this.time = '';
    this.timeHHMM = '';
    this.timeHHMMshort = '';
    this.gridref = '';
    this.latitude = 0;
    this.longitude = 0;
    this.postcode = null;
    this.osmaps = null;
    this.pctagid = "";
    this.isCancelled = false;
    this.convertPHPLocation = function (location, isCancelled) {
        this.type = location.type;
        if (location.time !== null) {
            this.time = ra.date.getDateTime(location.time.date);
            this.timeHHMM = location.timeHHMM;
            this.timeHHMMshort = location.timeHHMMshort;
        } else {
            //location.time = null;
        }
        this.description = location.description;
        this.gridref = location.gridref;
        this.latitude = location.latitude;
        this.longitude = location.longitude;
        this.w3w = location.w3w;
        if (location.postcode !== null) {
            this.postcode = new ra.event.postcode;
            var pc = {text: location.postcode,
                longitude: 0,
                latitude: 0,
                direction: ""};
            this.postcode.convertPHPPostcode(pc);
        }
        this.osmaps = location.osmaps;
        this.isCancelled = isCancelled;
        this.pctagid = "pc_" + Math.random().toString(36).slice(-10); // should be unique
        return this;
    };
    this.getValue = function ($option) {
        var out = "";
        switch ($option) {
            case "{}":
                out = this.timeHHMMshort;
                if (this.description) {
                    out += " at " + this.description;
                }
                break;
            case "{Time}":
                out = this.timeHHMMshort;
                break;
            case "{Place}":
                out = this.description;
                break;
            case "{GR}":
                out = this.gridref;
                break;
            case "{PC}":
                if (this.postcode !== null) {
                    out = this.postcode.text;
                }
                break;
            case "{OLC}":
                break;
            case "{w3w}":
                out = this.w3w;
                break;
            case "{Directions}":
                var $lat = this.latitude;
                var $long = this.longitude;
                out = ra.loc.directionsSpan($lat, $long);
                break;
            default:
                out = "";
        }
        return out;
    };
    this.getIntValue = function ($option) {
        switch ($option) {
            case "_latitude":
                return this.latitude;
            case "_longitude":
                return this.longitude;
            case "_icsDescription":
                return this.getICSTextDescription();
            case "_icsTime":
                return this.time;
            case "_type":
                return this.type;
            case "bookingtime":
                return this.time;
        }
        console.log("Invalid internal request: " + $option);
        return "";
    };
    this.getICSTextDescription = function () {
        var $textdescription = "";
        switch (this.type) {
            case "Meeting":
                $textdescription = "Meet: ";
                break;
            case "Start":
                $textdescription = "Start: ";
                break;
            case "Rough":
                $textdescription = "Walking area: ";
                break;
            case "Location":
                $textdescription = "Location: ";
                break;
            case "Finish":
                $textdescription = "Finish: ";
                break;
            default:
                ra.showError("Error 0003");
        }
        if (this.type !== "Rough") {
            if (this.timeHHMMshort !== "") {
                $textdescription += this.timeHHMMshort + " @ ";
            }
        }
        var $place = this.gridref;
        if (this.postcode !== null) {
            $place += ", " + this.postcode.text;
        }
        if (this.description !== '') {
            $textdescription += this.description + ' (' + $place + ')';
        } else {
            $textdescription += $place;
        }
        return $textdescription;
    };
    this.getSection = function (contentTag, tag) {
        var display = {title: "Unknown",
            notes: "",
            timeTitle: "<b>Time</b>: "};
        switch (this.type) {
            case "Meeting":
                display.title = "Meeting place";
                display.notes = "Walkers will travel together from the meeting place to the start of the walk, this may be by car, coach or public transport. Meeting times are often when the group will set off, rather than when you should arrive at the meeting place.";
                break;
            case "Start":
                display.title = "Start place";
                display.notes = "Start time is when the walk commences. Please arrive earlier to ensure you are ready for the start";
                break;
            case "Rough":
                display.title = "<b>No start place - Rough location only</b>";
                display.notes = "Location shown is an indication of where the walk will be and <b>NOT</b> the start place: Contact group if you wish to meet at the start";
                break;
            case "Location":
                display.title = "Location";
                break;
            case "Finish":
                display.title = "Finish";
                display.timeTitle = "<b>Estimated Time</b>: ";
                break;
            default:
                ra.showError("Error 0004");
        }

        var cancelled = "";
        var div = document.createElement('div');
        div.classList.add("place");
        tag.appendChild(div);
        if (this.isCancelled) {
            cancelled = "CANCELLED: ";
        }
        //  div.classList.add("notexact");
        div.innerHTML = "<b>" + cancelled + display.title + "</b>: " + this.description;
        if (this.timeHHMMshort !== "") {
            var time = document.createElement('div');
            time.innerHTML = display.timeTitle + this.timeHHMMshort;
            tag.appendChild(time);
        }
        var gr = document.createElement('div');
        tag.appendChild(gr);
        gr.innerHTML = "<b>Grid Ref</b>: " + this.gridref;
        if (!this.isCancelled) {
            this._displayPostcode(tag);
            var w3w = document.createElement('div');
            tag.appendChild(w3w);
            w3w.innerHTML = ra.w3w.toText(this.w3w, "");
            var a = document.createElement('a');
            tag.appendChild(a);
            a.classList.add("mappopup");
            var $loc = "javascript:ra.loc.directions([lat],[long])";
            $loc = $loc.replace("[lat]", this.latitude);
            $loc = $loc.replace("[long]", this.longitude);
            a.title = "You may need to adjust your 'Location' if you are not using a mobile device";
            a.setAttribute("href", $loc);
            a.textContent = "[Google Directions]";
            if (this.type !== "rough") {
                this._addLocationExtras(contentTag, tag);
            }
        }
        if (display.notes !== "") {
            var notes = document.createElement('div');
            tag.appendChild(notes);
            notes.innerHTML = display.notes;
            notes.classList.add("location", "notes");
        }
    };
    this._addLocationExtras = function (contentTag, tag) {
        var button = document.createElement('a');
        button.classList.add("mappopup");
        button.classList.add("pointer");
        button.title = "Click to see/hide additional location information";
        tag.appendChild(button);
        button.textContent = "[Map Info+]";
        var extras = document.createElement('div');
        extras.style.display = "none";
        extras.classList.add("mappopup");
        extras.classList.add("extra");
        tag.appendChild(extras);
        this._displayExtras(extras);
        button.addEventListener("click", e => {
            var element = e.target;
            if (element.textContent === "[Map Info+]") {
                element.textContent = "[Map Info-]";
                extras.style.display = "";
                if (this.osmaps === null) {
                    ra.map.os.getOSMapsAtLoc(this.latitude, this.longitude, result => {
                        if (result.error) {
                            this.osmaps = [];
                        } else {
                            this.osmaps = result.maps;
                        }
                        this._displayOsMaps(contentTag, this.osmaps);
                        this._displayExtras(extras);
                    });
                } else {
                    this._displayOsMaps(contentTag, this.osmaps);
                }
            } else {
                element.textContent = "[Map Info+]";
                extras.style.display = "none";
                this._displayOsMaps(contentTag, []);
            }
        });
    };
    this._displayOsMaps = function (tag, items) {
        let event = new Event("display-os-map", {bubbles: false});
        event.items = items;
        tag.dispatchEvent(event);
    };
    this._displayExtras = function (tag) {
        tag.innerHTML = ""; // cleat current display
        var ll = document.createElement('div');
        tag.appendChild(ll);
        ll.innerHTML = "<b>Latitude</b>: " + this.latitude.toFixed(5) + " , <b>Longitude</b>: " + this.longitude.toFixed(5);
        ll.classList.add("latlong");
        var section = document.createElement('div');
        tag.appendChild(section);
        var head = document.createElement('div');
        head.style.fontWeight = 700;
        head.textContent = "OS paper maps";
        tag.appendChild(head);
        var info = document.createElement('h4');
        tag.appendChild(info);
        if (this.osmaps === null) {
            info.innerHTML = "Retrieving map information ...";
            return;
        }
        if (this.osmaps.length === 0) {
            info.innerHTML = "No map information available";
        } else {
            var out = "<ul class='mappopup osmaps' >";
            this.osmaps.forEach(item => {
                out += "<li>" + item.type + " " + item.number + ": " + item.title + " (1:" + item.scale + ")</li>";
            });
            out += "</ul>";
            info.innerHTML = out + "<div class='location notes'>Note: all OS map information is unofficial, please check before using this information.</div>";
        }
    };
    this._addLocationMarker = function (layer, map) {
        var icon = ra.map.icon.markerRoute();
        var popup, title;
        var popupoffset = [0, -30];
        switch (this.type) {
            case "Meeting":
                popup = "<b>Meeting place</b><br/>" + this.timeHHMMshort + " " + this.description;
                popup += "<br/>" + this.gridref;
                icon = ra.map.icon.markerRoute();
                title = 'Meeting place';
                popupoffset = [0, -30];
                break;
            case "Start":
                popup = "<b>Walk start</b><br/>" + this.timeHHMMshort + " " + this.description;
                popup += "<br/>" + this.gridref;
                icon = ra.map.icon.markerStart();
                title = 'Start of walk';
                popupoffset = [0, -10];
                break;
            case "Rough":
                popup = "<b>General area for walk only</b>";
                popup += "<br/><b>Contact group</b> if you wish to join the walk at the start";
                icon = ra.map.icon.markerArea();
                title = 'General area of walk';
                popupoffset = [0, -10];
                break;
            case "Location":
                popup = "<b>Location</b><br/>" + this.timeHHMMshort + " " + this.description;
                popup += "<br/>" + this.gridref;
                icon = ra.map.icon.markerRoute();
                title = 'Location of event';
                popupoffset = [0, -10];
                break;
            case "Finish":
                popup = "<b>Walk Finish</b><br/>" + this.description;
                icon = ra.map.icon.markerFinish();
                title = 'End of walk';
                popupoffset = [0, -10];
                break;
            default:
                ra.showError("Error 0001");
        }

        var marker = L.marker([this.latitude, this.longitude], {icon: icon, title: title, riseOnHover: true}).addTo(layer);
        marker.bindPopup(popup, {offset: popupoffset, autoClose: false}).closePopup();
        var openPopups = true;
        map.on("mouseover", function (event) {
            if (openPopups) {
                marker.openPopup();
            }
            openPopups = false;
        });
    };
    this._addPostcodeMarker = function (layer) {

        if (this.postcode === null) {
            return;
        }
        if (this.postcode.text === "") {
            return;
        }
        var _this = this;
        setTimeout(function () {
            var tag = ra.html.getTag(_this.pctagid);
            if (tag !== null) {
                tag.addEventListener("postcode-data-retrieved", function (e) {
                    var pc = _this.postcode;
                    if (e.raData.error !== null) {
                        pc.latitude = 0;
                        pc.longitude = 0;
                        return;
                    }
                    var latlng = e.raData.latlng;
                    if (latlng === null) {
                        pc.latitude = 0;
                        pc.longitude = 0;
                    } else {
                        pc.latitude = latlng.lat;
                        pc.longitude = latlng.lon;
                    }
                    pc.distance = ra.geom.distance(pc.latitude, pc.longitude, _this.latitude, _this.longitude) * 1000;
                    pc.direction = ra.geom.direction(pc.latitude, pc.longitude, _this.latitude, _this.longitude);
                    tag.innerHTML = pc.toText();
                    if (_this.postcode.distance < 10000) {
                        var pcpop = "<b>" + _this.postcode.text + "</b>";
                        pcpop += "<br/>" + _this.type + " location is " + _this.postcode.distance.toFixed() + " metres to the " + _this.postcode.direction.name;
                        var pcIcon = ra.map.icon.postcode();
                        var marker = L.marker([_this.postcode.latitude, _this.postcode.longitude], {icon: pcIcon, riseOnHover: true}).addTo(layer);
                        marker.bindPopup(pcpop).closePopup();
                    }

                });
                _this.postcode.getPostcode(tag);
            }
        }, 200);
    };
    this._displayPostcode = function (tag) {
        if (this.postcode !== null) {
            if (this.postcode.text !== "") {
                var pc = document.createElement('span');
                tag.appendChild(pc);
                pc.innerHTML = this.postcode.toText();
                pc.setAttribute('id', this.pctagid);
            }

        }
    };
    this.addWalkMarker = function (cluster, walkClass, id, isEvent, isCancelled, summary, link, grade, title) {
        var $long, $lat, $icon, $class;
        var $popup;
        $long = this.longitude;
        $lat = this.latitude;
        if (this.type !== "Rough") {
            $icon = ra.map.icon.markerStart();
        } else {
            $icon = ra.map.icon.markerArea();
        }
        if (isCancelled) {
            $icon = ra.map.icon.markerCancelled();
        }
        if (isEvent) {
            $icon = ra.map.icon.event();
        }
        $popup = document.createElement('div');
        var summaryDiv = document.createElement('div');
        summaryDiv.setAttribute('class', 'pointer');
        summaryDiv.innerHTML = summary;
        summaryDiv.addEventListener("click", function (e) {
            ra.walk.displayWalkID(e, id);
        });
        var linkDiv = document.createElement('div');
        linkDiv.innerHTML = link;
        var gradeDiv = document.createElement('div');
        gradeDiv.setAttribute('class', 'pointer');
        gradeDiv.style.float = "right";
        gradeDiv.innerHTML = grade;
        gradeDiv.addEventListener("click", function (e) {
            ra.walk.dGH();
        });
        $popup.appendChild(gradeDiv);
        $popup.appendChild(summaryDiv);
        $popup.appendChild(linkDiv);
        var $class = walkClass + "Published";
        if (this.isCancelled) {
            $class = walkClass + "Cancelled";
        }
        cluster.addMarker($popup, $lat, $long, {icon: $icon, title: title, riseOnHover: true});
        return;
    };
};
ra.event.contact = function (id) {
    this.id = id;
    this.isLeader = false;
    this.contactName = '';
    this.contactForm = null;
    this.telephone1 = '';
    this.telephone2 = '';
    this.convertPHPContact = function (phpcontact) {
        this.isLeader = phpcontact.isLeader;
        this.contactName = phpcontact.contactName;
        this.contactForm = phpcontact.contactForm;
        this.telephone1 = phpcontact.telephone1;
        this.telephone2 = phpcontact.telephone2;
        return this;
    };
    this.getValue = function ($option) {
        var BR = '<br/>';
        var out = "";
        switch ($option) {
            case "{contact}":
                var $titlePrefix = '';
                out = "";
                if (this.isLeader) {
                    $titlePrefix = "Leader ";
                } else {
                    $titlePrefix = "Contact ";
                }
                if (this.contactName !== "") {
                    out = $titlePrefix + " <b>" + this.contactName + "</b>" + BR;
                }
                if (this.contactForm !== null) {
                    out += this.getValue("{emaillink}") + BR;
                }
                if (this.telephone1 !== "") {
                    out += this.telephone1 + BR;
                }
                if (this.telephone2 !== "") {
                    out += this.telephone2 + BR;
                }
                break;
            case "{contactname}":
                if (this.contactName !== '') {
                    if (this.isLeader) {
                        out = "Leader ";
                    } else {
                        out = "Contact ";
                    }
                    out += "<b>" + this.contactName + "</b>";
                }
                break;
            case "{contactperson}":
                out = this.contactName;
                break;
            case "{telephone}":
            case "{telephone1}":
                out = this.telephone1;
                break;
            case "{telephone2}":
                out = this.telephone2;
                break;
            case "{email}":
            case "{emaillink}":
                if (this.contactForm !== null) {
                    out = "<span><b>Contact link: </b><a target='_blank' href='" + this.contactForm + "' title='Click to send an email to leader/contact or group'>Email contact</a></span>";
                }
                break;
        }
        return out;
    };
    this.getIntValue = function ($option) {
        switch ($option) {
            case "contactName":
                return this.contactName;
            case "key":
                return this.key;
            case "_icsrecord":
                var out = "";
                if (this.contactName !== "") {
                    out = "Contact: " + this.contactName;
                }
                if (this.telephone1 !== "") {
                    out += ", " + this.telephone1;
                }
                if (this.telephone2 !== "") {
                    out += ", " + this.telephone2;
                }
                return out + "<br/>";
                break;
        }
        console.log("Invalid internal request: " + $option);
        return "";
    };
    this.getHtmlSection = function () {
        var $name;
        var $html = "";
        if (this.isLeader === false) {
            $html += "<b>Contact Details: </b>";
        } else {
            $html += "<b>Contact Leader: </b>";
        }
        $name = this.contactName !== "" ? "<b>Name</b>: " + this.contactName : "";
        $html += ra.html.addDiv("contactname", $name);
        var anyContact = false;
        if (this.telephone1 !== "") {
            anyContact = true;
            var $text = "<b>Telephone</b>: " + this.telephone1;
            if (this.telephone2 !== "") {
                $text += ", " + this.telephone2;
            }
            $html += ra.html.addDiv("telephone", $text);
        }

        if (this.contactForm !== null) {
            anyContact = true;
            $html += this.getValue("{emaillink}");
        }
        if (!anyContact) {
            $html += ra.html.addDiv("contactname", "<b>No contact details available</b>");
        }

        return $html;
    };
};
ra.event.flags = function () {
    this._flags = [];
    this.addFlags = function (flags) {
//        if (flags.length === 0) {
//            var newflag = {section: 'Uncategorised',
//                name: 'Uncategorised'};
//            this._flags.push(newflag);
//        }
        flags.forEach(flag => {
            var newflag = {section: flag.section,
                name: flag.name};
            this._flags.push(newflag);
        });
    };
    this.getFlags = function () {
        return this._flags;
    };
    this.addFlagsSection = function (tag) {
        if (this._flags.length === 0) {
            return;
        }
        var lastSection = '';
        var content = document.createElement('div');
        content.setAttribute('class', 'walkitem flags');
        var $html = "";
        this._flags.forEach(flag => {
            if (lastSection !== flag.section) {
                if (lastSection !== '') {
                    $html += "</ul>";
                }
                $html += "<b>" + flag.section + "</b>";
                lastSection = flag.section;
                $html += "<ul>";
            }
            $html += "<li class='item'>" + flag.name + "</li>";
        });
        if ($html !== "") {
            $html += "</ul>";
        }
        content.innerHTML = $html;
        tag.appendChild(content);
    };
};
ra.event.bookings = function (id, event) {
    this.id = id;
    let myEvent = event; // not this.event to stop circular reference
    this.enabled = false;
    this.convertPHPBookings = function (phpwalk) {
        var bookings = phpwalk.bookings;
        if (bookings !== null) {
            this.enabled = bookings.enabled;
        }
    };
    this.addBookingsSection = function (tag) {
        if (this.enabled === false) {
            return;
        }
        var file = new ra.ics.file();
        myEvent.addWalktoIcs(file);
        var ics = file.getFile();
        var bookings = new ra.bookings(tag, this.id, myEvent, ics);
        bookings.initialise();
    };
};
ra.event.general = function () {
    this.general = [];
    this.addSection = function (tag) {
        var div = document.createElement('div');
        div.setAttribute('class', 'walkitem transport');
        tag.appendChild(div);
        var content = document.createElement("details");
        div.appendChild(content);
        var summary = document.createElement("summary");
        summary.setAttribute('class', 'pointer');
        summary.innerHTML = "Public Transport info";
        content.appendChild(summary);
        var ol = document.createElement("ul");
        content.appendChild(ol);
        var li = document.createElement("li");
        ol.appendChild(li);
        var bus = document.createElement("a");
        bus.setAttribute("href", "https://bustimes.org/");
        bus.setAttribute("target", "_blank");
        bus.textContent = "Bustimes.org";
        li.appendChild(bus);
        li = document.createElement("li");
        ol.appendChild(li);
        bus = document.createElement("a");
        bus.setAttribute("href", "https://www.traveline.info/");
        bus.setAttribute("target", "_blank");
        bus.textContent = "Traveline.info";
        li.appendChild(bus);
    };
};
ra.event.media = function () {
    this.items = [];
    this.addPHPMedia = function (values) {
        values.forEach(item => {
            var newmedia = new ra.event.mediaItem();
            newmedia.convertPHPMedia(item);
            this.items.push(newmedia);
        });
    };
    this.getValue = function ($option) {
        var out = "";
        if (this.items.length > 0) {
            out = this.items[0].getValue($option);
        }
        return out;
    };
    this.addMediaSection = function (content) {
        if (this.items.length > 0) {
            var div = document.createElement('div');
            div.classList.add("walkitem");
            div.classList.add("media");
            // Shared image gallery lightbox (see media/js/ra.lightbox.js) -
            // its delegated click handler picks up any .ra-image-grid-item
            // link inside a .ra-image-grid container, so no per-thumbnail
            // click wiring is needed here any more (that used to build a
            // ra.modals + ra.previousNext popup by hand).
            div.classList.add("ra-image-grid");
            content.appendChild(div);
        }

        this.items.forEach(item => {
            item.addThumbnail(div);
        });
    };
};
ra.event.mediaItem = function () {
    this.alt = "";
    this.thumb = "";
    this.medium = "";
    this.convertPHPMedia = function (phpmedia) {
        this.alt = phpmedia.alt;
        this.thumb = phpmedia.thumb;
        this.medium = phpmedia.medium;
        return this;
    };
    this.getValue = function ($option) {
        var out = "";
        switch ($option) {
            case "{thumbr}":
                out = "<img class='mediathumbr' src='" + this.thumb + "' alt='" + this.alt + "'>";
                break;
            case "{thumbl}":
                out = "<img class='mediathumbl' src='" + this.thumb + "' alt='" + this.alt + "'>";
                break;
        }
        return out;
    };
    this.addThumbnail = function (tag) {
        var div = document.createElement('div');
        div.classList.add("walk-image");
        tag.appendChild(div);
        var div2 = document.createElement('div');
        div2.classList.add("mediapopup");
        div.appendChild(div2);
        // The clickable link the shared lightbox (media/js/ra.lightbox.js)
        // looks for: href is the image it opens full-size (the "medium"
        // resolution - there's no larger version available from the Walks
        // Manager feed), title is its caption. The visible inline thumbnail
        // stays the small "thumb" image, loaded first, same as before.
        var link = document.createElement('a');
        link.classList.add("ra-image-grid-item");
        link.setAttribute("href", this.medium);
        link.setAttribute("title", this.alt);
        div2.appendChild(link);
        var img = document.createElement('img');
        img.classList.add("walkmedia");
        img.setAttribute("alt", this.alt);
        img.setAttribute("title", this.alt);
        img.setAttribute("src", this.thumb);
        link.appendChild(img);
        return div;
    };
};
ra.event.nationalGrade = function (grade) {
    this.gradekey = "Event";
    this._grade = grade;
    var valid = ["Event", "Easy Access", "Easy", "Leisurely", "Moderate", "Strenuous", "Technical"];
    if (valid.includes(grade)) {
        this.gradekey = grade.replace(" ", "_");
    } else {
        ra.showError("Error: invalid walks grade found");
    }
    this.toText = function () {
        return this._grade;
    };
    this.key = function () {
        return this.gradekey;
    };
    this.gradeCSS = function () {
        var results = {
            Event: "grade-event",
            Easy_Access: "grade-ea",
            Easy: "grade-e",
            Leisurely: "grade-l",
            Moderate: "grade-m",
            Strenuous: "grade-s",
            Technical: "grade-t"
        };
        return results[this.gradekey];
    };
    this.abbr = function () {
        // get abbreviation from National Grade
        var results = {
            Event: "Event",
            Easy_Access: "EA",
            Easy: "E",
            Leisurely: "L",
            Moderate: "M",
            Strenuous: "S",
            Technical: "T"
        };
        return results[this.gradekey];
    };
    // get associated colour for grade
    this.colour = function () {
        var results = {
            Event: "#A0A0A0",
            Easy_Access: "#ADADAD",
            Easy: "#9BC8AB",
            Leisurely: "#F6B09D",
            Moderate: "#F08050",
            Strenuous: "#F9B104",
            Technical: "#404141"
        };
        return results[this.gradekey];
    };
    this.image = function () {
        var images = {
            Event: "event.png",
            Easy_Access: "ea.png",
            Easy: "e.png",
            Leisurely: "l.png",
            Moderate: "m.png",
            Strenuous: "s.png",
            Technical: "t.png"
        };
        var image = images[this.gradekey];
        var $url = ra.baseDirectory() + "media/com_ra_library/images/grades/";
        $url = "<img src='" + $url + image + "' alt='" + this._grade + "' height='30' width='30'>";
        return $url;
    };
    this.disp = function ($class) {
        var $tag = "";
        var $img = this.image();
        var dataDescr = this.gradekey.replace("_", " ");
        $tag = "<span data-descr='" + dataDescr + "' class='grade " + $class + "' onclick='javascript:ra.walk.dGH()' title='Click to see grading system'>" + $img + "</span>";
        return $tag;
    };
};
ra.event.postcode = function () {
    this.text = "";
    this.latitude = 0;
    this.longitude = 0;
    this.direction = "North";
    this.distance = 9999999;
    this.convertPHPPostcode = function (phpPostcode) {
        this.text = phpPostcode.text;
        this.latitude = phpPostcode.latitude;
        this.longitude = phpPostcode.longitude;
        this.direction = phpPostcode.direction;
        return this;
    };
    this.toText = function () {
        var $pc, $note2, $note, $distclass, $out;
        $distclass = "distfar";
        if (this.distance <= 100) {
            $note = this.text;
            $note2 = "Postcode is within 100m of location";
            $distclass = "distclose";
        } else {
            if (this.distance > 10000) {
                $note = this.text;
                $note2 = 'Postcode distance from location not known';
            } else {
                $note = "Location is " + this.distance.toFixed() + " metres to the " + this.direction.name + " of " + this.text;
                $note2 = "Check postcode suitablility on map";
                if (this.distance < 500) {
                    $distclass = "distnear";
                }
            }
        }
        if (this.latitude === 0 && this.longitude === 0) {
            $distclass = "distnotfound";
            $note = this.text;
            $note2 = "Postcode location not found";
        }
        $pc = "<b>Postcode</b>: <abbr title='" + $note2 + "'>" + $note + "</abbr>";
        $out = ra.html.addDiv("postcode " + $distclass, $pc);
        return $out;
    };
    this.getPostcode = function (tag) {
        var postcodeurl = "https://postcodes.theramblers.org.uk/?postcode=";
        var url = postcodeurl + this.text;
        ra.ajax.getJSON(url, function (err, items) {
            let event = new Event("postcode-data-retrieved", {bubbles: false});
            event.raData = {};
            event.raData.error = err;
            event.raData.latlng = null;
            if (items.length === 1) {
                var item = items[0];
                var pt = new OsGridRef(item.Easting, item.Northing);
                var latlng = OsGridRef.osGridToLatLon(pt);
                event.raData.latlng = latlng;
            }
            tag.dispatchEvent(event);
        });
    };
};
// *****************************************************************************
// *****************************************************************************
// *****************************************************************************
// *****************************************************************************
// *****************************************************************************
// *****************************************************************************



// static functions used as links from HTML
ra.walk = ra.walk || (function () {
    var my = {};
    my.DisplayWalkFunction = "ra.walk.displayWalkID";
    my._startup = true;
    // true once registerPHPWalks has run at least once
    my._registered = false;
    // clicks made before walks were registered; replayed once registration is done
    my._waiting = [];
    my.walks = new ra.events();
    my.registerEvent = function (newEvent) {
        my.walks.registerEvent(newEvent);
    };
    // command to store all walks from PHP display options
    my.registerPHPWalks = function (mapOptions, data) {
        // stores walks for php walks displays
        var phpwalks = null;
        phpwalks = data.walks;
        try {
            if (phpwalks !== null) {
                data.walks.forEach(phpwalk => {
                    var newEvent = new ra.event();
                    newEvent.convertPHPWalk(phpwalk);
                    my.walks.registerEvent(newEvent);
                });
                data.walks = null;
                my.displayUrlWalkPopup();
            }
        } finally {
            my._registered = true;
            // replay early clicks after the whole load event has finished, so walks
            // from every registerPHPWalks call on the page are registered first
            setTimeout(function () {
                my._waiting.splice(0).forEach(function (fn) {
                    fn();
                });
            }, 0);
        }
        this.load = function () {
        };
    };
    my.displayUrlWalkPopup = function () {
        if (my._startup) {
            my._startup = false;
            setTimeout(function () {
                var search = window.location.search;
                const urlParams = new URLSearchParams(search);
                if (urlParams.has('walkid')) {
                    var walkid = urlParams.getAll('walkid')[0];
                    my.displayWalkID(new Event("dummy"), walkid);
                }
            }, 1500);
        }
    };
    my.getEventID = function (id) {
        return my.walks.getEvent(id);
    };
    // accessed by HTML links to display walk
    my.displayWalkID = function (event, id) {
        var walk = my.walks.getEvent(id);
        if (walk !== null) {
            walk.displayInModal(event);
        } else if (!my._registered) {
            // page not fully loaded yet - run when walks have been registered
            my._waiting.push(function () {
                my.displayWalkID(event, id);
            });
        } else {
            ra.showMsg('SORRY unable to display specified walk/event.');
        }
    };
    // static option to display grades popup
    // used in html links from PHP and js
    my.dGH = function () {
        var $url;
        $url = ra.baseDirectory() + "media/lib_ramblers/pages/grades.html";
        var marker;
        ra.ajax.postUrl($url, "", marker, my._displayGradesModal);
    };
    // display popup showing grading system
    my._displayGradesModal = function (marker, $html) {
        $html = $html.replace(/basedirectory/g, ra.baseDirectory());
        ra.modals.createModal($html);
    };
    my.toggleDisplay = function (e, id) {
        var tag = e.currentTarget;
        tag.classList.toggle("active");
        if (!tag.className.includes('active')) {
            tag.nextElementSibling.remove();
        } else {
            var walk = my.walks.getEvent(id);
            if (walk !== null) {
                walk.walkDetailsDisplay(tag);
            } else {
                ra.showError("Walk not found - program error, please report issue to the webmaster");
            }
        }
    };
    return my;
}
());
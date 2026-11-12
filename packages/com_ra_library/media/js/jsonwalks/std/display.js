/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
var ra, FullCalendar, displayGradesRowClass, displayTableRowClass, displayListRowClass;

if (typeof (ra) === "undefined") {
    ra = {};
}
if (typeof (ra.display) === "undefined") {
    ra.display = {};
}
ra.display.walksTabs = function (mapOptions, data) {
    this.events = new ra.events();
    this.map = null;
    this.lmap = null;
    this.cluster = null;
    this.elements = null;
    this.settings = {
        walkClass: "walk",
        displayClass: "detailsView",
        tabOrder: ["Grades", "Table", "List", "Calendar", "Map"],
        tableFormat: [{"title": "Date", "items": "{dowddmm}"},
            {"title": "Meet", "items": "{meet}{, ?meetGR}{, ?meetPC} "},
            {"title": "Start", "items": "{start}{, ?startGR}{, ?startPC}"},
            {"title": "Title", "items": "{title}{mediathumbr}"},
            {"title": "Difficulty", "items": "{difficulty+}"},
            {"title": "Contact", "items": "{contact}"}],
        listFormat: "{dowdd}{, ?meet}{, ?start}{, ?title}{, ?distance}{, ?contactname}{, ?telephone} ",
        gradesFormat: "{gradeimg}{dowddmm}{, ?title}{, ?distance}{, ?contactname}",
        calendarFormat: "{gradeimgMiddle}{title}{, ?distance}, {meetTime}{ or ?meetTime}{startTime} ",
        withMonth: ["dowShortddmm", "dowddmm", "dowddmmyyyy", "datebadge", "datebadgedow"],
        itemsPerPage: 20,
        options: null,
        displayBookingsTable: false
    };

    this.listTemplate = {};
    this.gradesTemplate = {};
    this.calendarTemplate = {};

    this.paginationOptions = {pagination: {
            "10 per page": 10,
            "20 per page": 20,
            "25 per page": 25,
            "50 per page": 50,
            "100 per page": 100,
            "View all": 0
        },
        itemsPerPage: 25,
        currentPage: 1
    };
    this.optionTag = {};
    this.printContent = null;
    this.mapOptions = mapOptions;
    if (data.customTabOrder !== null) {
        this.settings.tabOrder = data.customTabOrder;
    }
    if (data.customGradesFormat !== null) {
        this.settings.gradesFormat = data.customGradesFormat;
    }
    if (data.customListFormat !== null) {
        this.settings.listFormat = data.customListFormat;
    }
    if (data.customTableFormat !== null) {
        this.settings.tableFormat = data.customTableFormat;
    }
    if (data.customCalendarFormat !== null) {
        this.settings.calendarFormat = data.customCalendarFormat;
    }

    if (data.displayClass !== "") {
        this.settings.displayClass = data.displayClass;
    }

    this.settings.displayBookingsTable = data.displayBookingsTable;

    //  set up simpleTemplate for each layout format
    this.gradesTemplate.template = new ra.SimpleTemplate(this.settings.gradesFormat);
    this.gradesTemplate.fields = this.gradesTemplate.template.getFields();

    this.listTemplate.template = new ra.SimpleTemplate(this.settings.listFormat);
    this.listTemplate.fields = this.listTemplate.template.getFields();

    this.calendarTemplate.template = new ra.SimpleTemplate(this.settings.calendarFormat);
    this.calendarTemplate.fields = this.calendarTemplate.template.getFields();

    this.settings.tableFormat.forEach((col) => {
        var columnTemplate = {};
        columnTemplate.template = new ra.SimpleTemplate(col.items);
        columnTemplate.fields = columnTemplate.template.getFields();
        col.template = columnTemplate;
    });

    data.walks.forEach(phpwalk => {
        var newEvent = new ra.event();
        newEvent.convertPHPWalk(phpwalk);
        this.events.registerEvent(newEvent);
        ra.walk.registerEvent(newEvent);
    });
    data.walks = null;
    ra.walk.displayUrlWalkPopup();
    this.load = function () {

        var tags = [
            {name: 'walksFilter', parent: 'root', tag: 'div', attrs: {class: 'walksFilter'}},
            {name: 'clear', parent: 'root', tag: 'div', attrs: {class: 'clear'}},
            {name: 'tabs', parent: 'root', tag: 'div'},
            {name: 'bookings', parent: 'root', tag: 'div'}
        ];
        this.masterdiv = document.getElementById(this.mapOptions.divId);
        this.elements = ra.html.generateTags(this.masterdiv, tags);
        this.events.setAllWalks();
        this.events.setFilters(this.elements.walksFilter);
        var _this = this;
        this.addTabs();
        if (this.settings.displayBookingsTable) {
            var book = new ra.bookings.displayEvents();
            book.display(this.elements.bookings);
        }
        document.addEventListener("reDisplayWalks", function () {
            _this.events.setDisplayFilter();
            _this.displayWalks();
        });

    };
    this.addTabs = function () {
        var _this = this;
        var options = {tabClass: 'walksDisplay',
            tabs: {}};
        this.settings.tabOrder.forEach(function (value, index, array) {
            options.tabs[value] = {title: value};
        });
        this.elements.tabs.addEventListener("displayTabContents", function (e) {
            _this.option = e.tabDisplay.tab;
            _this.displayTag = e.tabDisplay.displayInElement;
            _this.displayWalks();
        });
        var tabs = new ra.tabs(this.elements.tabs, options);
        tabs.display();
    };

    this.displayWalks = function () {
        var tag = this.displayTag;
        tag.innerHTML = '';
        this.printContent = null;
        if (this.events.length() === 0) {
            tag.innerHTML = '<h3>Sorry there are no walks at the moment.</h3>';
            return;
        }
        if (this.events.getNoEventsToDisplay() === 0) {
            tag.innerHTML = "<h3>Sorry, but no walks meet your filter search.</h3>";
            return;
        }
        switch (this.option) {
            case "Grades":
                this.displayWalksGrades(tag);
                break;
            case "List":
                this.displayWalksList(tag);
                break;
            case "Table":
                this.displayWalksTable(tag);
                break;
            case "Calendar":
                this.displayWalksCalendar(tag);
                break;
            case "Map":
                this.displayMap(tag);
                break;
            case "Contacts":
                this.displayContacts(tag);
                break;
        }
    };


    this.displayWalksGrades = function (tag) {
        var tags = [
            {name: 'legend', parent: 'root', tag: 'div'},
            {name: 'content', parent: 'root', tag: 'div'}
        ];
        var self = this;
        var elements = ra.html.generateTags(tag, tags);
        elements.legend.innerHTML = "<p class='noprint'>Click on item to display full details of walk</p>";
        elements.content.addEventListener('reportPagination', function (e) {
            self.paginationOptions.itemsPerPage = e.cvList.itemsPerPage;
            self.paginationOptions.currentPage = e.cvList.currentPage;
        });
        this.addPrintCalButtons(elements.legend);
        var month = "";
        var div;
        var should = this.shouldDisplayMonth('Grades');
        var list = new ra.paginatedList(elements.content, this.paginationOptions);
        this.events.forEachFiltered($walk => {
            var displayMonth = month !== $walk.getIntValue("basics", "displayMonth") && should;
            div = this.displayWalk_Grades($walk, displayMonth);
            list.listItem(div);
            month = $walk.getIntValue("basics", "displayMonth");
        });
        list.listEnd();
        this.printContent = list;
    };
    this.displayWalksList = function (tag) {
        var div;
        var self = this;
        var month = "";
        var should = this.shouldDisplayMonth('List');
        var tags = [
            {name: 'legend', parent: 'root', tag: 'div'},
            {name: 'content', parent: 'root', tag: 'div'}
        ];
        var elements = ra.html.generateTags(tag, tags);
        elements.legend.innerHTML = "<p class='noprint'>Click on item to display full details of walk</p>";
        elements.content.addEventListener('reportPagination', function (e) {
            self.paginationOptions.itemsPerPage = e.cvList.itemsPerPage;
            self.paginationOptions.currentPage = e.cvList.currentPage;

        });
        this.addPrintCalButtons(elements.legend);

        var list = new ra.paginatedList(elements.content, this.paginationOptions);
        this.events.forEachFiltered($walk => {
            var displayMonth = month !== $walk.getIntValue("basics", "displayMonth") && should;
            div = this.displayWalk_List($walk, displayMonth);
            list.listItem(div);
            month = $walk.getIntValue("basics", "displayMonth");
        });
        list.listEnd();
        this.printContent = list;
    };
    this.displayWalksTable = function (tag) {
        var month = "";
        var self = this;
        var should = this.shouldDisplayMonth('Table');
        var tags = [
            {name: 'legend', parent: 'root', tag: 'div'},
            {name: 'content', parent: 'root', tag: 'div'}
        ];
        var elements = ra.html.generateTags(tag, tags);
        elements.legend.innerHTML = "<p class='noprint'>Click on item to display full details of walk</p>";
        elements.content.addEventListener('reportPagination', function (e) {
            self.paginationOptions.itemsPerPage = e.cvList.itemsPerPage;
            self.paginationOptions.currentPage = e.cvList.currentPage;

        });
        this.addPrintCalButtons(elements.legend);

        var table = new ra.paginatedTable(elements.content, this.paginationOptions);
        table.tableHeading(this.settings.tableFormat);
        this.events.forEachFiltered($walk => {
            var displayMonth = month !== $walk.getIntValue("basics", "displayMonth") && should;
            this.displayWalk_Table(table, $walk, displayMonth);
            month = $walk.getIntValue("basics", "displayMonth");
        });
        table.tableEnd();
        this.printContent = table;
    };
    this.displayWalksCalendar = function (tag) {
        var events = [];
        var template = this.calendarTemplate;
        var tags = [
            {name: 'legend', parent: 'root', tag: 'div'},
            {name: 'content', parent: 'root', tag: 'div'}
        ];
        var elements = ra.html.generateTags(tag, tags);
        elements.legend.innerHTML = "<p class='noprint'>Click on item to display full details of walk</p>";

        this.events.forEachFiltered($walk => {
            var event = {};
            event.id = $walk.admin.id;
            event.start = $walk.basics.walkDate;
            if ($walk.basics.multiDate) {
                event.end = $walk.basics.finishDate;
            }
            event.raContent = '<div class="ra calendar event">' + $walk.getWalkText(template, false) + '</div>';
            event.textColor = '#111111';
            if ($walk.admin.status === 'Cancelled') {
                event.textColor = 'red';
                event.backgroundColor = 'white';
            } else {
                event.backgroundColor = '#EFEFEF';
                event.borderColor = '#AAAAAA';
            }

            event.classNames = ['pointer'];
            event.display = 'block';
            event.eventContent = {html: ''};
            events.push(event);
        });

        var calendar = new FullCalendar.Calendar(elements.content, {
            height: 'auto',
            selectable: true,
            displayEventTime: false,
            headerToolbar: {center: 'dayGridMonth,listMonth'}, // buttons for switching between views
            events: events,
            views: {
                dayGrid: {
                    eventTimeFormat: {
                        hour: '2-digit',
                        minute: '2-digit',
                        meridiem: false
                    }
                },
                timeGrid: {
                    // options apply to timeGridWeek and timeGridDay views
                },
                week: {
                    // options apply to dayGridWeek and timeGridWeek views
                },
                day: {
                    // options apply to dayGridDay and timeGridDay views
                }
            },
            eventClick: function (info) {
                var id = info.event.id;
                ra.walk.displayWalkID(info, id);
            },
            eventContent: function (arg) {
                return {
                    html: arg.event.extendedProps.raContent
                };
            }

        });
        calendar.render();
    };
    this.displayMap = function (tag) {
        var b = ra.baseDirectory();
        this.lmap = new ra.leafletmap(tag, this.mapOptions);
        this.lmap.display();
        this.map = this.lmap.map();
        this.cluster = new ra.map.cluster(this.map);
        var sImg = b + "media/com_ra_library/images/marker-start.png";
        var cImg = b + "media/com_ra_library/images/marker-cancelled.png";
        var aImg = b + "media/com_ra_library/images/marker-area.png";

        var leg = document.createElement('p');
        leg.innerHTML = '<strong>Zoom</strong> in to see where our walks are going to be. <strong>Click</strong> on a walk to see details.';
        this.lmap.preMapDiv().appendChild(leg);
        leg = document.createElement('div');
        leg.innerHTML += '<img src="' + sImg + '" alt="Walk start" height="26" width="26">&nbsp; Start locations&nbsp; <img src="' + cImg + '" alt="Cancelled walk" height="26" width="26"> Cancelled walk&nbsp; <img src="' + aImg + '" alt="Walking area" height="26" width="26"> Walk in that area.';
        this.lmap.preMapDiv().appendChild(leg);

        this.events.forEachFiltered($walk => {
            $walk.addWalkMarker(this.cluster, this.settings.walkClass);
        });
        this.cluster.addClusterMarkers();
        this.cluster.zoomAll();
        this.map.invalidateSize();
    };
    this.displayContacts = function (tag) {
        var $contacts = [];
        this.events.forEachFiltered($walk => {
            var contactName = $walk.getEventValue("{contactperson}");
            var telephone1 = $walk.getEventValue("{telephone1}");
            var telephone2 = $walk.getEventValue("{telephone2}");
            var $contact = {name: contactName, telephone1: telephone1, telephone2: telephone2};
            if (!ra.contains($contacts, $contact)) {
                $contacts.push($contact);
            }
        });
        $contacts.sort(function (a, b) {
            if (a.name.toLowerCase() > b.name.toLowerCase()) {
                return 1;
            }
            return -1;
        });
        var contactsFormat = [{"title": "Contact Name", "options": {align: "left"}, field: {type: 'text', filter: true, sort: false}},
            {"title": "Telephone", "options": {align: "left"}},
            {"title": "Alt Telephone", "options": {align: "left"}}];
        var table = new ra.paginatedTable(tag);
        table.tableHeading(contactsFormat);
        $contacts.forEach($contact => {
            table.tableRowStart();
            table.tableRowItem($contact.name, contactsFormat[0]);
            table.tableRowItem($contact.telephone1);
            table.tableRowItem($contact.telephone2);
            table.tableRowEnd();
        });
        table.tableEnd();
    };

    this.shouldDisplayMonth = function (view) {
        var $template = "";
        switch (view) {
            case "Grades":
                $template = this.gradesTemplate;
                break;
            case "List":
                $template = this.listTemplate;
                break;
            case "Table":
                var $cols = this.settings.tableFormat;
                for (let i = 0; i < $cols.length; i++) {
                    if (this.groupByMonth($cols[i].template)) {
                        return true;
                    }
                }
                return false;
                break;
            default:
                return false;
        }
        return this.groupByMonth($template);
    };
    this.groupByMonth = function ($template) {
        $temp = $template;
        $fields = $template.fields;
        for (let i = 0; i < this.settings.withMonth.length; i++) {
            if ($fields.includes(this.settings.withMonth[i])) {
                return false;
            }
        }
        return true;
    };

    this.displayWalk_Grades = function (walk, displayMonth) {
        var out, div, span;

        div = document.createElement('div');
        if (displayMonth) {
            var month = document.createElement('h3');
            month.innerHTML = walk.getIntValue("basics", "displayMonth");
            month.classList.add('ra', 'walk', 'month');
            div.appendChild(month);
        }

        div.classList.add("walk" + walk.admin.status);
        span = document.createElement('span');
        span.classList.add('walkdetail');
        if (typeof displayGradesRowClass === 'function') {
            span.classList.add(displayGradesRowClass(walk));
        }
        out = walk.getWalkText(this.gradesTemplate);
        span.innerHTML = walk.addTooltip(out);
        div.appendChild(span);
        return div;
    };
    this.displayWalk_List = function (walk, displayMonth) {
        var template = this.listTemplate;
        var div = document.createElement('div');
        if (displayMonth) {
            var month = document.createElement('h3');
            month.innerHTML = walk.getIntValue("basics", "displayMonth");
            month.setAttribute('class', 'ra walk month');
            div.appendChild(month);
        }
        var span = document.createElement('span');
        span.innerHTML = walk.addTooltip(walk.getWalkText(template));
        span.classList.add("walk" + walk.admin.status);
        span.classList.add("walkdetail");
        if (typeof displayListRowClass === 'function') {
            span.classList.add(displayListRowClass(walk));
        }
        div.appendChild(span);
        return div;
    };

    this.displayWalk_Table = function (table, walk, displayMonth) {

        var tr = table.tableRowStart();
        tr.classList.add("walk" + walk.admin.status);
        if (typeof displayTableRowClass === 'function') {
            tr.classList.add(displayTableRowClass(walk));
        }
        this.settings.tableFormat.forEach($col => {
            table.tableRowItem(walk.addTooltip(walk.getWalkText($col.template)));
        });
        var monthDisplay = null;
        if (displayMonth) {
            monthDisplay = document.createElement('h3');
            monthDisplay.innerHTML = walk.getIntValue("basics", "displayMonth");
        }
        table.tableRowEnd(monthDisplay, 'ra walk month');
        return tr;
    };
    this.addPrintCalButtons = function (tag) {
        var container = document.createElement('div');
        container.setAttribute('class', 'vertalign');
        tag.appendChild(container);
        this.addPrintButton(container);
        this.addToDiaryButton(container);
    };

    this.addPrintButton = function (tag) {

        var printButton = document.createElement('button');
        printButton.classList.add('link-button', 'tiny', 'button', 'mintcake');
        printButton.textContent = 'Print';
        tag.appendChild(printButton);
        var _this = this;
        printButton.addEventListener('click', function () {
            if (this.printContent !== null) {
                _this._print();
            }
        });
    };
    this._print = function () {
        var content = document.createElement('div');
        var heading = document.createElement('h2');
        heading.innerHTML = "Walking Programme";
        content.appendChild(heading);
        var node = this.printContent.getPrintAll();
        content.appendChild(node);
        ra.html.printHTML(content);
    };
    // Uses ra.calendarsubscribe (media/js/ra.calendarsubscribe.js); only shown if the display option says so.
    // The options are worked out when the button is clicked, so the download holds the walks currently shown/filtered.
    this.addToDiaryButton = function (tag) {
        if (data.showCalendarLink === false) {
            return;
        }
        var _this = this;
        ra.calendarsubscribe.createButton(tag, function () {
            _this.events.setDisplayFilter();
            var ids = [];
            _this.events.forEachFiltered(function ($walk) {
                ids.push(String($walk.admin.id));
            });
            return {
                baseUrl: data.calendarBaseUrl,
                groups: _this.events.getGroups(),
                ids: ids.slice(0, 100),
                idsTotal: ids.length,
                lists: data.calendarLists,
                calendarName: 'Ramblers Walks'
            };
        }, 'Add to calendar');
    };
};

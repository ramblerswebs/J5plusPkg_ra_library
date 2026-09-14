/* 
 * Copyright (C) 2025 chris vaughan
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

var ra;
if (typeof (ra) === "undefined") {
    ra = {};
}

ra.paginatedTable = function (tag, userOptions = null) {
    this.options = {
        className: "paginatedList"
    };
    this.defaultOptions = {pagination: {
            "10 per page": 10,
            "20 per page": 20,
            "25 per page": 25,
            "50 per page": 50,
            "100 per page": 100,
            "View all": 0
        },
        itemsPerPage: 20
    };
    this.cvListOptions = {...this.defaultOptions, ...userOptions};
    this.fields = [];
    this.format = null;
    this.defaultSort = [];
    this.noColumns = 1;
    var tags = [
        {name: 'home', parent: 'root', tag: 'div', attrs: {class: this.options.className}},
        {name: 'filtersDiv', parent: 'home', tag: 'div', attrs: {class: 'filter'}},
        {name: 'rapagination1', parent: 'home', tag: 'div', attrs: {class: 'pagination top'}},
        {name: 'items1', parent: 'rapagination1', tag: 'div'},
        {name: 'pagination1', parent: 'rapagination1', tag: 'div'},
        {name: 'itemsSelect1', parent: 'rapagination1', tag: 'div'},
        {name: 'table', parent: 'home', tag: 'table', attrs: {class: 'table'}},
        {name: 'thead', parent: 'table', tag: 'thead'},
        {name: 'tbody', parent: 'table', tag: 'tbody'},
        {name: 'blank', parent: 'home', tag: 'div'},
        {name: 'rapagination2', parent: 'home', tag: 'div', attrs: {class: 'pagination bottom'}},
        {name: 'items2', parent: 'rapagination2', tag: 'div'},
        {name: 'pagination2', parent: 'rapagination2', tag: 'div'},
        {name: 'itemsSelect2', parent: 'rapagination2', tag: 'div'}
    ];

    this.elements = ra.html.generateTags(tag, tags);

    this.list = new cvList(this.elements.tbody);

    var pag = this.list.createPagination(this.cvListOptions);
    pag.addPaginationDisplayText(this.elements.items1, 'Item {startItem} to {endItem} of {itemsNumber}');
    pag.addPaginationDisplayButton(this.elements.pagination1, 'paginationButtons');
    pag.addPaginationDisplayButton(this.elements.itemsSelect1, 'itemsPerPage');
    pag.addPaginationDisplayText(this.elements.items2, 'Item {startItem} to {endItem} of {itemsNumber}');
    pag.addPaginationDisplayButton(this.elements.pagination2, 'paginationButtons');
    pag.addPaginationDisplayButton(this.elements.itemsSelect2, 'itemsPerPage');
    var self = this;
    this.elements.tbody.addEventListener("cvList-after-display", function (e) {
        self._hideBlankColumns(self.elements.table);
    });
    this.elements.tbody.addEventListener('cvList-reportPagination', function (e) {
        let ev = new Event("reportPagination");
        ev.cvList = e.cvList;
        tag.dispatchEvent(ev);
    });

    this._createFields = function (format) {
        format.forEach(item => {
            if ('field' in item) {
                this.fields[item.title] = this.list.createField(item.title, item.field.type);
            }
        });
    };
    this._createFilters = function (format) {
        format.forEach(item => {
            if ('field' in item) {
                var field = item.field;
                if (field.filter) {
                    this.fields[item.title].setFilter(this.elements.filtersDiv);
                }
            }
        });
    };
// var format =   [ {
//        "title": "Running No",
//        "options": {
//            "align": "right"
//            "view": "notTablet" or "notMobile"
//            "style": {"min-width":"60px",...}
//        },
//        "field": {
//            "type": "number","text","date"
//            "filter": false,
//            "sort": true,
//            "defaultSort": 'Asc' or 'Desc' 
//        },
//        "ignore": true If set then that column is not displayed
//        view: 'notTablet' or 'notMobile'
//    }, {...}
//    ];
    this.tableHeading = function (format) {
        this.format = format;
        this._createFields(format);
        this._createFilters(format);
        var row = document.createElement("tr");
        this.noColumns = 0;
        format.forEach(item => {
            if ('ignore' in item) {
                if (item.ignore) {
                    return;
                }
            }
            this.noColumns += 1;
            var th = document.createElement("th");
            th.innerText = item.title;
            row.appendChild(th);
            if ('options' in item) {
                if ('align' in item.options) {
                    th.classList.add(item.options.align);
                }
                if ('view' in item.options) {
                    th.classList.add(item.options.view);
                }
                if ('style' in item.options) {
                    var items = item.options.style;
                    for (let item in items) {
                        th.style.setProperty(item, items[item]);
                    }
                }
            }
            if ('field' in item) {
                if (item.field.sort) {
                    var field = this.fields[item.title];
                    field.addSortArrows(th);
                    if (item.field.defaultSort) {
                        var item = {field: field, direction: item.field.defaultSort};
                        this.defaultSort.push(item);
                    }
                }
            }
        }
        );
        this.elements.thead.appendChild(row);
    };
    this.tableRowStart = function () {
        this.row = document.createElement("tr");
        return this.row;
    };
    this.tableRowItem = function (value, item = null, sortValue = null) {
        if (item !== null) {
            if ('ignore' in item) {
                if (item.ignore) {
                    return null;
                }
            }
        }
        var td = document.createElement("td");
        ra.html.setTag(td, value);
        this.row.appendChild(td);
        if (item !== null) {
            if ('field' in item) {
                var field = this.fields[item.title];
                field.setValue(td, value);
                if (sortValue !== null) {
                    field.setSortValue(td, sortValue);
                }
            }
            if ('options' in item) {
                var options = item.options;
                if ('align' in options) {
                    td.classList.add(options.align);
                }
                if ('view' in item.options) {
                    td.classList.add(item.options.view);
                }
            }
        }
        return td;
    };
    this.tableRowEnd = function (extraItem = null, cssClass = '') {
        if (extraItem !== null) {
            var nonPaginatedItem = document.createElement('tr');
            if (cssClass !== '') {
                nonPaginatedItem.setAttribute('class', cssClass);
            }
            var td = document.createElement('td');
            td.setAttribute('colspan', this.noColumns);
            nonPaginatedItem.appendChild(td);
            td.appendChild(extraItem);
            this.list.addItem(this.row, nonPaginatedItem);
        } else {
            this.list.addItem(this.row);
        }
        this.row = null;
    };
    this.tableEnd = function () {
        this.list.display();
        if (this.list.getNumberItems() < 20) {
            this.elements.rapagination1.style.display = 'none';
            this.elements.rapagination2.style.display = 'none';
        }
        this.defaultSort.forEach(item => {
            switch (item.direction) {
                case 'Asc':
                    item.field.sort(1);
                    break;
                case 'Desc':
                    item.field.sort(-1);
                    break;
                default:
                    alert('order error');
            }
        });
    };
    this.sort = function (param, order) {
        var field = this.fields[param];
        if (field) {
            switch (order) {
                case 'Asc':
                    field.sort(1);
                    break;
                case 'Desc':
                    field.sort(-1);
                    break;
                default:
                    alert('order error');
            }
        }
    };
    this.getPrintAll = function () {
        var div = document.createElement('div');
        div.classList.add(this.options.className);
        var table = document.createElement('table');
        div.appendChild(table);
        table.appendChild(this.elements.thead.cloneNode(true));
        var tbody = document.createElement('tbody');
        table.appendChild(tbody);
        this.list.appendPrintItems(tbody);
        this.resetColumnDisplay(div);
        this._hideBlankColumns(table);
        return div;
    };
    this._hideBlankColumns = function (table) {
        var cols = [];
        var thead = table.childNodes[0];
        var tbody = table.childNodes[1];
        var nodeList = thead.childNodes[0].childNodes;
        for (let i = 0; i < nodeList.length; i++) {
            cols.push(i);
        }
        var trs = tbody.childNodes;
        var len = cols.length;
        for (let j = 0; j < len; j++) {
            var col = len - j - 1;
            for (let i = 0; i < trs.length; i++) {
                var tr = trs[i];
                var td = tr.childNodes[col];

                if (typeof (td) !== "undefined") {
                    var value = td.innerText;
                    if (value !== '') {
                        cols.splice(col, 1);
                        break;
                    }
                }
            }
        }

        this.resetColumnDisplay(this.elements.table);
        this._removeColumns(cols);
    };
    this.resetColumnDisplay = function (tag) {
        var elements = tag.getElementsByClassName('paginationRemoveCol');
        // elements is a live list!
        for (let i = elements.length - 1; i >= 0; i--) {
            elements[i].classList.remove('paginationRemoveCol');
        }
        this.elements.blank.innerHTML = '';
    };
    this._removeColumns = function (cols) {
        var nodeList = this.elements.thead.childNodes[0].childNodes;
        var colNames = [];
        for (let i = 0; i < cols.length; i++) {
            var col = cols[i];
            nodeList[col].classList.add('paginationRemoveCol');
            colNames.push(this.format[col].title);
        }
        if (colNames.length > 0) {
            this.elements.blank.innerHTML = "Blank columns not displayed: " + colNames.join();
        }
        var trs = this.elements.tbody.childNodes;
        for (let j = 0; j < trs.length; j++) {
            var tr = trs[j];
            for (let i = 0; i < cols.length; i++) {
                var col = cols[i];
                this.removeColumn(tr, col);
            }
        }
    };
    this.removeColumn = function (tr, col) {
        var tds = tr.childNodes;
        if (tds === null) {
            return;
        }
        for (let i = 0; i < tds.length; i++) {
            var td = tds[i];
            var span = td.getAttribute("colspan");
            if (span > col) {
                td.setAttribute('colspan', span - 1);
                break;
            }
            if (col === i) {
                td.classList.add('paginationRemoveCol');
                break;
            }
        }
    };
};
ra.paginatedList = function (tag, userOptions = null) {
    this.options = {
        className: "paginatedList"
    };
    this.defaultOptions = {pagination: {
            "10 per page": 10,
            "20 per page": 20,
            "25 per page": 25,
            "50 per page": 50,
            "100 per page": 100,
            "View all": 0
        },
        itemsPerPage: 20
    };
    this.cvListOptions = {...this.defaultOptions, ...userOptions};
    this.fields = [];
    var tags = [
        {name: 'home', parent: 'root', tag: 'div', attrs: {class: this.options.className}},
        {name: 'filtersDiv', parent: 'home', tag: 'div', attrs: {class: 'filter'}},
        {name: 'rapagination1', parent: 'home', tag: 'div', attrs: {class: 'pagination top'}},
        {name: 'items1', parent: 'rapagination1', tag: 'div'},
        {name: 'pagination1', parent: 'rapagination1', tag: 'div'},
        {name: 'itemsSelect1', parent: 'rapagination1', tag: 'div'},
        {name: 'content', parent: 'home', tag: 'div'},
        {name: 'rapagination2', parent: 'home', tag: 'div', attrs: {class: 'pagination bottom'}},
        {name: 'items2', parent: 'rapagination2', tag: 'div'},
        {name: 'pagination2', parent: 'rapagination2', tag: 'div'},
        {name: 'itemsSelect2', parent: 'rapagination2', tag: 'div'}
    ];

    this.elements = ra.html.generateTags(tag, tags);

    this.list = new cvList(this.elements.content);
    var pag = this.list.createPagination(this.cvListOptions);
    pag.addPaginationDisplayText(this.elements.items1, 'Items {startItem} to {endItem} of {itemsNumber}');
    pag.addPaginationDisplayButton(this.elements.pagination1, 'paginationButtons');
    pag.addPaginationDisplayButton(this.elements.itemsSelect1, 'itemsPerPage');
    pag.addPaginationDisplayText(this.elements.items2, 'Items {startItem} to {endItem} of {itemsNumber}');
    pag.addPaginationDisplayButton(this.elements.pagination2, 'paginationButtons');
    pag.addPaginationDisplayButton(this.elements.itemsSelect2, 'itemsPerPage');
    this.elements.content.addEventListener('cvList-reportPagination', function (e) {
        let ev = new Event("reportPagination");
        ev.cvList = e.cvList;
        tag.dispatchEvent(ev);
    });

    this.listItem = function (tag) {
        this.list.addItem(tag);
    };
    this.listEnd = function () {
        this.list.display();
        if (this.list.getNumberItems() < 20) {
            this.elements.rapagination1.style.display = 'none';
            this.elements.rapagination2.style.display = 'none';
        }
    };
    this.getPrintAll = function () {
        var div = document.createElement('div');
        div.classList.add(this.options.className);
        this.list.appendPrintItems(div);
        return div;
    };
};
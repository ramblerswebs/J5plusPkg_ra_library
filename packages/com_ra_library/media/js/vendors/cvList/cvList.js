/* 
 * copyright: Chris Vaughan
 * email: ruby.tuesday@ramblers-webs.org.uk
 */
/**
 * cvList - ES6 class version.
 *
 * A straight port of cvList.js (the prototype / constructor-function version).
 * Public API and behaviour are unchanged, so it can be dropped in place of
 * cvList.js without altering any calling code:
 *
 *   cvList, cvListFields, cvListField, cvListFieldText, cvListFieldNumber,
 *   cvListFieldDate, cvListPagination, cvListPaginationDisplayTextItem,
 *   cvListPaginationDisplayButtonItem, createRangeSlider
 *
 */
(function (global) {
    'use strict';
    class cvList {

        constructor(displayTag) {
            this._displayTag = displayTag;
            this._items = [];
            this.keys = [];
            this._fields = new cvListFields(this._displayTag);
            this._pagination = null;
        }

        static uniqueID() {
            cvList.uniquenumber += 1;
            return 'cvlistuid' + cvList.uniquenumber; // lowercase
        }

        addItem(element, nonPaginatedItem = null) {
            const item = {element: element,
                nonPaginatedItem: nonPaginatedItem};
            this._items.push(item);
        }

        appendPrintItems(tag) {
            let oddRow = true;
            for (const key in this.keys) {
                const item = this._items[this.keys[key]];
                const element = item.element.cloneNode(true);
                element.classList.remove("odd");
                element.classList.remove("even");
                element.classList.add(oddRow ? "odd" : "even");
                oddRow = !oddRow;
                if (item.nonPaginatedItem !== null) {
                    tag.appendChild(item.nonPaginatedItem.cloneNode(true));
                }
                tag.appendChild(element);
            }
        }

        getNumberItems() {
            return this._items.length;
        }

        display() {
            if (this._items.length === 0) {
                const p = document.createElement('p');
                p.innerHTML = '<b>No information</b>';
                this._displayTag.appendChild(p);
                return;
            }
            this._fields._display();
            this._calcKeys();
            this._display();
            this._displayTag.addEventListener("cvList-redisplay", () => {
                this._display();
            });
            this._displayTag.addEventListener("cvList-reCalcKeys", () => {
                this._calcKeys();
                this._display();
            });
        }

        createField(title, type) {
            return this._fields._addField(title, type);
        }

        createPagination(userOptions) {
            const options = {pagination: {
                    "10 per page": 10,
                    "20 per page": 20,
                    "50 per page": 50,
                    "View all": 0
                },
                itemsPerPage: 10
            };
            const merged = {...options, ...userOptions};
            this._pagination = new cvListPagination(this._displayTag, merged);
            return this._pagination;
        }

        // internal functions

        _calcKeys() {
            const keys = Object.keys(this._items);
            this.keys = this._fields._setKeys(keys, this._items);
        }

        _display() {
            let oddRow = true;
            this._displayTag.innerHTML = '';
            let keys = this.keys;
            if (this._pagination !== null) {
                keys = this._pagination.setPagination(keys);
            }
            this._displayPagination();
            for (const key in keys) {
                const item = this._items[keys[key]];
                item.element.classList.remove("odd");
                item.element.classList.remove("even");
                item.element.classList.add(oddRow ? "odd" : "even");
                oddRow = !oddRow;
                if (item.nonPaginatedItem !== null) {
                    this._displayTag.appendChild(item.nonPaginatedItem);
                }
                this._displayTag.appendChild(item.element);
            }
            this._displayTag.dispatchEvent(new Event("cvList-after-display"));
        }

        _displayPagination() {
            this._pagination._displayPagination();
        }
    }
    cvList.uniquenumber = 0;

    class cvListFields {

        constructor(displayTag) {
            this._displayTag = displayTag;
            this._fields = [];
            this.no = 0;
            this.activeSort = null; // field that is active for sorting
            this.sortOrder = 1; // direction 1: asc -1: desc
            this._displayTag.addEventListener("cvList-setActiveSort", (e) => {
                this.activeSort = e.cvList.activeSort;
                this.sortOrder = e.cvList.sortOrder;
                this._displayTag.dispatchEvent(new Event("cvList-reCalcKeys"));
            });
        }

        _display() {
            // display filters after the min / max has been calculated
            this._fields.forEach((field) => {
                field._display();
            });
        }

        _addField(title, type) {
            const no = this._fields.length + 1;
            let field = null;
            switch (type.toLowerCase()) {
                case 'text':
                    field = new cvListFieldText(this._displayTag, title, no);
                    break;
                case 'number':
                    field = new cvListFieldNumber(this._displayTag, title, no);
                    break;
                case 'date':
                    field = new cvListFieldDate(this._displayTag, title, no);
                    break;
                default:
                    alert('Invalid type of cvList Field defined: ' + type);
                    field = new cvListFieldText(this._displayTag, title, no);
                    break;
            }
            this._fields.push(field);
            return field;
        }

        _setKeys(keys, items) {
            keys = this._setKeysFilters(keys, items);
            this._setKeysSort(keys, items);
            return keys;
        }

        _setKeysFilters(keys, items) {
            // get list of keys that are set
            const activeFields = this._fields.filter((field) => field._isSet());
            // if none, return keys
            if (activeFields.length === 0) {
                return keys;
            }
            // for each item check if it should be included against keys
            for (const i in items) {
                const item = items[i];
                let keep = true;
                for (const f of activeFields) {
                    if (!f._shouldDisplayItem(item)) {
                        keep = false;
                    }
                }
                if (!keep) {
                    // remove key.
                    const index = keys.indexOf(i);
                    keys.splice(index, 1);
                }
            }
            return keys;
        }

        _setKeysSort(keys, items) {
            if (this.activeSort === null) {
                return keys;
            }
            // retrieve data for that active filter/sort
            this.activeSort._sortKeys(keys, items, this.sortOrder);
        }
    }

    class cvListField {

        constructor(displayTag, title, no) {
            this._displayTag = displayTag;
            this.title = title;
            this.no = no;
            this.type = 'Invalid';
        }

        addSort(tag, direction) {
            const sort = {order: 1,
                tag: tag
            };
            let which;
            switch (direction.toLowerCase()) {
                case 'asc':
                    sort.order = 1;
                    which = 'ascending';
                    break;
                case 'desc':
                    sort.order = -1;
                    which = 'descending';
                    break;
                default:
                    alert('Invalid type of direction for Sort defined');
                    return null;
            }
            tag.title = 'Sort ' + this.title + " " + which;
            tag.addEventListener("click", () => {
                const event = new Event("cvList-setActiveSort");
                event.cvList = {activeSort: this, sortOrder: sort.order};
                this._displayTag.dispatchEvent(event);
            });
        }

        sort(order) {
            switch (order) {
                case 1:
                case -1:
                {
                    const event = new Event("cvList-setActiveSort");
                    event.cvList = {activeSort: this, sortOrder: order};
                    this._displayTag.dispatchEvent(event);
                    break;
                }
            }
        }

        addSortArrows(tag) {
            const div = this._addElement(tag, 'div', 'cvList sortArrows');
            const up = this._addElement(div, 'img', 'asc');
            this.addSort(up, 'asc');
            const down = this._addElement(div, 'img', 'desc');
            this.addSort(down, 'desc');
        }

        getType() {
            return this.type;
        }

        setSortValue(tag, value) {
            // value is either a string, number or data
            if (value !== null) {
                let type = typeof value;
                if (value instanceof Date) {
                    type = 'date';
                }
                let sorttxt = '';
                switch (type.toLowerCase()) {
                    case "string":
                        sorttxt = value;
                        break;
                    case "date":
                        sorttxt = value.getFullYear() + "-" + (value.getMonth() + 1) + "-" + value.getDate();
                        break;
                    case "number":
                        sorttxt = value;
                        break;
                }
                tag.setAttribute('data-cvListSortValue', sorttxt);
            }
        }

        // internal functions

        _getItemValue(attrib, item) {
            const search = '[' + attrib + '="' + this.no + '"]';
            const matches = item.element.querySelectorAll(search);
            if (matches.length > 0) {
                const match = matches[0];
                const sortValue = match.getAttribute('data-cvListSortValue');
                if (sortValue !== null) {
                    return sortValue;
                }
                return match.innerText;
            }
            return null;
        }

        _addElement(target, tagType, classes) {
            const tag = document.createElement(tagType);
            if (classes !== '') {
                tag.setAttribute('class', classes);
            }
            target.appendChild(tag);
            return tag;
        }

        // shared by all field types: sort 'values' ([{key, value}, ...]) and
        // write the resulting key order back into 'keys' (in place)
        _applySort(keys, values, order) {
            values.sort((a, b) => {
                if (a.value < b.value) {
                    return -1 * order;
                }
                if (a.value > b.value) {
                    return 1 * order;
                }
                return 0;
            });
            values.forEach((val, i) => {
                keys[i] = val.key;
            });
        }
    }

    class cvListFieldText extends cvListField {

        constructor(displayTag, title, no) {
            super(displayTag, title, no);
            this.filterValue = {text: '',
                caseSensitive: false};
            this.type = 'text';
        }

        setValue(tag, value) {
            tag.setAttribute('data-cvListFieldNo', this.no);
            tag.innerHTML = value;
        }

        setFilter(tag) {
            const idleTime = 200; // milliseconds
            let mouseStopTimer;
            const div = this._addElement(tag, 'div', 'cvList text filter');
            const id1 = cvList.uniqueID();
            const input = this._addElement(div, 'input', '');
            input.setAttribute('id', id1);
            input.placeholder = "Filter by " + this.title;
            input.classList.add('textFilter');
            const textLabel = this._addElement(div, 'label', '');
            const id2 = cvList.uniqueID();
            textLabel.setAttribute('for', id2);
            textLabel.innerHTML = 'Match case';
            const textCase = this._addElement(div, 'input', '');
            textCase.setAttribute('id', id2);
            textCase.setAttribute('type', 'checkbox');

            input.addEventListener("keyup", (e) => {
                clearTimeout(mouseStopTimer);
                mouseStopTimer = setTimeout(() => {
                    this.filterValue = {text: e.target.value,
                        caseSensitive: textCase.checked};
                    this._displayTag.dispatchEvent(new Event("cvList-reCalcKeys"));
                }, idleTime);
            });
            textCase.addEventListener("click", () => {
                input.dispatchEvent(new Event("keyup"));
            });
        }

        _display() {
        }

        _isSet() {
            return this.filterValue.text !== '';
        }

        _shouldDisplayItem(item) {
            const value = this._getItemValue('data-cvListFieldNo', item);
            if (value === null) {
                return false;
            }
            if (this.filterValue.text === '') {
                return true;
            }
            if (this.filterValue.caseSensitive) {
                return value.includes(this.filterValue.text);
            }
            return value.toLowerCase().includes(this.filterValue.text.toLowerCase());
        }

        _sortKeys(keys, items, order) {
            const values = keys.map((key) => ({
                    key: key,
                    value: this._getItemValue('data-cvListFieldNo', items[key])
                }));
            this._applySort(keys, values, order);
        }
    }

    class cvListFieldNumber extends cvListField {

        constructor(displayTag, title, no) {
            super(displayTag, title, no);
            this.filterValue = null;
            this.type = 'number';
            this.minValue = null;
            this.maxValue = null;
            this.filterTag = null;
        }

        setValue(tag, value) {
            let no;
            tag.setAttribute('data-cvListFieldNo', this.no);
            tag.innerHTML = value;
            if (typeof value === 'string' || value instanceof String) {
                no = parseFloat(value.replaceAll(",", ""));
            } else {
                no = value;
            }
            if (no !== null) {
                if (this.minValue === null) {
                    this.minValue = Math.floor(no);
                    this.maxValue = Math.ceil(no);
                }
                if (no < this.minValue) {
                    this.minValue = Math.floor(no);
                }
                if (no > this.maxValue) {
                    this.maxValue = Math.ceil(no);
                }
            }
        }

        setFilter(tag) {
            this.filterTag = tag;
        }

        _display() {
            if (this.filterTag === null) {
                return;
            }
            if (this.minValue === this.maxValue) {
                return;
            }
            const idleTime = 200;
            let mouseStopTimer;
            const tag = this.filterTag;
            const div = this._addElement(tag, 'div', 'cvList number range filter');
            const title = this._addElement(div, 'div', 'title');
            title.innerText = this.title;
            div.appendChild(title);
            const divAcross = this._addElement(div, 'div', 'cvList');
            const minText = this._addElement(divAcross, 'div', 'minText');
            minText.innerText = this.minValue;
            const slider = createRangeSlider(this.minValue, this.maxValue);
            divAcross.appendChild(slider);
            const maxText = this._addElement(divAcross, 'div', 'maxText');
            maxText.innerText = this.maxValue;
            slider.addEventListener("rangeSlider-change", (e) => {
                clearTimeout(mouseStopTimer);
                this.filterValue = e.range;
                minText.innerText = cvListFieldNumber._round(e.range.min, 4);
                maxText.innerText = cvListFieldNumber._round(e.range.max, 4);
                mouseStopTimer = setTimeout(() => {
                    this._displayTag.dispatchEvent(new Event("cvList-reCalcKeys"));
                }, idleTime);
            });
        }

        /**
         * Rounds a value to a given number of significant figures
         * @param value The number to round
         * @param significantFigures The number of significant figures
         * @returns The value rounded to the given number of significant figures
         */
        static _round(value, significantFigures) {
            return cvListFieldNumber._removeTrailingZeros(value.toPrecision(significantFigures));
        }

        static _removeTrailingZeros(value) {
            value = value.toString();
            // # if not containing a dot, we do not need to do anything
            if (value.indexOf('.') === -1) {
                return value;
            }
            // # as long as the last character is a 0 or a dot, remove it
            while ((value.slice(-1) === '0' || value.slice(-1) === '.') && value.indexOf('.') !== -1) {
                value = value.substr(0, value.length - 1);
            }
            return value;
        }

        _isSet() {
            if (this.filterValue === null) {
                return false;
            }
            if (this.filterValue.min !== this.minValue) {
                return true;
            }
            if (this.filterValue.max !== this.maxValue) {
                return true;
            }
            return false;
        }

        _shouldDisplayItem(item) {
            const value = this._getItemValue('data-cvListFieldNo', item);
            if (value === null) {
                return false;
            }
            const num = parseFloat(value.replaceAll(",", ""));
            if (Number.isNaN(num)) {
                return false;
            }
            if (num < this.filterValue.min) {
                return false;
            }
            if (num > this.filterValue.max) {
                return false;
            }
            return true;
        }

        _sortKeys(keys, items, order) {
            const values = keys.map((key) => {
                const no = parseFloat(this._getItemValue('data-cvListFieldNo', items[key]).replaceAll(",", ""));
                return {key: key, value: isNaN(no) ? 0 : no};
            });
            this._applySort(keys, values, order);
        }
    }

    class cvListFieldDate extends cvListField {

        constructor(displayTag, title, no) {
            super(displayTag, title, no);
            this.filterValue = null;
            this.type = 'date';
            this.filterTag = null;
        }

        setValue(tag) {
            tag.setAttribute('data-cvListFieldNo', this.no);
        }

        setFilter(tag) {
            this.filterTag = tag;
        }

        _display() {
            if (this.filterTag === null) {
                return;
            }
            const input = document.createElement('input');
            input.classList.add('cvList');
            input.classList.add('text');
            input.classList.add('filter');
            input.placeholder = "Filter by " + this.title;
            this.filterTag.appendChild(input);
            input.addEventListener("keyup", (e) => {
                this.filterValue = e.target.value;
                this._displayTag.dispatchEvent(new Event("cvList-reCalcKeys"));
            });
        }

        _isSet() {
            return this.filterValue !== null;
        }

        _shouldDisplayItem(item) {
            const value = this._getItemValue('data-cvListFieldNo', item);
            if (value === null) {
                return false;
            }
            return value.includes(this.filterValue);
        }

        _sortKeys(keys, items, order) {
            const values = keys.map((key) => {
                const txt = this._getItemValue('data-cvListFieldNo', items[key]);
                let d = this.parseDateFromFormat(txt, "YYYY-MM-DD");
                if (!this.isValidDate(d)) {
                    d = new Date(txt);
                }
                if (!this.isValidDate(d)) {
                    d = (order === 1) ? new Date('01-01-1970') : new Date('01-01-2999');
                }
                return {key: key, value: d};
            });
            this._applySort(keys, values, order);
        }

        parseDateFromFormat(dateString, format) {
            const formatParts = format.split(/[-\/]/); // supports '-' or '/' as separators
            const dateParts = dateString.split(/[-\/]/);

            let day, month, year;

            formatParts.forEach((part, idx) => {
                if (part === 'DD')
                    day = parseInt(dateParts[idx], 10);
                if (part === 'MM')
                    month = parseInt(dateParts[idx], 10) - 1; // JS months are 0-based
                if (part === 'YYYY')
                    year = parseInt(dateParts[idx], 10);
            });

            return new Date(year, month, day);
        }

        isValidDate(date) {
            return date instanceof Date && !isNaN(date.getTime());
        }
    }

    class cvListPagination {

        constructor(displayTag, userOptions) {
            const defaultOptions = {pagination: {
                    "20 per page": 20,
                    "View all": 0
                },
                itemsPerPage: 10,
                currentPage: 1
            };
            const options = {...defaultOptions, ...userOptions};
            this.displayTag = displayTag;
            this.paginationOptions = options.pagination;
            this.itemsPerPage = options.itemsPerPage;
            this.currentPage = options.currentPage;
            this.numberPages = 0;
            this.firstItem = 0;
            this.lastItem = 0;
            this.totalItems = 0;
            this._displayItems = [];
            this.displayTag.addEventListener("cvList-resetItems", (e) => {
                this.itemsPerPage = e.cvList.itemsPerPage;
                this.currentPage = 1;
                this._reportPagination();
            });
            this.displayTag.addEventListener("cvList-resetPage", (e) => {
                this.currentPage = e.cvList.pageNumber;
                this._reportPagination();
            });
        }

        addPaginationDisplayText(tag, format) {
            this._displayItems.push(new cvListPaginationDisplayTextItem(tag, format));
        }

        addPaginationDisplayButton(tag, type) {
            this._displayItems.push(new cvListPaginationDisplayButtonItem(this.displayTag, tag, type, this.paginationOptions));
        }

        setPagination(keys) {
            this.totalItems = keys.length;
            if (this.totalItems === 0) {
                this.firstItem = 0;
                this.lastItem = 0;
                this.numberPages = 0;
                this.currentPage = 0;
                return keys;
            }
            this.firstItem = 1;
            this.lastItem = this.totalItems;
            if (this.itemsPerPage === 0) {
                this.numberPages = 1;
                this.currentPage = 1;
                return keys;
            }
            this.numberPages = Math.ceil(this.totalItems / this.itemsPerPage);
            if (this.currentPage > this.numberPages) {
                this.currentPage = this.numberPages;
            }
            if (this.currentPage < 1) {
                this.currentPage = 1;
            }
            this.firstItem = (this.currentPage - 1) * this.itemsPerPage + 1;
            this.lastItem = this.currentPage * this.itemsPerPage;
            if (this.lastItem > this.totalItems) {
                this.lastItem = this.totalItems;
            }
            return keys.slice(this.firstItem - 1, this.lastItem);
        }

        // internal functions

        _reportPagination() {
            const ev = new Event("cvList-reportPagination");
            ev.cvList = {
                itemsPerPage: this.itemsPerPage,
                currentPage: this.currentPage,
                numberPages: this.numberPages,
                totalItems: this.totalItems};
            this.displayTag.dispatchEvent(ev);
        }

        _displayPagination() {
            const data = {
                currentPage: this.currentPage,
                numberPages: this.numberPages,
                firstItem: this.firstItem,
                lastItem: this.lastItem,
                totalItems: this.totalItems,
                itemsPerPage: this.itemsPerPage
            };
            this._displayItems.forEach((item) => {
                item._displayInfo(data);
            });
        }
    }

    class cvListPaginationDisplayTextItem {

        constructor(tag, format) {
            this.tag = tag;
            this.format = format;
        }

        _displayInfo(data) {
            // replace the following strings with approriate values
            //  {pageNumber}, {numberPages}, {startItem}, {endItem}, {itemsNumber}
            let content = this.format;
            content = content.replaceAll("{pageNumber}", data.currentPage);
            content = content.replaceAll("{numberPages}", data.numberPages);
            content = content.replaceAll("{startItem}", data.firstItem);
            content = content.replaceAll("{endItem}", data.lastItem);
            content = content.replaceAll("{itemsNumber}", data.totalItems);
            this.tag.innerHTML = content;
        }
    }

    class cvListPaginationDisplayButtonItem {

        constructor(displayTag, tag, type, paginationOptions) {
            this.displayTag = displayTag;
            this.tag = tag;
            this.type = type; //  paginationButtons or itemsPerPage
            this.paginationOptions = paginationOptions;
        }

        _displayInfo(data) {
            const tag = this.tag;
            tag.innerHTML = '';
            switch (this.type) {
                case 'paginationButtons':
                    this._createPaginationButtons(tag, data);
                    break;
                case 'itemsPerPage':
                    this._createItemsPerPageSelect(tag, data);
                    break;
                default:
                    tag.innerHTML = 'Invalid pagination button specified: ' + this.type;
                    break;
            }
        }

        _createPaginationButtons(tag, data) {
            const current = data.currentPage;
            const start = Math.max(current - 4, 1);
            const last = Math.min(current + 4, data.numberPages);
            const previous = Math.max(current - 1, 1);
            const next = Math.min(current + 1, data.numberPages);
            if (data.numberPages > 1) {
                this._addPageButton(tag, 1, '|&lt;', current > 1, false);
                this._addPageButton(tag, previous, '&lt;', current > 1, false);
                for (let i = start; i < last + 1; i++) {
                    this._addPageButton(tag, i, i, true, i === current);
                }
                this._addPageButton(tag, next, '&gt;', data.numberPages !== current, false);
                this._addPageButton(tag, data.numberPages, '&gt;|', data.numberPages !== current, false);
            }
        }

        _addPageButton(tag, page, text, enabled, active) {
            const button = document.createElement('button');
            button.setAttribute('type', 'button');
            button.setAttribute('data-page', page);
            button.innerHTML = text;
            button.classList.add('cvList');
            button.classList.add('pagination');
            if (!enabled) {
                button.classList.add('disabled');
            }
            if (active) {
                button.classList.add('active');
            }
            tag.appendChild(button);
            button.addEventListener("click", (e) => {
                const ev = new Event("cvList-resetPage");
                ev.cvList = {pageNumber: parseInt(e.currentTarget.getAttribute("data-page"))};
                this.displayTag.dispatchEvent(ev);
                this.displayTag.dispatchEvent(new Event("cvList-redisplay"));
            });
        }

        _createItemsPerPageSelect(tag, data) {
            const select = document.createElement('select');
            select.setAttribute('class', 'cvList itemsPerPage nonmobile');
            select.setAttribute('name', 'itemsSelect');
            tag.appendChild(select);
            for (const key in this.paginationOptions) {
                const option = document.createElement('option');
                option.textContent = key;
                option.value = this.paginationOptions[key];
                if (parseInt(option.value) === data.itemsPerPage) {
                    option.setAttribute('selected', 'selected');
                }
                select.appendChild(option);
            }
            select.addEventListener("change", (e) => {
                const ev = new Event("cvList-resetItems");
                ev.cvList = {itemsPerPage: parseInt(e.currentTarget.value)};
                this.displayTag.dispatchEvent(ev);
                this.displayTag.dispatchEvent(new Event("cvList-reCalcKeys"));
            });
        }
    }

    /**
     * Two overlapping <input type=range> controls that act as a min / max slider.
     * The inputs work on 0 to 1000 and the result is scaled to the data values.
     * 'element' is the wrapping div; it fires a "rangeSlider-change" event with
     * event.range = {min, max} in real (data) units.
     */
    class cvListRangeSlider {

        constructor(minValue, maxValue) {
            this.realRange = {min: minValue,
                max: maxValue};
            this.range = {min: 0,
                max: 1000,
                step: 1};
            this.options = {
                width: 200,
                thumbnailWidth: 5
            };

            this.element = document.createElement('div');
            this.element.classList.add("min-max-slider");
            this.minSlider = cvListRangeSlider._createRangeInput('min', this.range.min, this.range.max, this.range.step);
            this.element.appendChild(this.minSlider);
            this.maxSlider = cvListRangeSlider._createRangeInput('max', this.range.min, this.range.max, this.range.step);
            this.element.appendChild(this.maxSlider);
            this._observeResize();
            this._draw();

            this.minSlider.addEventListener("input", () => this._changed());
            this.maxSlider.addEventListener("input", () => this._changed());
        }

        _changed() {
            this._draw();
            const event = new Event("rangeSlider-change");
            event.range = {min: this._getRealValue(this.minSlider.value),
                max: this._getRealValue(this.maxSlider.value)};
            this.element.dispatchEvent(event);
        }

        _getRealValue(t) {
            const y = parseInt(t);
            return this.realRange.min + (y - this.range.min) / (this.range.max - this.range.min) * (this.realRange.max - this.realRange.min);
        }

        static _createRangeInput(type, minValue, maxValue, step) {
            const ele = document.createElement('input');
            ele.classList.add(type, 'rangeslider');
            ele.setAttribute('id', cvList.uniqueID());
            ele.setAttribute('name', cvList.uniqueID());
            ele.setAttribute('type', 'range');
            ele.setAttribute('step', step);
            ele.setAttribute('min', minValue);
            ele.setAttribute('max', maxValue);
            if (type === 'min') {
                ele.value = minValue;
            }
            if (type === 'max') {
                ele.value = maxValue;
            }
            return ele;
        }

        _observeResize() {
            const resizeObserver = new ResizeObserver((entries) => {
                for (const entry of entries) {
                    this.options.width = entry.contentRect.width;
                    this._draw();
                }
            });
            resizeObserver.observe(this.element);
        }

        _draw() {
            const minValue = parseInt(this.minSlider.value); // value is a string
            const maxValue = parseInt(this.maxSlider.value);
            const splitvalue = parseInt((minValue + maxValue) / 2);
            const sliderwidth = this.options.width;
            const minWidth = parseInt(((splitvalue - this.range.min) / (this.range.max - this.range.min)) * sliderwidth);
            const maxWidth = sliderwidth - minWidth;
            /* set min and max attributes */
            this.minSlider.setAttribute('max', splitvalue);
            this.maxSlider.setAttribute('min', splitvalue);
            this.minSlider.style.width = minWidth + 'px';
            this.maxSlider.style.width = maxWidth + 'px';
            this.minSlider.style.left = '0px';
            this.maxSlider.style.left = minWidth + 'px';
        }
    }

    // kept so existing calling code does not need to change
    function createRangeSlider(minValue, maxValue) {
        return new cvListRangeSlider(minValue, maxValue).element;
    }

    // Explicit globals (a top-level 'class' is not a property of window)
    Object.assign(global, {
        cvList,
        cvListFields,
        cvListField,
        cvListFieldText,
        cvListFieldNumber,
        cvListFieldDate,
        cvListPagination,
        cvListPaginationDisplayTextItem,
        cvListPaginationDisplayButtonItem,
        cvListRangeSlider,
        createRangeSlider
    });

})(window);


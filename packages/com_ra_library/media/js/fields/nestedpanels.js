document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.nestedpanels-field').forEach(initNestedPanels);
});

function initNestedPanels(container) {
    const dataEl = container.querySelector('.nestedpanels-data');
    const tree = JSON.parse(dataEl.textContent);
    const hidden = container.querySelector('input[type="hidden"]');
    const levelsEl = container.querySelector('.nestedpanels-levels');
    const previewEl = container.querySelector('.nestedpanels-preview');

    // The leaf option currently saved/chosen. The preview area falls back
    // to this whenever the pointer or keyboard focus leaves an option.
    let selectedNode = null;

    // Shows the large preview image (and optional caption) for an option.
    // With no node, or a node without a preview, the area is left empty and
    // CSS shows a placeholder message.
    function showPreview(node) {
        if (!previewEl)
            return;
        previewEl.innerHTML = '';
        if (!node || !node.preview)
            return;

        const img = document.createElement('img');
        img.src = node.preview;
        img.alt = ''; // decorative: the option label is already visible and read out
        previewEl.appendChild(img);

        if (node.caption) {
            const cap = document.createElement('div');
            cap.className = 'nestedpanels-preview-caption';
            cap.textContent = node.caption;
            previewEl.appendChild(cap);
        }
    }

    // path[i] = the node chosen at row i (group at every row except
    // possibly the last, which may be the final chosen leaf option).
    let path = [];

    // Returns the full trail of nodes (groups ... then the leaf) leading
    // to the given option value, or null if not found.
    function findPathToValue(nodes, value, trail) {
        for (const node of nodes) {
            if (node.type === 'option' && node.value === value) {
                return trail.concat([node]);
            }
            if (node.type === 'group') {
                const found = findPathToValue(node.children, value, trail.concat([node]));
                if (found)
                    return found;
            }
        }
        return null;
    }

    function renderRow(nodes, level, parentLabel) {
        const row = document.createElement('div');
        row.className = 'nestedpanels-row';
        row.dataset.level = String(level);
        row.style.setProperty('--level', level);

        // Above every row except the top, name the group you drilled
        // into to get here, so depth is obvious without counting lines.
        if (level > 0 && parentLabel) {
            const heading = document.createElement('div');
            heading.className = 'nestedpanels-row-heading';
            heading.textContent = parentLabel;
            row.appendChild(heading);
        }

        // Only the row that holds the selectable options (the lowest level
        // currently shown) gets the prompt, so it sits next to those panels.
        if (nodes.some(node => node.type === 'option')) {
            const hint = document.createElement('div');
            hint.className = 'nestedpanels-row-hint';
            hint.textContent = 'Select one of these options';
            row.appendChild(hint);
        }

        const panelsWrap = document.createElement('div');
        panelsWrap.className = 'nestedpanels-row-panels';

        nodes.forEach(node => {
            const panel = document.createElement('div');
            panel.className = 'nestedpanels-panel nestedpanels-panel--' + node.type;
            panel.tabIndex = 0;
            panel.setAttribute('role', 'button');

            const isActiveBranch = path[level] === node && node.type === 'group';
            const isFinalSelected = node.type === 'option' && node.value === hidden.value;

            if (isActiveBranch)
                panel.classList.add('is-active');
            if (isFinalSelected)
                panel.classList.add('is-selected');

            if (node.image) {
                const img = document.createElement('img');
                img.src = node.image;
                img.alt = node.label;
                panel.appendChild(img);
            }

            const label = document.createElement('span');
            label.className = 'nestedpanels-panel-label';
            label.textContent = node.label;
            panel.appendChild(label);

            const activate = () => {
                // Choosing anything at this row invalidates any deeper
                // selection previously made further down the tree.
                path[level] = node;
                path.length = level + 1;

                if (node.type === 'option') {
                    hidden.value = node.value;
                    selectedNode = node;
                    hidden.dispatchEvent(new Event('change', {bubbles: true}));
                }

                render();
            };

            // Leaf options preview on hover/focus; leaving reverts to the
            // option that is actually selected (or the empty placeholder).
            if (node.type === 'option') {
                panel.addEventListener('mouseenter', () => showPreview(node));
                panel.addEventListener('focus', () => showPreview(node));
                panel.addEventListener('mouseleave', () => showPreview(selectedNode));
                panel.addEventListener('blur', () => showPreview(selectedNode));
            }

            panel.addEventListener('click', activate);
            panel.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    activate();
                }
            });

            panelsWrap.appendChild(panel);
        });

        row.appendChild(panelsWrap);
        levelsEl.appendChild(row);
    }

    function render() {
        levelsEl.innerHTML = '';

        let nodesAtLevel = tree;
        let level = 0;
        let parentLabel = null;

        while (nodesAtLevel && nodesAtLevel.length) {
            renderRow(nodesAtLevel, level, parentLabel);

            const chosen = path[level];
            if (!chosen || chosen.type !== 'group')
                break;

            parentLabel = chosen.label;
            nodesAtLevel = chosen.children;
            level++;
        }

        // The rows were rebuilt, so show the selected option's preview again.
        showPreview(selectedNode);
    }

    // If a value is already saved (editing an existing record), expand
    // every row down to that value so the whole path is visible on load.
    if (hidden.value) {
        const found = findPathToValue(tree, hidden.value, []);
        if (found) {
            path = found;
            selectedNode = found[found.length - 1]; // the leaf option
        }
    }

    render();
}
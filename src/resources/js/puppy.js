/**
 * Puppy — Craft CMS 5 CP Companion
 *
 * A floating, draggable panel that tracks the editor's session trail,
 * edits, and provides quick links back to recently worked-on locations.
 */
(function () {
    'use strict';

    const STORAGE_KEY_POS = 'puppy.position';
    const STORAGE_KEY_STATE = 'puppy.state'; // expanded | collapsed
    const STORAGE_KEY_TAB = 'puppy.activeTab';
    const STORAGE_KEY_PAUSED = 'puppy.paused';
    const POLL_INTERVAL = 10000;
    const MAX_DISPLAY_TRAIL = 25;
    const MAX_DISPLAY_EDITS = 15;

    // --- State ---
    let trail = [];
    let edits = [];
    let isCollapsed = localStorage.getItem(STORAGE_KEY_STATE) === 'collapsed';
    let isPaused = localStorage.getItem(STORAGE_KEY_PAUSED) === 'true';
    let activeTab = localStorage.getItem(STORAGE_KEY_TAB) || 'trail';
    let sessionStart = Date.now();
    let pollTimer = null;

    // --- Icon map ---
    const ICONS = {
        entry: '\uD83D\uDCC4',
        asset: '\uD83D\uDDBC\uFE0F',
        category: '\uD83C\uDFF7\uFE0F',
        globalset: '\u2699\uFE0F',
        user: '\uD83D\uDC64',
        route: '\uD83D\uDCCD',
        element: '\u25C6',
    };

    const ACTION_LABELS = {
        visited: 'Visited',
        saved: 'Saved',
        created: 'Created',
        updated: 'Updated',
    };

    // =======================================================================
    // DOM Helpers — safe element creation, no innerHTML for dynamic content
    // =======================================================================

    function el(tag, attrs, children) {
        const node = document.createElement(tag);
        if (attrs) {
            Object.keys(attrs).forEach(key => {
                if (key === 'className') {
                    node.className = attrs[key];
                } else if (key === 'textContent') {
                    node.textContent = attrs[key];
                } else if (key.startsWith('data-')) {
                    node.setAttribute(key, attrs[key]);
                } else {
                    node[key] = attrs[key];
                }
            });
        }
        if (children) {
            (Array.isArray(children) ? children : [children]).forEach(child => {
                if (typeof child === 'string') {
                    node.appendChild(document.createTextNode(child));
                } else if (child) {
                    node.appendChild(child);
                }
            });
        }
        return node;
    }

    // =======================================================================
    // Initialization
    // =======================================================================

    function init() {
        if (!window.PuppyConfig) return;
        buildPanel();
        recordCurrentPage();
        fetchTrail();
        startPolling();
    }

    // =======================================================================
    // Panel Construction (static structure — safe to set once)
    // =======================================================================

    function buildPanel() {
        const panel = el('div', { id: 'puppy-panel' });
        if (isCollapsed) panel.classList.add('is-collapsed');
        if (isPaused) panel.classList.add('is-paused');

        // --- Header ---
        const header = el('div', { className: 'puppy-header' }, [
            el('span', { className: 'puppy-logo', textContent: '\uD83D\uDC3E' }),
            el('span', { className: 'puppy-title', textContent: 'Puppy' }),
            el('span', { className: 'puppy-badge', 'data-puppy-badge': '', textContent: '0' }),
            el('div', { className: 'puppy-header-actions' }, [
                el('button', {
                    className: 'puppy-header-btn',
                    'data-puppy-pause': '',
                    title: isPaused ? 'Resume tracking' : 'Pause tracking',
                    textContent: isPaused ? '\u25B6' : '\u23F8',
                }),
                el('button', {
                    className: 'puppy-header-btn',
                    'data-puppy-toggle': '',
                    title: 'Toggle panel',
                    textContent: isCollapsed ? '\u25B2' : '\u25BC',
                }),
            ]),
        ]);

        // --- Tabs ---
        const tabs = el('div', { className: 'puppy-tabs' }, [
            el('button', { className: 'puppy-tab' + (activeTab === 'trail' ? ' is-active' : ''), 'data-puppy-tab': 'trail', textContent: 'Trail' }),
            el('button', { className: 'puppy-tab' + (activeTab === 'edits' ? ' is-active' : ''), 'data-puppy-tab': 'edits', textContent: 'Edits' }),
            el('button', { className: 'puppy-tab' + (activeTab === 'stats' ? ' is-active' : ''), 'data-puppy-tab': 'stats', textContent: 'Stats' }),
        ]);

        // --- Body ---
        const body = el('div', { className: 'puppy-body' }, [
            el('div', { className: 'puppy-tab-content' + (activeTab === 'trail' ? ' is-active' : ''), 'data-puppy-content': 'trail' }, [
                el('div', { className: 'puppy-empty', textContent: 'No pages visited yet.' }),
            ]),
            el('div', { className: 'puppy-tab-content' + (activeTab === 'edits' ? ' is-active' : ''), 'data-puppy-content': 'edits' }, [
                el('div', { className: 'puppy-empty', textContent: 'No edits recorded yet.' }),
            ]),
            el('div', { className: 'puppy-tab-content' + (activeTab === 'stats' ? ' is-active' : ''), 'data-puppy-content': 'stats' }),
        ]);

        // --- Footer ---
        const footer = el('div', { className: 'puppy-footer' }, [
            el('span', { 'data-puppy-session-time': '', textContent: '0m in session' }),
            el('button', { className: 'puppy-footer-btn', 'data-puppy-clear': '', textContent: 'Clear trail' }),
        ]);

        panel.appendChild(header);
        panel.appendChild(tabs);
        panel.appendChild(body);
        panel.appendChild(footer);
        document.body.appendChild(panel);

        // Restore position
        const savedPos = getSavedPosition();
        panel.style.right = savedPos.right + 'px';
        panel.style.bottom = savedPos.bottom + 'px';

        // Bind events
        bindDrag(panel);
        bindHeaderActions(panel);
        bindTabs(panel);
        bindFooterActions(panel);
        updateCollapsedState(panel);
        renderStats();
    }

    // =======================================================================
    // Rendering (safe DOM construction)
    // =======================================================================

    function buildItemEl(item, showAction) {
        const icon = ICONS[item.type] || ICONS.route;
        const time = formatTime(item.timestamp);
        const metaParts = [item.context, time].filter(Boolean);
        const metaText = metaParts.join(' \u00B7 ');

        const bodyChildren = [
            el('span', { className: 'puppy-item-label', textContent: item.label }),
        ];
        if (metaText) {
            bodyChildren.push(el('span', { className: 'puppy-item-meta', textContent: metaText }));
        }

        const linkChildren = [
            el('span', { className: 'puppy-item-icon', textContent: icon }),
            el('span', { className: 'puppy-item-body' }, bodyChildren),
        ];

        if (showAction && item.action) {
            const actionEl = el('span', {
                className: 'puppy-item-action',
                'data-action': item.action,
                textContent: ACTION_LABELS[item.action] || item.action,
            });
            linkChildren.push(actionEl);
        }

        return el('a', { className: 'puppy-item', href: item.url, title: item.label }, linkChildren);
    }

    function renderTrail() {
        const container = document.querySelector('[data-puppy-content="trail"]');
        if (!container) return;

        // Clear safely
        while (container.firstChild) container.removeChild(container.firstChild);

        if (trail.length === 0) {
            container.appendChild(el('div', { className: 'puppy-empty', textContent: 'No pages visited yet.' }));
            return;
        }

        trail.slice(0, MAX_DISPLAY_TRAIL).forEach(item => {
            container.appendChild(buildItemEl(item, false));
        });
    }

    function renderEdits() {
        const container = document.querySelector('[data-puppy-content="edits"]');
        if (!container) return;

        while (container.firstChild) container.removeChild(container.firstChild);

        if (edits.length === 0) {
            container.appendChild(el('div', { className: 'puppy-empty', textContent: 'No edits recorded yet.' }));
            return;
        }

        edits.slice(0, MAX_DISPLAY_EDITS).forEach(item => {
            container.appendChild(buildItemEl(item, true));
        });
    }

    function renderStats() {
        const minutes = Math.round((Date.now() - sessionStart) / 60000);

        const statsContainer = document.querySelector('[data-puppy-content="stats"]');
        if (!statsContainer) return;

        while (statsContainer.firstChild) statsContainer.removeChild(statsContainer.firstChild);

        const statsDiv = el('div', { className: 'puppy-stats' }, [
            el('div', { className: 'puppy-stat' }, [
                el('div', { className: 'puppy-stat-value', 'data-puppy-stat-visits': '', textContent: String(trail.length) }),
                el('div', { className: 'puppy-stat-label', textContent: 'Visited' }),
            ]),
            el('div', { className: 'puppy-stat' }, [
                el('div', { className: 'puppy-stat-value', 'data-puppy-stat-edits': '', textContent: String(edits.length) }),
                el('div', { className: 'puppy-stat-label', textContent: 'Edited' }),
            ]),
            el('div', { className: 'puppy-stat' }, [
                el('div', { className: 'puppy-stat-value', 'data-puppy-stat-time': '', textContent: minutes + 'm' }),
                el('div', { className: 'puppy-stat-label', textContent: 'Session' }),
            ]),
        ]);
        statsContainer.appendChild(statsDiv);

        // Update footer session time
        const sessionTime = document.querySelector('[data-puppy-session-time]');
        if (sessionTime) sessionTime.textContent = minutes + 'm in session';
    }

    function renderBadge() {
        const badge = document.querySelector('[data-puppy-badge]');
        if (badge) {
            const count = trail.length + edits.length;
            badge.textContent = String(count);
            badge.style.display = count > 0 ? '' : 'none';
        }
    }

    function renderAll() {
        renderTrail();
        renderEdits();
        renderStats();
        renderBadge();
    }

    // =======================================================================
    // Drag & Drop
    // =======================================================================

    function bindDrag(panel) {
        const header = panel.querySelector('.puppy-header');
        let dragging = false;
        let startX, startY, startRight, startBottom;

        header.addEventListener('mousedown', (e) => {
            if (e.target.closest('button')) return;
            dragging = true;
            startX = e.clientX;
            startY = e.clientY;
            startRight = parseInt(panel.style.right) || 20;
            startBottom = parseInt(panel.style.bottom) || 20;
            e.preventDefault();
        });

        document.addEventListener('mousemove', (e) => {
            if (!dragging) return;
            const dx = startX - e.clientX;
            const dy = startY - e.clientY;
            const newRight = Math.max(0, Math.min(window.innerWidth - 60, startRight + dx));
            const newBottom = Math.max(0, Math.min(window.innerHeight - 60, startBottom + dy));
            panel.style.right = newRight + 'px';
            panel.style.bottom = newBottom + 'px';
        });

        document.addEventListener('mouseup', () => {
            if (!dragging) return;
            dragging = false;
            savePosition(panel);
        });
    }

    // =======================================================================
    // Header Actions
    // =======================================================================

    function bindHeaderActions(panel) {
        panel.querySelector('[data-puppy-toggle]').addEventListener('click', () => {
            isCollapsed = !isCollapsed;
            localStorage.setItem(STORAGE_KEY_STATE, isCollapsed ? 'collapsed' : 'expanded');
            updateCollapsedState(panel);
        });

        panel.querySelector('[data-puppy-pause]').addEventListener('click', () => {
            isPaused = !isPaused;
            localStorage.setItem(STORAGE_KEY_PAUSED, isPaused ? 'true' : 'false');
            panel.classList.toggle('is-paused', isPaused);
            const btn = panel.querySelector('[data-puppy-pause]');
            btn.textContent = isPaused ? '\u25B6' : '\u23F8';
            btn.title = isPaused ? 'Resume tracking' : 'Pause tracking';
        });
    }

    function updateCollapsedState(panel) {
        panel.classList.toggle('is-collapsed', isCollapsed);
        const toggleBtn = panel.querySelector('[data-puppy-toggle]');
        toggleBtn.textContent = isCollapsed ? '\u25B2' : '\u25BC';
        toggleBtn.title = isCollapsed ? 'Expand panel' : 'Collapse panel';

        const tabs = panel.querySelector('.puppy-tabs');
        const body = panel.querySelector('.puppy-body');
        const footer = panel.querySelector('.puppy-footer');
        if (tabs) tabs.style.display = isCollapsed ? 'none' : '';
        if (body) body.style.display = isCollapsed ? 'none' : '';
        if (footer) footer.style.display = isCollapsed ? 'none' : '';
    }

    // =======================================================================
    // Tabs
    // =======================================================================

    function bindTabs(panel) {
        panel.querySelectorAll('[data-puppy-tab]').forEach(tab => {
            tab.addEventListener('click', () => {
                const tabName = tab.dataset.puppyTab;
                activeTab = tabName;
                localStorage.setItem(STORAGE_KEY_TAB, tabName);

                panel.querySelectorAll('.puppy-tab').forEach(t => t.classList.remove('is-active'));
                panel.querySelectorAll('.puppy-tab-content').forEach(c => c.classList.remove('is-active'));

                tab.classList.add('is-active');
                const content = panel.querySelector('[data-puppy-content="' + tabName + '"]');
                if (content) content.classList.add('is-active');
            });
        });
    }

    // =======================================================================
    // Footer Actions
    // =======================================================================

    function bindFooterActions(panel) {
        panel.querySelector('[data-puppy-clear]').addEventListener('click', () => {
            if (!confirm('Clear your Puppy session trail?')) return;
            clearTrail();
        });
    }

    // =======================================================================
    // Page Detection & Recording
    // =======================================================================

    function recordCurrentPage() {
        if (isPaused) return;

        const config = window.PuppyConfig;
        const pathInfo = config.cpUrl || '';
        const parsed = parseCpRoute(pathInfo);

        sendVisit(parsed);
    }

    function parseCpRoute(path) {
        const segments = path.replace(/^\/+|\/+$/g, '').split('/');
        const result = {
            url: '/' + Craft.cpTrigger + '/' + path,
            type: 'route',
            label: 'Control Panel',
            elementId: null,
            context: null,
        };

        if (!segments[0] || segments[0] === '' || path === 'dashboard') {
            result.label = 'Dashboard';
            result.url = '/' + Craft.cpTrigger + '/dashboard';
            return result;
        }

        const first = segments[0];

        switch (first) {
            case 'entries': {
                result.type = 'entry';
                if (segments.length >= 3) {
                    const slug = segments.slice(2).join('/');
                    const title = extractTitleFromPage() || humanize(slug);
                    result.label = title;
                    result.context = 'Entries: ' + humanize(segments[1]);
                    result.elementId = extractIdFromSlug(slug);
                } else if (segments.length === 2) {
                    result.label = 'Entries: ' + humanize(segments[1]);
                    result.context = 'Entries';
                } else {
                    result.label = 'Entries';
                }
                break;
            }
            case 'assets': {
                result.type = 'asset';
                if (segments.length >= 3) {
                    const title = extractTitleFromPage() || humanize(segments.slice(2).join('/'));
                    result.label = title;
                    result.context = 'Assets: ' + humanize(segments[1]);
                    result.elementId = extractIdFromSlug(segments[2]);
                } else if (segments.length === 2) {
                    result.label = 'Assets: ' + humanize(segments[1]);
                    result.context = 'Assets';
                } else {
                    result.label = 'Assets';
                }
                break;
            }
            case 'categories': {
                result.type = 'category';
                if (segments.length >= 3) {
                    const title = extractTitleFromPage() || humanize(segments.slice(2).join('/'));
                    result.label = title;
                    result.context = 'Categories: ' + humanize(segments[1]);
                    result.elementId = extractIdFromSlug(segments[2]);
                } else if (segments.length === 2) {
                    result.label = 'Categories: ' + humanize(segments[1]);
                } else {
                    result.label = 'Categories';
                }
                break;
            }
            case 'globals': {
                result.type = 'globalset';
                if (segments.length >= 2) {
                    const title = extractTitleFromPage() || humanize(segments[1]);
                    result.label = 'Global: ' + title;
                } else {
                    result.label = 'Globals';
                }
                break;
            }
            case 'users': {
                result.type = 'user';
                if (segments.length >= 2) {
                    const title = extractTitleFromPage() || humanize(segments[1]);
                    result.label = 'User: ' + title;
                    result.elementId = extractIdFromSlug(segments[1]);
                } else {
                    result.label = 'Users';
                }
                break;
            }
            case 'settings':
                result.label = 'Settings' + (segments.length > 1 ? ': ' + humanize(segments.slice(1).join('/')) : '');
                break;
            case 'utilities':
                result.label = 'Utilities' + (segments.length > 1 ? ': ' + humanize(segments[1]) : '');
                break;
            case 'commerce':
                result.label = 'Commerce' + (segments.length > 1 ? ': ' + humanize(segments.slice(1).join('/')) : '');
                break;
            default:
                result.label = humanize(segments.join('/'));
                break;
        }

        return result;
    }

    function extractTitleFromPage() {
        const heading = document.querySelector('#main-content h1, #content-header h1, #header h1');
        if (heading) {
            const text = heading.textContent.trim();
            if (text && text.length < 120) return text;
        }
        return null;
    }

    function extractIdFromSlug(slug) {
        const match = slug.match(/^(\d+)/);
        return match ? parseInt(match[1], 10) : null;
    }

    function humanize(str) {
        return str
            .replace(/[-_]/g, ' ')
            .replace(/\b\w/g, function (c) { return c.toUpperCase(); })
            .replace(/^\d+\s*/, '');
    }

    // =======================================================================
    // API Communication
    // =======================================================================

    function sendVisit(parsed) {
        if (isPaused) return;

        var body = {
            url: parsed.url,
            label: parsed.label,
            type: parsed.type,
        };
        if (parsed.elementId) body.elementId = parsed.elementId;
        if (parsed.context) body.context = parsed.context;

        postAction('puppy/session/record-visit', body);
    }

    function fetchTrail() {
        getAction('puppy/session/get-trail', function (data) {
            if (data) {
                trail = data.trail || [];
                edits = data.edits || [];
                renderAll();
            }
        });
    }

    function clearTrail() {
        postAction('puppy/session/clear', {}, function () {
            trail = [];
            edits = [];
            sessionStart = Date.now();
            renderAll();
        });
    }

    function postAction(action, data, callback) {
        var config = window.PuppyConfig;
        var formData = new FormData();
        formData.append(config.csrfTokenName, config.csrfTokenValue);

        Object.keys(data).forEach(function (key) {
            formData.append(key, data[key]);
        });

        fetch(Craft.getActionUrl(action), {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
        })
        .then(function (res) { return res.json(); })
        .then(function (json) {
            if (callback) callback(json);
        })
        .catch(function () {
            // Silently fail — Puppy should never interrupt the user
        });
    }

    function getAction(action, callback) {
        fetch(Craft.getActionUrl(action), {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
        .then(function (res) { return res.json(); })
        .then(function (json) { callback(json); })
        .catch(function () {
            // Silently fail
        });
    }

    // =======================================================================
    // Polling
    // =======================================================================

    function startPolling() {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(function () {
            fetchTrail();
        }, POLL_INTERVAL);
    }

    // =======================================================================
    // Position Persistence
    // =======================================================================

    function getSavedPosition() {
        try {
            var saved = JSON.parse(localStorage.getItem(STORAGE_KEY_POS));
            if (saved && typeof saved.right === 'number' && typeof saved.bottom === 'number') {
                return saved;
            }
        } catch (e) {
            // Ignore
        }
        return { right: 20, bottom: 20 };
    }

    function savePosition(panel) {
        var pos = {
            right: parseInt(panel.style.right) || 20,
            bottom: parseInt(panel.style.bottom) || 20,
        };
        localStorage.setItem(STORAGE_KEY_POS, JSON.stringify(pos));
    }

    // =======================================================================
    // Utilities
    // =======================================================================

    function formatTime(timestamp) {
        var date = new Date(timestamp * 1000);
        var now = new Date();
        var diffMs = now - date;
        var diffMin = Math.round(diffMs / 60000);

        if (diffMin < 1) return 'just now';
        if (diffMin < 60) return diffMin + 'm ago';
        if (diffMin < 1440) return Math.round(diffMin / 60) + 'h ago';
        return date.toLocaleDateString();
    }

    // =======================================================================
    // Boot
    // =======================================================================

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();

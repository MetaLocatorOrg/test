/**
 * MetaLocator Directory Tabs
 *
 * Provides setupDirectoryTabs() which normalizes Bootstrap tab markup at
 * runtime so that exactly one tab and one tab-pane are active per group,
 * regardless of how many tabs are present or whether the server-rendered
 * markup contains errors (e.g. duplicate .tab-content wrappers).
 *
 * This file is the un-minified source.  The production build inlines it
 * into all.min.js.
 */

/* global console */

(function () {
    'use strict';

    /**
     * Initialise / normalise every tab group found in the document.
     *
     * Safe to call more than once (idempotent) – useful after AJAX loads
     * inject new tab markup into the page.
     */
    function setupDirectoryTabs() {
        // -----------------------------------------------------------------
        // 1.  Detect nested / duplicate .tab-content containers
        // -----------------------------------------------------------------
        var tabContents = document.querySelectorAll('.tab-content');

        tabContents.forEach(function (container) {
            var nested = container.querySelectorAll('.tab-content');
            if (nested.length > 0) {
                console.warn(
                    'MetaLocator: Duplicate tab-content containers detected. Check tab templates.'
                );
            }
        });

        // -----------------------------------------------------------------
        // 2.  Normalise each tab group
        // -----------------------------------------------------------------
        //     A "tab group" is a .nav-tabs list followed (somewhere) by a
        //     .tab-content container whose pane IDs match the nav hrefs.
        // -----------------------------------------------------------------
        var navTabLists = document.querySelectorAll('.nav-tabs');

        navTabLists.forEach(function (navList) {
            var links = navList.querySelectorAll('.nav-link');
            if (links.length === 0) {
                return; // no tabs – no-op
            }

            // Collect all pane IDs referenced by the nav links.
            var paneIds = [];
            links.forEach(function (link) {
                var href = link.getAttribute('href');
                if (href && href.charAt(0) === '#') {
                    paneIds.push(href.substring(1));
                }
            });

            // Find the .tab-content that owns these panes.
            // Walk the DOM forward from the nav list.
            var tabContent = null;
            var sibling = navList.nextElementSibling;
            while (sibling) {
                if (sibling.classList && sibling.classList.contains('tab-content')) {
                    tabContent = sibling;
                    break;
                }
                sibling = sibling.nextElementSibling;
            }

            // Fallback: search the whole document for panes by ID.
            if (!tabContent && paneIds.length > 0) {
                var firstPane = document.getElementById(paneIds[0]);
                if (firstPane) {
                    tabContent = firstPane.parentElement;
                }
            }

            if (!tabContent) {
                return; // nothing to normalise
            }

            // ---------------------------------------------------------------
            // 2a.  Ensure every .nav-link has correct aria-controls (no '#')
            // ---------------------------------------------------------------
            links.forEach(function (link) {
                var href = link.getAttribute('href');
                if (href && href.charAt(0) === '#') {
                    var paneId = href.substring(1);
                    link.setAttribute('aria-controls', paneId);

                    // Verify pane exists
                    if (!document.getElementById(paneId)) {
                        console.warn(
                            'MetaLocator: nav-link references pane "' + paneId +
                            '" which does not exist in the DOM.'
                        );
                    }
                }
            });

            // ---------------------------------------------------------------
            // 2b.  Ensure every tab-pane has aria-labelledby pointing back
            // ---------------------------------------------------------------
            paneIds.forEach(function (paneId) {
                var pane = document.getElementById(paneId);
                if (pane) {
                    pane.setAttribute('aria-labelledby', paneId + '-tab');
                }
            });

            // ---------------------------------------------------------------
            // 2c.  Strip active / show from ALL links and panes first
            // ---------------------------------------------------------------
            links.forEach(function (link) {
                link.classList.remove('active');
                link.setAttribute('aria-selected', 'false');
            });

            var panes = tabContent.querySelectorAll('.tab-pane');
            panes.forEach(function (pane) {
                pane.classList.remove('active', 'show');
                if (!pane.classList.contains('fade')) {
                    pane.classList.add('fade');
                }
            });

            // ---------------------------------------------------------------
            // 2d.  Activate the first tab + pane
            // ---------------------------------------------------------------
            var firstLink = links[0];
            firstLink.classList.add('active');
            firstLink.setAttribute('aria-selected', 'true');

            if (paneIds.length > 0) {
                var firstPaneEl = document.getElementById(paneIds[0]);
                if (firstPaneEl) {
                    firstPaneEl.classList.add('active', 'show');
                }
            }
        });
    }

    // -----------------------------------------------------------------
    // Expose globally so it can be called after AJAX loads too.
    // -----------------------------------------------------------------
    window.setupDirectoryTabs = setupDirectoryTabs;

    // Run on initial DOM ready.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupDirectoryTabs);
    } else {
        setupDirectoryTabs();
    }
})();

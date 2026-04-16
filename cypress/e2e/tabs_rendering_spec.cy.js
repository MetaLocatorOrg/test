/**
 * MetaLocator Tab Control – Rendering & Behaviour Tests
 *
 * Validates that the tab UI renders correctly for various tab counts,
 * that only one pane is visible at a time, and that the defensive JS
 * normalisation (setupDirectoryTabs) works as expected.
 */

describe('Tab Control Rendering', () => {
    // -------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------

    /**
     * Build a self-contained HTML page with N Bootstrap-style tabs and
     * inject it into the Cypress test runner via cy.document().
     *
     * @param {number} count          – number of tabs to render
     * @param {object} [opts]         – optional overrides
     * @param {boolean} opts.duplicateContainer – inject a duplicate .tab-content
     * @param {boolean} opts.allActive          – mark every pane as active show
     * @param {boolean} opts.thirdActive        – mark the 3rd pane active (bug repro)
     * @param {boolean} opts.badAria            – use aria-controls="#id" (incorrect)
     */
    function buildTabPage(count, opts) {
        opts = opts || {};

        let navItems = '';
        let panes = '';

        for (let i = 1; i <= count; i++) {
            const isFirst = i === 1;
            const isThird = i === 3;

            // Nav link
            let linkClass = 'nav-link';
            let ariaSelected = 'false';
            if (opts.allActive || (opts.thirdActive && isThird)) {
                linkClass += ' active';
                ariaSelected = 'true';
            } else if (isFirst && !opts.thirdActive) {
                linkClass += ' active';
                ariaSelected = 'true';
            }

            const ariaControlsVal = opts.badAria ? `#tab${i}` : `tab${i}`;

            navItems += `
                <li class="nav-item" role="presentation">
                    <a class="${linkClass}"
                       id="tab${i}-tab"
                       data-toggle="tab"
                       href="#tab${i}"
                       role="tab"
                       aria-controls="${ariaControlsVal}"
                       aria-selected="${ariaSelected}">Tab ${i}</a>
                </li>`;

            // Pane
            let paneClass = 'tab-pane fade';
            if (opts.allActive || (opts.thirdActive && isThird)) {
                paneClass += ' show active';
            } else if (isFirst && !opts.thirdActive) {
                paneClass += ' show active';
            }

            panes += `
                <div class="${paneClass}"
                     id="tab${i}"
                     role="tabpanel"
                     aria-labelledby="tab${i}-tab">
                    <p>Content for Tab ${i}</p>
                </div>`;
        }

        let tabContent = `<div class="tab-content" id="myTabs-content">${panes}</div>`;
        if (opts.duplicateContainer) {
            // Inject an extra .tab-content wrapping the last pane (the original bug)
            tabContent = `
                <div class="tab-content" id="myTabs-content">
                    ${panes}
                    <div class="tab-content" id="myTabs-content-dup">
                        <div class="tab-pane fade show active" id="tabDup" role="tabpanel">
                            <p>Duplicate container pane</p>
                        </div>
                    </div>
                </div>`;
        }

        return `
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <title>Tab Test</title>
                <link rel="stylesheet"
                      href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
            </head>
            <body>
                <ul class="nav nav-tabs" id="myTabs" role="tablist">
                    ${navItems}
                </ul>
                ${tabContent}

                <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
                <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
                <script src="/html/components/com_locator/assets/directory-tabs.js"></script>
            </body>
            </html>`;
    }

    function visitWithTabs(count, opts) {
        const html = buildTabPage(count, opts);
        // Write to a temp file served by Cypress
        cy.intercept('GET', '/tab-test', {
            statusCode: 200,
            headers: { 'content-type': 'text/html' },
            body: html,
        }).as('tabPage');
        cy.visit('/tab-test');
    }

    // -------------------------------------------------------------------
    // AC1 – Only one tab-pane visible when 4+ tabs are present
    // -------------------------------------------------------------------
    describe('AC1: 4+ tabs – single visible pane at load', () => {
        it('renders 4 tabs with only the first pane active', () => {
            visitWithTabs(4);

            cy.get('.nav-link.active').should('have.length', 1);
            cy.get('.tab-pane.active.show').should('have.length', 1);
            cy.get('.tab-pane.active.show').should('have.id', 'tab1');
        });

        it('renders 6 tabs with only the first pane active', () => {
            visitWithTabs(6);

            cy.get('.nav-link.active').should('have.length', 1);
            cy.get('.tab-pane.active.show').should('have.length', 1);
            cy.get('.tab-pane.active.show').should('have.id', 'tab1');
        });
    });

    // -------------------------------------------------------------------
    // AC2 – First tab active by default
    // -------------------------------------------------------------------
    describe('AC2: first tab is active by default', () => {
        [1, 2, 3, 4, 5].forEach((n) => {
            it(`${n} tab(s) – first tab is active`, () => {
                visitWithTabs(n);

                cy.get('.nav-link').first().should('have.class', 'active');
                cy.get('.nav-link').first().should('have.attr', 'aria-selected', 'true');

                cy.get('.tab-pane').first().should('have.class', 'active');
                cy.get('.tab-pane').first().should('have.class', 'show');
            });
        });
    });

    // -------------------------------------------------------------------
    // AC3 – Clicking any tab shows only its associated pane
    // -------------------------------------------------------------------
    describe('AC3: clicking a tab shows its pane exclusively', () => {
        it('switches pane on click (4 tabs)', () => {
            visitWithTabs(4);

            // Click second tab
            cy.get('#tab2-tab').click();
            cy.get('.tab-pane.active.show').should('have.length', 1);
            cy.get('#tab2').should('have.class', 'active');
            cy.get('#tab1').should('not.have.class', 'active');

            // Click fourth tab
            cy.get('#tab4-tab').click();
            cy.get('.tab-pane.active.show').should('have.length', 1);
            cy.get('#tab4').should('have.class', 'active');
            cy.get('#tab2').should('not.have.class', 'active');

            // Click first tab again
            cy.get('#tab1-tab').click();
            cy.get('.tab-pane.active.show').should('have.length', 1);
            cy.get('#tab1').should('have.class', 'active');
        });
    });

    // -------------------------------------------------------------------
    // AC4 – Single .tab-content container per group
    // -------------------------------------------------------------------
    describe('AC4: one .tab-content per group', () => {
        it('well-formed markup has exactly one .tab-content', () => {
            visitWithTabs(4);
            cy.get('.tab-content').should('have.length', 1);
        });
    });

    // -------------------------------------------------------------------
    // AC5 – No stale active / show on non-selected panes
    // -------------------------------------------------------------------
    describe('AC5: non-selected panes have no active/show', () => {
        it('normalises when all panes are marked active (malformed markup)', () => {
            visitWithTabs(4, { allActive: true });

            // After setupDirectoryTabs runs, only the first should be active
            cy.get('.tab-pane.active.show').should('have.length', 1);
            cy.get('.tab-pane.active.show').should('have.id', 'tab1');
        });

        it('normalises when third pane is marked active (original bug)', () => {
            visitWithTabs(4, { thirdActive: true });

            cy.get('.tab-pane.active.show').should('have.length', 1);
            cy.get('.tab-pane.active.show').should('have.id', 'tab1');
        });
    });

    // -------------------------------------------------------------------
    // AC6 – aria-controls / href match pane IDs
    // -------------------------------------------------------------------
    describe('AC6: aria-controls and href match pane IDs', () => {
        it('aria-controls does not contain "#"', () => {
            visitWithTabs(4, { badAria: true });

            // setupDirectoryTabs should normalise aria-controls to remove '#'
            cy.get('.nav-link').each(($link) => {
                const ctrl = $link.attr('aria-controls');
                expect(ctrl).not.to.include('#');
                // The referenced pane should exist
                cy.get(`#${ctrl}`).should('exist');
            });
        });

        it('href and aria-controls reference matching IDs', () => {
            visitWithTabs(4);

            cy.get('.nav-link').each(($link) => {
                const href = $link.attr('href');
                const ctrl = $link.attr('aria-controls');
                expect(href).to.equal(`#${ctrl}`);
            });
        });
    });

    // -------------------------------------------------------------------
    // AC7 – No regression for 1–3 tab configurations
    // -------------------------------------------------------------------
    describe('AC7: regression – 1 to 3 tabs still work', () => {
        it('1 tab – content visible, no errors', () => {
            visitWithTabs(1);

            cy.get('.nav-link').should('have.length', 1);
            cy.get('.tab-pane').should('have.length', 1);
            cy.get('.tab-pane.active.show').should('have.length', 1);
        });

        it('3 tabs – switching works correctly', () => {
            visitWithTabs(3);

            cy.get('#tab2-tab').click();
            cy.get('.tab-pane.active.show').should('have.length', 1);
            cy.get('#tab2').should('have.class', 'active');

            cy.get('#tab3-tab').click();
            cy.get('.tab-pane.active.show').should('have.length', 1);
            cy.get('#tab3').should('have.class', 'active');
        });
    });

    // -------------------------------------------------------------------
    // AC8 – Console warning for nested .tab-content
    // -------------------------------------------------------------------
    describe('AC8: console warns on nested .tab-content', () => {
        it('logs warning when duplicate tab-content is detected', () => {
            visitWithTabs(3, { duplicateContainer: true });

            cy.window().then((win) => {
                cy.spy(win.console, 'warn').as('consoleWarn');
            });

            // Re-run normalisation so the spy can capture
            cy.window().then((win) => {
                win.setupDirectoryTabs();
            });

            cy.get('@consoleWarn').should(
                'be.calledWithMatch',
                /Duplicate tab-content containers detected/
            );
        });
    });
});

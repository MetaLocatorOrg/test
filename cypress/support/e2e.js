// Cypress E2E Support File
// https://on.cypress.io/configuration

// Import commands
// import './commands';

// Prevent Cypress from failing tests on uncaught exceptions from the app
Cypress.on('uncaught:exception', () => {
    return false;
});

/**
 * MetaLocator API Sample Application
 * 
 * This application demonstrates how to use the MetaLocator /search API
 * with proper rate limiting and error handling.
 */

// Application state
const MetaLocatorSampleApp = {
    config: null,
    requestLog: [],
    rateLimitWindow: 60000, // 1 minute in milliseconds

    /**
     * Initialize the application
     */
    init: function() {
        this.loadConfig()
            .then(() => {
                this.attachEventHandlers();
                this.updateRateLimitDisplay();
                this.loadStateOptions();
                this.showStatus('Application initialized. Ready to search.', 'success');
            })
            .catch(error => {
                this.showStatus('Error loading configuration: ' + error.message, 'error');
                console.error('Configuration error:', error);
            });
    },

    /**
     * Load configuration from config.json
     */
    loadConfig: function() {
        return $.getJSON('config.json')
            .then(config => {
                this.config = config;
                if (!config.apiKey || config.apiKey === 'YOUR_PUBLIC_API_KEY_HERE') {
                    throw new Error('Please configure your API key in config.json');
                }
                if (!config.itemId || config.itemId === 'YOUR_ITEM_ID_HERE') {
                    throw new Error('Please configure your Item ID in config.json');
                }
                return config;
            });
    },

    /**
     * Load the list of states from the API and populate the state dropdown
     */
    loadStateOptions: function() {
        const url = `${this.config.apiBaseUrl}/interfaces/${this.config.itemId}/get-field-data-list`;

        $.ajax({
            url: url,
            dataType: 'json',
            headers: {
                'X-API-Key': this.config.apiKey
            },
            timeout: 10000,
            success: (data) => {
                const stateSelect = $('#state');
                // API may return a top-level array or an object with a `data` array.
                // Each item may be a plain string or an object with a `state`, `value`, or `name` property.
                const items = Array.isArray(data) ? data : (data && Array.isArray(data.data) ? data.data : []);
                const uniqueStates = [...new Set(items.map(item => {
                    return (typeof item === 'string') ? item : (item.state || item.value || item.name || '');
                }).filter(Boolean))].sort();

                uniqueStates.forEach(state => {
                    stateSelect.append($('<option></option>').val(state).text(state));
                });

                stateSelect.prop('disabled', false);
            },
            error: (jqXHR, textStatus, errorThrown) => {
                console.error('Error loading state options:', textStatus, errorThrown);
            }
        });
    },

    /**
     * Attach event handlers to UI elements
     */
    attachEventHandlers: function() {
        $('#search-button').on('click', () => this.performSearch());
        $('#clear-button').on('click', () => this.clearResults());
        
        // Allow Enter key to submit search
        $('input').on('keypress', (e) => {
            if (e.which === 13) {
                this.performSearch();
            }
        });

        // Lead modal close handlers
        $('#lead-modal').on('click', '.modal-close, .modal-close-btn', () => this.closeLeadModal());
        $('#lead-modal .modal-overlay').on('click', () => this.closeLeadModal());
        $(document).on('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeLeadModal();
                this.closeReviewModal();
            }
        });

        // Lead form submit
        $('#lead-submit').on('click', () => this.submitLead());

        // Review modal close handlers
        $('#review-modal').on('click', '.modal-close, .modal-close-btn', () => this.closeReviewModal());
        $('#review-modal .modal-overlay').on('click', () => this.closeReviewModal());

        // Review form submit
        $('#review-submit').on('click', () => this.submitReview());
    },

    /**
     * Check if we're within rate limits
     */
    checkRateLimit: function() {
        const now = Date.now();
        const windowStart = now - this.rateLimitWindow;
        
        // Remove requests older than the window
        this.requestLog = this.requestLog.filter(time => time > windowStart);
        
        // Check if we've exceeded the rate limit
        if (this.requestLog.length >= this.config.rateLimitPerMinute) {
            const oldestRequest = Math.min(...this.requestLog);
            const waitTime = Math.ceil((oldestRequest + this.rateLimitWindow - now) / 1000);
            return {
                allowed: false,
                waitTime: waitTime
            };
        }
        
        return { allowed: true };
    },

    /**
     * Log a request for rate limiting purposes
     */
    logRequest: function() {
        this.requestLog.push(Date.now());
        this.updateRateLimitDisplay();
    },

    /**
     * Update the rate limit display
     */
    updateRateLimitDisplay: function() {
        if (!this.config) return;
        
        const now = Date.now();
        const windowStart = now - this.rateLimitWindow;
        this.requestLog = this.requestLog.filter(time => time > windowStart);
        
        const remaining = this.config.rateLimitPerMinute - this.requestLog.length;
        $('#rate-limit-info').html(
            `Rate Limit: ${this.requestLog.length}/${this.config.rateLimitPerMinute} requests in last minute (${remaining} remaining)`
        );
    },

    /**
     * Build the search URL with parameters
     */
    buildSearchURL: function(params) {
        const url = `${this.config.apiBaseUrl}/interfaces/${this.config.itemId}/search`;
        const queryParams = new URLSearchParams();

        if (params.postal_code) {
            queryParams.append('postal_code', params.postal_code);
        }
        
        if (params.radius) {
            queryParams.append('radius', params.radius);
        } else {
            queryParams.append('radius', this.config.defaultRadius);
        }
        
        if (params.limit) {
            queryParams.append('limit', params.limit);
        } else {
            queryParams.append('limit', this.config.defaultLimit);
        }
        
        if (params.keyword) {
            queryParams.append('keyword', params.keyword);
        }

        if (params.state) {
            queryParams.append('state', params.state);
        }

        return `${url}?${queryParams.toString()}`;
    },

    /**
     * Perform a search request
     */
    performSearch: function() {
        // Check rate limits
        const rateLimitCheck = this.checkRateLimit();
        if (!rateLimitCheck.allowed) {
            this.showStatus(
                `Rate limit exceeded. Please wait ${rateLimitCheck.waitTime} seconds before trying again.`,
                'warning'
            );
            return;
        }

        // Get search parameters
        const params = {
            postal_code: $('#postal_code').val().trim(),
            radius: $('#radius').val().trim(),
            limit: $('#limit').val().trim(),
            keyword: $('#keyword').val().trim(),
            state: $('#state').val()
        };

        // Build URL and perform search
        const searchURL = this.buildSearchURL(params);
        
        this.showStatus('Searching...', 'info');
        $('#search-button').prop('disabled', true);

        // Log the request
        this.logRequest();

        // Make the API call
        $.ajax({
            url: searchURL,
            dataType: 'json',
            headers: {
                'X-API-Key': this.config.apiKey,
            },
            timeout: 10000,
            success: (data) => {
                this.handleSearchSuccess(data, params);
            },
            error: (jqXHR, textStatus, errorThrown) => {
                this.handleSearchError(jqXHR, textStatus, errorThrown);
            },
            complete: () => {
                $('#search-button').prop('disabled', false);
            }
        });
    },

    /**
     * Handle successful search response
     */
    handleSearchSuccess: function(data, params) {
        console.log('Search response:', data);
        
        if (!data || !data.results || data.results.length === 0) {
            this.showStatus('No results found.', 'warning');
            this.displayResults([]);
            return;
        }

        const count = data.results.length;
        this.showStatus(
            `Found ${count} location${count !== 1 ? 's' : ''} within ${params.radius || this.config.defaultRadius} miles of ${params.postal_code}`,
            'success'
        );
        
        this.displayResults(data.results);
    },

    /**
     * Handle search error
     */
    handleSearchError: function(jqXHR, textStatus, errorThrown) {
        console.error('Search error:', textStatus, errorThrown);
        let errorMessage = 'Error performing search. ' + jqXHR?.responseJSON?.error ?? 'Unknown error';

        if (textStatus === 'timeout') {
            errorMessage += 'Request timed out.';
        } else if (textStatus === 'parsererror') {
            errorMessage += 'Error parsing response.';
        } else {
            errorMessage += textStatus || 'Unknown error.';
        }
        
        this.showStatus(errorMessage, 'error');
    },

    /**
     * Display search results
     */
    displayResults: function(locations) {
        const container = $('#results-container');
        container.empty();

        if (locations.length === 0) {
            container.html('<p class="placeholder">No locations found.</p>');
            return;
        }

        const resultsList = $('<div class="results-list"></div>');

        locations.forEach((location, index) => {
            const card = this.createLocationCard(location, index + 1);
            resultsList.append(card);
        });

        container.append(resultsList);
    },

    /**
     * Create a location card element
     */
    createLocationCard: function(location, index) {
        const card = $('<div class="location-card"></div>');
        
        const header = $('<div class="location-header"></div>');
        header.append(`<span class="location-number">#${index}</span>`);
        header.append(`<h3>${this.escapeHtml(location.name || 'Unnamed Location')}</h3>`);
        
        if (location.distance) {
            header.append(`<span class="distance">${parseFloat(location.distance).toFixed(2)} miles</span>`);
        }
        
        card.append(header);

        const details = $('<div class="location-details"></div>');
        
        if (location.address) {
            const addressLines = [];
            if (location.address) addressLines.push(this.escapeHtml(location.address));
            if (location.address2) addressLines.push(this.escapeHtml(location.address2));
            if (location.city || location.state || location.postalcode) {
                const cityLine = [
                    location.city,
                    location.state,
                    location.postalcode
                ].filter(Boolean).join(', ');
                if (cityLine) addressLines.push(this.escapeHtml(cityLine));
            }
            if (location.country) addressLines.push(this.escapeHtml(location.country));
            
            if (addressLines.length > 0) {
                details.append(`<p class="address">${addressLines.join('<br>')}</p>`);
            }
        }

        if (location.phone) {
            details.append(`<p class="phone">Phone: ${this.escapeHtml(location.phone)}</p>`);
        }

        if (location.email) {
            details.append(`<p class="email">Email: <a href="mailto:${this.escapeHtml(location.email)}">${this.escapeHtml(location.email)}</a></p>`);
        }

        if (location.link) {
            details.append(`<p class="website"><a href="${this.escapeHtml(location.link)}" target="_blank">Visit Website</a></p>`);
        }

        if (location.lat && location.lng) {
            details.append(`<p class="coordinates">Coordinates: ${location.lat}, ${location.lng}</p>`);
        }

        card.append(details);

        const actions = $('<div class="location-card-actions"></div>');
        const contactBtn = $('<button class="btn btn-primary btn-contact">Contact</button>');
        contactBtn.on('click', () => this.openLeadModal(location));
        actions.append(contactBtn);

        const reviewBtn = $('<button class="btn btn-accent btn-review">Write Review</button>');
        reviewBtn.on('click', () => this.openReviewModal(location));
        actions.append(reviewBtn);

        card.append(actions);
        
        return card;
    },

    /**
     * Open the lead modal for a given location
     */
    openLeadModal: function(location) {
        this._leadLocationId = location.id || location.location_id || null;
        $('#lead-form')[0].reset();
        $('#lead-status').hide().removeClass('status-success status-error status-info');
        $('#lead-submit').prop('disabled', false).text('Send');
        $('#lead-modal').addClass('is-open');
        $('#lead-modal .modal-dialog').find('input, textarea').first().focus();
    },

    /**
     * Close the lead modal
     */
    closeLeadModal: function() {
        $('#lead-modal').removeClass('is-open');
        this._leadLocationId = null;
    },

    /**
     * Open the review modal for a given location
     */
    openReviewModal: function(location) {
        this._reviewLocationId = location.id || location.location_id || null;
        $('#review-form')[0].reset();
        $('#review-status').hide().removeClass('status-success status-error status-info');
        $('#review-submit').prop('disabled', false).text('Submit Review');
        $('#review-modal').addClass('is-open');
        $('#review-modal .modal-dialog').find('input, textarea').first().focus();
    },

    /**
     * Close the review modal
     */
    closeReviewModal: function() {
        $('#review-modal').removeClass('is-open');
        this._reviewLocationId = null;
    },

    /**
     * Submit the review form
     */
    submitReview: function() {
        const reviewtitle = $('#review-title').val().trim();
        const fullname = $('#review-fullname').val().trim();
        const rating = $('#review-rating').val().trim();
        const published = $('#review-published').val().trim();
        const emailaddress = $('#review-emailaddress').val().trim();
        const url = $('#review-url').val().trim();
        const comments = $('#review-comments').val().trim();
        const custom1 = $('#review-custom1').val().trim();
        const custom2 = $('#review-custom2').val().trim();

        if (!reviewtitle || !fullname || !rating || !emailaddress || !comments) {
            this.showReviewStatus('Please fill in all required fields (Title, Name, Rating, Email, Comments).', 'error');
            return;
        }

        const payload = {
            reviewtitle,
            fullname,
            rating,
            published,
            emailaddress,
            url,
            contactfield: 'email',
            comments,
            custom1,
            custom2,
            location_id: this._reviewLocationId
        };

        const apiUrl = `${this.config.apiBaseUrl}/interfaces/${this.config.itemId}/reviews`;

        $('#review-submit').prop('disabled', true).text('Submitting...');

        $.ajax({
            url: apiUrl,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            headers: {
                'X-API-Key': this.config.apiKey
            },
            timeout: 10000,
            success: (data) => {
                const createdId = data?.id ?? data?.review_id ?? null;
                const successMsg = createdId
                    ? `Review submitted successfully! Review ID: ${createdId}`
                    : 'Review submitted successfully!';
                this.showReviewStatus(successMsg, 'success');
                $('#review-form')[0].reset();
                $('#review-submit').text('Submitted');
            },
            error: (jqXHR) => {
                const errMsg = (jqXHR.responseJSON && jqXHR.responseJSON.error)
                    ? jqXHR.responseJSON.error
                    : 'An error occurred. Please try again.';
                this.showReviewStatus('Error: ' + errMsg, 'error');
                $('#review-submit').prop('disabled', false).text('Submit Review');
            }
        });
    },

    /**
     * Show a status message inside the review modal
     */
    showReviewStatus: function(message, type) {
        const el = $('#review-status');
        el.removeClass('status-success status-error status-info status-warning');
        el.addClass('status-' + type);
        el.text(message).show();
    },

    /**
     * Submit the lead form
     */
    submitLead: function() {
        const subject = $('#lead-subject').val().trim();
        const fromname = $('#lead-fromname').val().trim();
        const fromemail = $('#lead-fromemail').val().trim();
        const fromphone = $('#lead-fromphone').val().trim();
        const message = $('#lead-message').val().trim();
        const custom1 = $('#lead-custom1').val().trim();
        const custom2 = $('#lead-custom2').val().trim();

        if (!subject || !fromname || !fromemail || !message) {
            this.showLeadStatus('Please fill in all required fields (Subject, Name, Email, Message).', 'error');
            return;
        }

        const payload = {
            subject,
            fromname,
            fromemail,
            fromphone,
            contactfield: 'email',
            message,
            custom1,
            custom2,
            location_id: this._leadLocationId
        };

        const url = `${this.config.apiBaseUrl}/interfaces/${this.config.itemId}/leads`;

        $('#lead-submit').prop('disabled', true).text('Sending...');

        $.ajax({
            url: url,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            headers: {
                'X-API-Key': this.config.apiKey
            },
            timeout: 10000,
            success: () => {
                this.showLeadStatus('Your message has been sent successfully!', 'success');
                $('#lead-form')[0].reset();
                $('#lead-submit').text('Sent');
            },
            error: (jqXHR) => {
                const errMsg = (jqXHR.responseJSON && jqXHR.responseJSON.error)
                    ? jqXHR.responseJSON.error
                    : 'An error occurred. Please try again.';
                this.showLeadStatus('Error: ' + errMsg, 'error');
                $('#lead-submit').prop('disabled', false).text('Send');
            }
        });
    },

    /**
     * Show a status message inside the lead modal
     */
    showLeadStatus: function(message, type) {
        const el = $('#lead-status');
        el.removeClass('status-success status-error status-info status-warning');
        el.addClass('status-' + type);
        el.text(message).show();
    },

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml: function(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    },

    /**
     * Show status message
     */
    showStatus: function(message, type = 'info') {
        const statusEl = $('#status-message');
        statusEl.removeClass('status-info status-success status-warning status-error');
        statusEl.addClass(`status-${type}`);
        statusEl.text(message);
        statusEl.fadeIn();

        // Auto-hide success messages after 5 seconds
        if (type === 'success') {
            setTimeout(() => {
                statusEl.fadeOut();
            }, 5000);
        }
    },

    /**
     * Clear search results
     */
    clearResults: function() {
        $('#postal_code').val('');
        $('#radius').val('');
        $('#limit').val('');
        $('#keyword').val('');
        $('#state').val('');
        $('#results-container').html('<p class="placeholder">Enter search criteria and click "Search" to view results.</p>');
        this.showStatus('Form cleared.', 'info');
    }
};

// Initialize the application when document is ready
$(document).ready(() => {
    MetaLocatorSampleApp.init();
});

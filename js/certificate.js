/**
 * @file plugins/generic/reviewerCertificate/js/certificate.js
 *
 * Copyright (c) 2024
 * Distributed under the GNU GPL v3.
 *
 * JavaScript for reviewer certificate functionality
 */

(function($) {
    'use strict';

    /**
     * Certificate download handler
     */
    var CertificateHandler = {

        /**
         * Initialize certificate functionality
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Handle certificate download button clicks
            $(document).on('click', '.certificate-download-button', function(e) {
                var $button = $(this);
                var url = $button.attr('href');

                // Track download
                CertificateHandler.trackDownload(url);

                // Show loading state
                $button.addClass('loading');
                $button.find('span').removeClass('fa-certificate').addClass('fa-spinner fa-spin');
            });

            // Color picker helpers for settings form
            if ($('#textColorR').length) {
                CertificateHandler.initColorPicker();
            }
        },

        /**
         * Track certificate download
         * @param {string} url
         */
        trackDownload: function(url) {
            // Send analytics event if available
            if (typeof gtag !== 'undefined') {
                gtag('event', 'certificate_download', {
                    'event_category': 'reviewer_engagement',
                    'event_label': 'Certificate Download'
                });
            }

            // Log for internal tracking
            console.log('Certificate download initiated:', url);
        },

        /**
         * Initialize color picker for settings form
         */
        initColorPicker: function() {
            var $colorInputs = $('#textColorR, #textColorG, #textColorB');
            var $preview = $('<div id="colorPreview"></div>').css({
                'width': '50px',
                'height': '50px',
                'border': '1px solid #ccc',
                'display': 'inline-block',
                'margin-left': '10px',
                'border-radius': '3px'
            });

            $colorInputs.last().parent().append($preview);

            var updateColorPreview = function() {
                var r = parseInt($('#textColorR').val()) || 0;
                var g = parseInt($('#textColorG').val()) || 0;
                var b = parseInt($('#textColorB').val()) || 0;

                // Validate RGB values
                r = Math.max(0, Math.min(255, r));
                g = Math.max(0, Math.min(255, g));
                b = Math.max(0, Math.min(255, b));

                $preview.css('background-color', 'rgb(' + r + ',' + g + ',' + b + ')');
            };

            $colorInputs.on('input change', updateColorPreview);
            updateColorPreview();
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        CertificateHandler.init();
    });

    // Make CertificateHandler globally accessible
    window.ReviewerCertificate = CertificateHandler;

})(jQuery);

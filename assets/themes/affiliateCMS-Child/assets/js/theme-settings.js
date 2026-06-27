/**
 * AffiliateCMS Child - Theme Settings
 *
 * CodeMirror initialization + tab switching
 */
(function($) {
    'use strict';

    var editors = {};

    function initEditor(textareaId, settings) {
        if (!settings || settings === false) {
            return null;
        }

        var textarea = document.getElementById(textareaId);
        if (!textarea) {
            return null;
        }

        return wp.codeEditor.initialize(textarea, settings);
    }

    function initTabs() {
        var $tabs = $('.nav-tab-wrapper .nav-tab');
        var $contents = $('.acmsc-tab-content');

        $tabs.on('click', function(e) {
            e.preventDefault();

            var $this = $(this);
            var href = $this.attr('href');
            var tab = href.split('tab=')[1];

            // Update active tab
            $tabs.removeClass('nav-tab-active');
            $this.addClass('nav-tab-active');

            // Show/hide content
            $contents.hide();
            $('#tab-' + tab).show();

            // Update hidden input
            $('input[name="active_tab"]').val(tab);

            // Update URL without reload
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', href);
            }

            // Refresh CodeMirror (fix blank editor on hidden tabs)
            setTimeout(function() {
                $.each(editors, function(key, editor) {
                    if (editor && editor.codemirror) {
                        editor.codemirror.refresh();
                    }
                });
            }, 10);
        });
    }

    function initDisplaySettings() {
        var $modeRadios = $('input[name="acmsc_theme_mode"]');
        var $timeSettings = $('#acmsc-time-settings');
        var $modeOptions = $('.acmsc-mode-option');

        $modeRadios.on('change', function() {
            // Show/hide time settings
            if ($(this).val() === 'time') {
                $timeSettings.slideDown(200);
            } else {
                $timeSettings.slideUp(200);
            }

            // Update active state
            $modeOptions.removeClass('acmsc-mode-option--active');
            $(this).closest('.acmsc-mode-option').addClass('acmsc-mode-option--active');
        });
    }

    $(function() {
        initTabs();
        initDisplaySettings();

        if (typeof acmscEditorSettings !== 'undefined') {
            editors.customCss   = initEditor('acmsc_custom_css', acmscEditorSettings.css);
            editors.codeHead    = initEditor('acmsc_code_head', acmscEditorSettings.html);
            editors.codeBody    = initEditor('acmsc_code_body_open', acmscEditorSettings.html);
            editors.codeFooter  = initEditor('acmsc_code_footer', acmscEditorSettings.html);
        }
    });

})(jQuery);

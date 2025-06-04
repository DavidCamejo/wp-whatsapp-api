/**
 * Debug Panel for WhatsApp Integration Admin
 * 
 * This script helps diagnose UI rendering issues in real time
 * and provides diagnostic information in the console and on screen.
 * 
 * Version: 1.0.0
 */

(function() {
    'use strict';

    // Debug state
    const debugState = {
        scriptsLoaded: {},
        domReady: false,
        uiRendered: false,
        errors: [],
        warnings: []
    };

    // Check if we're in admin area
    const isAdminPage = document.body && document.body.classList.contains('wp-admin');
    
    // Create visual debugging panel
    function createDebugPanel() {
        // Check if panel already exists
        if (document.getElementById('wpwa-debug-panel')) {
            return;
        }
        
        // Create debug panel container
        const debugPanel = document.createElement('div');
        debugPanel.id = 'wpwa-debug-panel';
        debugPanel.style.position = 'fixed';
        debugPanel.style.bottom = '10px';
        debugPanel.style.right = '10px';
        debugPanel.style.width = '300px';
        debugPanel.style.maxHeight = '200px';
        debugPanel.style.overflow = 'auto';
        debugPanel.style.backgroundColor = 'rgba(0,0,0,0.8)';
        debugPanel.style.color = '#fff';
        debugPanel.style.padding = '10px';
        debugPanel.style.borderRadius = '5px';
        debugPanel.style.fontSize = '12px';
        debugPanel.style.fontFamily = 'monospace';
        debugPanel.style.zIndex = '9999';
        
        // Add header
        const header = document.createElement('div');
        header.style.fontWeight = 'bold';
        header.style.borderBottom = '1px solid #555';
        header.style.marginBottom = '5px';
        header.style.paddingBottom = '5px';
        header.textContent = 'WhatsApp API Debug Panel';
        
        // Add toggle button
        const toggleBtn = document.createElement('button');
        toggleBtn.textContent = 'Hide';
        toggleBtn.style.float = 'right';
        toggleBtn.style.border = '1px solid #555';
        toggleBtn.style.background = '#333';
        toggleBtn.style.color = '#fff';
        toggleBtn.style.padding = '2px 5px';
        toggleBtn.style.cursor = 'pointer';
        toggleBtn.style.fontSize = '10px';
        toggleBtn.onclick = function() {
            const content = document.getElementById('wpwa-debug-content');
            if (content.style.display === 'none') {
                content.style.display = 'block';
                toggleBtn.textContent = 'Hide';
            } else {
                content.style.display = 'none';
                toggleBtn.textContent = 'Show';
            }
        };
        
        header.appendChild(toggleBtn);
        debugPanel.appendChild(header);
        
        // Add content area
        const content = document.createElement('div');
        content.id = 'wpwa-debug-content';
        debugPanel.appendChild(content);
        
        // Add to DOM
        document.body.appendChild(debugPanel);
        
        return content;
    }
    
    // Log messages to debug panel
    function logToPanel(message, type = 'info') {
        const content = document.getElementById('wpwa-debug-content');
        if (!content) return;
        
        const log = document.createElement('div');
        log.style.marginBottom = '3px';
        log.style.borderLeft = '3px solid ' + (type === 'error' ? '#f55' : type === 'warning' ? '#fa5' : '#5af');
        log.style.paddingLeft = '5px';
        
        if (typeof message === 'object') {
            log.textContent = JSON.stringify(message);
        } else {
            log.textContent = message;
        }
        
        content.appendChild(log);
        
        // Auto-scroll to bottom
        content.scrollTop = content.scrollHeight;
    }
    
    // Check script loading status
    function checkScripts() {
        // Check for jQuery
        debugState.scriptsLoaded.jquery = typeof jQuery !== 'undefined';
        
        // Check for jQuery UI
        debugState.scriptsLoaded.jqueryUI = debugState.scriptsLoaded.jquery && 
                                            typeof jQuery.ui !== 'undefined';
        
        // Check for jQuery UI Tabs
        debugState.scriptsLoaded.jqueryUITabs = debugState.scriptsLoaded.jqueryUI && 
                                                typeof jQuery.ui.tabs !== 'undefined';
        
        return debugState.scriptsLoaded;
    }
    
    // Check DOM elements
    function checkDOMElements() {
        const elements = {
            adminTabs: !!document.getElementById('wpwa-admin-tabs'),
            settingsTab: !!document.getElementById('wpwa-tab-settings'),
            logsTab: !!document.getElementById('wpwa-tab-logs'),
            sessionsTab: !!document.getElementById('wpwa-tab-sessions'),
            tabNav: !!document.querySelector('.wpwa-tab-nav'),
            forms: document.querySelectorAll('form').length,
            formInputs: document.querySelectorAll('input, select, textarea').length
        };
        
        return elements;
    }
    
    // Initialize debug system
    function init() {
        // Check if we're on a page where debugging is needed
        if (!document.querySelector('.wpwa-settings-wrap') && 
            !document.querySelector('.wpwa-frontend-admin-panel')) {
            return;
        }
        
        console.log('WPWA Debug: Initializing debug panel');
        
        // Check for DOM readiness
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            debugState.domReady = true;
            setupDebugPanel();
        } else {
            document.addEventListener('DOMContentLoaded', function() {
                debugState.domReady = true;
                setupDebugPanel();
            });
        }
        
        // Additional check in case DOMContentLoaded already fired
        window.addEventListener('load', function() {
            debugState.domReady = true;
            if (!document.getElementById('wpwa-debug-panel')) {
                setupDebugPanel();
            }
        });
    }
    
    // Set up debug panel and run initial checks
    function setupDebugPanel() {
        // Don't set up if document.body isn't available yet
        if (!document.body) {
            setTimeout(setupDebugPanel, 100);
            return;
        }
        
        // Create debug panel
        const debugContent = createDebugPanel();
        
        // Initial checks
        const scriptStatus = checkScripts();
        const domElements = checkDOMElements();
        
        // Log script status to console
        console.log('WPWA Debug: Script Status', scriptStatus);
        console.log('WPWA Debug: DOM Elements', domElements);
        
        // Log to visual panel
        logToPanel('WhatsApp Integration Debug v1.0', 'info');
        logToPanel(`WordPress Version: ${wpwaDebug?.wpVersion || 'unknown'}`, 'info');
        logToPanel(`Plugin Version: ${wpwaDebug?.pluginVersion || 'unknown'}`, 'info');
        
        // Check jQuery and dependencies
        if (!scriptStatus.jquery) {
            const msg = 'jQuery not loaded!';
            console.error('WPWA Debug:', msg);
            logToPanel(msg, 'error');
            debugState.errors.push(msg);
        } else {
            logToPanel('jQuery loaded ✓', 'info');
            
            // Only check jQuery UI if jQuery is present
            if (!scriptStatus.jqueryUI) {
                const msg = 'jQuery UI not loaded!';
                console.warn('WPWA Debug:', msg);
                logToPanel(msg, 'warning');
                debugState.warnings.push(msg);
            } else {
                logToPanel('jQuery UI loaded ✓', 'info');
            }
        }
        
        // Check DOM elements
        if (!domElements.adminTabs) {
            const msg = 'Admin tabs container not found!';
            console.warn('WPWA Debug:', msg);
            logToPanel(msg, 'warning');
            debugState.warnings.push(msg);
        } else {
            logToPanel('Admin tabs found ✓', 'info');
        }
        
        // Check form elements
        if (domElements.forms === 0) {
            const msg = 'No forms found on page!';
            console.warn('WPWA Debug:', msg);
            logToPanel(msg, 'warning');
            debugState.warnings.push(msg);
        } else {
            logToPanel(`Found ${domElements.forms} forms with ${domElements.formInputs} inputs ✓`, 'info');
        }
        
        // Monitor for DOM changes - check if UI appears
        setupMutationObserver();
    }
    
    // Set up mutation observer to watch for UI changes
    function setupMutationObserver() {
        // Don't set up if MutationObserver is not supported
        if (!window.MutationObserver) return;
        
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    // Check if our UI elements appear in the DOM
                    const elements = checkDOMElements();
                    
                    // Only log if we find something new
                    if (elements.adminTabs && !debugState.uiRendered) {
                        logToPanel('Admin tabs rendered ✓', 'info');
                        debugState.uiRendered = true;
                        
                        // Check visibility of elements
                        setTimeout(checkElementVisibility, 1000);
                    }
                }
            });
        });
        
        // Start observing
        observer.observe(document.body, { childList: true, subtree: true });
    }
    
    // Check if elements are visibly rendered
    function checkElementVisibility() {
        const tabsContainer = document.getElementById('wpwa-admin-tabs');
        if (!tabsContainer) return;
        
        // Check tab visibility
        const tabs = tabsContainer.querySelectorAll('[id^="wpwa-tab-"]');
        
        tabs.forEach(function(tab) {
            const style = window.getComputedStyle(tab);
            const isVisible = style.display !== 'none' && style.visibility !== 'hidden';
            const hasHeight = tab.offsetHeight > 0;
            
            if (!isVisible || !hasHeight) {
                const msg = `Tab ${tab.id} is not visible!`;
                console.warn('WPWA Debug:', msg);
                logToPanel(msg, 'warning');
            }
        });
        
        // Check form field visibility
        const inputs = document.querySelectorAll('.wpwa-form-group input, .wpwa-form-group select, .wpwa-form-group textarea');
        inputs.forEach(function(input) {
            const style = window.getComputedStyle(input);
            const isVisible = style.display !== 'none' && style.visibility !== 'hidden';
            const hasSize = input.offsetWidth > 0 || input.offsetHeight > 0;
            
            if (!isVisible || !hasSize) {
                const id = input.id || input.name || 'unknown';
                const msg = `Form field ${id} is not visible!`;
                console.warn('WPWA Debug:', msg);
                logToPanel(msg, 'warning');
            }
        });
    }
    
    // API for external scripts to use debug panel
    window.wpwaDebug = {
        log: function(message) {
            console.log('WPWA Debug:', message);
            logToPanel(message, 'info');
        },
        warn: function(message) {
            console.warn('WPWA Debug:', message);
            logToPanel(message, 'warning');
            debugState.warnings.push(message);
        },
        error: function(message) {
            console.error('WPWA Debug:', message);
            logToPanel(message, 'error');
            debugState.errors.push(message);
        },
        getState: function() {
            return debugState;
        },
        refreshChecks: function() {
            checkScripts();
            checkDOMElements();
            checkElementVisibility();
        }
    };
    
    // Start initialization
    init();
    
})();
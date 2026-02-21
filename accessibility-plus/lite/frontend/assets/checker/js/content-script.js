/**
 * Accessibility Checker Content Script
 * Handles Shadow DOM creation, dashboard loading, and audit execution
 */

// Global state
let shadowHost = null;
let shadowRoot = null;
let dashboardContainer = null;
let iframeElement = null;
let isDashboardOpen = false;
let selectedDevice = 'desktop';
let currentAuditResult = null;
let currentIframeUrl = null;
let isReauditing = false;

// Device viewports
const DEVICE_VIEWPORTS = {
  desktop: { width: 1200, height: 1080 },
  mobile: { width: 375, height: 667 }
};

/**
 * Get viewport dimensions based on selected device
 * Calculates dimensions and scale to fit device viewport in available space
 */
function getViewportDimensions(device = selectedDevice) {
  const deviceViewport = DEVICE_VIEWPORTS[device];
  const actualViewport = {
    width: window.innerWidth,
    height: window.innerHeight
  };
  
  // Calculate available space (subtract dashboard width)
  const dashboardWidth = 400;
  const availableWidth = actualViewport.width - dashboardWidth;
  const availableHeight = actualViewport.height;
  
  if (device === 'desktop') {
    // Desktop: Use full available dimensions
    return {
      deviceWidth: availableWidth,
      deviceHeight: availableHeight,
      scaledWidth: availableWidth,
      scaledHeight: availableHeight,
      scale: 1,
      availableWidth,
      availableHeight
    };
  } else {
    // Mobile: Calculate scale based on available height and width
    const scaleX = availableWidth / deviceViewport.width;
    const scaleY = availableHeight / deviceViewport.height;
    
    // Check if device dimensions fit within available space
    if (deviceViewport.width <= availableWidth && deviceViewport.height <= availableHeight) {
      // Device fits - use device dimensions with scale 1
      return {
        deviceWidth: deviceViewport.width,
        deviceHeight: deviceViewport.height,
        scaledWidth: deviceViewport.width,
        scaledHeight: deviceViewport.height,
        scale: 1,
        availableWidth,
        availableHeight
      };
    } else {
      // Device doesn't fit - calculate minimum scale to fit both dimensions
      const scale = Math.min(scaleX, scaleY);
      
      const scaledWidth = Math.floor(deviceViewport.width * scale);
      const scaledHeight = Math.floor(deviceViewport.height * scale);

      return {
        deviceWidth: deviceViewport.width,
        deviceHeight: deviceViewport.height,
        scaledWidth: scaledWidth,
        scaledHeight: scaledHeight,
        scale: scale,
        availableWidth,
        availableHeight
      };
    }
  }
}

/**
 * Update iframe dimensions based on selected device
 * Applies proper width, height, and scale transform to simulate device viewport
 */
function updateIframeDimensions(device = selectedDevice) {
  if (!iframeElement) {
    return;
  }
  
  const viewport = getViewportDimensions(device);
  
  // Set iframe dimensions to device viewport size
  iframeElement.style.width = viewport.deviceWidth + 'px';
  iframeElement.style.height = viewport.deviceHeight + 'px';
  
  // Apply scale transform to fit in available space
  iframeElement.style.transform = 'scale(' + viewport.scale + ')';
  iframeElement.style.transformOrigin = 'top center';
  
  // Ensure iframe is properly positioned
  iframeElement.style.margin = '0';
  iframeElement.style.display = 'block';
  
}

/**
 * Handle device change from dashboard
 * Updates selected device and adjusts iframe dimensions accordingly
 */
function handleDeviceChange(device) {
  selectedDevice = device;
  
  if (isDashboardOpen) {
    updateIframeDimensions(device);
    
    // Clear highlights when device changes
    if (window.webyesHighlightUtils) {
      window.webyesHighlightUtils.cleanup();
    }
  }
}

/**
 * Wait for CSS to load
 * @param {HTMLLinkElement} styleLink - The link element to wait for
 * @param {number} maxWaitMs - Maximum time to wait in milliseconds
 * @returns {Promise<void>}
 */
function waitForCssLoad(styleLink, maxWaitMs = 5000) {
  return new Promise((resolve, reject) => {
    // If already loaded, resolve immediately
    if (styleLink.sheet) {
      resolve();
      return;
    }
    
    let timeout;
    let checkInterval;
    let resolved = false;
    
    const cleanup = () => {
      if (timeout) clearTimeout(timeout);
      if (checkInterval) clearInterval(checkInterval);
    };
    
    const checkCssLoaded = () => {
      try {
        // Check if stylesheet is loaded by trying to access sheet property
        if (styleLink.sheet) {
          // Additional check: verify stylesheet has rules (indicates it's fully loaded)
          if (styleLink.sheet.cssRules && styleLink.sheet.cssRules.length > 0) {
            resolved = true;
            cleanup();
            resolve();
            return;
          }
        }
      } catch (error) {
        // Cross-origin stylesheets may throw errors, but if sheet exists, it's loaded
        if (styleLink.sheet) {
          resolved = true;
          cleanup();
          resolve();
          return;
        }
      }
    };
    
    // Check immediately
    checkCssLoaded();
    
    // Set up polling to check CSS load status
    checkInterval = setInterval(() => {
      checkCssLoaded();
    }, 50); // Check every 50ms
    
    // Set timeout
    timeout = setTimeout(() => {
      cleanup();
      if (!resolved) {
        resolved = true;
        // Resolve anyway after timeout to prevent blocking
        // The skeleton loader will handle the visual state
        console.warn('[WebYes Checker] CSS load timeout, proceeding anyway');
        resolve();
      }
    }, maxWaitMs);
    
    // Also listen to load event
    styleLink.onload = () => {
      setTimeout(() => {
        if (!resolved) {
          resolved = true;
          cleanup();
          resolve();
        }
      }, 100);
    };
    
    // Listen to error event
    styleLink.onerror = () => {
      cleanup();
      if (!resolved) {
        resolved = true;
        console.error('[WebYes Checker] CSS failed to load');
        // Resolve anyway to prevent blocking
        resolve();
      }
    };
  });
}

/**
 * Create Shadow DOM structure with iframe and dashboard container
 */
function createShadowStructure() {
  // Create shadow host
  shadowHost = document.createElement('div');
  shadowHost.id = 'wya11y-checker-root';
  shadowHost.style.cssText = 'all: initial; position: fixed; top: 0; right: 0; width: 100%; height: 100vh; z-index: 2147483647;';
  
  // Attach shadow DOM
  shadowRoot = shadowHost.attachShadow({ mode: 'open' });
  
  // Store CSS link element for later reference
  let cssLinkElement = null;
  
  // Inject CSS if available
  if (window.wya11yChecker && window.wya11yChecker.dashboardCssUrl) {
    const styleLink = document.createElement('link');
    styleLink.rel = 'stylesheet';
    styleLink.href = window.wya11yChecker.dashboardCssUrl;
    styleLink.onerror = () => console.error('[WebYes Checker] Failed to load CSS');
    shadowRoot.appendChild(styleLink);
    cssLinkElement = styleLink;
    
    // Store CSS link element globally for dashboard to check
    window.wya11yCssLink = styleLink;
  }
  
  // Create container structure
  const container = document.createElement('div');
  container.className = 'wya11y-container';
  container.style.cssText = 'display: flex; width: 100%; height: 100vh; background: white;';
  
  // Create iframe container for proper positioning
  const iframeContainer = document.createElement('div');
  iframeContainer.id = 'wya11y-iframe-container';
  iframeContainer.className = 'wya11y-iframe-container';
  iframeContainer.style.cssText = 'flex: 1; display: flex; align-items: flex-start; justify-content: center; background: #f3f4f6; overflow: auto; position: relative;';
  
  // Create iframe for page content
  iframeElement = document.createElement('iframe');
  iframeElement.id = 'wya11y-page-iframe';
  iframeElement.style.cssText = 'border: none; background: white; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);';
  iframeElement.src = window.location.href;
  
  iframeElement.onload = () => {
    // Hide the accessibility checker icon inside the iframe to prevent duplicate dashboards
    try {
      const iframeDoc = iframeElement.contentDocument || iframeElement.contentWindow.document;
      
      // Hide the accessibility checker icon
      const checkerIcon = iframeDoc.getElementById('wya11y-checker-icon');
      if (checkerIcon) {
        checkerIcon.style.display = 'none';
      }
      
      // Also hide the accessibility widget container if present
      const widgetContainer = iframeDoc.getElementById('accessibility-plus-container');
      if (widgetContainer) {
        widgetContainer.style.display = 'none';
      }
      
      // Check for URL change and reload main page (no re-audit)
      try {
        const newUrl = iframeElement.contentWindow.location.href;
        
        if (currentIframeUrl && currentIframeUrl !== newUrl && !isReauditing) {
          // Page navigation detected - reload the main webpage
          // This removes the shadow DOM and provides a clean state
          window.location.href = newUrl;
        } else if (!currentIframeUrl) {
          // First load, just track the URL
          currentIframeUrl = newUrl;
        }
      } catch (error) {
        // Ignore cross-origin errors
      }
    } catch (error) {
      // Ignore iframe access errors
    }
  };
  
  // Store iframe globally for highlight utils
  window.wya11yIframeElement = iframeElement;
  
  // Create dashboard container
  dashboardContainer = document.createElement('div');
  dashboardContainer.id = 'checker-dashboard-root';
  dashboardContainer.style.cssText = 'width: 400px; height: 100vh; solid #e5e7eb; overflow: auto; background: #DCE3EE';
  
  // Append iframe to iframe container
  iframeContainer.appendChild(iframeElement);
  
  // Append containers to main container
  container.appendChild(iframeContainer);
  container.appendChild(dashboardContainer);
  shadowRoot.appendChild(container);
  document.body.appendChild(shadowHost);
}

/**
 * Wait for audit store to be available
 */
async function waitForAuditStore(maxWaitMs = 5000) {
  const startTime = Date.now();
  
  while (!window.useAuditStore) {
    if (Date.now() - startTime > maxWaitMs) {
      throw new Error('Audit store not available after ' + maxWaitMs + 'ms');
    }
    await new Promise(resolve => setTimeout(resolve, 100));
  }
  
  return true;
}

/**
 * Wait for iframe to be ready
 */
async function waitForIframeReady(maxWaitMs = 10000) {
  if (!iframeElement) {
    return false;
  }
  
  const startTime = Date.now();
  
  while (true) {
    try {
      const iframeDoc = iframeElement.contentDocument;
      if (iframeDoc && iframeDoc.readyState === 'complete') {
        return true;
      }
    } catch (error) {
      // Ignore access errors
    }
    
    if (Date.now() - startTime > maxWaitMs) {
      return false;
    }
    
    await new Promise(resolve => setTimeout(resolve, 100));
  }
}

/**
 * Run initial audit
 */
async function runInitialAudit() {
  try {
    await waitForAuditStore();
    await waitForIframeReady();
    await new Promise(resolve => setTimeout(resolve, 500));
    
    if (window.webyesAuditUtils) {
      const results = await window.webyesAuditUtils.runMultiDeviceAuditFormattedIsolated();
      
      if (window.useAuditStore) {
        const store = window.useAuditStore.getState();
        store.setAuditResult(results);
        store.setAuditError(null); // Clear any previous errors
      }
    }
  } catch (error) {
    console.error('[WebYes Checker] Initial audit failed:', error);
    
    // Set error state in store
    if (window.useAuditStore) {
      const store = window.useAuditStore.getState();
      const errorMessage = error?.message || String(error);
      
      // Check if it's a timeout error
      if (errorMessage.includes('timeout') || errorMessage.includes('Timeout')) {
        store.setAuditError('timeout');
      } else {
        store.setAuditError(errorMessage);
      }
      store.setAuditResult(null);
    }
  }
}

/**
 * Show the dashboard
 */
function showDashboard() {
  if (!shadowHost) {
    createShadowStructure();
  }
  
  shadowHost.style.display = 'block';
  isDashboardOpen = true;
  document.body.style.overflow = 'hidden';
  
  // Update iframe dimensions for current device
  updateIframeDimensions(selectedDevice);
  
  // Update Zustand store visibility state
  if (window.useAuditStore) {
    const store = window.useAuditStore.getState();
    store.setIsDashboardVisible(true);
  }
  
  loadDashboardBundle();
  runInitialAudit();
}

/**
 * Hide the dashboard
 */
function hideDashboard() {
  if (shadowHost) {
    shadowHost.style.display = 'none';
  }
  isDashboardOpen = false;
  document.body.style.overflow = '';
  
  // Update Zustand store visibility state
  if (window.useAuditStore) {
    const store = window.useAuditStore.getState();
    store.setIsDashboardVisible(false);
  }
  
  if (window.webyesHighlightUtils) {
    window.webyesHighlightUtils.cleanup();
  }
}

/**
 * Toggle dashboard visibility
 */
function toggleDashboard() {
  if (isDashboardOpen) {
    hideDashboard();
  } else {
    showDashboard();
  }
}

/**
 * Preload CSS in document head for faster loading
 */
function preloadCss() {
  if (window.wya11yChecker && window.wya11yChecker.dashboardCssUrl) {
    // Check if preload link already exists
    const existingPreload = document.querySelector(`link[rel="preload"][href="${window.wya11yChecker.dashboardCssUrl}"]`);
    if (existingPreload) {
      return;
    }
    
    const preloadLink = document.createElement('link');
    preloadLink.rel = 'preload';
    preloadLink.as = 'style';
    preloadLink.href = window.wya11yChecker.dashboardCssUrl;
    preloadLink.crossOrigin = 'anonymous';
    document.head.appendChild(preloadLink);
  }
}

/**
 * Load the dashboard React bundle
 */
function loadDashboardBundle() {
  // Preload CSS in document head for faster loading
  preloadCss();
  
  if (window.renderCheckerDashboard) {
    window.renderCheckerDashboard(dashboardContainer, {
      onClose: hideDashboard,
      currentUrl: window.location.href,
      selectedDevice: selectedDevice,
      onDeviceChange: handleDeviceChange
    });
    return;
  }
  
  if (!window.wya11yChecker || !window.wya11yChecker.dashboardUrl) {
    console.error('[WebYes Checker] Dashboard URL not provided');
    dashboardContainer.innerHTML = '<div style="padding: 20px; color: red;">Error: Dashboard bundle not configured</div>';
    return;
  }
  
  const dashboardUrl = window.wya11yChecker.dashboardUrl;
  
  if (!dashboardUrl || dashboardUrl === '') {
    console.error('[WebYes Checker] Dashboard URL is empty');
    dashboardContainer.innerHTML = '<div style="padding: 20px; color: red; font-family: sans-serif;"><h3>Error Loading Dashboard</h3><p>Dashboard URL is empty</p></div>';
    return;
  }
  
  const script = document.createElement('script');
  script.type = 'module';
  script.src = dashboardUrl;
  
  script.onload = () => {
    setTimeout(() => {
      if (window.renderCheckerDashboard) {
        try {
          window.renderCheckerDashboard(dashboardContainer, {
            onClose: hideDashboard,
            currentUrl: window.location.href,
            selectedDevice: selectedDevice,
            onDeviceChange: handleDeviceChange
          });
        } catch (err) {
          console.error('[WebYes Checker] Error rendering dashboard:', err);
          dashboardContainer.innerHTML = '<div style="padding: 20px; color: red; font-family: sans-serif;"><h3>Error Rendering Dashboard</h3><p>' + err.message + '</p></div>';
        }
      } else {
        console.error('[WebYes Checker] renderCheckerDashboard function not found');
        dashboardContainer.innerHTML = '<div style="padding: 20px; color: red; font-family: sans-serif;"><h3>Dashboard Function Not Found</h3></div>';
      }
    }, 100);
  };
  
  script.onerror = () => {
    console.error('[WebYes Checker] Failed to load dashboard script from:', dashboardUrl);
    
    // Try to fetch the URL to see what the actual error is
    fetch(dashboardUrl)
      .then(response => {
        if (!response.ok) {
          console.error('[WebYes Checker] HTTP', response.status, response.statusText);
        }
        return response.text();
      })
      .catch(fetchError => {
        console.error('[WebYes Checker] Fetch error:', fetchError);
      });
    
    dashboardContainer.innerHTML = '<div style="padding: 20px; color: red; font-family: sans-serif;"><h3>Error Loading Dashboard</h3><p>' + errorMsg + '</p><p>Check console for details.</p></div>';
  };
  
  // Append to document head (scripts don't execute in shadow DOM)
  document.head.appendChild(script);
}

/**
 * Run audit for a specific device viewport
 */
async function runAuditForDevice(device, viewport, pageUrl = false) {
  const targetUrl = pageUrl || window.location.href;
  
  try {
    const auditFrame = document.createElement('iframe');
    auditFrame.style.cssText = `position: absolute; left: -9999px; width: ${viewport.width}px; height: ${viewport.height}px;`;
    auditFrame.src = targetUrl;
    document.body.appendChild(auditFrame);
    
    // Wait for iframe to load with multiple fallback mechanisms
    await new Promise((resolve, reject) => {
      let timeout;
      let pollInterval;
      let resolved = false;
      const maxWaitMs = 60000; // Increased to 30 seconds
      const pollIntervalMs = 200; // Check every 200ms
      const startTime = Date.now();
      
      const cleanup = () => {
        if (timeout) clearTimeout(timeout);
        if (pollInterval) clearInterval(pollInterval);
      };
      
      const checkIframeReady = () => {
        try {
          const auditDoc = auditFrame.contentDocument || auditFrame.contentWindow?.document;
          if (auditDoc) {
            // Check if document is ready
            if (auditDoc.readyState === 'complete' || auditDoc.readyState === 'interactive') {
              // Try to access body to ensure document is accessible
              if (auditDoc.body) {
                return true;
              }
            }
          }
        } catch (error) {
          // Cross-origin or access error - might still be loading
          // For same-origin, this shouldn't happen, so iframe might be blocked
          return false;
        }
        return false;
      };
      
      const resolveIfReady = () => {
        if (resolved) return;
        
        if (checkIframeReady()) {
          resolved = true;
          cleanup();
          
          try {
            const auditDoc = auditFrame.contentDocument || auditFrame.contentWindow?.document;
            if (auditDoc) {
              const checkerIcon = auditDoc.getElementById('wya11y-checker-icon');
              if (checkerIcon) checkerIcon.style.display = 'none';
              const widgetContainer = auditDoc.getElementById('accessibility-plus-container');
              if (widgetContainer) widgetContainer.style.display = 'none';
            }
          } catch (error) {
            // Ignore access errors
          }
          
          resolve();
        }
      };
      
      // Primary: onload event handler
      auditFrame.onload = () => {
        // Small delay to ensure DOM is ready
        setTimeout(() => {
          resolveIfReady();
        }, 100);
      };
      
      // Fallback: onerror handler
      auditFrame.onerror = () => {
        cleanup();
        if (!resolved) {
          resolved = true;
          reject(new Error('Iframe failed to load'));
        }
      };
      
      // Fallback: Polling mechanism to check iframe readiness
      pollInterval = setInterval(() => {
        if (Date.now() - startTime > maxWaitMs) {
          cleanup();
          if (!resolved) {
            resolved = true;
            reject(new Error(`Iframe load timeout after ${maxWaitMs}ms - page may be too slow or blocked from iframe embedding`));
          }
          return;
        }
        resolveIfReady();
      }, pollIntervalMs);
      
      // Final timeout safety net
      timeout = setTimeout(() => {
        cleanup();
        if (!resolved) {
          resolved = true;
          reject(new Error(`Iframe load timeout after ${maxWaitMs}ms - page may be too slow or blocked from iframe embedding`));
        }
      }, maxWaitMs);
    });

    // Verify we can access the iframe document before proceeding
    const auditDoc = auditFrame.contentDocument || auditFrame.contentWindow?.document;
    if (!auditDoc) {
      throw new Error('Cannot access iframe document - possible cross-origin restriction or X-Frame-Options blocking');
    }
    
    // Load axe-core from local assets
    const axeScript = auditDoc.createElement('script');
    const axeUrl = window.wya11yChecker.assetsUrl + 'js/axe.min.js';
    axeScript.src = axeUrl;
    auditDoc.head.appendChild(axeScript);
    
    await new Promise((resolve, reject) => {
      const timeout = setTimeout(() => reject(new Error('Axe-core load timeout')), 10000);
      axeScript.onload = () => {
        clearTimeout(timeout);
        resolve();
      };
      axeScript.onerror = () => {
        clearTimeout(timeout);
        reject(new Error('Failed to load axe-core'));
      };
    });
    const wcagTags = [
      'wcag2a','wcag2aa','wcag2aaa',
      'wcag21a','wcag21aa',
      'wcag22aa',
      'section508'
    ];
    var runOptions = {
      runOnly: { type: 'tag', values: wcagTags },
    };
    const results = await auditFrame.contentWindow.axe.run(auditDoc, runOptions);
    
    // Clean up iframe
    try {
      if (auditFrame && auditFrame.parentNode) {
        document.body.removeChild(auditFrame);
      }
    } catch (cleanupError) {
      console.warn('[WebYes Checker] Error cleaning up audit iframe:', cleanupError);
    }
    
    return results;
  } catch (error) {
    // Clean up iframe on error - find and remove any audit iframes
    try {
      // Find iframes that are positioned off-screen (our audit iframes)
      const allIframes = document.querySelectorAll('iframe');
      allIframes.forEach(iframe => {
        try {
          const style = window.getComputedStyle(iframe);
          if (style.position === 'absolute' && (style.left === '-9999px' || parseInt(style.left) < -9000)) {
            if (iframe.parentNode) {
              iframe.parentNode.removeChild(iframe);
            }
          }
        } catch (e) {
          // Ignore errors during cleanup
        }
      });
    } catch (cleanupError) {
      // Ignore cleanup errors
    }
    
    console.error(`[WebYes Checker] Error during ${device} audit:`, error);
    throw error;
  }
}

/**
 * Load WCAG guidelines data
 */
async function loadWCAGGuidelines() {
  try {
    const guidelinesUrl = window.wya11yChecker.assetsUrl + 'data/wcag_guidelines.json';
    const response = await fetch(guidelinesUrl);
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }
    
    const guidelinesArray = await response.json();
    const guidelines = {};
    
    guidelinesArray.forEach(guideline => {
      if (guideline.rule_id) {
        guidelines[guideline.rule_id] = guideline;
      }
    });
    
    return guidelines;
  } catch (error) {
    console.error('[WebYes Checker] Failed to load WCAG guidelines:', error);
    return {};
  }
}

/**
 * Derive WCAG info from axe-core tags when guideline mapping is missing
 */
function deriveWcagFromAxeTags(tags = []) {
  let code;
  let level;
  let version;
  
  // e.g., 'wcag412' => '4.1.2', 'wcag121' => '1.2.1'
  const codeTag = tags.find(t => /^wcag\d{3}$/.test(t));
  if (codeTag) {
    const digits = codeTag.replace('wcag', '');
    code = `${digits[0]}.${digits[1]}.${digits[2]}`;
  }
  
  // e.g., 'wcag2a', 'wcag2aa', 'wcag2aaa' => level A/AA/AAA and version 2.0+
  const levelTag = tags.find(t => /^wcag\d{1,2}a{1,3}$/i.test(t));
  if (levelTag) {
    const m = levelTag.match(/^wcag(\d{1,2})(a{1,3})$/i);
    if (m) {
      const ver = m[1];
      const lv = m[2];
      version = `${ver}.0`;
      level = lv.toUpperCase(); // 'A', 'AA', 'AAA'
    }
  }
  
  // Prefer more specific tags for version (wcag21, wcag22)
  if (tags.includes('wcag22')) version = '2.2';
  else if (tags.includes('wcag21')) version = '2.1';
  else if (tags.includes('wcag2')) version = version || '2.0';
  
  return { code, level, version };
}

/**
 * Enrich audit results with WCAG guideline information
 */
async function enrichWithWCAG(results) {
  const guidelines = await loadWCAGGuidelines();
  
  // Helper function to format violations like the extension
  const formatViolations = (violations, device) => {
    // Filter to only WCAG-tagged issues (not best-practice)
    const hasWcagTag = (tags = []) => tags.some(t => /^wcag(2|21|22)(a{1,3}|\d{3})$/i.test(t));
    const filtered = violations.filter(v => hasWcagTag(v.tags) && !(v.tags || []).includes('best-practice'));
    
    return filtered.map((v, idx) => {
      const guideline = guidelines[v.id] || {};
      const issueSeverity = guideline.issue_severity || v.impact || 'moderate';
      const title = guideline.title || v.help || v.id;
      
      // Extract WCAG info from guideline or derive from tags
      let standardCode = guideline.standard_code || undefined;
      let wcagLevel = guideline.wcag_level || '';
      let wcagVersion = '';
      
      // Convert version number to string
      if (guideline.wcag_version_number) {
        const ver = guideline.wcag_version_number;
        wcagVersion = String(ver).includes('.') ? String(ver) : `${ver}.0`;
      }
      
      // Derive from tags if not in guideline
      if (!standardCode || !wcagLevel || !wcagVersion) {
        const derived = deriveWcagFromAxeTags(v.tags);
        if (derived.code && !standardCode) standardCode = derived.code;
        if (derived.level && !wcagLevel) wcagLevel = derived.level;
        if (derived.version && !wcagVersion) wcagVersion = derived.version;
      }
      
      // Format items (elements) with proper structure
      const items = (v.nodes || []).map(node => ({
        node: {
          snippet: node.html || 'No snippet available',
          selector: (node.target && node.target[0]) || 'No selector available',
          nodeLabel: (node.target && node.target[0]) || 'No label available',
          boundingRect: { top: 0, left: 0, right: 0, bottom: 0, width: 0, height: 0 }
        },
        target: node.target || ['No target available'],
        explanation: node.failureSummary || 'No explanation available'
      }));
      
      return {
        issue_id: v.id,
        issue_title: title,
        issue_severity: issueSeverity,
        issue_category: 'accessibility',
        audit_device: device,
        wcag_version_number: wcagVersion,
        wcag_level: wcagLevel,
        standard_code: standardCode,
        standard_list_json: v.tags || [],
        affected_disabilities_json: parseAffectedDisabilities(guideline.affected_disabilities_json),
        issue_content_json: {
          id: v.id,
          title: title,
          help: guideline.help || v.help || '',
          helpUrl: v.helpUrl || '',
          description: guideline.description || v.description || '',
          layman_description: guideline.layman_description || v.description || '',
          dev_description: guideline.dev_description || v.description || '',
          standardCode: standardCode,
          details: {
            items: items,
            debugData: {
              tags: v.tags || [],
              impact: issueSeverity
            }
          }
        },
        audit_data_json: {
          help: guideline.help || v.help || '',
          title: title,
          dev_help: guideline.dev_help || '',
          help_url: guideline.help_url || v.helpUrl || '',
          description: guideline.description || v.description || '',
          layman_help: guideline.layman_help || '',
          passed_title: guideline.passed_title || '',
          dev_description: guideline.dev_description || '',
          layman_description: guideline.layman_description || ''
        }
      };
    });
  };
  
  // Helper to parse affected disabilities
  function parseAffectedDisabilities(disabilitiesString) {
    if (!disabilitiesString) return [];
    
    // Convert to camelCase to match React component expectations
    const toCamelCase = (str) => {
      const trimmed = str.trim();
      const words = trimmed.split(/\s+/);
      
      if (words.length === 1) {
        return words[0].toLowerCase();
      }
      
      // Special cases that don't use camelCase in the component
      const normalized = words.map(w => w.toLowerCase()).join('');
      if (normalized === 'colorblindness' || normalized === 'attentiondeficit') {
        return normalized;
      }
      
      // Convert to camelCase: "Low vision" -> "lowVision"
      return words[0].toLowerCase() + words.slice(1).map(w => 
        w.charAt(0).toUpperCase() + w.slice(1).toLowerCase()
      ).join('');
    };
    
    // The format is "Blind | Low vision | Cognitive" (pipe-separated)
    if (typeof disabilitiesString === 'string') {
      return disabilitiesString
        .split('|')
        .map(d => toCamelCase(d))
        .filter(d => d);
    }
    
    if (Array.isArray(disabilitiesString)) {
      return disabilitiesString;
    }
    
    return [];
  }
  
  return {
    body: {
      data: {
        report_data: {
          desktop: formatViolations(results.desktop.violations, 'desktop'),
          mobile: formatViolations(results.mobile.violations, 'mobile')
        }
      }
    },
    mobile: results.mobile,
    desktop: results.desktop
  };
}

/**
 * Run multi-device audit
 */
async function runMultiDeviceAuditFormattedIsolated() {
  let pageUrl = null;
  
  if (iframeElement) {
    try {
      pageUrl = iframeElement.contentWindow.location.href;
    } catch (error) {
      pageUrl = window.location.href;
    }
  } else {
    pageUrl = window.location.href;
  }
  
  try {
    const results = {};
    
    for (const [device, viewport] of Object.entries(DEVICE_VIEWPORTS)) {
      results[device] = await runAuditForDevice(device, viewport, pageUrl);
    }
    
    const enrichedResults = await enrichWithWCAG(results);
    currentAuditResult = enrichedResults;
    
    return enrichedResults;
  } catch (error) {
    console.error('[WebYes Checker] Multi-device audit failed:', error);
    throw error;
  }
}

/**
 * Highlight utilities for marking elements
 */
window.webyesHighlightUtils = {
  markers: [],
  borderedElement: null,
  currentItems: null,
  currentTargetDocument: null,
  scrollListener: null,
  selectedIndex: null,  // Track which element is currently selected
  clickOutsideListener: null,  // Track click outside listener
  
  // Get the iframe document where the page content is displayed
  getIframeDocument() {
    try {
      if (window.wya11yIframeElement && window.wya11yIframeElement.contentDocument) {
        return window.wya11yIframeElement.contentDocument;
      }
      
      const shadowHost = document.getElementById('wya11y-checker-root');
      if (shadowHost && shadowHost.shadowRoot) {
        const iframe = shadowHost.shadowRoot.getElementById('wya11y-page-iframe');
        if (iframe && iframe.contentDocument) {
          return iframe.contentDocument;
        }
      }
    } catch (error) {
      // Ignore
    }
    
    return null;
  },
  
  // Inject styles for markers into iframe
  injectMarkerStyles(targetDoc) {
    const styleId = 'wya11y-marker-styles';
    
    if (targetDoc.getElementById(styleId)) {
      return;
    }
    
    try {
      const style = targetDoc.createElement('style');
      style.id = styleId;
      style.textContent = `
        .wya11y-issue-marker {
          position: absolute !important;
          width: 20px !important;
          height: 20px !important;
          padding: 0 !important;
          margin: 0 !important;
          background-color: #dc2626 !important;
          border: 3px solid white !important;
          border-radius: 50% !important;
          cursor: pointer !important;
          z-index: 999999 !important;
          box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3) !important;
          transition: transform 0.2s ease !important;
        }
        
        .wya11y-issue-marker:hover {
          transform: scale(1.1) !important;
        }
        
        .wya11y-issue-border {
          outline: 3px solid #dc2626 !important;
          outline-offset: 2px !important;
          position: relative !important;
          z-index: 99998 !important;
        }
      `;
      
      if (!targetDoc.head) {
        const head = targetDoc.createElement('head');
        targetDoc.documentElement.insertBefore(head, targetDoc.body);
      }
      
      targetDoc.head.appendChild(style);
    } catch (error) {
      // Ignore
    }
  },
  
  // Mark all issue items with red dots
  markIssueItems(items) {
    this.clearMarkers();
    
    const targetDoc = this.getIframeDocument();
    if (!targetDoc) {
      return;
    }
    
    this.injectMarkerStyles(targetDoc);
    this.currentItems = items;
    this.currentTargetDocument = targetDoc;
    
    items.forEach((item, index) => {
      const selector = item.target?.[0] || item.selector;
      
      if (!selector) {
        return;
      }
      
      try {
        const targetElement = targetDoc.querySelector(selector);
        if (!targetElement) {
          return;
        }
        
        const marker = this.createMarkerButton(index, item, targetDoc);
        this.positionMarkerOnElement(marker, targetElement);
        targetDoc.body.appendChild(marker);
        this.markers.push(marker);
      } catch (error) {
        // Ignore
      }
    });
    
    this.setupScrollListener();
  },
  
  // Create marker button
  createMarkerButton(index, item, targetDoc) {
    const button = targetDoc.createElement('button');
    button.className = 'wya11y-issue-marker';
    button.id = `wya11y-issue-marker-${index}`;
    button.setAttribute('data-issue-index', index.toString());
    button.setAttribute('data-selector', item.target?.[0] || '');
    button.title = 'Click to highlight in list';
    
    // Add click handler
    button.addEventListener('click', (e) => {
      e.stopPropagation();
      this.handleMarkerClick(index, item);
    });
    
    return button;
  },
  
  // Position marker on element
  positionMarkerOnElement(marker, targetElement) {
    const position = this.calculateElementPosition(targetElement);
    marker.style.position = 'absolute';
    marker.style.top = position.top + 'px';
    marker.style.left = position.left + 'px';
  },
  
  // Calculate element position
  calculateElementPosition(element) {
    const rect = element.getBoundingClientRect();
    const doc = element.ownerDocument || document;
    const win = doc.defaultView || window;
    
    const scrollTop = win.pageYOffset || doc.documentElement.scrollTop || doc.body.scrollTop || 0;
    const scrollLeft = win.pageXOffset || doc.documentElement.scrollLeft || doc.body.scrollLeft || 0;
    
    return {
      top: rect.top + scrollTop,
      left: rect.left + scrollLeft
    };
  },
  
  // Handle marker click (toggle behavior)
  handleMarkerClick(index, item) {    
    const selector = item.target?.[0];
    
    // Toggle behavior: if clicking the same marker, deselect it
    if (this.selectedIndex === index) {      
      // Remove border
      this.removeElementBorder();
      
      // Clear selection
      this.selectedIndex = null;
      
      // Remove click outside listener
      this.removeClickOutsideListener();
      
      // Notify dashboard to deselect element in list
      window.postMessage({
        source: 'WEBYES',
        type: 'ISSUE_MARKER_DESELECT',
        index: index
      }, '*');
    } else {      
      // Select new element
      this.selectedIndex = index;
      
      // Add border to element
      if (selector) {
        this.addElementBorder(selector);
        this.scrollToElementInIframe(selector);
      }
      
      // Set up click outside listener
      this.setupClickOutsideListener();
      
      // Notify dashboard to highlight element in list
      window.postMessage({
        source: 'WEBYES',
        type: 'ISSUE_MARKER_CLICK',
        index: index,
        selector: selector
      }, '*');
    }
  },
  
  // Set up scroll listener to reposition markers
  setupScrollListener() {
    try {
      this.removeScrollListener();
      
      const targetDoc = this.currentTargetDocument;
      if (!targetDoc) return;
      
      const win = targetDoc.defaultView || window;
      
      this.scrollListener = () => {
        this.repositionMarkers();
      };
      
      win.addEventListener('scroll', this.scrollListener, { passive: true });
      win.addEventListener('resize', this.scrollListener, { passive: true });
    } catch (error) {
      // Ignore
    }
  },
  
  // Remove scroll listener
  removeScrollListener() {
    try {
      if (this.scrollListener && this.currentTargetDocument) {
        const win = this.currentTargetDocument.defaultView || window;
        win.removeEventListener('scroll', this.scrollListener);
        win.removeEventListener('resize', this.scrollListener);
        this.scrollListener = null;
      }
    } catch (error) {
      // Ignore
    }
  },
  
  // Reposition all markers
  repositionMarkers() {
    try {
      if (!this.currentItems || !this.currentTargetDocument) return;
      
      this.currentItems.forEach((item, index) => {
        const selector = item.target?.[0];
        if (!selector || !this.currentTargetDocument) return;
        
        const targetElement = this.currentTargetDocument.querySelector(selector);
        const marker = this.currentTargetDocument.getElementById(`wya11y-issue-marker-${index}`);
        
        if (targetElement && marker) {
          this.positionMarkerOnElement(marker, targetElement);
        }
      });
    } catch (error) {
      // Ignore
    }
  },
  
  // Clear all markers
  clearMarkers() {
    this.removeScrollListener();
    this.removeClickOutsideListener();
    
    this.markers.forEach(m => {
      try {
        m.remove();
      } catch (e) {}
    });
    this.markers = [];
    
    this.currentItems = null;
    this.currentTargetDocument = null;
    this.selectedIndex = null;
  },
  
  // Set selection from dashboard (when user clicks element in list)
  setSelection(index) {
    this.selectedIndex = index;
    this.setupClickOutsideListener();
  },
  
  // Clear selection from dashboard
  clearSelection() {
    this.selectedIndex = null;
    this.removeClickOutsideListener();
  },
  
  // Set up click outside listener to deselect
  setupClickOutsideListener() {
    try {
      this.removeClickOutsideListener();
      
      const targetDoc = this.getIframeDocument();
      if (!targetDoc) return;
      
      this.clickOutsideListener = (event) => {
        if (this.borderedElement && !this.borderedElement.contains(event.target)) {
          const clickedMarker = event.target.closest('.wya11y-issue-marker');
          if (!clickedMarker) {
            this.deselectFromClickOutside();
          }
        }
      };
      
      targetDoc.addEventListener('click', this.clickOutsideListener, true);
    } catch (error) {
      // Ignore
    }
  },
  
  // Remove click outside listener
  removeClickOutsideListener() {
    try {
      if (this.clickOutsideListener && this.currentTargetDocument) {
        this.currentTargetDocument.removeEventListener('click', this.clickOutsideListener, true);
        this.clickOutsideListener = null;
      }
    } catch (error) {
      // Ignore
    }
  },
  
  // Deselect from click outside
  deselectFromClickOutside() {
    const currentIndex = this.selectedIndex;
    this.removeElementBorder();
    this.selectedIndex = null;
    this.removeClickOutsideListener();
    
    window.postMessage({
      source: 'WEBYES',
      type: 'ISSUE_MARKER_DESELECT',
      index: currentIndex
    }, '*');
  },
  
  // Add border to element
  addElementBorder(selector) {
    this.removeElementBorder();
    
    const targetDoc = this.getIframeDocument();
    if (!targetDoc) return;
    
    const element = targetDoc.querySelector(selector);
    if (element) {
      element.classList.add('wya11y-issue-border');
      this.borderedElement = element;
    }
  },
  
  // Remove border from element
  removeElementBorder() {
    if (this.borderedElement) {
      this.borderedElement.classList.remove('wya11y-issue-border');
      this.borderedElement = null;
    }
  },
  
  // Add border and set up click outside listener
  addElementBorderWithListener(selector, index) {
    this.addElementBorder(selector);
    this.selectedIndex = index;
    this.setupClickOutsideListener();
  },
  
  // Scroll to element in iframe
  scrollToElementInIframe(selector) {
    try {
      const targetDoc = this.getIframeDocument();
      if (!targetDoc) return false;
      
      const element = targetDoc.querySelector(selector);
      if (!element) return false;
      
      element.scrollIntoView({
        behavior: 'smooth',
        block: 'center',
        inline: 'nearest'
      });
      
      return true;
    } catch (error) {
      return false;
    }
  },
  
  // Clear all highlights
  clearAllHighlights() {
    this.cleanup();
  },
  
  // Cleanup everything
  cleanup() {
    this.clearMarkers();
    this.removeElementBorder();
  }
};

/**
 * Run audit with proper preparation (same as initial audit)
 * This ensures iframe is ready and all delays are in place
 */
async function runAuditWithPreparation() {
  try {
    // Wait for audit store (if needed)
    if (!window.useAuditStore) {
      await waitForAuditStore();
    }
    
    // Wait for iframe to be ready
    await waitForIframeReady();
    
    // Additional delay to ensure everything is ready
    await new Promise(resolve => setTimeout(resolve, 500));
    
    // Now run the audit
    const results = await runMultiDeviceAuditFormattedIsolated();
    return results;
  } catch (error) {
    console.error('[WebYes Checker] Audit with preparation failed:', error);
    throw error;
  }
}

/**
 * Expose audit utilities globally
 */
window.webyesAuditUtils = {
  runMultiDeviceAuditFormattedIsolated,
  runAuditWithPreparation // New method with proper preparation
};

/**
 * Expose device change handler globally
 * Used by the React dashboard to update iframe viewport
 */
window.webyesDeviceHandler = {
  handleDeviceChange,
  updateIframeDimensions,
  getViewportDimensions
};

/**
 * Initialize - attach to checker icon
 */
function initialize() {
  // Preload CSS early for faster loading
  preloadCss();
  
  const checkerIcon = document.getElementById('wya11y-checker-icon');
  if (checkerIcon) {
    checkerIcon.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      toggleDashboard();
    });
  } else {
    console.error('[WebYes Checker] Checker icon not found');
  }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initialize);
} else {
  initialize();
}

/**
 * Handle window resize - update iframe dimensions
 */
window.addEventListener('resize', function() {
  if (isDashboardOpen && iframeElement) {
    updateIframeDimensions(selectedDevice);
  }
});

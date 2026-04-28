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
/** Per-device cache: { desktop: { reportData: [], raw }, mobile: { reportData: [], raw } } or null for unaudited */
let currentAuditResultByDevice = { desktop: null, mobile: null };
/** Device we are currently auditing for; used to ignore completion if user switched device */
let currentAuditTargetDevice = null;
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
 * Updates selected device and iframe. If new device has no cached result, runs audit for it (cancels in-flight audit for previous device).
 */
function handleDeviceChange(device) {
  currentAuditTargetDevice = device;
  selectedDevice = device;

  if (isDashboardOpen) {
    updateIframeDimensions(device);

    if (window.webyesHighlightUtils) {
      window.webyesHighlightUtils.cleanup();
    }

    if (window.useAuditStore) {
      let store = window.useAuditStore.getState();
      if (hasCachedResultForDevice(device)) {
        store.setAuditResult(buildMergedAuditResult());
        store.setAuditError(null);
      } else {
        runSingleDeviceAuditAndUpdateStore(device);
      }
    }
  }
}

/**
 * Build the full auditResult shape from per-device cache for the store (unchanged contract for dashboard).
 */
function buildMergedAuditResult() {
  let desktop = currentAuditResultByDevice.desktop;
  let mobile = currentAuditResultByDevice.mobile;
  return {
    body: {
      data: {
        report_data: {
          desktop: (desktop && desktop.reportData) ? desktop.reportData : [],
          mobile: (mobile && mobile.reportData) ? mobile.reportData : [],
          best_practices: {
            desktop: (desktop && desktop.bestPracticesData) ? desktop.bestPracticesData : [],
            mobile: (mobile && mobile.bestPracticesData) ? mobile.bestPracticesData : []
          }
        }
      }
    },
    desktop: desktop ? desktop.raw : null,
    mobile: mobile ? mobile.raw : null
  };
}

/**
 * Check if we have cached result for a device
 */
function hasCachedResultForDevice(device) {
  let cached = currentAuditResultByDevice[device];
  return cached && cached.reportData && Array.isArray(cached.reportData);
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
 * Wait for visible iframe body to have content (so we can audit via visible iframe only).
 * Rejects after maxWaitMs with a clear error; no hidden iframe fallback.
 */
function waitForVisibleIframeBodyContent(maxWaitMs) {
  maxWaitMs = maxWaitMs || 25000;
  let pollMs = 200;
  let start = Date.now();
  return new Promise(function (resolve, reject) {
    function check() {
      try {
        if (!iframeElement || !iframeElement.contentDocument) {
          return false;
        }
        let doc = iframeElement.contentDocument;
        if (doc.readyState !== 'complete' && doc.readyState !== 'interactive') {
          return false;
        }
        if (doc.body && doc.body.children && doc.body.children.length > 0) {
          return true;
        }
      } catch (e) {}
      return false;
    }
    function tick() {
      if (check()) {
        resolve();
        return;
      }
      if (Date.now() - start >= maxWaitMs) {
        reject(new Error('Page content didn\'t load in time'));
        return;
      }
      setTimeout(tick, pollMs);
    }
    tick();
  });
}

/**
 * Run initial audit for the currently selected device only (used when opening dashboard with no cache).
 */
function runInitialAudit() {
  if (window.useAuditStore) {
    let s = window.useAuditStore.getState();
    if (s.setAuditInProgress) s.setAuditInProgress(true);
  }
  (async function () {
    try {
      await waitForAuditStore();
      await waitForVisibleIframeBodyContent(25000);
      await runSingleDeviceAuditAndUpdateStore(selectedDevice);
    } catch (error) {
      console.error('[WebYes Checker] Initial audit failed:', error);
      if (window.useAuditStore) {
        let store = window.useAuditStore.getState();
        let errorMessage = error && error.message ? error.message : String(error);
        if (errorMessage.indexOf('timeout') !== -1 || errorMessage.indexOf('Timeout') !== -1 || errorMessage.indexOf('didn\'t load in time') !== -1) {
          store.setAuditError('timeout');
        } else {
          store.setAuditError(errorMessage);
        }
        store.setAuditResult(buildMergedAuditResult());
        if (store.setAuditInProgress) {
          store.setAuditInProgress(false);
        }
      }
    }
  })();
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

  // If we already have cached result for the selected device, show it without re-scanning
  if (hasCachedResultForDevice(selectedDevice)) {
    if (window.useAuditStore) {
      let store = window.useAuditStore.getState();
      store.setAuditResult(buildMergedAuditResult());
      store.setAuditError(null);
    }
  } else {
    runInitialAudit();
  }
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
 * Set the visible page iframe to exact device dimensions for audit (no scale).
 * Used when auditing via the visible iframe so the document reflows to that viewport.
 */
function setVisibleIframeAuditDimensions(device) {
  if (!iframeElement) return;
  const vp = DEVICE_VIEWPORTS[device];
  if (!vp) return;
  iframeElement.style.width = vp.width + 'px';
  iframeElement.style.height = vp.height + 'px';
  iframeElement.style.transform = 'none';
  iframeElement.style.transformOrigin = '';
}

/**
 * Inject or update viewport meta in the iframe document for audit (so page reflows to device size).
 * Returns a restore function to revert to the original viewport meta after audit.
 */
function injectViewportMetaForAudit(doc, device) {
  let vp = DEVICE_VIEWPORTS[device];
  if (!vp || !doc || !doc.head) return function () {};

  let viewportMeta = doc.querySelector('meta[name="viewport"]');
  let hadMeta = !!viewportMeta;
  let originalContent = hadMeta ? viewportMeta.getAttribute('content') : null;

  if (!viewportMeta) {
    viewportMeta = doc.createElement('meta');
    viewportMeta.setAttribute('name', 'viewport');
    doc.head.appendChild(viewportMeta);
  }
  viewportMeta.setAttribute('content', 'width=' + vp.width + ', initial-scale=1');

  return function restore() {
    if (!doc.head) return;
    let meta = doc.querySelector('meta[name="viewport"]');
    if (!meta) return;
    if (hadMeta && originalContent !== null) {
      meta.setAttribute('content', originalContent);
    } else if (!hadMeta) {
      meta.remove();
    }
  };
}

/**
 * Run audit using the visible page iframe by resizing it per device.
 * Avoids hidden-iframe load timeouts; the visible iframe has already loaded.
 * Caller must restore iframe dimensions after both devices (updateIframeDimensions(selectedDevice)).
 */
async function runAuditUsingVisibleIframe(device) {
  if (!iframeElement || !iframeElement.contentWindow || !iframeElement.contentDocument) {
    throw new Error('Visible iframe or its document not available');
  }
  let auditDoc = iframeElement.contentDocument;
  let win = iframeElement.contentWindow;
  if (!auditDoc.body) {
    throw new Error('Visible iframe document has no body');
  }

  let vp = DEVICE_VIEWPORTS[device];
  if (!vp) throw new Error('Unknown device: ' + device);

  setVisibleIframeAuditDimensions(device);
  let restoreViewportMeta = injectViewportMetaForAudit(auditDoc, device);
  try {
    try { win.dispatchEvent(new Event('resize')); } catch (e) {}
    await new Promise(function (r) { setTimeout(r, 1000); });

    if (!win.webyes || !win.webyes.run) {
      let axeScript = auditDoc.createElement('script');
      axeScript.src = (window.wya11yChecker && window.wya11yChecker.assetsUrl ? window.wya11yChecker.assetsUrl : '') + 'js/webyes-a11y-core.min.js';
      auditDoc.head.appendChild(axeScript);
      await new Promise(function (resolve, reject) {
        let t = setTimeout(function () { reject(new Error('Axe-core load timeout')); }, 10000);
        axeScript.onload = function () { clearTimeout(t); resolve(); };
        axeScript.onerror = function () { clearTimeout(t); reject(new Error('Failed to load axe-core')); };
      });
    }
    await new Promise(function (r) { setTimeout(r, 1000); });

    let wcagTags = ['wcag2a', 'wcag2aa', 'wcag2aaa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'section508', 'best-practice'];
    let runOptions = { runOnly: { type: 'tag', values: wcagTags } };
    let results = await win.webyes.run(auditDoc, runOptions);
    return results;
  } finally {
    restoreViewportMeta();
  }
}

/**
 * Load WCAG guidelines data
 */
async function loadWCAGGuidelines() {
  try {
    const baseUrl = window.wya11yChecker.assetsUrl + 'data/wcag_guidelines.json';
    const version = (window.wya11yChecker && window.wya11yChecker.version) ? window.wya11yChecker.version : '';
    const guidelinesUrl = version ? baseUrl + '?v=' + encodeURIComponent(version) : baseUrl;
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
  
  // Helper to parse affected disabilities.
  // Accepts: (1) pipe-separated "Blind | Low vision | Cognitive", (2) JSON-array string '["blind","deafblind"]', (3) array.
  // Output is always camelCase keys matching AffectedDisabilities: blind, lowVision, cognitive, mobility, deafBlind, deaf, etc.
  function parseAffectedDisabilities(disabilitiesString) {
    if (!disabilitiesString) return [];

    let toCamelCase = function (str) {
      let trimmed = (str || '').trim();
      let words = trimmed.split(/\s+/).filter(Boolean);
      if (words.length === 0) return '';
      if (words.length === 1) {
        let lower = words[0].toLowerCase();
        if (lower === 'deafblind') return 'deafBlind';
        if (lower === 'lowvision') return 'lowVision';
        if (lower === 'colorblindness' || lower === 'attentiondeficit') return lower;
        if (lower === 'mobility' || lower === 'cognitive' || lower === 'blind' || lower === 'deaf' || lower === 'hearing') return lower;
        return lower;
      }
      let normalized = words.map(function (w) { return w.toLowerCase(); }).join('');
      if (normalized === 'colorblindness' || normalized === 'attentiondeficit') return normalized;
      if (normalized === 'deafblind') return 'deafBlind';
      return words[0].toLowerCase() + words.slice(1).map(function (w) {
        return w.charAt(0).toUpperCase() + (w.slice(1) || '').toLowerCase();
      }).join('');
    };

    if (Array.isArray(disabilitiesString)) {
      return disabilitiesString.map(function (d) {
        let s = typeof d === 'string' ? d : String(d);
        return toCamelCase(s);
      }).filter(Boolean);
    }

    if (typeof disabilitiesString === 'string') {
      let s = disabilitiesString.trim();
      if (s.indexOf('[') === 0 && s.indexOf(']') !== -1) {
        try {
          let arr = JSON.parse(s);
          if (Array.isArray(arr)) {
            return arr.map(function (d) { return toCamelCase(String(d)); }).filter(Boolean);
          }
        } catch (e) { /* fall through to pipe-separated */ }
      }
      return s.split('|').map(function (d) { return toCamelCase(d); }).filter(Boolean);
    }

    return [];
  }

  // Format best-practice violations (same issue shape as WCAG for detail page reuse)
  const formatBestPractices = (violations, device) => {
    const filtered = (violations || []).filter(v => (v.tags || []).includes('best-practice'));
    return filtered.map((v) => {
      const guideline = guidelines[v.id] || {};
      const issueSeverity = guideline.issue_severity || v.impact || 'moderate';
      const title = guideline.title || v.help || v.id;
      let standardCode = guideline.standard_code || undefined;
      let wcagLevel = guideline.wcag_level || '';
      let wcagVersion = '';
      if (guideline.wcag_version_number) {
        const ver = guideline.wcag_version_number;
        wcagVersion = String(ver).includes('.') ? String(ver) : `${ver}.0`;
      }
      if (!standardCode || !wcagLevel || !wcagVersion) {
        const derived = deriveWcagFromAxeTags(v.tags);
        if (derived.code && !standardCode) standardCode = derived.code;
        if (derived.level && !wcagLevel) wcagLevel = derived.level;
        if (derived.version && !wcagVersion) wcagVersion = derived.version;
      }
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
        issue_category: 'best-practice',
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

  const desktopViolations = results.desktop ? results.desktop.violations : [];
  const mobileViolations = results.mobile ? results.mobile.violations : [];
  const bestPracticesDesktop = formatBestPractices(desktopViolations, 'desktop');
  const bestPracticesMobile = formatBestPractices(mobileViolations, 'mobile');

  return {
    body: {
      data: {
        report_data: {
          desktop: results.desktop ? formatViolations(results.desktop.violations, 'desktop') : [],
          mobile: results.mobile ? formatViolations(results.mobile.violations, 'mobile') : [],
          best_practices: {
            desktop: bestPracticesDesktop,
            mobile: bestPracticesMobile
          }
        }
      }
    },
    mobile: results.mobile || null,
    desktop: results.desktop || null
  };
}

/**
 * Run multi-device audit
 */
async function runMultiDeviceAuditFormattedIsolated() {
  if (!iframeElement || !iframeElement.contentDocument || !iframeElement.contentDocument.body || iframeElement.contentDocument.body.children.length === 0) {
    throw new Error('Visible iframe or page content not available');
  }
  let results = {};
  let savedDevice = selectedDevice;
  try {
    for (let deviceKey in DEVICE_VIEWPORTS) {
      if (DEVICE_VIEWPORTS.hasOwnProperty(deviceKey)) {
        results[deviceKey] = await runAuditUsingVisibleIframe(deviceKey);
      }
    }
  } finally {
    updateIframeDimensions(savedDevice);
  }
  let enrichedResults = await enrichWithWCAG(results);
  let rd = enrichedResults.body.data.report_data;
  currentAuditResultByDevice.desktop = {
    reportData: rd.desktop,
    bestPracticesData: rd.best_practices ? rd.best_practices.desktop : [],
    raw: enrichedResults.desktop
  };
  currentAuditResultByDevice.mobile = {
    reportData: rd.mobile,
    bestPracticesData: rd.best_practices ? rd.best_practices.mobile : [],
    raw: enrichedResults.mobile
  };
  return enrichedResults;
}

/**
 * Run audit for a single device and update cache/store. Respects currentAuditTargetDevice (ignores result if user switched device).
 * Returns the merged audit result when store was updated, or undefined if audit was ignored (device switched).
 */
async function runSingleDeviceAuditAndUpdateStore(device) {
  if (!iframeElement || !iframeElement.contentDocument || !iframeElement.contentDocument.body || iframeElement.contentDocument.body.children.length === 0) {
    throw new Error('Visible iframe or page content not available');
  }
  currentAuditTargetDevice = device;
  let store = window.useAuditStore ? window.useAuditStore.getState() : null;
  if (store && store.setAuditInProgress) {
    store.setAuditInProgress(true);
  }
  let savedDevice = selectedDevice;
  try {
    let rawResult = await runAuditUsingVisibleIframe(device);
    if (currentAuditTargetDevice !== device) {
      return;
    }
    let partialResults = {};
    partialResults[device] = rawResult;
    let enriched = await enrichWithWCAG(partialResults);
    let reportData = enriched.body.data.report_data;
    let bp = reportData.best_practices || {};
    currentAuditResultByDevice[device] = {
      reportData: reportData[device],
      bestPracticesData: bp[device] || [],
      raw: rawResult
    };
    let merged = buildMergedAuditResult();
    if (store) {
      store.setAuditResult(merged);
      store.setAuditError(null);
    }
    return merged;
  } catch (error) {
    console.error('[WebYes Checker] Single-device audit failed:', error);
    if (currentAuditTargetDevice === device && store) {
      let errorMessage = error && error.message ? error.message : String(error);
      if (errorMessage.indexOf('timeout') !== -1 || errorMessage.indexOf('Timeout') !== -1 || errorMessage.indexOf('didn\'t load in time') !== -1) {
        store.setAuditError('timeout');
      } else {
        store.setAuditError(errorMessage);
      }
    }
    if (store) {
      store.setAuditResult(buildMergedAuditResult());
    }
    throw error;
  } finally {
    updateIframeDimensions(savedDevice);
    if (store && store.setAuditInProgress) {
      store.setAuditInProgress(false);
    }
  }
}

/**
 * Run audit for one device (wait for store/iframe, then run single-device audit). Used by retry; returns merged result.
 */
async function runAuditForDeviceOnly(device) {
  await waitForAuditStore();
  await waitForVisibleIframeBodyContent(25000);
  return runSingleDeviceAuditAndUpdateStore(device);
}

/**
 * Highlight utilities for marking elements
 */
window.webyesHighlightUtils = {
  markers: [],
  borderedElement: null,
  currentItems: null,
  currentTargetDocument: null,
  currentIssueCategory: null,  // 'wcag' | 'best-practice' – used for marker/border color
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
        .wya11y-issue-marker.wya11y-issue-marker--best-practice {
          background-color: #f59e0b !important;
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
        .wya11y-issue-border.wya11y-issue-border--best-practice {
          outline-color: #f59e0b !important;
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
  
  // Mark all issue items with dots (red for violations, amber for best-practice)
  markIssueItems(items, issueCategory) {
    this.clearMarkers();
    
    const targetDoc = this.getIframeDocument();
    if (!targetDoc) {
      return;
    }
    
    this.currentIssueCategory = issueCategory === 'best-practice' ? 'best-practice' : 'wcag';
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
    button.className = 'wya11y-issue-marker' + (this.currentIssueCategory === 'best-practice' ? ' wya11y-issue-marker--best-practice' : '');
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
  
  // Add border to element (red for violations, amber for best-practice)
  addElementBorder(selector) {
    this.removeElementBorder();
    
    const targetDoc = this.getIframeDocument();
    if (!targetDoc) return;
    
    const element = targetDoc.querySelector(selector);
    if (element) {
      element.classList.add('wya11y-issue-border');
      if (this.currentIssueCategory === 'best-practice') {
        element.classList.add('wya11y-issue-border--best-practice');
      }
      this.borderedElement = element;
    }
  },
  
  // Remove border from element
  removeElementBorder() {
    if (this.borderedElement) {
      this.borderedElement.classList.remove('wya11y-issue-border', 'wya11y-issue-border--best-practice');
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
 * Run audit with proper preparation for the currently selected device (used by retry / Scan again).
 */
async function runAuditWithPreparation() {
  if (!window.useAuditStore) {
    await waitForAuditStore();
  }
  await waitForVisibleIframeBodyContent(25000);
  return runSingleDeviceAuditAndUpdateStore(selectedDevice);
}

/**
 * Expose audit utilities globally
 */
window.webyesAuditUtils = {
  runMultiDeviceAuditFormattedIsolated,
  runAuditWithPreparation,
  runAuditForDeviceOnly,
  buildMergedAuditResult
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

import './bootstrap';

import Chart from 'chart.js/auto';
import DOMPurify from 'dompurify';

/**
 * The chat view's inline script reads both of these off `window`. Bundling
 * them here rather than loading them from a CDN means a blocked or slow CDN
 * can no longer leave replies rendering unsanitised, and no third party can
 * change what runs on the page.
 */
window.Chart = Chart;
window.DOMPurify = DOMPurify;

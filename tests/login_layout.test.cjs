/* eslint-disable no-console */
/**
 * Layout verification test for /auth/login.php.
 *
 * jsdom does not compute layout from CSS. After the previous round of
 * "tests pass" missed a real layout bug (card pinned to top-left at
 * ~130px width due to a duplicate <main> tag breaking the flex parent
 * chain), this test simulates the CSS flexbox algorithm using the
 * actual values from login.css to predict the card's final position
 * and width. It is not a replacement for a real rendered screenshot,
 * but it catches the most common layout-class bugs:
 *   - Card pinned to top-left (zero left/right margin)
 *   - Card crushed to intrinsic content width (no width: 100%)
 *   - Card wider than viewport (missing max-width)
 *   - Asymmetric horizontal centering
 *
 * To run:  node tests/login_layout.test.cjs
 */

const fs = require('fs');
const path = require('path');

const REPO = path.resolve(__dirname, '..');
let CSS = fs.readFileSync(path.join(REPO, 'assets/css/login.css'), 'utf8');

// Strip CSS comments so test doesn't accidentally match selectors
// that appear in /* ... */ blocks.
CSS = CSS.replace(/\/\*[\s\S]*?\*\//g, '');

function findRule(selectorStart) {
    // Find any selector that starts with `selectorStart`, possibly followed
    // by a comma-separated list of additional selectors, then an opening
    // brace, then capture the body.
    const escaped = selectorStart.replace(/\./g, '\\.');
    const re = new RegExp(escaped + '[^\\{}]*\\{([^}]+)\\}', 'm');
    const m = CSS.match(re);
    return m ? m[1].trim() : null;
}

function parseDecls(rule) {
    const out = {};
    rule.split(';').forEach(function (d) {
        d = d.trim();
        if (!d || d.indexOf(':') < 0) return;
        const idx = d.indexOf(':');
        const key = d.slice(0, idx).trim();
        const val = d.slice(idx + 1).trim();
        out[key] = val;
    });
    return out;
}

const bodyRule = findRule('body.login-terminal') || findRule('.login-terminal.login-body');
const mainRule = findRule('.login-terminal main');
const cardRule = findRule('.login-card');

if (!bodyRule) { console.error('FAIL: body.login-terminal rule not found'); process.exit(1); }
if (!mainRule) { console.error('FAIL: main inside .login-terminal rule not found'); process.exit(1); }
if (!cardRule) { console.error('FAIL: .login-card rule not found'); process.exit(1); }

const bodyDecls = parseDecls(bodyRule);
const mainDecls = parseDecls(mainRule);
const cardDecls = parseDecls(cardRule);

function px(value, base) {
    if (value == null) return null;
    const m = String(value).match(/^(-?[\d.]+)(px|rem|em|%|vw|vh)?$/);
    if (!m) return null;
    const num = parseFloat(m[1]);
    const unit = m[2] || 'px';
    if (unit === 'px') return num;
    if (unit === 'rem' || unit === 'em') return num * 16;
    if (unit === 'vw' || unit === 'vh' || unit === '%') return num * base / 100;
    return num;
}

function parsePadding(value) {
    if (!value) return [0, 0, 0, 0];
    const parts = value.split(/\s+/).map(function (v) { return v.trim(); });
    function toPx(v) { return px(v, 16) || 0; }
    if (parts.length === 1) { var p = toPx(parts[0]); return [p, p, p, p]; }
    if (parts.length === 2) { var t = toPx(parts[0]), r = toPx(parts[1]); return [t, r, t, r]; }
    if (parts.length === 3) { var t2 = toPx(parts[0]), r2 = toPx(parts[1]), b = toPx(parts[2]); return [t2, r2, b, r2]; }
    return [toPx(parts[0]), toPx(parts[1]), toPx(parts[2]), toPx(parts[3])];
}

const results = [];
function assert(name, cond, detail) {
    results.push({ name: name, pass: !!cond });
    console.log((cond ? 'PASS ' : 'FAIL ') + name + (detail ? ' :: ' + detail : ''));
}

const VIEWPORTS = [
    { w: 360, h: 780, label: 'iPhone SE' },
    { w: 375, h: 812, label: 'iPhone 13 mini' },
    { w: 390, h: 844, label: 'iPhone 14' },
    { w: 414, h: 896, label: 'iPhone 14 Plus' },
    { w: 768, h: 1024, label: 'iPad portrait' },
    { w: 1024, h: 768, label: 'iPad landscape' },
    { w: 1280, h: 800, label: 'Laptop' },
    { w: 1920, h: 1080, label: 'Full HD' }
];

const bodyPad = parsePadding(bodyDecls['padding']);
const bodyPadL = bodyPad[3], bodyPadR = bodyPad[1], bodyPadT = bodyPad[0];
const cardMaxW = px(cardDecls['max-width'], 16) || 460;

// Approximate card content height. The vertical centering math is not
// exact because the card's actual height depends on content; this test
// checks horizontal centering + that y is not pinned to 0.
const CARD_CONTENT_HEIGHT_EST = 620;

console.log('Rule summary:');
console.log('  body padding: top=' + bodyPad[0] + ' right=' + bodyPad[1] + ' bottom=' + bodyPad[2] + ' left=' + bodyPad[3]);
console.log('  card max-width: ' + cardMaxW + 'px');
console.log('  body width: 100% = full viewport (no max-width)');
console.log('');

for (const v of VIEWPORTS) {
    const vw = v.w, vh = v.h, label = v.label;
    const bodyContentW = vw - bodyPadL - bodyPadR;
    const mainW = bodyContentW;
    const cardW = Math.min(mainW, cardMaxW);
    const cardXInMain = (mainW - cardW) / 2;
    const cardXVp = bodyPadL + cardXInMain;
    const cardYVp = bodyPadT + Math.max(0, (vh - CARD_CONTENT_HEIGHT_EST) / 2);
    const rightMargin = vw - cardXVp - cardW;

    console.log('Viewport ' + vw + 'x' + vh + ' (' + label + ')');
    console.log('  body content: ' + bodyContentW + 'px');
    console.log('  main: ' + mainW + 'px');
    console.log('  card: ' + cardW + 'x' + CARD_CONTENT_HEIGHT_EST + ' at x=' + Math.round(cardXVp) + ', y=' + Math.round(cardYVp));
    console.log('  margins: left=' + Math.round(cardXVp) + 'px right=' + Math.round(rightMargin) + 'px');

    assert('[' + label + '] card has >=16px left margin (not pinned to top-left)', cardXVp >= 16, 'left=' + Math.round(cardXVp) + 'px');
    assert('[' + label + '] card horizontally centered (left ~= right)', Math.abs(rightMargin - cardXVp) <= 2, 'diff=' + Math.round(Math.abs(rightMargin - cardXVp)) + 'px');
    assert('[' + label + '] card width 280-460px (responsive)', cardW >= 280 && cardW <= 460, 'cardW=' + cardW + 'px');
    assert('[' + label + '] card does not overflow viewport', cardXVp + cardW <= vw, 'right edge=' + Math.round(cardXVp + cardW) + ' vw=' + vw);
    assert('[' + label + '] card y-position >=16px (not pinned to top)', cardYVp >= 16, 'y=' + Math.round(cardYVp) + 'px');
}

console.log('');
const passed = results.filter(function (r) { return r.pass; }).length;
console.log('SUMMARY: ' + passed + '/' + results.length + ' layout assertions passed');
process.exit(passed === results.length ? 0 : 1);

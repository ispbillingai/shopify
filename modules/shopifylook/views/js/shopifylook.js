/*
 * Icon font readiness.
 *
 * PrestaShop's admin icons are Material Symbols ligatures — the markup contains
 * the icon's *name* as text — served from a 3.2 MB font declared
 * font-display:swap. Until it lands the browser paints "point_of_sale",
 * "keyboard_arrow_down" and friends, which are far wider than an icon and shove
 * the sidebar labels into a one-letter-per-line column.
 *
 * admin.css hides the icons until this script marks the font ready. The timeout
 * is the important half: if the font 404s or the network dies, the back office
 * must still end up usable rather than permanently iconless.
 */

(function () {
    'use strict';

    var root = document.documentElement;
    var settled = false;

    function ready() {
        if (settled) {
            return;
        }

        settled = true;
        root.classList.add('sl-icons-ready');
    }

    // No Font Loading API (or no fonts at all): show the icons and let the
    // browser do whatever it was going to do anyway.
    if (!document.fonts || typeof document.fonts.load !== 'function') {
        ready();

        return;
    }

    try {
        document.fonts.load('24px "Material Symbols Outlined"').then(ready, ready);
    } catch (error) {
        ready();
    }

    // Belt and braces: never leave the menu without icons for more than a moment.
    window.setTimeout(ready, 2500);
}());

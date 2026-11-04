/*
 * asset/frontend-blocks-cart.js - Versione 2.8.2
 * Author: Gianfranco Greco con Codice Sorgente
 * Copyright (c) 2025 Gianfranco Greco
 * Licensed under the GNU GPL v2 or later: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Carrello a blocchi (WooCommerce Cart block): il carrello classico usa i filtri PHP
 * woocommerce_cart_item_name / woocommerce_cart_item_class, che il blocco ignora.
 * Qui gli stessi dati arrivano dalla Store API (extensions['wcb-bundle']):
 * - classi sulle righe (cornice del bundle) con il filtro cartItemClass;
 * - una riga di intestazione sopra il bundle con "Bundle: ...", "Modifica Bundle" ed "Elimina bundle";
 * - il cestino delle singole righe del bundle è nascosto (CSS): si elimina il bundle intero.
*/
(function () {
    'use strict';

    var NS = 'wcb-bundle';
    var cfg = window.wcb_blocks_cart || {};
    var i18n = cfg.i18n || {};
    var bundles = {}; // bundle_id -> { name, edit_url, color, keys: [cart item key] }

    function bundleData(extensions) {
        var d = extensions && extensions[NS];
        return d && d.bundle_id ? d : null;
    }

    function rowClasses(d) {
        return ['wcb-cart-item-child', 'wcb-group-' + d.position, 'wcb-bundle-color-' + (d.color === 2 ? 2 : 1), 'wcb-bid-' + d.bundle_id];
    }

    function rememberBundle(d, key) {
        var b = bundles[d.bundle_id] || (bundles[d.bundle_id] = { keys: [] });
        b.name = d.bundle_name;
        b.edit_url = d.edit_url;
        b.color = d.color === 2 ? 2 : 1;
        if (key && b.keys.indexOf(key) === -1) b.keys.push(key);
    }

    // 1. Classi delle righe con il filtro del blocco
    function registerFilters() {
        var api = window.wc && window.wc.blocksCheckout;
        if (!api || typeof api.registerCheckoutFilters !== 'function') return;
        api.registerCheckoutFilters(NS, {
            cartItemClass: function (value, extensions, args) {
                var d = bundleData(extensions);
                // Solo nel carrello: il riepilogo del checkout resta com'è (come nel classico)
                if (!d || (args && args.context && args.context !== 'cart')) return value;
                rememberBundle(d, args && args.cartItem ? args.cartItem.key : null);
                return (value ? value + ' ' : '') + rowClasses(d).join(' ');
            }
        });
    }

    function getStore() {
        var data = window.wp && window.wp.data;
        if (!data || !data.select) return null;
        var store = data.select('wc/store/cart');
        return store && typeof store.getCartData === 'function' ? store : null;
    }

    // Rete di sicurezza: se il filtro è stato registrato dopo il primo disegno del carrello,
    // le classi si ricavano dai dati del carrello (stesso ordine delle righe)
    var GROUP_CLASS_RE = /^wcb-(cart-item-child|group-(start|middle|end|single)|bundle-color-\d|bid-.+)$/;
    function syncRowsFromStore() {
        var store = getStore();
        if (!store) return;
        var items = (store.getCartData() || {}).items || [];
        Object.keys(bundles).forEach(function (id) { bundles[id].keys = []; });
        items.forEach(function (item) {
            var d = bundleData(item.extensions);
            if (d) rememberBundle(d, item.key);
        });
        var rows = document.querySelectorAll('.wc-block-cart .wc-block-cart-items tbody tr.wc-block-cart-items__row');
        if (!items.length || rows.length !== items.length) return;
        Array.prototype.forEach.call(rows, function (row, idx) {
            var d = bundleData(items[idx].extensions);
            var wanted = d ? rowClasses(d) : [];
            var current = Array.prototype.filter.call(row.classList, function (c) { return GROUP_CLASS_RE.test(c); });
            // Tocca il DOM solo se serve: ogni modifica riattiva l'observer
            if (current.slice().sort().join(' ') === wanted.slice().sort().join(' ')) return;
            current.forEach(function (c) { row.classList.remove(c); });
            wanted.forEach(function (c) { row.classList.add(c); });
        });
    }

    function bundleIdFromRow(row) {
        var match = (' ' + row.className + ' ').match(/ wcb-bid-([A-Za-z0-9_-]+) /);
        return match ? match[1] : null;
    }

    // 2. Intestazione del bundle: una riga in più subito prima della prima riga del bundle
    function buildHeader(id, data, colspan) {
        var tr = document.createElement('tr');
        tr.className = 'wcb-bundle-header wcb-cart-item-child wcb-bundle-color-' + data.color;
        tr.setAttribute('data-wcb-bundle-id', id);
        var td = document.createElement('td');
        td.colSpan = colspan;
        var box = document.createElement('div');
        box.className = 'wcb-cart-bundle-info wcb-bundle-header-inner';
        box.setAttribute('data-wcb-bundle-id', id);

        var label = document.createElement('span');
        label.className = 'wcb-bundle-label';
        label.textContent = (i18n.bundle_label || 'Bundle: %s').replace('%s', data.name || '');
        box.appendChild(label);

        var actions = document.createElement('span');
        actions.className = 'wcb-bundle-actions';
        if (data.edit_url) {
            var edit = document.createElement('a');
            edit.className = 'wcb-edit-bundle-link';
            edit.href = data.edit_url;
            edit.textContent = i18n.edit_bundle || 'Modifica Bundle';
            actions.appendChild(edit);
        }
        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'wcb-remove-bundle-link';
        remove.setAttribute('data-wcb-bundle-id', id);
        remove.textContent = i18n.remove_bundle || 'Elimina bundle';
        actions.appendChild(remove);
        box.appendChild(actions);

        td.appendChild(box);
        tr.appendChild(td);
        return tr;
    }

    function syncHeaders() {
        var cart = document.querySelector('.wc-block-cart');
        if (!cart) return;
        var firstRows = {};
        Array.prototype.forEach.call(cart.querySelectorAll('tr.wc-block-cart-items__row.wcb-cart-item-child'), function (row) {
            var id = bundleIdFromRow(row);
            if (id && !firstRows[id]) firstRows[id] = row;
        });
        // Intestazioni di bundle non più nel carrello
        Array.prototype.forEach.call(cart.querySelectorAll('tr.wcb-bundle-header'), function (h) {
            if (!firstRows[h.getAttribute('data-wcb-bundle-id')]) h.remove();
        });
        Object.keys(firstRows).forEach(function (id) {
            var row = firstRows[id];
            var data = bundles[id];
            if (!data) return;
            var header = cart.querySelector('tr.wcb-bundle-header[data-wcb-bundle-id="' + id + '"]');
            if (!header) header = buildHeader(id, data, row.children.length || 1);
            // Il colore alternato cambia se cambia l'ordine dei bundle nel carrello
            var colorClass = 'wcb-bundle-color-' + data.color;
            if (!header.classList.contains(colorClass)) {
                header.classList.remove('wcb-bundle-color-1', 'wcb-bundle-color-2');
                header.classList.add(colorClass);
            }
            if (row.previousElementSibling !== header) row.parentNode.insertBefore(header, row);
        });
    }

    var scheduled = false;
    function scheduleSync() {
        if (scheduled) return;
        scheduled = true;
        window.requestAnimationFrame(function () {
            scheduled = false;
            syncRowsFromStore();
            syncHeaders();
        });
    }

    function observe() {
        // Il blocco ridisegna le righe a ogni aggiornamento del carrello: ripassiamo dopo ogni modifica
        var observer = new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var t = mutations[i].target;
                // Ignora le modifiche dentro le nostre intestazioni
                if (t && t.closest && t.closest('.wcb-bundle-header')) continue;
                scheduleSync();
                return;
            }
        });
        observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });
        scheduleSync();
    }

    // 3. "Elimina bundle": basta togliere una riga, il server toglie tutte le altre del bundle
    function removeBundle(id, button) {
        var data = bundles[id];
        var key = data && data.keys.length ? data.keys[0] : null;
        if (!key) return;
        if (!window.confirm(i18n.confirm_remove_whole || 'Vuoi eliminare l\'intero bundle dal carrello?')) return;
        var dispatch = window.wp && window.wp.data && window.wp.data.dispatch ? window.wp.data.dispatch('wc/store/cart') : null;
        if (dispatch && typeof dispatch.removeItemFromCart === 'function') {
            button.disabled = true;
            button.classList.add('is-loading');
            Promise.resolve(dispatch.removeItemFromCart(key)).catch(function () {
                button.disabled = false;
                button.classList.remove('is-loading');
            });
            return;
        }
        // Senza lo store: clic sul cestino (nascosto) della prima riga, saltando la conferma qui sotto
        var row = document.querySelector('.wc-block-cart tr.wc-block-cart-items__row.wcb-bid-' + id);
        var link = row ? row.querySelector('.wc-block-cart-item__remove-link') : null;
        if (link) {
            skipConfirm = true;
            link.click();
            skipConfirm = false;
        }
    }

    var skipConfirm = false;
    document.addEventListener('click', function (e) {
        var target = e.target && e.target.closest ? e.target : null;
        if (!target) return;
        var removeBtn = target.closest('.wcb-remove-bundle-link');
        if (removeBtn && removeBtn.closest('.wc-block-cart')) {
            e.preventDefault();
            removeBundle(removeBtn.getAttribute('data-wcb-bundle-id'), removeBtn);
            return;
        }
        // Cestino di una riga del bundle (nascosto via CSS, ma un tema può mostrarlo):
        // conferma in fase di cattura, prima del gestore di React
        var link = target.closest('.wc-block-cart-item__remove-link');
        if (!link || skipConfirm || !link.closest('tr.wcb-cart-item-child')) return;
        if (!window.confirm(i18n.confirm_remove_bundle || 'Rimuovendo questo articolo verrà tolto dal carrello l\'intero bundle. Vuoi procedere?')) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
        }
    }, true);

    registerFilters();
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', observe);
    } else {
        observe();
    }
})();

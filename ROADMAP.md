# 🎯 Roadmap Sviluppo - WooC Bundle gIA70

## 📊 Overview

Questo documento contiene la pianificazione dettagliata delle prossime release del plugin.

---

## ✅ v2.5 - COMPLETATA (15/02/2026)

### Funzionalità Implementate
- ✅ Tab "Bundle Disponibili" con shortcode rapidi
- ✅ Sistema copia-incolla shortcode con un clic
- ✅ Manuale operativo aggiornato
- ✅ Sistema di release automatizzato con GitHub Actions
- ✅ Auto-aggiornamento del plugin da GitHub

---

## 🚀 v2.6 - PROSSIMA RELEASE

### Focus: Miglioramenti UX Admin

#### 🎯 Feature Principale: Drag & Drop Riordino Prodotti

**Problema da Risolvere:**
Attualmente l'ordine dei prodotti in un gruppo è fisso. Gli admin devono eliminare e riaggiungere prodotti per cambiarne l'ordine.

**Soluzione:**
Implementare drag & drop per riordinare i prodotti all'interno di ciascun gruppo.

**Implementazione Tecnica:**

1. **Frontend (Admin):**
   - Usare jQuery UI Sortable (già incluso in WordPress)
   - Aggiungere handle visivo per drag & drop
   - Feedback visivo durante il trascinamento
   - Toast notification al salvataggio

2. **Backend:**
   - Nuovo campo meta `_product_order` per salvare l'ordine
   - AJAX endpoint per salvare l'ordine: `wcb_save_product_order`
   - Aggiornare la query prodotti per rispettare l'ordine salvato

3. **File da Modificare:**
   - `assets/admin.js` - Logica drag & drop
   - `assets/admin.css` - Stili per drag & drop
   - `woocommerce-custom-bundle.php` - AJAX handler
   - `templates/single-product/add-to-cart/custom-bundle.php` - Query con ordine

**Stima Implementazione:** 4-6 ore

**Test Necessari:**
- ✅ Drag & drop funziona con mouse
- ✅ Drag & drop funziona con touch (mobile)
- ✅ Ordine si salva correttamente
- ✅ Ordine persiste dopo aggiornamento pagina
- ✅ Ordine si riflette nel frontend
- ✅ Compatibilità con Classic Editor e Gutenberg

**Benefici:**
- ⚡ UX migliore per amministratori
- 🎯 Controllo totale sulla presentazione prodotti
- 🚀 Workflow più rapido

---

## 🧩 v2.7 - RELEASE FUTURA

### Focus: Compatibilità con Block Themes

#### 🧩 Feature Principale: Supporto Temi a Blocchi

**Problema da Risolvere:**
I temi a blocchi (Block Themes) di WordPress 6.0+ usano Full Site Editing (FSE) e potrebbero non visualizzare correttamente i template PHP del plugin.

**Soluzione:**
Creare block pattern e template parts nativi per i temi FSE.

**Implementazione Tecnica:**

1. **Block Pattern per Bundle:**
   - Creare pattern riutilizzabili per mostrare bundle
   - Pattern per grid layout
   - Pattern per list layout
   - Pattern per single bundle

2. **Template Parts:**
   - `bundle-form.html` - Form selezione bundle
   - `bundle-summary.html` - Riepilogo bundle
   - `bundle-price.html` - Visualizzazione prezzo

3. **Block Personalizzato (Opzionale):**
   - Custom Gutenberg block "Bundle Display"
   - Settings per layout, stile, filtri
   - Preview live nel editor

4. **File da Creare:**
   - `/patterns/bundle-*.php` - Pattern definitions
   - `/parts/bundle-*.html` - Template parts
   - `/blocks/bundle-display/` - Custom block (se necessario)

**Ricerca Necessaria:**
- ⚠️ Analisi compatibilità temi FSE più popolari (Twenty Twenty-Four, etc.)
- ⚠️ Studio API Block Patterns WordPress
- ⚠️ Test con Full Site Editor
- ⚠️ Verifica necessità custom block vs pattern

**Stima Implementazione:** 10-15 ore (include ricerca)

**Test Necessari:**
- ✅ Funziona con Twenty Twenty-Four theme
- ✅ Funziona con altri temi FSE popolari
- ✅ Fallback elegante per temi classic
- ✅ Editor esperienza ottimale
- ✅ Performance non impattata

**Benefici:**
- 🎨 Compatibilità con temi moderni
- 📱 Migliore esperienza mobile
- ⚡ Velocità di caricamento ottimizzata
- 🚀 Future-proof per WordPress 7.0+

---

## 📋 Feature Queue (Backlog)

### Feature Minori da Valutare

#### v2.8+
- 📊 **Dashboard Analytics** - Statistiche vendite bundle
- 🎨 **Live Preview** - Anteprima bundle in tempo reale durante configurazione
- 📧 **Email Personalizzate** - Template email specifici per bundle
- 🔍 **Ricerca Avanzata** - Filtri e ricerca nei prodotti del gruppo
- 🌐 **Multilingua** - Supporto WPML/Polylang
- 📦 **Bundle Templates** - Template predefiniti per bundle comuni
- 💾 **Import/Export** - Importa/esporta configurazioni bundle

---

## 🎯 Priorità di Sviluppo

### Alta Priorità
1. ✅ v2.5 - Release system + Shortcode (COMPLETATA)
2. 🚀 v2.6 - Drag & Drop riordino prodotti

### Media Priorità
3. 🧩 v2.7 - Block Themes compatibility
4. 📊 Dashboard Analytics
5. 🎨 Live Preview

### Bassa Priorità
6. Altre feature del backlog

---

## 📝 Note di Sviluppo

### Linee Guida
- Mantenere retrocompatibilità con WooCommerce 6.0+
- Testare con WordPress 6.0+
- Seguire WordPress Coding Standards
- Documentare ogni nuova feature nel manuale
- Aggiornare CHANGELOG.md ad ogni release
- Creare migration script se necessario

### Testing
- Test su almeno 3 temi diversi (classic theme, block theme, woocommerce theme)
- Test browser: Chrome, Firefox, Safari, Edge
- Test mobile: iOS, Android
- Test performance con 100+ prodotti in bundle

---

## 🤝 Contributi

Suggerimenti per nuove feature? Apri una issue su GitHub:
https://github.com/Gianfry70IT/WooC-Bundle-gIA70/issues

---

**Ultimo aggiornamento:** 15 Febbraio 2026

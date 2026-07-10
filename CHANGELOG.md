# Changelog

Tutte le modifiche notevoli a questo progetto verranno documentate in questo file.

Il formato è basato su [Keep a Changelog](https://keepachangelog.com/it/1.0.0/),
e questo progetto aderisce al [Semantic Versioning](https://semver.org/lang/it/).

---

## 🗺️ Roadmap - Prossime Release

### [2.7] - Pianificata  
**Focus: Compatibilità Moderna**

#### In Valutazione
- 🧩 **Compatibilità con Block Themes** - Supporto completo per i temi a blocchi WordPress
  - Block pattern personalizzati per bundle
  - Integrazione con Full Site Editing (FSE)
  - Template parts per bundle
  
---

## [2.6.1] - 2026-07-10

### Corretto
- 🐛 Il totale in anteprima ("Riepilogo Bundle") non si aggiornava cambiando l'opzione di un prodotto variabile con prezzi diversi: restava sempre il prezzo della prima opzione. Ora il prezzo di ogni variazione è incluso nei dati del template e il totale si aggiorna alla selezione (anche per i singoli "Pezzi" nelle modalità a quantità)

## [2.6] - 2026-07-10

### Aggiunto
- ✨ Ordinamento drag & drop dei prodotti all'interno di un gruppo (maniglia ☰ su ogni riga prodotto in admin); l'ordine viene salvato e rispettato nel frontend

## [2.5] - 2026-02-15

### Aggiunto
- ✨ Nuova tab "Bundle Disponibili" nel pannello impostazioni
- ✨ Tabella automatica con tutti i bundle pubblicati
- ✨ Shortcode con funzione copia-e-incolla (un clic)
- ✨ Feedback visivo quando lo shortcode viene copiato
- ✨ Link rapido "Modifica" per ogni bundle
- ✨ Istruzioni complete sull'uso degli shortcode
- ✨ Supporto shortcode con parametro ID: `[wcb_bundle_form id="123"]`

### Modificato
- 📝 Aggiornato manuale operativo con esempi shortcode
- 📝 Migliorata documentazione per l'uso con page builder (Elementor, etc.)
- 🔧 Aggiornati header versione in tutti i file

### Corretto
- 🐛 Risolto problema visualizzazione tab nel pannello impostazioni
- 🐛 Corretto comportamento condizionale delle tab

## [2.4.10] - 2026-02-14

### Corretto
- 🐛 Risolti problemi visualizzazione bundle nel carrello
- 🐛 Corretto "bundle circling" (raggruppamento visivo)
- 🐛 Ripristinato pulsante "Modifica Bundle" nel carrello
- 🐛 Migliorata persistenza metadata bundle nella sessione WooCommerce

### Rimosso
- 🧹 Eliminati tutti i log di debug

## Template per Versioni Future

### [Unreleased]

### Aggiunto
- Nuove funzionalità

### Modificato
- Miglioramenti a funzionalità esistenti

### Deprecato
- Funzionalità che verranno rimosse

### Rimosso
- Funzionalità eliminate

### Corretto
- Bug fix

### Sicurezza
- Patch di sicurezza

---

## Come usare questo Changelog

- **Aggiunto**: per nuove funzionalità
- **Modificato**: per modifiche a funzionalità esistenti
- **Deprecato**: per funzionalità in via di dismissione
- **Rimosso**: per funzionalità rimosse
- **Corretto**: per bug fix
- **Sicurezza**: per patch di sicurezza

### Versionamento

- **MAJOR** (x.0.0): Cambiamenti incompatibili con versioni precedenti
- **MINOR** (0.x.0): Nuove funzionalità retrocompatibili
- **PATCH** (0.0.x): Bug fix retrocompatibili

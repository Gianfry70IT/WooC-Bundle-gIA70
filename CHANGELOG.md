# Changelog

Tutte le modifiche notevoli a questo progetto verranno documentate in questo file.

Il formato è basato su [Keep a Changelog](https://keepachangelog.com/it/1.0.0/),
e questo progetto aderisce al [Semantic Versioning](https://semver.org/lang/it/).

---

## 🗺️ Roadmap - Prossime Release

### Prossima release - In valutazione
**Focus: Temi a blocchi** (il supporto base FSE è arrivato con la 2.8.1)

- 🧩 Block pattern personalizzati per bundle
- 🧩 Template parts per bundle
  
---

## [2.8.1] - 2026-11-04

Prima release pubblicata su GitHub dopo la 2.6.2: include anche le modifiche delle versioni 2.7.x e 2.8.0, installate finora solo a mano.

### Aggiunto
- ✨ **Layout del configuratore** (stile, header, vista prodotti, colonne, posizione del riepilogo), globale e per singolo prodotto, con supporto ai temi a blocchi (FSE)
- ✨ **Modifica del bundle dal carrello**
- ✨ **Unità extra per prodotto**: prezzo per unità aggiuntiva ("+N × €/cad") con etichetta personalizzabile, anche in modalità articoli separati
- ✨ **Cosa manca al bundle**: il pulsante "Aggiungi al carrello" resta cliccabile anche se il bundle è incompleto; al clic la pagina scorre al primo gruppo da completare, lo evidenzia e spiega cosa manca, e sotto il pulsante compare l'elenco dei gruppi da completare
- ✨ **Coupon su tutto il Bundle** (modalità carrello): nuovo checkbox nel tab Generale per applicare i coupon a tutti i prodotti del bundle, anche a quelli fuori dalle categorie/prodotti a cui il coupon è ristretto. Nota: non bypassa l'opzione "Escludi articoli in saldo" del coupon
- ✨ **Prodotti esauriti nel bundle**: i prodotti con scorta a 0 (o stato "Esaurito") restano visibili ma non sono selezionabili e mostrano l'avviso "Momentaneamente non disponibile" (testo modificabile con il filtro `wcb_out_of_stock_label`). Nei prodotti variabili le singole opzioni esaurite (es. una taglia) sono disattivate e marcate "esaurito". Se in un gruppo obbligatorio non resta nessun prodotto disponibile, il gruppo lo segnala subito. Gli articoli in arretrato restano ordinabili

### Corretto
- 🐛 **Bundle incompleto aggiunto in silenzio**: il server ora rifiuta i prodotti esauriti o con scorta insufficiente (tenendo conto della quantità predefinita e dello stesso prodotto in più gruppi) con un messaggio chiaro. In modalità "righe separate", se WooCommerce rifiuta un articolo le righe già aggiunte vengono tolte e il cliente vede il motivo, invece di "Prodotti aggiunti al carrello" con un bundle a metà
- 🐛 **Freeze della pagina con l'autofill di Chrome** ("La pagina non risponde"): quando il browser inseriva automaticamente un numero lungo (es. telefono o CAP) in un campo quantità, il ciclo che genera i "Pezzi" girava per milioni di iterazioni bloccando il thread. Ora la quantità è limitata a un tetto massimo (il max del gruppo, altrimenti 1000) prima di generare le righe; i campi quantità hanno anche `autocomplete="off"` e `max` per evitare del tutto l'autofill del browser
- 🐛 Autofill di Chrome sui campi di personalizzazione: i valori autocompilati non riabilitavano il pulsante "Aggiungi al carrello" perché la validazione ascoltava solo l'evento `input`. Ora ascolta anche `change` e rileva l'autofill via animazione CSS
- 🐛 Riepilogo delle unità extra allineato al prezzo addebitato; dati strutturati del prodotto mantenuti in modalità Hero; un import JSON non valido non fa più perdere le modifiche del pannello; il suffisso IVA non entra più nel totale live
- 🐛 CSS e JS versionati nel percorso (`uploads/wcb-assets`): i CDN come Cloudflare non servono più file vecchi ignorando `?ver=`

### Sicurezza
- 🔒 Sanitizzazione unica dei gruppi (form, import JSON, CSV/REST), `unserialize` senza oggetti, escape in admin.js e nell'indicatore di avanzamento, marker FSE accettati solo se emessi dal plugin, tetto alla quantità (1000), rimossa la riscrittura di `active_plugins` a ogni richiesta

## [2.6.2] - 2026-07-14

### Aggiunto
- ✨ **Spedizione Bundle** (modalità carrello): nuovo checkbox nel tab Generale per calcolare la spedizione con peso, dimensioni e classe del prodotto bundle invece che con quelli dei singoli articoli. La tab "Spedizione" di WooCommerce è ora visibile per i prodotti bundle. Attivo solo se il bundle ha peso o classe di spedizione impostati

### Sicurezza
- 🔒 Il server ora valida che ogni prodotto inviato dal form appartenga alla lista configurata per il gruppo: un POST manipolato poteva inserire prodotti arbitrari nel bundle e sfruttare prezzo fisso/override per pagarli una frazione del prezzo
- 🔒 Gli indici dei gruppi restano stabili anche quando un gruppo viene escluso dal rendering: prima form e configurazione server potevano disallinearsi

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

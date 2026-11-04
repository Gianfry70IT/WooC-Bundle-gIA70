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

## [2.8.3] - 2026-11-04

### Corretto
- 🐛 **Scorte dei bundle a riga unica**: la riduzione ora segue il flusso di WooCommerce invece dello stato "In lavorazione". Avviene una sola volta per ordine (prima un ordine che tornava in lavorazione scalava di nuovo), anche per gli ordini che passano da "In attesa" o vanno direttamente a "Completato", e viene **ripristinata** quando l'ordine è annullato, torna in attesa di pagamento o fallisce. Conta la quantità predefinita del prodotto (badge 2x) e il numero di bundle nella riga; le quantità sono fissate sull'ordine al checkout. Note d'ordine e email di scorta bassa come per i prodotti normali

### Compatibilità
- Dichiarata la compatibilità con le tabelle ordini HPOS e con Carrello/Checkout a blocchi: WooCommerce non segnala più il plugin come incompatibile
- Header aggiornati: `Requires Plugins: woocommerce`, `WC requires at least: 8.0`, `WC tested up to: 11.2`, `Requires PHP: 7.4`, `Requires at least: 6.2`, `Tested up to: 7.1`

### Sicurezza
- 🔒 **Updater**: risposta dell'API GitHub in cache (6 ore, 30 minuti dopo un errore; "Controlla di nuovo" la salta) per non superare il limite di 60 richieste/ora; si installa solo lo zip allegato alla release, scaricato dal repository, e solo se corrisponde all'impronta SHA-256 pubblicata da GitHub; la cartella estratta prende sempre il nome di quella installata, così il plugin non si disattiva; il controllo aggiornamenti funziona anche da cron

## [2.8.2] - 2026-11-04

### Aggiunto
- ✨ **Carrello a blocchi (temi a blocchi / blocco Carrello)**: i bundle a righe separate ora sono raggruppati con la cornice colorata, la prima riga mostra "Bundle: ..." e il link "Modifica Bundle", e togliere una riga chiede conferma come nel carrello classico. I dati arrivano dalla Store API (`extensions.wcb-bundle`), le classi dal filtro `cartItemClass` del blocco
- ✨ **Intestazione del bundle nel carrello**: "Bundle: ...", "Modifica Bundle" e il nuovo **"Elimina bundle"** stanno sopra il primo prodotto del bundle (nel carrello a blocchi in una riga dedicata in cima alla cornice). Il cestino delle singole righe del bundle è nascosto nella pagina carrello: si elimina il bundle intero (nel mini-carrello il cestino resta)

### Modificato
- Avviso di rimozione più preciso: togliendo una riga del bundle viene tolto l'intero bundle (prima diceva che i prezzi tornavano al listino)

### Sicurezza
- 🔒 **Quantità bloccate nel carrello per i bundle a righe separate**: con prezzo fisso o prezzo di gruppo, aumentare la quantità di una riga nel carrello dava più pezzi allo stesso prezzo. Ora la quantità delle righe del bundle è mostrata come testo nel carrello classico, non è modificabile nel carrello a blocchi (Store API) e il server la riporta comunque a quella di aggiunta prima di calcolare i prezzi. Per cambiare il contenuto si usa "Modifica Bundle"
- 🔒 **Bundle non pubblicati non più acquistabili**: il bundle risultava sempre acquistabile, anche in bozza, privato o nel cestino. Ora vale la regola di WooCommerce (solo pubblicati, o visibili a chi può modificarli) senza richiedere un prezzo proprio; l'aggiunta dal form rifiuta anche i bundle protetti da password e rispetta le regole di acquisto di altri plugin (`woocommerce_add_to_cart_validation`, es. membership)
- 🔒 **Niente bundle vuoti nel carrello**: un link `?add-to-cart=ID`, la Store API o "Ordina di nuovo" potevano aggiungere il bundle senza prodotti scelti (con prezzo calcolato anche a 0 €). Ora il bundle si aggiunge solo dal suo form; al checkout vengono tolti i bundle vuoti e le righe di bundle diventati non acquistabili
- 🔒 Il prezzo live delle varianti non viene più restituito per prodotti non acquistabili (bozze, privati)

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

# WooC Bundle gIA70

Plugin WooCommerce per creare prodotti bundle personalizzabili con gruppi di articoli, regole di selezione e prezzi avanzati.

## 🚀 Versione Corrente: 2.5

### ✨ Novità v2.5
- ✅ Nuova tab "Bundle Disponibili" con shortcode rapidi
- ✅ Funzionalità copia shortcode con un clic
- ✅ Manuale operativo aggiornato con esempi shortcode
- ✅ Miglioramenti alla documentazione interna

## 📦 Installazione

1. Scarica l'ultima release da [GitHub Releases](https://github.com/Gianfry70IT/WooC-Bundle-gIA70/releases)
2. Carica il file ZIP dal pannello WordPress: Plugin → Aggiungi nuovo → Carica plugin
3. Attiva il plugin
4. Vai su WooCommerce → Settings → Bundle Settings per configurare

## 🛠️ Sviluppo

### Commit Rapido
```bash
./commit.sh "Messaggio del commit"
```

### Creare una Release

#### Opzione 1: Script Manuale (Locale + GitHub Manuale)
```bash
./release.sh 2.5 "Descrizione della release"
```
Questo script:
1. ✅ Fa commit delle modifiche
2. ✅ Crea il tag versione
3. ✅ Push su GitHub
4. ✅ Genera lo ZIP con il nome cartella corretto
5. ℹ️ Ti mostra i passi per creare la release su GitHub manualmente

#### Opzione 2: GitHub Action Automatico
Quando fai push di un tag versione:
```bash
git tag -a v2.5 -m "Release v2.5"
git push origin v2.5
```

Il GitHub Action automaticamente:
1. ✅ Crea la release
2. ✅ Genera lo ZIP con il nome cartella corretto (`wooc-bundle-gia70`)
3. ✅ Carica lo ZIP nella release

### Struttura del Repository

```
wooc-bundle-gia70/
├── assets/                 # CSS e JavaScript
├── includes/               # Classi PHP
├── templates/              # Template WooCommerce
├── .github/
│   └── workflows/
│       └── release.yml    # Automazione release
├── woocommerce-custom-bundle.php  # File principale
├── updater.php            # Sistema aggiornamenti
├── release.sh             # Script release manuale
├── commit.sh              # Script commit rapido
└── README.md              # Questo file
```

## 📝 Workflow Release

### Processo Completo per una Nuova Release

1. **Aggiorna la versione** nel file principale:
   ```php
   * Version: 2.5
   ```

2. **Aggiorna la versione** in `updater.php`:
   ```php
   private $version = '2.5';
   ```

3. **Commit e release**:
   ```bash
   # Opzione A: Automatico (con GitHub Action)
   git add .
   git commit -m "chore: bump version to 2.5"
   git push origin main
   git tag -a v2.5 -m "Release v2.5: Added bundles tab"
   git push origin v2.5
   # GitHub Action creerà automaticamente la release
   
   # Opzione B: Script locale
   ./release.sh 2.5 "Added bundles tab and updated manual"
   # Poi segui le istruzioni per caricare lo ZIP su GitHub
   ```

4. **Il plugin può auto-aggiornarsi** grazie a `updater.php` che controlla le release GitHub

## 🔧 Sistema di Aggiornamento

Il plugin include un sistema di auto-aggiornamento che:
- ✅ Controlla le release su GitHub
- ✅ Notifica quando è disponibile un aggiornamento
- ✅ Permette l'aggiornamento con un clic
- ✅ Mantiene il nome corretto della cartella (`wooc-bundle-gia70`)

**IMPORTANTE**: Lo ZIP della release DEVE contenere una cartella chiamata `wooc-bundle-gia70` per evitare problemi di aggiornamento.

## 📚 Documentazione

Per la documentazione completa sull'uso del plugin, vai su:
**WooCommerce → Settings → Bundle Settings → Manuale Operativo**

## 🐛 Segnalazione Bug

Apri una [issue su GitHub](https://github.com/Gianfry70IT/WooC-Bundle-gIA70/issues)

## 📄 Licenza

GNU GPL v2 or later

## 👤 Autore

**gIA70 - Gianfranco Greco con Codice Sorgente**

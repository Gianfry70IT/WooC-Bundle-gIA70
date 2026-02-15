#!/bin/bash

# Script per creare una release del plugin WooC Bundle gIA70
# Uso: ./release.sh [versione] [messaggio]
# Esempio: ./release.sh 2.5 "Added bundles tab and updated manual"

set -e  # Esce se c'è un errore

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Verifica argomenti
if [ -z "$1" ]; then
    echo -e "${RED}❌ Errore: Versione non specificata${NC}"
    echo "Uso: ./release.sh [versione] [messaggio]"
    echo "Esempio: ./release.sh 2.5 \"Added bundles tab\""
    exit 1
fi

VERSION=$1
MESSAGE=${2:-"Release version $VERSION"}
PLUGIN_SLUG="wooc-bundle-gia70"
BUILD_DIR="build"
RELEASE_DIR="releases"

echo -e "${YELLOW}🚀 Inizio processo di release v${VERSION}${NC}\n"

# 1. Verifica che non ci siano modifiche uncommitted
if [[ -n $(git status -s) ]]; then
    echo -e "${YELLOW}📝 Ci sono modifiche non committate. Le aggiungo...${NC}"
    git add .
    git commit -m "chore: prepare release v${VERSION}"
fi

# 2. Crea tag
echo -e "${GREEN}🏷️  Creo tag v${VERSION}...${NC}"
git tag -a "v${VERSION}" -m "${MESSAGE}"

# 3. Push su GitHub
echo -e "${GREEN}📤 Push su GitHub...${NC}"
git push origin main
git push origin "v${VERSION}"

# 4. Crea directory di build
echo -e "${GREEN}📦 Creo pacchetto per release...${NC}"
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR/$PLUGIN_SLUG"
mkdir -p "$RELEASE_DIR"

# 5. Copia i file del plugin (esclusi file di sviluppo)
rsync -av \
    --exclude='.git' \
    --exclude='.gitignore' \
    --exclude='node_modules' \
    --exclude='build' \
    --exclude='releases' \
    --exclude='*.sh' \
    --exclude='.github' \
    --exclude='.DS_Store' \
    ./ "$BUILD_DIR/$PLUGIN_SLUG/"

# 6. Crea ZIP con nome corretto della cartella
cd "$BUILD_DIR"
ZIP_FILE="../$RELEASE_DIR/${PLUGIN_SLUG}-v${VERSION}.zip"
zip -r "$ZIP_FILE" "$PLUGIN_SLUG/"
cd ..

# 7. Cleanup
rm -rf "$BUILD_DIR"

echo -e "\n${GREEN}✅ Release v${VERSION} creata con successo!${NC}"
echo -e "${GREEN}📦 ZIP: ${RELEASE_DIR}/${PLUGIN_SLUG}-v${VERSION}.zip${NC}"
echo -e "\n${YELLOW}📋 Prossimi passi:${NC}"
echo -e "1. Vai su https://github.com/Gianfry70IT/WooC-Bundle-gIA70/releases/new"
echo -e "2. Seleziona il tag v${VERSION}"
echo -e "3. Titolo: \"Release v${VERSION}\""
echo -e "4. Descrizione: ${MESSAGE}"
echo -e "5. Carica il file: ${RELEASE_DIR}/${PLUGIN_SLUG}-v${VERSION}.zip"
echo -e "6. Pubblica la release\n"

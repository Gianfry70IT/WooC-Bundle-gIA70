#!/bin/bash

# Script per commit e push rapido
# Uso: ./commit.sh [messaggio]
# Esempio: ./commit.sh "Fixed bundle tab display"

set -e

# Colori
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

MESSAGE=${1:-"Update files"}

echo -e "${YELLOW}📝 Commit modifiche...${NC}"
git add .
git commit -m "$MESSAGE"

echo -e "${GREEN}📤 Push su GitHub...${NC}"
git push origin main

echo -e "\n${GREEN}✅ Modifiche pubblicate con successo!${NC}\n"

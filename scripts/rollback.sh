#!/usr/bin/env bash
# =============================================================================
# scripts/rollback.sh — Procédure opérationnelle de retour arrière (Rollback)
# Conforme aux exigences du projet DevOps (Atelier 5, Point 7)
#
# Rôle :
# - Permet de revenir instantanément à une version antérieure sans recompiler le code.
# - Télécharge l'image déjà publiée depuis le registre d'images.
# - Redémarre l'application et valide la santé avec /health.
#
# Usage :
#   ./scripts/rollback.sh <TAG_VERSION>
# Exemple :
#   ./scripts/rollback.sh v1.0.0
# =============================================================================

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

if [ $# -lt 1 ]; then
    echo -e "${RED}Erreur : Version cible manquante.${NC}"
    echo -e "Usage : $0 <tag-version> (ex: $0 v1.0.0)"
    exit 1
fi

TARGET_TAG="$1"
REGISTRY_IMAGE="ghcr.io/elae620/tunisiastay-app"
DEPLOY_COMPOSE_FILE="compose.deploy.yaml"
PROJECT_NAME="${COMPOSE_PROJECT_NAME:-tunisiastay-deploy}"

echo -e "\n${BLUE}======================================================================${NC}"
echo -e "${BLUE}▶ [ROLLBACK] Déclenchement du retour arrière vers la version : ${TARGET_TAG}${NC}"
echo -e "${BLUE}▶ Projet Compose ciblé : ${PROJECT_NAME}${NC}"
echo -e "${BLUE}======================================================================${NC}"

# Détection de la commande Compose
COMPOSE_CMD=""
if docker compose version >/dev/null 2>&1; then
    COMPOSE_CMD="docker compose"
elif command -v docker-compose >/dev/null 2>&1; then
    COMPOSE_CMD="docker-compose"
else
    echo -e "${RED}Erreur : Docker Compose non trouvé.${NC}"
    exit 1
fi

export APP_IMAGE="${REGISTRY_IMAGE}:${TARGET_TAG}"
echo -e "${YELLOW}ℹ Image cible : ${APP_IMAGE}${NC}"

# 1. Téléchargement de l'image depuis le registre (sans reconstruction et sans masquer les erreurs)
echo -e "\n${BLUE}1/3 Téléchargement de l'image de la version précédente...${NC}"
if ! $COMPOSE_CMD -f "$DEPLOY_COMPOSE_FILE" -p "$PROJECT_NAME" pull app; then
    # Si le pull distant échoue, on vérifie si l'image est au moins disponible dans le cache local
    if ! docker image inspect "$APP_IMAGE" >/dev/null 2>&1; then
        echo -e "${RED}✖ [ÉCHEC] Impossible de télécharger l'image ${APP_IMAGE} depuis le registre et celle-ci est introuvable localement.${NC}"
        exit 1
    else
        echo -e "${YELLOW}⚠ Image introuvable sur le registre distant mais présente dans le cache local. Poursuite avec l'image locale.${NC}"
    fi
else
    echo -e "${GREEN}✔ Image ${APP_IMAGE} téléchargée avec succès depuis le registre.${NC}"
fi

# 2. Mise à jour du conteneur en service (démarrage sans rebuild sur le même projet)
echo -e "\n${BLUE}2/3 Remplacement du conteneur en cours d'exécution...${NC}"
$COMPOSE_CMD -f "$DEPLOY_COMPOSE_FILE" -p "$PROJECT_NAME" up -d --no-build app

# 3. Contrôle de santé (Healthcheck HTTP)
echo -e "\n${BLUE}3/3 Vérification du contrôle de santé de l'application...${NC}"
PORT="${APP_PORT:-8080}"
MAX_RETRIES=10
RETRY_DELAY=3
SUCCESS=0

for i in $(seq 1 $MAX_RETRIES); do
    if curl -s -f "http://localhost:${PORT}/health" >/dev/null 2>&1; then
        SUCCESS=1
        break
    fi
    echo -e "Attente de la réponse de /health (tentative $i/$MAX_RETRIES)..."
    sleep $RETRY_DELAY
done

if [ $SUCCESS -eq 1 ]; then
    echo -e "\n${GREEN}✔ [SUCCÈS] Retour arrière vers la version ${TARGET_TAG} effectué avec succès !${NC}"
    echo -e "${GREEN}L'application répond HTTP 200 sur http://localhost:${PORT}/health.${NC}\n"
    exit 0
else
    echo -e "\n${RED}✖ [ÉCHEC] L'application n'a pas répondu favorablement au contrôle de santé.${NC}\n"
    exit 1
fi

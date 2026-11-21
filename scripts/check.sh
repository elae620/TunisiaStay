#!/usr/bin/env bash
# =============================================================================
# scripts/check.sh — Script Bash de contrôle qualité et validation DevOps
# Conforme aux exigences du projet de fin de module DevOps (Atelier 2)
#
# Rôle :
# - Exécute une suite de contrôles non destructifs sur le projet.
# - Renvoie le code de sortie 0 en cas de succès complet.
# - Renvoie un code non nul (1) dès qu'un contrôle échoue.
# =============================================================================

set -uo pipefail

# Couleurs pour un affichage clair
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

FAILED=0

step_info() {
    echo -e "\n${BLUE}======================================================================${NC}"
    echo -e "${BLUE}▶ [CONTRÔLE] $1${NC}"
    echo -e "${BLUE}======================================================================${NC}"
}

step_success() {
    echo -e "${GREEN}✔ [SUCCÈS] $1${NC}"
}

step_error() {
    echo -e "${RED}✖ [ÉCHEC] $1${NC}"
    FAILED=1
}

# Détection automatique de l'interpréteur PHP (Linux natif, Git Bash, WSL interop, ou chemins Windows)
PHP_BIN=""
if command -v php >/dev/null 2>&1; then
    PHP_BIN="php"
elif command -v php.exe >/dev/null 2>&1; then
    PHP_BIN="php.exe"
elif [ -f "/c/afcs/php/php-8.5.2-nts-Win32-vs17-x64/php.exe" ]; then
    PHP_BIN="/c/afcs/php/php-8.5.2-nts-Win32-vs17-x64/php.exe"
elif [ -f "/mnt/c/afcs/php/php-8.5.2-nts-Win32-vs17-x64/php.exe" ]; then
    PHP_BIN="/mnt/c/afcs/php/php-8.5.2-nts-Win32-vs17-x64/php.exe"
elif [ -f "/c/xampp/php/php.exe" ]; then
    PHP_BIN="/c/xampp/php/php.exe"
elif [ -f "/mnt/c/xampp/php/php.exe" ]; then
    PHP_BIN="/mnt/c/xampp/php/php.exe"
fi

# 1. Vérification du répertoire d'exécution (racine du dépôt)
step_info "1/4 Vérification de la structure du dépôt"
if [ ! -f "composer.json" ] || [ ! -f "compose.yaml" ]; then
    step_error "Le script doit être exécuté depuis la racine du dépôt TunisiaStay."
    exit 1
else
    step_success "Racine du dépôt détectée avec succès (composer.json et compose.yaml présents)."
fi

# 2. Validation de la configuration Docker Compose
step_info "2/4 Validation de la syntaxe et configuration Docker Compose"
COMPOSE_CMD=""
if docker compose version >/dev/null 2>&1; then
    COMPOSE_CMD="docker compose"
elif command -v docker-compose >/dev/null 2>&1; then
    COMPOSE_CMD="docker-compose"
elif command -v docker-compose.exe >/dev/null 2>&1; then
    COMPOSE_CMD="docker-compose.exe"
fi

if [ -n "$COMPOSE_CMD" ]; then
    if $COMPOSE_CMD config >/dev/null 2>&1; then
        step_success "Configuration Docker Compose valide ($COMPOSE_CMD config)."
    else
        step_error "Erreur de syntaxe ou de configuration dans compose.yaml."
    fi
else
    echo -e "${YELLOW}⚠ Docker Compose non détecté dans cet environnement, contrôle ignoré.${NC}"
fi

# 3. Validation des métadonnées Doctrine (non destructive)
step_info "3/4 Validation du mapping Doctrine (sans synchronisation base)"
if [ -n "$PHP_BIN" ] && [ -f "bin/console" ]; then
    if "$PHP_BIN" bin/console doctrine:schema:validate --skip-sync >/dev/null 2>&1; then
        step_success "Mapping Doctrine valide (doctrine:schema:validate --skip-sync)."
    else
        step_error "Le mapping des entités Doctrine comporte des erreurs."
    fi
else
    step_error "Interpréteur PHP introuvable pour valider Doctrine."
fi

# 4. Exécution de la suite de tests automatisés (PHPUnit)
step_info "4/4 Exécution de la suite de tests automatisés (PHPUnit)"
PHPUNIT_BIN=""
if [ -f "bin/phpunit" ]; then
    PHPUNIT_BIN="bin/phpunit"
elif [ -f "vendor/bin/phpunit" ]; then
    PHPUNIT_BIN="vendor/bin/phpunit"
fi

if [ -n "$PHP_BIN" ] && [ -n "$PHPUNIT_BIN" ]; then
    if "$PHP_BIN" "$PHPUNIT_BIN" ${PHPUNIT_EXTRA_ARGS:-}; then
        step_success "Tous les tests automatisés sont passés avec succès."
    else
        step_error "Au moins un test automatisé a échoué."
    fi
else
    step_error "Impossible d'exécuter PHPUnit : PHP ou phpunit introuvable."
fi

# Bilan final
echo -e "\n${BLUE}======================================================================${NC}"
if [ "$FAILED" -eq 0 ]; then
    echo -e "${GREEN}🎉 [RÉSULTAT GLOBAL] Tous les contrôles ont réussi avec succès. (Code 0)${NC}"
    echo -e "${BLUE}======================================================================${NC}\n"
    exit 0
else
    echo -e "${RED}💥 [RÉSULTAT GLOBAL] Un ou plusieurs contrôles ont échoué. (Code 1)${NC}"
    echo -e "${BLUE}======================================================================${NC}\n"
    exit 1
fi

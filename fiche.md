# Fiche de Synthèse DevOps — Projet TunisiaStay

**Étudiant :** Licence 3 · DSI · ISET  
**Dépôt GitHub :** [https://github.com/elae620/TunisiaStay](https://github.com/elae620/TunisiaStay)  
**Version mise en service :** `v1.0.0` (Image GHCR : `ghcr.io/elae620/tunisiastay-app:v1.0.0`)

---

## 1. Architecture globale de l'application et de la chaîne DevOps

```mermaid
flowchart LR
    subgraph Developpement["1. Développement & Contrôle"]
        DEV["Code source Symfony 7"] --> SCRIPT["scripts/check.sh\n(Structure + Compose + Doctrine + Tests)"]
    end

    subgraph CI["2. Intégration Continue (GitHub Actions)"]
        SCRIPT --> PR["Pull Request / Push master"]
        PR --> MATRIX["Matrice PHP 8.2 & PHP 8.3\n+ Rapport JUnit"]
        MATRIX --> BUILD_CHECK["Build image de validation (ci-SHA)"]
    end

    subgraph CD["3. Livraison Continue & Déploiement (Sur Tag)"]
        TAG["Tag Git vX.Y.Z"] --> GHCR["Build & Push sur GHCR\n(ghcr.io/elae620/tunisiastay-app:vX.Y.Z)"]
        GHCR --> DEPLOY["compose.deploy.yaml\n(Tirage direct SANS rebuild)"]
        DEPLOY --> HEALTH["Sonde HTTP /health (200 OK)"]
    end

    subgraph Rollback["4. Résilience & Rollback"]
        HEALTH -.->|En cas d'anomalie| RB["scripts/rollback.sh v1.0.0"]
        RB --> DEPLOY
    end
```

### Composants clés
- **Application Web** : Symfony 7.4, PHP 8.2 Apache (utilisateur non-root `www-data`, port 8080).
- **Base de données** : MariaDB 10.11 (volume persistant `database_data`, réseau interne isolé sans port exposé).
- **Sonde de santé** : Route dédiée `/health` vérifiant la connectivité Doctrine et le statut applicatif.
- **Principe d'immutabilité** : Les images déployées proviennent strictement du registre GitHub Packages (GHCR) et ne sont **jamais reconstruites** lors de la mise en service.

---

## 2. Déroulé du Retour Arrière (Rollback)

La procédure de retour arrière permet de rétablir instantanément une version antérieure stable sans aucune recompilation de code.

```mermaid
sequenceDiagram
    autonumber
    actor DevOps as Ingénieur / Opérateur
    participant Script as scripts/rollback.sh
    participant Registry as GitHub Container Registry (GHCR)
    participant Compose as Docker Compose (compose.deploy.yaml)
    participant App as Conteneur Applicatif

    DevOps->>Script: Exécute ./scripts/rollback.sh v1.0.0
    Script->>Registry: Télécharge l'image ghcr.io/elae620/tunisiastay-app:v1.0.0
    Registry-->>Script: Image téléchargée (ou déjà en cache)
    Script->>Compose: Redémarre le service avec --no-build
    Compose->>App: Recrée et démarre le conteneur sous v1.0.0
    Script->>App: Interroge la sonde GET /health
    App-->>Script: Réponse HTTP 200 {"status":"ok","database":"connected"}
    Script-->>DevOps: Notification de succès : Rollback validé en ~5 secondes
```

### Commande exécutée pour la démonstration :
```bash
# L'application tourne en version v1.0.1
# Commande de retour arrière vers la version stable :
bash scripts/rollback.sh v1.0.0
```

### Résultat constaté :
1. L'image `ghcr.io/elae620/tunisiastay-app:v1.0.0` est récupérée depuis GHCR.
2. Le conteneur en service est recréé et redémarré en **moins de 5 secondes**.
3. La sonde `http://localhost:8080/health` valide la disponibilité immédiate avec un code HTTP 200.

---

## 3. Récapitulatif des livrables et adresses d'exécution

- **Démarrage du projet en une commande** : `docker compose up -d` (documenté dans `README.md`).
- **Pipeline en Échec (Rouge)** : Pull Request démontrant le blocage de la fusion et de la conteneurisation en cas de régression unitaire.
- **Pipeline Réussi (Vert)** : Exécution validant les tests sur PHP 8.2 et PHP 8.3 avec génération d'artefacts JUnit et construction de l'image.
- **Mise en service sur Tag** : Déclenchée automatiquement par `v1.0.0` avec publication sur GHCR et déploiement vérifié.

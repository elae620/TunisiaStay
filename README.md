# TunisiaStay — Plateforme de réservation d'hébergements

Application web Symfony avec base de données MariaDB, conteneurisée avec Docker Compose et outillée d'une chaîne DevOps complète (CI/CD, Registre d'images, Healthcheck, Rollback).

---

## Démarrer le projet en une commande (Développement local)

Pour lancer l'application et la base de données :

```bash
docker compose up -d
```
*(ou `docker-compose up -d` selon votre version de Docker)*

L'application est ensuite accessible sur :
- **Application Web** : [http://localhost:8000](http://localhost:8000)
- **Sonde de santé (Healthcheck)** : [http://localhost:8000/health](http://localhost:8000/health)

---

## Exécuter le script de contrôle qualité

Pour vérifier la structure, la configuration Compose, le mapping Doctrine et la suite de tests automatisés :

- **Sous Linux, macOS ou Git Bash** :
  ```bash
  bash scripts/check.sh
  ```
- **Sous Windows PowerShell** :
  ```powershell
  & "C:\Program Files\Git\bin\bash.exe" scripts/check.sh
  ```

Le script renvoie le code de sortie `0` en cas de succès complet et un code non nul (`1`) en cas d'échec.

---

## Déploiement d'une version publiée (Environnement de test / Production)

L'image de l'application est publiée sur GitHub Container Registry (GHCR) et n'est jamais reconstruite lors du déploiement :

```bash
# Définir la version souhaitée (ex: v1.0.0)
export APP_IMAGE=ghcr.io/elae620/tunisiastay-app:v1.0.0

# Tirer l'image publiée et démarrer sans rebuild
docker compose -f compose.deploy.yaml pull app
docker compose -f compose.deploy.yaml up -d --no-build
```

Contrôle de santé :
```bash
curl -f http://localhost:8080/health
```

---

## Procédure de retour arrière (Rollback)

En cas d'anomalie sur une nouvelle version, revenir à une version antérieure stable en une commande :

```bash
bash scripts/rollback.sh v1.0.0
```

Le script télécharge l'image de la version spécifiée sans recompiler le code, redémarre le conteneur applicatif et vérifie que la sonde `/health` répond HTTP 200.

---

## Arrêter les conteneurs

Pour arrêter proprement les services sans supprimer les données :

```bash
docker compose stop
```

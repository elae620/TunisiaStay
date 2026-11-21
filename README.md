# TunisiaStay — Plateforme de réservation d'hébergements

Application web Symfony avec base de données MariaDB, conteneurisée avec Docker Compose.

---

## Démarrer le projet en une commande

Pour lancer l'application et la base de données :

```bash
docker compose up -d
```
*(ou `docker-compose up -d` selon votre version de Docker)*

L'application est ensuite accessible sur :
- **Application Web** : [http://localhost:8000](http://localhost:8000)
- **Sonde de santé** : [http://localhost:8000/health](http://localhost:8000/health)

---

## Exécuter le script de contrôle qualité

Pour vérifier la validité de la configuration Compose, le mapping Doctrine et la suite de tests automatisés :

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

## Arrêter les conteneurs

Pour arrêter proprement les services sans supprimer les données :

```bash
docker compose stop
```

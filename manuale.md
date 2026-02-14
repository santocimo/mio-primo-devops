# 🚀 MANUALE TECNICO: SANTO DEVOPS LAB

Benvenuto nella documentazione ufficiale del tuo laboratorio. Qui troverai tutto ciò che abbiamo costruito, dai comandi Docker alla logica PHP.

---

## 🐳 1. Gestione Docker (L'Infrastruttura)
Questi comandi controllano i tuoi "server virtuali" e i servizi attivi.

| Comando | Descrizione |
| :--- | :--- |
| `docker compose up -d` | Accende tutti i container (Web, DB, Adminer, Portainer). |
| `docker compose down` | Spegne tutto e libera le risorse del PC. |
| `docker compose ps` | Controlla quali servizi sono "Up" (attivi). |
| `docker compose restart` | Riavvia i servizi (utile dopo modifiche ai file). |

---

## 📁 2. Controllo Versioni Git (La Sicurezza)
Per salvare il tuo codice e caricare i progressi su GitHub.

1. **Stato:** `git status` (Vedi cosa è cambiato)
2. **Preparazione:** `git add .` (Prepara i file per il salvataggio)
3. **Salvataggio:** `git commit -m "Descrizione modifica"` (Crea il punto di ripristino)
4. **Cloud:** `git push origin master` (Invia tutto online)

---

## 🌐 3. Mappa degli Accessi e Credenziali
Accedi ai tuoi strumenti tramite questi indirizzi nel browser:

* **Sito Web (App PHP):** [http://localhost:8081](http://localhost:8081)
* **Adminer (Gestione DB):** [http://localhost:8082](http://localhost:8082)
* **Portainer (Monitoraggio):** [https://localhost:9443](https://localhost:9443)

### 🔑 Credenziali Database
* **Server:** `database-santo`
* **Utente:** `root`
* **Password:** `password_segreta`
* **Database:** `mio_database`

---

## 🐘 4. Sviluppo PHP & SQL (Ciclo CRUD)
Abbiamo implementato le funzioni principali per gestire i dati.

### 🟢 Inserimento (CREATE)
```php
$sql = "INSERT INTO visitatori (nome) VALUES (?)";
$pdo->prepare($sql)->execute([$nome]);# 🚀 MANUALE TECNICO: SANTO DEVOPS LAB

Benvenuto nella documentazione ufficiale del tuo laboratorio. Qui troverai tutto ciò che abbiamo costruito, dai comandi Docker alla logica PHP.

---

## 🐳 1. Gestione Docker (L'Infrastruttura)
Questi comandi controllano i tuoi "server virtuali" e i servizi attivi.

| Comando | Descrizione |
| :--- | :--- |
| `docker compose up -d` | Accende tutti i container (Web, DB, Adminer, Portainer). |
| `docker compose down` | Spegne tutto e libera le risorse del PC. |
| `docker compose ps` | Controlla quali servizi sono "Up" (attivi). |
| `docker compose restart` | Riavvia i servizi (utile dopo modifiche ai file). |

---

## 📁 2. Controllo Versioni Git (La Sicurezza)
Per salvare il tuo codice e caricare i progressi su GitHub.

1. **Stato:** `git status` (Vedi cosa è cambiato)
2. **Preparazione:** `git add .` (Prepara i file per il salvataggio)
3. **Salvataggio:** `git commit -m "Descrizione modifica"` (Crea il punto di ripristino)
4. **Cloud:** `git push origin master` (Invia tutto online)

---

## 🌐 3. Mappa degli Accessi e Credenziali
Accedi ai tuoi strumenti tramite questi indirizzi nel browser:

* **Sito Web (App PHP):** [http://localhost:8081](http://localhost:8081)
* **Adminer (Gestione DB):** [http://localhost:8082](http://localhost:8082)
* **Portainer (Monitoraggio):** [https://localhost:9443](https://localhost:9443)

### 🔑 Credenziali Database
* **Server:** `database-santo`
* **Utente:** `root`
* **Password:** `password_segreta`
* **Database:** `mio_database`

---

## 🐘 4. Sviluppo PHP & SQL (Ciclo CRUD)
Abbiamo implementato le funzioni principali per gestire i dati.

### 🟢 Inserimento (CREATE)
```php
$sql = "INSERT INTO visitatori (nome) VALUES (?)";
$pdo->prepare($sql)->execute([$nome]);
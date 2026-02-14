docker run -d -p 8080:80 --name mio-server nginx
docker exec -it mio-server bash -c 'echo "<h1>Benvenuto nel server di Santo!</h1>" > /usr/share/nginx/html/index.html'
docker compose up -d
ls
docker compose up -d
docker ps
docker compose down
docker rm -f mio-server
docker ps
git config --global user.name "santocimo"
git config --global user.email "santo.cimo@gmail.com"
git config --global user.name
git config --global user.email
git status
git remote -v
git remote add origin https://github.com/santocimo/mio-primo-devops.git
git init
git remote add origin https://github.com/santocimo/mio-primo-devops.git
git add docker-compose.yml
git commit -m "Il mio primo file di configurazione"
git push -u origin master
git pull origin master
ls -la
clear
git pull origin master --rebase
code README.md
pwd
git add README.md
git commit -m "Aggiornato il README con la descrizione del progetto"
git push origin master
docker compose up -d
docker --version
docker compose up -d
docker compose down
docker compose up -d
docker compose up -d --force-recreate
git add
# 1. Aggiungi tutti i file (anche quelli Untracked)
git add .
# 2. Crea il commit
git commit -m "Personalizzazione pagina index e volumi Docker"
# 3. Spedisci online
git push origin master
# Rimuovi tutto quello che hai aggiunto, ma mantieni i file fisicamente sul PC
git reset
# 1. Aggiungi i file (Git ora ignorerà la cartella pesante grazie al .gitignore)
git add docker-compose.yml index.html README.md .gitignore
# 2. Crea il commit
git commit -m "Pulizia e aggiunta file corretti"
# 3. Spedisci
git push origin master
git add README.md
# Rimuove tutto dalla lista dei file da caricare (ma non cancella nulla dal PC)
git rm -r --cached .
# Aggiunge di nuovo tutto, ma questa volta rispetterà il file .gitignore
git add .
git commit -m "Reset e pulizia file pesanti"
git push origin master
git reset --soft origin/master
git add docker-compose.yml index.html README.md .gitignore
git commit -m "Correzione finale file"
git push origin master
git add index.html
git commit -m "Corretto problema codifica caratteri accentati"
git push origin master
git add index.html
git commit -m "Aggiunto stile CSS Dark Mode"
git push origin master
ip route show | grep eth0
docker compose up -d
ls -F
# 1. Spegni e rimuovi il container
docker compose down
# 2. Rimuovi eventuali volumi "fantasma" rimasti orfani
docker volume prune -f
# 3. Riavvia tutto da zero
docker compose up -d
docker compose exec web-automatico ls -l /usr/share/nginx/html
docker compose exec web-automatico cat /usr/share/nginx/html/segreto.html
docker compose exec web-automatico cat /usr/share/nginx/html/contatti.html
# Spegne e cancella ogni traccia dei container attuali
docker compose down --volumes --remove-orphans
# Forza la creazione di un nuovo container leggendo la cartella 'nuda'
docker compose up -d --force-recreate
# Spegne e cancella ogni traccia dei container attuali
docker compose down --volumes --remove-orphans
# Forza la creazione di un nuovo container leggendo la cartella 'nuda'
docker compose up -d --force-recreate
ls segreto.html
chmod 644 *.html
chmod 755 .
git push
# 1. Prepara tutti i file (anche quelli nuovi e i permessi cambiati)
git add .
# 2. Scrivi l'etichetta del pacchetto
git commit -m "Fix permessi, configurazione pro e pagina segreta"
# 3. Spedisci online
git push origin master
docker compose up -d
docker compose ps
docker compose exec database-santo mariadb -u root -p
git add docker-compose.yml
git commit -m "Aggiunto servizio MariaDB al cluster"
git push origin master
docker compose up -d
git add docker-compose.yml
git commit -m "Aggiunto Adminer per la gestione grafica del DB"
git push origin master
git commit --allow-empty -m "Struttura database 'visitatori' completata"
git push origin master
docker compose down
git add .
git commit -m "Sessione conclusa: infrastruttura completa e funzionante"
git push origin master
docker compose up -d
git add .
git commit -m "Aggiunto Portainer per il monitoraggio"
git push origin master
# Riavvia con la nuova configurazione
docker compose up -d
# Installa i driver MySQL dentro il container PHP (fondamentale!)
docker compose exec web-automatico docker-php-ext-install pdo pdo_mysql
# Riavvia il container per rendere attivi i driver
docker compose restart web-automatico
# Installa i driver PDO per MySQL
docker compose exec web-automatico docker-php-ext-install pdo pdo_mysql
# Riavvia il container per attivare i driver
docker compose restart web-automatico
docker compose ps
git add .
git commit -m "Fase 3 completata: Sito PHP dinamico con grafica Bootstrap"
git push origin master
git add .
git commit -m "Progetto completato: Form di inserimento dati interattivo"
git push origin master
docker compose down
docker compose up -d
# 1. Installa i driver PDO dentro il container attivo
docker compose exec web-automatico docker-php-ext-install pdo pdo_mysql
# 2. Riavvia il container per rendere effettive le modifiche
docker compose restart web-automatico
docker compose up -d
git add .
git commit -m "Sistema completo: Database persistente e funzione Elimina funzionante"
git push origin master
docker compose up -d
git add .
git commit -m "Versione Finale: CRUD completo con ricerca, contatore e bugfix header"
git push origin master
docker compose up -d
docker-compose up -d
docker ps
docker-compose up -d
SHOW VARIABLES LIKE "secure_file_priv";
docker-compose up -d
mysqldump -u root -p mio_database > backup_progetto.sql
git add .
git commit -m "Aggiunta grafica viola"
git push origin master
docker-compose up -d
git add .
git commit -m "Aggiunta funzione Modifica, gestione accenti (Cimò) e mb_strtoupper"
git push
docker-compose up -d
docker-compose down
docker-compose up -d
docker-compose up -d --build
die("Sì, sto leggendo il file index.php!");
docker-compose up -d --build
docker-compose up -d
git add .
git commit -m "Aggiunto calcolo automatico CF a 16 cifre e fix duplicati comuni"
git push
docker-compose up -d
# 1. Prepara tutti i file modificati
git add .
# 2. Registra il salvataggio con la descrizione delle migliorie
git commit -m "Fix accenti (CIMÒ), integrazione SweetAlert2 e data entry assistito"
# 3. Carica le modifiche sul server remoto (se configurato)
git push
docker-compose stop

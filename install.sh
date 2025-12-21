#!/bin/bash

CONFIG_FILE="/etc/mirza-bot/script.conf"
SCRIPT_PATH=$(realpath "$0")

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m'

print_header() {
    clear
    echo -e "${GREEN}############################################################${NC}"
    echo -e "${GREEN}##                                                        ##${NC}"
    echo -e "${GREEN} ##                  Mirza Pro Bot                       ##${NC}"
    echo -e "${GREEN}  ##            Automated Management Script             ##${NC}"
    echo -e "${GREEN}   ##                                                  ##${NC}"
    echo -e "${GREEN}  ##     Installer by @H_ExPLoSiVe (ExPLoSiVe1988)      ##${NC}"
    echo -e "${GREEN} ##     Based on the original project by mahdiMGF2       ##${NC}"
    echo -e "${GREEN}##                                                        ##${NC}"
    echo -e "${GREEN}############################################################${NC}"
    echo ""
}

save_config() {
    mkdir -p /etc/mirza-bot
    echo "DOMAIN=$1" > "$CONFIG_FILE"
    echo "BOT_TOKEN=$2" >> "$CONFIG_FILE"
    echo "ADMIN_TELEGRAM_ID=$3" >> "$CONFIG_FILE"
    echo "BACKUP_CHAT_ID=$4" >> "$CONFIG_FILE"
    echo "DB_PASSWORD=$5" >> "$CONFIG_FILE"
    echo "DB_NAME=$6" >> "$CONFIG_FILE"
    echo "DB_USER=$7" >> "$CONFIG_FILE"
}

load_config() {
    if [ -f "$CONFIG_FILE" ]; then
        source "$CONFIG_FILE"
        return 0
    else
        echo -e "${RED}Error: Configuration file not found.${NC}"
        return 1
    fi
}

check_apache() {
    if ! systemctl is-active --quiet apache2; then
        echo -e "\n${RED}=====================================================${NC}"
        echo -e "${RED}Error: Apache service failed to start. Aborting.${NC}"
        echo -e "${YELLOW}--- Displaying Apache Status ---${NC}"
        systemctl status apache2 --no-pager
        echo -e "\n${YELLOW}--- Displaying Last Journal Entries for Apache ---${NC}"
        journalctl -xeu apache2.service --no-pager | tail -n 20
        echo -e "${RED}=====================================================${NC}"
        exit 1
    fi
}

############################################################
# CORE FUNCTIONS
############################################################

install_bot() {
    print_header
    echo -e "${YELLOW}Starting Mirza Pro Bot Installation...${NC}"

    echo "--- Basic Information ---"
    read -p "Enter your domain (e.g., bot.yourdomain.com): " DOMAIN
    read -p "Enter your email for SSL notifications: " SSL_EMAIL
    echo -e "\n--- Database Settings ---"
    read -p "Enter a name for the database (e.g., mirza_db): " DB_NAME
    read -p "Enter a username for the database (e.g., mirza_user): " DB_USER
    SUGGESTED_DB_PASSWORD=$(openssl rand -base64 18)
    read -p "Enter a database password or press Enter to use this secure one [$SUGGESTED_DB_PASSWORD]: " USER_DB_PASSWORD
    DB_PASSWORD=${USER_DB_PASSWORD:-$SUGGESTED_DB_PASSWORD}
    echo -e "\n--- Telegram Bot Settings ---"
    read -p "Enter your Telegram Bot Token: " BOT_TOKEN
    read -p "Enter your numeric Telegram Admin ID: " ADMIN_TELEGRAM_ID
    read -p "Enter your Telegram Bot Username (without @): " BOT_USERNAME
    echo -e "\n${YELLOW}--- Backup Configuration ---${NC}"
    read -p "Enter the private channel/group Chat ID for backups (optional, defaults to your admin account): " BACKUP_CHAT_ID
    if [ -z "$BACKUP_CHAT_ID" ]; then
        BACKUP_CHAT_ID=$ADMIN_TELEGRAM_ID
    fi
    echo -e "\n--- Panel Compatibility ---"
    read -p "Are you using the NEW Marzban panel? (yes/no): " MARZBAN_CHOICE

    echo -e "\n${BLUE}Starting automatic installation... This may take a few minutes.${NC}"

    echo -e "${BLUE}Step 1: Hardening Environment (Aggressive Cleanup)...${NC}"
    systemctl stop apache2 >/dev/null 2>&1
    rm -f /etc/apache2/sites-available/mirza-pro.conf /etc/apache2/sites-enabled/mirza-pro.conf
    rm -f /etc/apache2/sites-available/mirza-pro-le-ssl.conf /etc/apache2/sites-enabled/mirza-pro-le-ssl.conf
    certbot delete --cert-name "$DOMAIN" --non-interactive > /dev/null 2>&1
    systemctl start apache2 >/dev/null 2>&1

    echo -e "${BLUE}Step 2: System Update & Dependencies...${NC}"
    apt-get update > /dev/null 2>&1
    apt-get install -y ufw apache2 mysql-server git software-properties-common certbot python3-certbot-apache > /dev/null 2>&1
    add-apt-repository ppa:ondrej/php -y > /dev/null 2>&1
    apt-get update > /dev/null 2>&1
    apt-get install -y php8.2 libapache2-mod-php8.2 php8.2-cli php8.2-common php8.2-mbstring php8.2-curl php8.2-xml php8.2-zip php8.2-mysql php8.2-gd php8.2-bcmath > /dev/null 2>&1
    update-alternatives --set php /usr/bin/php8.2
    echo -e "${BLUE}Step 3: Database Setup...${NC}"
    mysql -u root <<MYSQL_SCRIPT
DROP DATABASE IF EXISTS \`$DB_NAME\`;
DROP USER IF EXISTS '$DB_USER'@'localhost';
CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
MYSQL_SCRIPT

    echo -e "${BLUE}Step 4: Downloading Source Code...${NC}"
    rm -rf /var/www/mirza_pro
    git clone https://github.com/KillHosein/MirzaFutured.git /var/www/mirza_pro > /dev/null 2>&1
    chown -R www-data:www-data /var/www/mirza_pro

    echo -e "${BLUE}Step 5: Generating config.php...${NC}"
    cat > /var/www/mirza_pro/config.php <<'EOF'
<?php
$dbname = '{database_name}';
$usernamedb = '{username_db}';
$passworddb = '{password_db}';
$connect = mysqli_connect("localhost", $usernamedb, $passworddb, $dbname);
if ($connect->connect_error) { die("error" . $connect->connect_error); }
mysqli_set_charset($connect, "utf8mb4");
$options = [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false, ];
$dsn = "mysql:host=localhost;dbname=$dbname;charset=utf8mb4";
try { $pdo = new PDO($dsn, $usernamedb, $passworddb, $options); } catch (\PDOException $e) { die("DATABASE ERROR: " . $e->getMessage()); }
$APIKEY = '{API_KEY}';
$adminnumber = '{admin_number}';
$domainhosts = '{domain_name}';
$usernamebot = '{username_bot}';
?>
EOF

    if [[ "$MARZBAN_CHOICE" =~ ^[yY] ]]; then
        echo '$new_marzban = true;' >> /var/www/mirza_pro/config.php
    fi
    
    sed -i "s|{database_name}|$DB_NAME|g" /var/www/mirza_pro/config.php
    sed -i "s|{username_db}|$DB_USER|g" /var/www/mirza_pro/config.php
    sed -i "s|{password_db}|$DB_PASSWORD|g" /var/www/mirza_pro/config.php
    sed -i "s|{API_KEY}|$BOT_TOKEN|g" /var/www/mirza_pro/config.php
    sed -i "s|{admin_number}|$ADMIN_TELEGRAM_ID|g" /var/www/mirza_pro/config.php
    sed -i "s|{domain_name}|http://$DOMAIN|g" /var/www/mirza_pro/config.php
    sed -i "s|{username_bot}|$BOT_USERNAME|g" /var/www/mirza_pro/config.php

    echo -e "${BLUE}Step 6: Creating Database Tables...${NC}"
    cd /var/www/mirza_pro
    php table.php
    mv table.php table.php.installed

    echo -e "${BLUE}Step 7: Configure Apache for HTTP...${NC}"
    ufw allow ssh > /dev/null; ufw allow 'Apache Full' > /dev/null; ufw --force enable > /dev/null
    a2dismod php7.4 php8.0 php8.1 2>/dev/null; a2enmod php8.2 rewrite > /dev/null
    cat > /etc/apache2/sites-available/mirza-pro.conf <<EOF
<VirtualHost *:80>
    ServerName $DOMAIN
    DocumentRoot /var/www/mirza_pro
    <Directory /var/www/mirza_pro>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
EOF
    a2dissite 000-default.conf > /dev/null 2>&1
    a2ensite mirza-pro.conf > /dev/null
    systemctl restart apache2
    check_apache

    echo -e "${BLUE}Step 8: Obtaining SSL Certificate (HTTPS)...${NC}"
    certbot --apache --non-interactive --agree-tos --redirect -d "$DOMAIN" -m "$SSL_EMAIL"
    check_apache
    
    sed -i "s|http://$DOMAIN|https://$DOMAIN|g" /var/www/mirza_pro/config.php
    
    echo -e "${BLUE}Step 9: Finalizing Setup...${NC}"
    curl -s "https://api.telegram.org/bot${BOT_TOKEN}/setWebhook?url=https://${DOMAIN}/index.php"
    mysql -u root <<MYSQL_SCRIPT
USE \`$DB_NAME\`;
UPDATE admin SET id_admin = '$ADMIN_TELEGRAM_ID';
MYSQL_SCRIPT
    CRON_LOG_DIR="${MIRZA_CRON_LOG_DIR:-/var/log/mirza_pro/cron}"
    mkdir -p "$CRON_LOG_DIR" >/dev/null 2>&1
    mkdir -p /var/www/mirza_pro/cronbot/logs >/dev/null 2>&1
    chown -R www-data:www-data "$CRON_LOG_DIR" /var/www/mirza_pro/cronbot/logs >/dev/null 2>&1
    PHP_BIN="${MIRZA_PHP_BIN:-$(command -v php 2>/dev/null)}"
    if [ -z "$PHP_BIN" ]; then PHP_BIN="/usr/bin/php"; fi
    (crontab -l 2>/dev/null | grep -v "/var/www/mirza_pro/cronbot/" | grep -v "backup_db" | grep -v "backup_files") | crontab -
    CRON_JOBS="*/15 * * * * MIRZA_CRON_LOG_DIR=$CRON_LOG_DIR $PHP_BIN /var/www/mirza_pro/cronbot/statusday.php >> $CRON_LOG_DIR/statusday.log 2>&1
* * * * * MIRZA_CRON_LOG_DIR=$CRON_LOG_DIR $PHP_BIN /var/www/mirza_pro/cronbot/NoticationsService.php >> $CRON_LOG_DIR/notifications.log 2>&1
* * * * * MIRZA_CRON_LOG_DIR=$CRON_LOG_DIR $PHP_BIN /var/www/mirza_pro/cronbot/croncard.php >> $CRON_LOG_DIR/croncard.log 2>&1
*/5 * * * * MIRZA_CRON_LOG_DIR=$CRON_LOG_DIR $PHP_BIN /var/www/mirza_pro/cronbot/payment_expire.php >> $CRON_LOG_DIR/payment_expire.log 2>&1
* * * * * MIRZA_CRON_LOG_DIR=$CRON_LOG_DIR $PHP_BIN /var/www/mirza_pro/cronbot/sendmessage.php >> $CRON_LOG_DIR/sendmessage.log 2>&1
*/3 * * * * MIRZA_CRON_LOG_DIR=$CRON_LOG_DIR $PHP_BIN /var/www/mirza_pro/cronbot/plisio.php >> $CRON_LOG_DIR/plisio.log 2>&1
* * * * * MIRZA_CRON_LOG_DIR=$CRON_LOG_DIR $PHP_BIN /var/www/mirza_pro/cronbot/iranpay1.php >> $CRON_LOG_DIR/iranpay1.log 2>&1
* * * * * MIRZA_CRON_LOG_DIR=$CRON_LOG_DIR $PHP_BIN /var/www/mirza_pro/cronbot/activeconfig.php >> $CRON_LOG_DIR/activeconfig.log 2>&1
* * * * * MIRZA_CRON_LOG_DIR=$CRON_LOG_DIR $PHP_BIN /var/www/mirza_pro/cronbot/disableconfig.php >> $CRON_LOG_DIR/disableconfig.log 2>&1
* * * * * MIRZA_CRON_LOG_DIR=$CRON_LOG_DIR $PHP_BIN /var/www/mirza_pro/cronbot/backupbot.php >> $CRON_LOG_DIR/backupbot.log 2>&1
*/2 * * * * MIRZA_CRON_LOG_DIR=$CRON_LOG_DIR $PHP_BIN /var/www/mirza_pro/cronbot/gift.php >> $CRON_LOG_DIR/gift.log 2>&1
*/30 * * * * MIRZA_CRON_LOG_DIR=$CRON_LOG_DIR $PHP_BIN /var/www/mirza_pro/cronbot/expireagent.php >> $CRON_LOG_DIR/expireagent.log 2>&1
*/15 * * * * MIRZA_CRON_LOG_DIR=$CRON_LOG_DIR $PHP_BIN /var/www/mirza_pro/cronbot/on_hold.php >> $CRON_LOG_DIR/on_hold.log 2>&1
*/2 * * * * MIRZA_CRON_LOG_DIR=$CRON_LOG_DIR $PHP_BIN /var/www/mirza_pro/cronbot/configtest.php >> $CRON_LOG_DIR/configtest.log 2>&1
*/15 * * * * MIRZA_CRON_LOG_DIR=$CRON_LOG_DIR $PHP_BIN /var/www/mirza_pro/cronbot/uptime_node.php >> $CRON_LOG_DIR/uptime_node.log 2>&1
*/15 * * * * MIRZA_CRON_LOG_DIR=$CRON_LOG_DIR $PHP_BIN /var/www/mirza_pro/cronbot/uptime_panel.php >> $CRON_LOG_DIR/uptime_panel.log 2>&1
0 0 * * * MIRZA_CRON_LOG_DIR=$CRON_LOG_DIR $PHP_BIN /var/www/mirza_pro/cronbot/lottery.php >> $CRON_LOG_DIR/lottery.log 2>&1"
    BACKUP_DIR_DEFAULT="${MIRZA_BACKUP_DIR:-/var/backups/mirza_pro}"
    BACKUP_LOG_DIR_DEFAULT="${MIRZA_BACKUP_LOG_DIR:-/var/log/mirza_pro/backup}"
    mkdir -p "$BACKUP_DIR_DEFAULT/db" "$BACKUP_DIR_DEFAULT/files" "$BACKUP_LOG_DIR_DEFAULT" >/dev/null 2>&1
    chmod 750 "$BACKUP_DIR_DEFAULT" "$BACKUP_LOG_DIR_DEFAULT" >/dev/null 2>&1
    chmod 750 "$BACKUP_DIR_DEFAULT/db" "$BACKUP_DIR_DEFAULT/files" >/dev/null 2>&1

    BACKUP_CRON_JOBS="5 */6 * * * MIRZA_BACKUP_DIR=$BACKUP_DIR_DEFAULT MIRZA_BACKUP_LOG_DIR=$BACKUP_LOG_DIR_DEFAULT $SCRIPT_PATH backup_db >> $BACKUP_LOG_DIR_DEFAULT/backup_db.log 2>&1
35 */6 * * * MIRZA_BACKUP_DIR=$BACKUP_DIR_DEFAULT MIRZA_BACKUP_LOG_DIR=$BACKUP_LOG_DIR_DEFAULT $SCRIPT_PATH backup_files >> $BACKUP_LOG_DIR_DEFAULT/backup_files.log 2>&1"

    (crontab -l 2>/dev/null; echo "$CRON_JOBS"; echo "$BACKUP_CRON_JOBS") | crontab -

    save_config "$DOMAIN" "$BOT_TOKEN" "$ADMIN_TELEGRAM_ID" "$BACKUP_CHAT_ID" "$DB_PASSWORD" "$DB_NAME" "$DB_USER"
    echo -e "\n${GREEN}Installation Complete! You can now use the bot.${NC}"
}

update_bot() {
    print_header
    echo -e "${YELLOW}Updating Bot Source Code...${NC}"
    
    INSTALL_DIR="/var/www/mirza_pro"
    
    if [ ! -d "$INSTALL_DIR" ]; then
        echo -e "${RED}Error: Bot folder not found!${NC}"
        read -p "Press Enter..."
        return
    fi

    cd "$INSTALL_DIR" || return

    cp -f config.php /tmp/mirza_config_tmp.php

    chown -R root:root "$INSTALL_DIR"
    git config --global --add safe.directory "$INSTALL_DIR"
    
    git fetch --all
    git reset --hard origin/main
    git pull origin main

    if [ -f "/tmp/mirza_config_tmp.php" ]; then
        cp -f /tmp/mirza_config_tmp.php config.php
        rm /tmp/mirza_config_tmp.php
    fi

    chown -R www-data:www-data "$INSTALL_DIR"
    chmod -R 755 "$INSTALL_DIR"
    
    systemctl restart apache2

    echo -e "${GREEN}✅ Update Finished successfully!${NC}"
}

uninstall_bot() {
    print_header
    local DOMAIN_TO_UNINSTALL
    if [ -f "$CONFIG_FILE" ]; then
        source "$CONFIG_FILE"
        DOMAIN_TO_UNINSTALL=$DOMAIN
    else
        read -p "Configuration file not found. Please enter the domain you want to uninstall: " DOMAIN_TO_UNINSTALL
        if [ -z "$DOMAIN_TO_UNINSTALL" ]; then echo "Domain cannot be empty. Aborting."; return; fi
    fi
    
    read -p "Are you sure? This will delete all files and database for the bot on domain '$DOMAIN_TO_UNINSTALL'. (yes/no): " CONFIRM
    if [[ "$CONFIRM" != "yes" ]]; then echo "Cancelled."; return; fi

    echo -e "${YELLOW}Uninstalling... (This will remove all traces)${NC}"
    (crontab -l 2>/dev/null | grep -v "/var/www/mirza_pro/\|backup_now\|backup_db\|backup_files") | crontab -
    
    systemctl stop apache2 >/dev/null 2>&1
    
    a2dissite mirza-pro.conf > /dev/null 2>&1
    a2dissite mirza-pro-le-ssl.conf > /dev/null 2>&1
    rm -f /etc/apache2/sites-available/mirza-pro.conf /etc/apache2/sites-enabled/mirza-pro.conf
    rm -f /etc/apache2/sites-available/mirza-pro-le-ssl.conf /etc/apache2/sites-enabled/mirza-pro-le-ssl.conf

    certbot delete --cert-name "$DOMAIN_TO_UNINSTALL" --non-interactive > /dev/null 2>&1
    
    if [ -f "$CONFIG_FILE" ]; then
        mysql -u root <<MYSQL_SCRIPT
DROP DATABASE IF EXISTS \`$DB_NAME\`;
DROP USER IF EXISTS '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
MYSQL_SCRIPT
    fi
    
    rm -rf /var/www/mirza_pro /etc/mirza-bot
    
    systemctl start apache2 >/dev/null 2>&1
    echo -e "${GREEN}Uninstallation complete.${NC}"
}

############################################################
# BACKUP FUNCTIONS
############################################################

backup_now_ts() {
    date +'%Y-%m-%d %H:%M:%S %Z'
}

backup_base_dir() {
    local dir="${MIRZA_BACKUP_DIR:-/var/backups/mirza_pro}"
    echo "${dir%/}"
}

backup_log_dir() {
    local dir="${MIRZA_BACKUP_LOG_DIR:-/var/log/mirza_pro/backup}"
    echo "${dir%/}"
}

backup_ensure_dirs() {
    local baseDir
    baseDir="$(backup_base_dir)"
    local logDir
    logDir="$(backup_log_dir)"

    mkdir -p "$baseDir/db" "$baseDir/files" "$logDir" >/dev/null 2>&1
    chmod 750 "$baseDir" "$logDir" >/dev/null 2>&1
    chmod 750 "$baseDir/db" "$baseDir/files" >/dev/null 2>&1
}

backup_log() {
    local jobName="$1"
    shift
    local logDir
    logDir="$(backup_log_dir)"
    backup_ensure_dirs
    echo "[$(backup_now_ts)] [$jobName] $*" | tee -a "$logDir/${jobName}.log"
}

backup_record_history() {
    local jobName="$1"
    local status="$2"
    local durationSec="$3"
    local artifactPath="$4"
    local extra="$5"
    local logDir
    logDir="$(backup_log_dir)"
    backup_ensure_dirs
    echo "[$(backup_now_ts)] job=$jobName status=$status duration_sec=$durationSec artifact=$artifactPath ${extra:-}" >> "$logDir/backup_history.log"
}

backup_send_message() {
    local message="$1"
    if ! load_config; then
        return 1
    fi
    local apiUrl="https://api.telegram.org/bot${BOT_TOKEN}/sendMessage"
    local resp
    resp=$(curl -fsS -X POST "$apiUrl" -F "chat_id=${BACKUP_CHAT_ID}" --form-string "text=${message}" -F "parse_mode=HTML" --max-time 20 2>/dev/null) || return 1
    echo "$resp" | grep -q '"ok":true'
}

backup_notify_failure() {
    local jobName="$1"
    local step="$2"
    local msg
    printf -v msg "<b>Backup Failed</b>\n<b>Job:</b> <code>%s</code>\n<b>Domain:</b> <code>%s</code>\n<b>Step:</b> %s\n<b>Time:</b> <code>%s</code>" "$jobName" "$DOMAIN" "$step" "$(backup_now_ts)"
    backup_send_message "$msg" >/dev/null 2>&1 || true
}

run_with_timeout() {
    local timeoutSpec="$1"
    shift
    if command -v timeout >/dev/null 2>&1; then
        timeout --preserve-status "$timeoutSpec" "$@"
        return $?
    fi
    "$@"
}

backup_acquire_lock() {
    local jobName="$1"
    backup_ensure_dirs
    local logDir
    logDir="$(backup_log_dir)"
    local lockFile="$logDir/${jobName}.lock"
    if command -v flock >/dev/null 2>&1; then
        exec 9>"$lockFile"
        flock -n 9
        return $?
    fi
    return 0
}

rotate_backups() {
    local dir="$1"
    local pattern="$2"
    local keep="$3"
    if [ ! -d "$dir" ]; then
        return 0
    fi
    local files=()
    mapfile -t files < <(ls -1t "$dir"/$pattern 2>/dev/null || true)
    local count="${#files[@]}"
    if [ "$count" -le "$keep" ]; then
        return 0
    fi
    local i
    for ((i=keep; i<count; i++)); do
        rm -f "${files[$i]}" >/dev/null 2>&1 || true
    done
}

run_db_backup() {
    local jobName="backup_db"
    local startTs
    startTs=$(date +%s)
    echo -e "${YELLOW}Starting Database Backup...${NC}"
    if ! load_config; then return 1; fi

    if ! backup_acquire_lock "$jobName"; then
        backup_log "$jobName" "Skipped (already running)"
        backup_record_history "$jobName" "skipped" "0" "-" "reason=already_running"
        return 0
    fi

    backup_ensure_dirs
    local baseDir
    baseDir="$(backup_base_dir)"
    local dbDir="$baseDir/db"
    local dbBackupFile="$dbDir/${DB_NAME}_$(date +%Y%m%d_%H%M%S).sql"
    local credsFile
    credsFile="$(mktemp /tmp/mirza-mysql-XXXXXX.cnf)"
    chmod 600 "$credsFile"
    cat > "$credsFile" <<EOF
[client]
user=$DB_USER
password=$DB_PASSWORD
host=localhost
EOF

    backup_log "$jobName" "Dumping database to $dbBackupFile"
    if ! run_with_timeout 20m mysqldump --defaults-extra-file="$credsFile" --no-tablespaces "$DB_NAME" > "$dbBackupFile" 2>>"$(backup_log_dir)/${jobName}.log"; then
        rm -f "$credsFile" "$dbBackupFile" >/dev/null 2>&1
        local endTs
        endTs=$(date +%s)
        local durationSec=$((endTs - startTs))
        backup_log "$jobName" "Failed to create database dump"
        backup_record_history "$jobName" "failed" "$durationSec" "-" "step=dump"
        backup_notify_failure "$jobName" "dump"
        return 1
    fi
    rm -f "$credsFile" >/dev/null 2>&1

    if [ ! -s "$dbBackupFile" ]; then
        rm -f "$dbBackupFile" >/dev/null 2>&1
        local endTs
        endTs=$(date +%s)
        local durationSec=$((endTs - startTs))
        backup_log "$jobName" "Dump file is empty"
        backup_record_history "$jobName" "failed" "$durationSec" "-" "step=dump_empty"
        backup_notify_failure "$jobName" "dump_empty"
        return 1
    fi

    backup_log "$jobName" "Sending dump to Telegram"
    local apiUrl="https://api.telegram.org/bot${BOT_TOKEN}/sendDocument"
    local caption
    caption=$(cat <<EOF

  
<b>Mirza Pro Bot: Database Backup</b>

💾 <b>Type:</b> Database (.sql)
🌐 <b>Domain:</b> <code>$DOMAIN</code>
📅 <b>Timestamp:</b> <code>$(date +'%Y-%m-%d %H:%M:%S %Z')</code>
EOF
)
    local resp
    resp=$(curl -fsS -X POST "$apiUrl" \
        -F "chat_id=${BACKUP_CHAT_ID}" \
        -F "document=@${dbBackupFile}" \
        --form-string "caption=${caption}" \
        -F "parse_mode=HTML" --max-time 120 2>>"$(backup_log_dir)/${jobName}.log") || resp=""

    local endTs
    endTs=$(date +%s)
    local durationSec=$((endTs - startTs))
    if echo "$resp" | grep -q '"ok":true'; then
        local sizeBytes
        sizeBytes=$(stat -c%s "$dbBackupFile" 2>/dev/null || echo "0")
        backup_log "$jobName" "Sent successfully (size=$sizeBytes bytes, duration=${durationSec}s)"
        backup_record_history "$jobName" "success" "$durationSec" "$dbBackupFile" "size_bytes=$sizeBytes"
        rotate_backups "$dbDir" "${DB_NAME}_*.sql" 10
        return 0
    fi

    backup_log "$jobName" "Failed to send to Telegram"
    backup_record_history "$jobName" "failed" "$durationSec" "$dbBackupFile" "step=telegram_send"
    backup_notify_failure "$jobName" "telegram_send"
    return 1
}

run_files_backup() {
    local jobName="backup_files"
    local startTs
    startTs=$(date +%s)
    echo -e "${YELLOW}Starting Source Files Backup...${NC}"
    if ! load_config; then return 1; fi

    if ! backup_acquire_lock "$jobName"; then
        backup_log "$jobName" "Skipped (already running)"
        backup_record_history "$jobName" "skipped" "0" "-" "reason=already_running"
        return 0
    fi

    backup_ensure_dirs
    local baseDir
    baseDir="$(backup_base_dir)"
    local filesDir="$baseDir/files"
    local filesBackupFile="${filesDir}/${DB_NAME}_$(date +%Y%m%d_%H%M%S).tar.gz"
    local MAX_FILE_SIZE_BYTES=50000000
    local SPLIT_SIZE_BYTES=45000000

    backup_log "$jobName" "Creating archive: $filesBackupFile"
    if ! run_with_timeout 60m tar -czf "$filesBackupFile" -C /var/www mirza_pro 2>>"$(backup_log_dir)/${jobName}.log"; then
        rm -f "$filesBackupFile" >/dev/null 2>&1
        local endTs
        endTs=$(date +%s)
        local durationSec=$((endTs - startTs))
        backup_log "$jobName" "Failed to create archive"
        backup_record_history "$jobName" "failed" "$durationSec" "-" "step=tar"
        backup_notify_failure "$jobName" "tar"
        return 1
    fi

    if [ ! -f "$filesBackupFile" ] || [ ! -s "$filesBackupFile" ]; then
        rm -f "$filesBackupFile" >/dev/null 2>&1
        local endTs
        endTs=$(date +%s)
        local durationSec=$((endTs - startTs))
        backup_log "$jobName" "Archive is empty"
        backup_record_history "$jobName" "failed" "$durationSec" "-" "step=tar_empty"
        backup_notify_failure "$jobName" "tar_empty"
        return 1
    fi
    local sizeBytes
    sizeBytes=$(stat -c%s "$filesBackupFile" 2>/dev/null || echo "0")
    backup_log "$jobName" "Archive created (size=$sizeBytes bytes)"

    local BASE_CAPTION=$(cat <<EOF
<b>Mirza Pro Bot: Files Backup</b>
📦 <b>Type:</b> Source Files (.tar.gz)
🌐 <b>Domain:</b> <code>$DOMAIN</code>
📅 <b>Timestamp:</b> <code>$(date +'%Y-%m-%d %H:%M:%S %Z')</code>
EOF
)

    local API_URL="https://api.telegram.org/bot${BOT_TOKEN}/sendDocument"
    local resp=""
    backup_log "$jobName" "Sending archive to Telegram"

    if [ "$sizeBytes" -le "$MAX_FILE_SIZE_BYTES" ]; then
        resp=$(curl -fsS -X POST "$API_URL" \
            -F chat_id="$BACKUP_CHAT_ID" \
            -F document=@"$filesBackupFile" \
            --form-string "caption=$BASE_CAPTION" \
            -F parse_mode="HTML" --max-time 180 2>>"$(backup_log_dir)/${jobName}.log") || resp=""
    else
        backup_log "$jobName" "Size > 50MB, splitting"
        split -b $SPLIT_SIZE_BYTES -d -a 3 "$filesBackupFile" "${filesBackupFile}.part_"
        local allOk=1
        for PART in ${filesBackupFile}.part_*; do
            local PART_NUM=$(basename "$PART" | awk -F'_part_' '{print $2}')
            local CAPTION="${BASE_CAPTION}"$'\n'"<b>Part:</b> $PART_NUM"
            resp=$(curl -fsS -X POST "$API_URL" \
                -F chat_id="$BACKUP_CHAT_ID" \
                -F document=@"$PART" \
                --form-string "caption=$CAPTION" \
                -F parse_mode="HTML" --max-time 180 2>>"$(backup_log_dir)/${jobName}.log") || resp=""
            if echo "$resp" | grep -q '"ok":true'; then
                backup_log "$jobName" "Part $PART_NUM sent successfully"
            else
                allOk=0
                backup_log "$jobName" "Failed to send part $PART_NUM"
            fi
        done
        if [ "$allOk" -eq 1 ]; then
            resp='{"ok":true}'
        fi
    fi

    rm -f "${filesBackupFile}.part_"* >/dev/null 2>&1 || true

    local endTs
    endTs=$(date +%s)
    local durationSec=$((endTs - startTs))
    if echo "$resp" | grep -q '"ok":true'; then
        backup_log "$jobName" "Sent successfully (duration=${durationSec}s)"
        backup_record_history "$jobName" "success" "$durationSec" "$filesBackupFile" "size_bytes=$sizeBytes"
        rotate_backups "$filesDir" "${DB_NAME}_*.tar.gz" 2
        return 0
    fi

    backup_log "$jobName" "Failed to send to Telegram"
    backup_record_history "$jobName" "failed" "$durationSec" "$filesBackupFile" "step=telegram_send"
    backup_notify_failure "$jobName" "telegram_send"
    return 1
}

configure_backup_schedule() {
    local backup_type=$1
    local cron_job_name="backup_$backup_type"
    
    print_header
    echo -e "${YELLOW}Configure schedule for ${backup_type^^} backup:${NC}"
    echo " 1. Every 2 minutes (For DB testing)"
    echo " 2. Every hour"
    echo " 3. Every 6 hours"
    echo " 4. Daily (at 3:00 AM)"
    echo " 5. Weekly (on Sundays)"
    echo " 6. Custom Interval (in minutes)"
    echo " 7. Disable for ${backup_type^^}"
    read -p "Enter your choice [1-7]: " cron_choice

    local default_backup_dir="/var/backups/mirza_pro"
    local default_backup_log_dir="/var/log/mirza_pro/backup"
    local resolved_backup_dir="${MIRZA_BACKUP_DIR:-$default_backup_dir}"
    local resolved_backup_log_dir="${MIRZA_BACKUP_LOG_DIR:-$default_backup_log_dir}"
    mkdir -p "$resolved_backup_log_dir" >/dev/null 2>&1

    (crontab -l 2>/dev/null | grep -v "$cron_job_name") | crontab -
    local log_file="$resolved_backup_log_dir/${cron_job_name}.log"
    CRON_COMMAND="MIRZA_BACKUP_DIR=$resolved_backup_dir MIRZA_BACKUP_LOG_DIR=$resolved_backup_log_dir $SCRIPT_PATH $cron_job_name >> $log_file 2>&1"
    CRON_SCHEDULE=""
    MSG=""

    case $cron_choice in
        1) CRON_SCHEDULE="*/2 * * * *"; MSG="Every 2 minutes" ;;
        2) CRON_SCHEDULE="0 * * * *"; MSG="Hourly" ;;
        3)
            if [ "$backup_type" = "db" ]; then
                CRON_SCHEDULE="5 */6 * * *"
            else
                CRON_SCHEDULE="35 */6 * * *"
            fi
            MSG="Every 6 hours"
            ;;
        4) CRON_SCHEDULE="0 3 * * *"; MSG="Daily" ;;
        5) CRON_SCHEDULE="0 3 * * 0"; MSG="Weekly" ;;
        6) 
            read -p "Enter interval in minutes: " INTERVAL
            if [[ ! $INTERVAL =~ ^[0-9]+$ ]] || [[ $INTERVAL -eq 0 ]]; then echo -e "${RED}Invalid.${NC}"; return; fi
            CRON_SCHEDULE="*/$INTERVAL * * * *"; MSG="Every $INTERVAL minutes"
            ;;
        7) echo -e "${YELLOW}Automatic ${backup_type^^} backups disabled.${NC}"; return ;;
        *) echo -e "${RED}Invalid option.${NC}"; return ;;
    esac

    (crontab -l 2>/dev/null; echo "$CRON_SCHEDULE $CRON_COMMAND") | crontab -
    echo -e "${GREEN}Automatic ${backup_type^^} backup schedule set: $MSG${NC}"
}

backup_menu() {
    while true; do
        print_header
        if ! load_config; then read -p "Press Enter to return..."; return; fi
        echo -e "Backup Destination Chat ID: ${YELLOW}$BACKUP_CHAT_ID${NC}"
        echo "-----------------------------------------------------"
        echo " 1. Run Manual Database Backup (.sql)"
        echo " 2. Run Manual Source Files Backup (.tar.gz)"
        echo " 3. Configure Auto DB Backup"
        echo " 4. Configure Auto Files Backup"
        echo " 5. Change Backup Destination"
        echo " 6. Back to Main Menu"
        read -p "Enter your choice [1-6]: " choice

        case $choice in
            1) run_db_backup; read -p "Press Enter..." ;;
            2) run_files_backup; read -p "Press Enter..." ;;
            3) configure_backup_schedule "db"; read -p "Press Enter..." ;;
            4) configure_backup_schedule "files"; read -p "Press Enter..." ;;
            5)
                read -p "Enter new Backup Chat ID: " NEW_ID
                if [[ -n "$NEW_ID" ]]; then
                    sed -i "s/^BACKUP_CHAT_ID=.*/BACKUP_CHAT_ID=$NEW_ID/" "$CONFIG_FILE"
                    echo -e "${GREEN}Destination updated.${NC}"
                fi
                read -p "Press Enter..."
                ;;
            6) return ;;
            *) echo -e "${RED}Invalid option.${NC}"; read -p "Press Enter..." ;;
        esac
    done
}

############################################################
# MAIN MENU
############################################################

show_menu() {
    print_header
    echo "Select an option from the menu:"
    echo -e " ${GREEN}1.${NC} Install / Re-install Bot"
    echo -e " ${BLUE}2.${NC} Update Bot"
    echo -e " ${YELLOW}3.${NC} Backup Management"
    echo -e " ${BLUE}4.${NC} Renew SSL Certificate"
    echo -e " ${RED}5.${NC} Uninstall Bot"
    echo -e " ${NC}6. Exit"
    echo ""
    read -p "Enter your choice [1-6]: " choice
    
    case $choice in
        1) install_bot ;;
        2) update_bot; read -p "Press Enter..." ;;
        3) backup_menu ;;
        4) echo "Renewing SSL..."; certbot renew; echo "Done."; read -p "Press Enter..." ;;
        5) uninstall_bot ;;
        6) exit 0 ;;
        *) echo -e "${RED}Invalid option.${NC}" ;;
    esac
    read -p "Press Enter to return to the menu..."
    show_menu
}

if [ "$EUID" -ne 0 ]; then
  echo -e "${RED}Error: This script must be run as root.${NC}"
  exit 1
fi

case "$1" in
    backup_db)
        run_db_backup
        exit $?
        ;;
    backup_files)
        run_files_backup
        exit $?
        ;;
esac


show_menu

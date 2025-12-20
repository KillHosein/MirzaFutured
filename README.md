    
<div align="center">

  <a href="README.md">
    <img src="https://img.shields.io/badge/Language-English-blue?style=for-the-badge" alt="English">
  </a>
  
  <a href="README-FA.md">
    <img src="https://img.shields.io/badge/Language-Persian_(فارسی)-green?style=for-the-badge" alt="Persian">
  </a>

  <a href="installer/README.md">
    <img src="https://img.shields.io/badge/Guide-Install_on_Hosting_(cPanel)-orange?style=for-the-badge&logo=cpanel&logoColor=white" alt="Hosting Installation">
  </a>

</div>
<br>

  

# Mirza Pro Bot & Management Script
This repository contains the open-sourced **Mirza Pro Bot** project, enhanced with a powerful automated management script for easy installation and maintenance.

<p align="center">
  <img src="https://raw.githubusercontent.com/KillHosein/MirzaFutured/main/Banner.jpg" alt="Mirza Pro Bot Banner" width="600"/>
</p>

## 🎉 The Pro Version is Now Open Source!
The **Pro** version of this project is now officially **open-sourced**!  
We welcome your contributions, suggestions, and collaboration to help it grow 🤝

#### 📜 About This Script
This repository provides a powerful bash script that simplifies the entire lifecycle of the Mirza Pro Bot. From a clean installation to regular backups and easy uninstallation, this script handles it all.

#### ✨ Features
*   **Automated Installation:** Installs the bot, web server (Apache), PHP, MySQL database, and secures it with a free SSL certificate (Let's Encrypt).
*   **Backup Management:**
    *   Create manual database (`.sql`) and source file (`.tar.gz`) backups.
    *   Send backups directly to a private Telegram channel or chat.
    *   Configure automated backup schedules (daily, weekly, custom).
*   **Easy Uninstallation:** Completely removes all traces of the bot, including files, database, web server configurations, and cron jobs.
*   **SSL Renewal:** A simple command to renew your SSL certificate.
*   **Interactive Menu:** A user-friendly menu to access all features.

#### 🚀 Installation
To install the bot using the management script, run the following command as a root user:

```bash
bash <(curl -s https://raw.githubusercontent.com/KillHosein/MirzaFutured/main/install.sh)
```

**Menu Options:**
1.  **Install / Re-install Bot:** Runs the full installation process.
2.  **Backup Management:** Opens the backup submenu.
3.  **Renew SSL Certificate:** Renews the Let's Encrypt SSL certificate.
4.  **Uninstall Bot:** Completely removes the bot and its components.
5.  **Exit:** Exits the script.

#### ⚠️ Important Notes for Users
To ensure a successful installation, please provide the following information correctly:

*   **Domain:** Enter your domain or subdomain **without** `http://` or `https://`. (e.g., `bot.yourdomain.com`).
*   **Telegram Bot Token:** Make sure you copy the full, correct token from BotFather.
*   **Admin Telegram ID:** This must be your **numeric** user ID, not your username. Get it from bots like `@userinfobot`.
*   **Backup Chat ID:** For private channels, the ID usually starts with `-100`. The bot must be an **admin** in the channel with permission to post messages.
*   **Marzban Panel Version:** If you are using Marzban panel version **0.8.4 or lower**, you must answer **`no`** to the question `Are you using the NEW Marzban panel?`. For newer versions, answer **`yes`**.

---

## Telegram Mini App (Web App)
This repository includes a Telegram Mini App under `app/` that uses **Telegram Web Apps** and connects to the backend via `api/verify.php` and `api/miniapp.php`.

### BotFather setup
1. Create/configure a Web App in BotFather (Web App section).
2. Set the Web App URL to:
   - `https://YOUR_DOMAIN/app/`

### How authentication works
- The client reads `initData` from `Telegram.WebApp.initData`.
- The server validates it in `api/verify.php` using the bot token (`$APIKEY` in `config.php`) and issues a per-user `token`.
- The Mini App calls `api/miniapp.php` with `Authorization: Bearer <token>`.

---

### 💖 Support the Developers
This project is the result of the hard work of multiple developers. Please consider supporting them.

#### For the Original Mirza Pro Bot Project (mahdiMGF2)
To support the creator of the original bot project:

👉 [Support via NowPayments](https://nowpayments.io/donation/permiumbotmirza)

🙏 Thank you for supporting open-source development! 🚀

#### For the Management Script (ExPLoSiVe1988)
If you find this management script useful, you can support its developer for maintenance and future updates:

| Cryptocurrency            | Address                                      |
|:--------------------------|:---------------------------------------------|
| 🟣 **Ethereum (ETH - ERC20)** | `0x157F3Eb423A241ccefb2Ddc120eF152ce4a736eF` |
| 🔵 **Tron (TRX - TRC20)**     | `TEdu5VsNNvwjCRJpJJ7zhjXni8Y6W5qAqk`         |
| 🟢 **Tether (USDT - TRC20)**  | `TN3cg5RM5JLEbnTgK5CU95uLQaukybPhtR`         |

---

⭐ Don’t forget to **Star** the repository to help others discover it!

---

### 👥 Credits
*   **Original Project (Mirza Pro Bot):** [mahdiMGF2](https://github.com/mahdiMGF2)
*   **Management Script Developer:** 
    *   Telegram: [@H_ExPLoSiVe](https://t.me/H_ExPLoSiVe)
    *   Channel: [@Botgineer](https://t.me/Botgineer)

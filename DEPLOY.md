# Deploy FoodStock AI ke VPS (hasilnya berupa link https)
1. Buat server Ubuntu 22.04/24.04 (min. 2 GB RAM, ideal 4 GB). Catat IP publiknya.
2. Di firewall/security group server buka port 22, 80, 443.
3. SSH ke server lalu pasang Docker:  curl -fsSL https://get.docker.com | sh
4. Unggah proyek (zip lalu scp, atau git clone) ke server, masuk ke foldernya.
5. cp .env.prod.example .env  -> edit (nano .env): DOMAIN, email admin, secret, password DB, APP_KEY.
6. docker compose -f docker-compose.prod.yml up -d --build   (build pertama 5-15 menit)
7. Buka https://DOMAIN-Anda dan LANGSUNG login dengan email admin untuk membuat Admin Utama.
Perintah: logs -> docker compose -f docker-compose.prod.yml logs -f ; berhenti -> ... down ; update -> ... up -d --build

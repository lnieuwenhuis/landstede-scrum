# Landstede-Scrum

Landstede-Scrum is een lichtgewicht Scrum-board geïnspireerd door Jira, met ingebouwde burndown-grafieken voor betere sprintbeheer. Dit project is gebouwd met Laravel en Vue.js.

## 🚀 Functionaliteiten

- Drag-and-drop Scrum-board voor taken en kaarten
- Ingebouwde burndown-grafieken voor visuele voortgangsmonitoring
- Sprintbeheer met eenvoudige planning en tracking
- Gebruiksvriendelijke interface met intuïtieve navigatie

## 🛠️ Installatie

1. **Kloon de repository**
   ```bash
   git clone https://github.com/lnieuwenhuis/landstede-scrum.git
   cd landstede-scrum
   ```
2. **Installeer afhankelijkheden**
   ```bash
   composer install
   npm install
   ```
3. **Omgevingsvariabelen instellen** Kopieer het `.env.example` bestand naar `.env` en pas de instellingen aan.
   ```bash
   cp .env.example .env
   ```
4. **Database migreren**
   ```bash
   php artisan migrate --seed
   ```
5. **Start de applicatie**
   ```bash
   composer run dev
   ```

## 🛠️ Installatie op Productie

1. Stappen 1->3 van Installatie
2. **Maak een Nginx Server Block aan**
3. **Zorg er voor dat je PHP8.4-fpm hebt**
4. **In plaats van** `composer run dev`, **bouw de applicatie**
   ```bash
   npm run build
   ```
5. **Herstart Nginx en PHP-fpm na configuratie**
   ```bash
   sudo systemctl restart php8.4-fpm
   sudo systemctl restart nginx
   ```

## 📌 Gebruik

- Maak nieuwe sprints en voeg taken toe
- Sleep kaarten tussen kolommen voor statusupdates
- Bekijk burndown-grafieken om voortgang te analyseren
- Beheer teamleden en taken efficiënt

## 🤝 Bijdragen

Wil je bijdragen aan dit project? Voel je vrij om een pull request in te dienen of een issue aan te maken!

## 📜 Licentie

Dit project is open-source en beschikbaar onder de MIT-licentie.

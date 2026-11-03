# MetricNotes

> **Productivity Management and Business Intelligence for remote workers**
> 
> *Web system prototype developed as a Final Course Project (TCC) in Systems Analysis and Development.*

![Status](https://img.shields.io/badge/Status-Completed-blue)<br>

### Prerequisites
- PHP (8.3.30)
- Composer (2.9.5)
- Node.js (v24.13.1)
- NPM (11.8.0)

### Step by Step

1. Backend:<br>
   Clone the repository
   ```bash
   git clone https://github.com/gabriellatcc/metricnotes-api/
   ```
   Install the dependencies and run the backend:
   ```bash
   composer install
   cp .env.example .env
   php artisan migrate
   php artisan jwt:secret
   php artisan serve
   ```

2. Frontend:<br>
   Clone the repository
   ```bash
   git clone https://github.com/gabriellatcc/metricnotes-web/
   ```
   Install the dependencies and run the frontend:
   ```bash
   npm install
   npm run dev
   ```

3. Open: http://localhost:3000
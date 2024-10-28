# Bot Registration Challenge

This project implements an automated registration bot using Laravel's job system. The bot attempts to register on a challenge website while handling various security measures including cookie management and JavaScript-based form modifications.

## Prerequisites

- Docker & Docker Compose
- Composer
- PHP 8.1 or higher

## Project Setup

1. Clone the repository:
   ```bash
   git clone https://github.com/oliveirajota/blackscale-challenge.git
   cd blackscale-challenge
   ```

2. Install PHP dependencies:
   ```bash
   composer install
   ```

3. Generate application key:
   ```bash
   php artisan key:generate
   ```

4. Start Docker containers:
   ```bash
   docker-compose up -d
   ```

## Running the Bot

1. Run the queue worker:
   ```bash
   docker-compose exec app php artisan register-bot:run
   ```

## Monitoring

- Check the Laravel logs for detailed information:
  ```bash
  tail -f storage/logs/laravel.log
  ```


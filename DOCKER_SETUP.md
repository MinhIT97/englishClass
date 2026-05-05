# Docker Setup for English Class Laravel Application

## Prerequisites
- Docker Desktop (includes Docker Engine and Docker Compose)
- Git

## Quick Start

### 1. Clone Environment Variables
```bash
cp .env.docker .env
# Edit .env with your configurations
```

### 2. Build and Start Containers
```bash
docker compose up -d --build
```

### 3. Run Database Migrations
```bash
docker compose exec app php artisan migrate
```

### 4. Generate Application Key
```bash
docker compose exec app php artisan key:generate
```

### 5. Set File Permissions
```bash
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
docker compose exec app chmod -R 755 storage bootstrap/cache
```

## Access Application
- **Web Application**: http://localhost
- **Database**: localhost:3306
- **Redis**: localhost:6379

## Common Commands

### View Logs
```bash
docker compose logs -f app
docker compose logs -f nginx
docker compose logs -f db
```

### Run Artisan Commands
```bash
docker compose exec app php artisan <command>
```

### Run Tinker
```bash
docker compose exec app php artisan tinker
```

### Install Dependencies
```bash
# PHP dependencies
docker compose exec app composer install

# Node dependencies
docker compose exec app npm install
docker compose exec app npm run build
```

### Stop Containers
```bash
docker compose down
```

### Stop and Remove Volumes
```bash
docker compose down -v
```

## Services

- **app**: PHP-FPM application server
- **nginx**: Web server (reverse proxy)
- **db**: MySQL 8.0 database
- **redis**: Redis cache/session store

## Environment Variables
Configure your environment in `.env` file. Key variables:
- `DB_HOST`: Database host (use `db` for Docker)
- `DB_DATABASE`: Database name
- `DB_USERNAME`: Database username
- `DB_PASSWORD`: Database password
- `REDIS_HOST`: Redis host (use `redis` for Docker)
- `QUEUE_CONNECTION`: Set to `redis` to use Redis queues

## Troubleshooting

### Permission Issues
```bash
docker compose exec app chown -R www-data:www-data /app/storage /app/bootstrap/cache
```

### Clear Cache
```bash
docker compose exec app php artisan cache:clear
docker compose exec app php artisan config:clear
```

### Database Issues
```bash
# Fresh migration
docker compose exec app php artisan migrate:fresh --seed
```

## Production Deployment
For production, update your `.github/workflows/deploy.yml` to ensure the server has:
1. Docker and Docker Compose installed
2. SSH access configured
3. The application repository cloned to `/home/minhnv/projects/englishClass`

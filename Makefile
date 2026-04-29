.PHONY: help build up down logs migrate seed fresh tinker shell clean

help:
	@echo "English Class Docker Commands"
	@echo "================================"
	@echo "build          - Build Docker images"
	@echo "up             - Start all containers"
	@echo "up-detached    - Start containers in background"
	@echo "down           - Stop all containers"
	@echo "logs           - View application logs"
	@echo "migrate        - Run database migrations"
	@echo "seed           - Run database seeders"
	@echo "fresh          - Fresh migration and seed"
	@echo "tinker         - Start Laravel Tinker REPL"
	@echo "shell          - Access application shell (bash)"
	@echo "artisan        - Run artisan command (make artisan cmd=migrate:status)"
	@echo "composer       - Run composer command (make composer cmd=require/package)"
	@echo "npm            - Run npm command (make npm cmd=install)"
	@echo "clean          - Remove containers, volumes, and cache"

build:
	docker compose build

up:
	docker compose up

up-detached:
	docker compose up -d

down:
	docker compose down

logs:
	docker compose logs -f app

logs-nginx:
	docker compose logs -f nginx

logs-db:
	docker compose logs -f db

migrate:
	docker compose exec app php artisan migrate

seed:
	docker compose exec app php artisan db:seed

fresh:
	docker compose exec app php artisan migrate:fresh --seed

tinker:
	docker compose exec app php artisan tinker

shell:
	docker compose exec app /bin/bash

artisan:
	docker compose exec app php artisan $(cmd)

composer:
	docker compose exec app composer $(cmd)

npm:
	docker compose exec app npm $(cmd)

test:
	docker compose exec app php artisan test

clean:
	docker compose down -v
	find . -type d -name vendor -exec rm -rf {} + 2>/dev/null || true
	find . -type d -name node_modules -exec rm -rf {} + 2>/dev/null || true
	docker system prune -f

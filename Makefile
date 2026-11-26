.PHONY: up down logs build-ui test audit seed

up:
    bash scripts/vibe-deploy.sh

down:
    docker compose down

logs:
    docker compose logs -f --tail=100

build-ui:
    docker run --rm -v "$(PWD)/backend:/app" -w /app node:20-alpine sh -c "npm ci && npm run build"

test:
    docker exec -w /var/www/html laravel_bss_oss php artisan test

audit:
    docker exec -w /var/www/html laravel_bss_oss sh -c "./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --memory-limit=1G"

seed:
    docker exec -w /var/www/html laravel_bss_oss php artisan db:seed --force

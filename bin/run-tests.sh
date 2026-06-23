#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

PHP_IMAGE="${PHP_IMAGE:-php:8.2-cli}"

echo "==> Installing Composer dependencies (${PHP_IMAGE})..."
docker run --rm \
	-v "$ROOT:/app" \
	-w /app \
	"$PHP_IMAGE" \
	bash -lc 'apt-get update -qq && apt-get install -y -qq unzip git libzip-dev > /dev/null && docker-php-ext-install zip > /dev/null 2>&1 && if ! command -v composer >/dev/null 2>&1; then curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer; fi && composer install --no-interaction --prefer-dist'

echo "==> Running PHPUnit (unit + smoke)..."
docker run --rm \
	-v "$ROOT:/app" \
	-w /app \
	"$PHP_IMAGE" \
	./vendor/bin/phpunit --testdox

echo "==> All tests passed."

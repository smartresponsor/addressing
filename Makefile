# Makefile для удобных команд

.PHONY: test coverage up down build clean

test:
	docker-compose run --rm app vendor/bin/phpunit

coverage:
	docker-compose run --rm app vendor/bin/phpunit --coverage-html build/coverage-html

up:
	docker-compose up -d

down:
	docker-compose down

build:
	docker-compose build

clean:
	docker-compose down -v --remove-orphans
	rm -rf build/coverage-html/*
	rm -rf build/logs/*

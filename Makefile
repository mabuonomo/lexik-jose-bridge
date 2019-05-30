.PHONY: start

start:
	docker-compose up -d

stop:
	docker-compose stop

down:
	docker-compose down

up_and_pull:
	docker-compose pull
	docker-compose up -d

composer_init:
	docker-compose run php composer install

composer_update:
	docker-compose run php composer update

phpunit:
	docker-compose run php ./vendor/bin/phpunit -c phpunit.xml.dist --coverage-text --coverage-html coverage/ --colors=never tests/

cs-fix:
	docker-compose run php composer cs-fix

phpstan:
	docker-compose run php ./vendor/bin/phpstan analyse -c phpstan.neon -n --no-progress -l 1 src tests

php:
	docker-compose exec php zsh
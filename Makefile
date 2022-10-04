PWD:=$(shell pwd -L)

#IMAGE:=php:7.4-cli-alpine3.15
#IMAGE:=composer:latest
IMAGE:=webdevops/php-dev:8.1-alpine

DOCKER_RUN = docker run --rm --interactive --tty --volume ${PWD}:/var/www/html --workdir=/var/www/html ${IMAGE}

init:
	- ${DOCKER_RUN} composer init

configure:
	- ${DOCKER_RUN} composer update

dump-autoload:
	- ${DOCKER_RUN} composer dump-autoload

test:
	- ${DOCKER_RUN} php vendor/bin/phpunit

version:
	- ${DOCKER_RUN} php --version && composer --version

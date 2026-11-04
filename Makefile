HOST_UID ?= $(shell id -u)
HOST_GID ?= $(shell id -g)
export HOST_UID HOST_GID

DC    = docker compose
EXEC  = $(DC) exec app
ENTRY ?= illuminate   # Laravel Zero entry file in project root

.PHONY: help up down rebuild shell install update cli test coverage pint pint-test stan rector rector-fix qa build logs

help:            ## Show available commands
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

up:              ## Build image and start the container
	$(DC) up -d --build

down:            ## Stop and remove the container
	$(DC) down

rebuild:         ## Rebuild image from scratch
	$(DC) build --no-cache && $(DC) up -d

shell:           ## Open a shell inside the container
	$(EXEC) bash

install:         ## composer install
	$(EXEC) composer install

update:          ## composer update
	$(EXEC) composer update

cli:             ## Run the CLI: make cli c="list"
	$(EXEC) php $(ENTRY) $(c)

test:            ## Run Pest
	$(EXEC) pest

coverage:        ## Pest with coverage (pcov)
	$(EXEC) pest --coverage

pint:            ## Fix code style
	$(EXEC) pint

pint-test:       ## Check code style only
	$(EXEC) pint --test

stan:            ## Larastan / PHPStan
	$(EXEC) phpstan analyse --memory-limit=-1

rector:          ## Rector dry-run
	$(EXEC) rector process --dry-run

rector-fix:      ## Rector apply changes
	$(EXEC) rector process

qa: pint-test stan rector test   ## All checks (CI-style)

build:           ## Build the PHAR -> builds/illuminate
	$(EXEC) php $(ENTRY) app:build illuminate

logs:
	$(DC) logs -f app

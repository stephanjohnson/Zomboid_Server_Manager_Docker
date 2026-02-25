ARCH := $(shell uname -m)
ifeq ($(ARCH),aarch64)
    ARCH_FILE := docker-compose.arm64.yml
else
    ARCH_FILE := docker-compose.amd64.yml
endif

COMPOSE := docker compose -f docker-compose.yml -f $(ARCH_FILE)

.PHONY: up down build restart logs ps stop pull migrate test exec

up:
	$(COMPOSE) up -d

down:
	$(COMPOSE) down

build:
	$(COMPOSE) build

restart:
	$(COMPOSE) restart

stop:
	$(COMPOSE) stop

logs:
	$(COMPOSE) logs -f

ps:
	$(COMPOSE) ps

pull:
	$(COMPOSE) pull

migrate:
	$(COMPOSE) exec app php artisan migrate

test:
	$(COMPOSE) exec app php artisan test

exec:
	$(COMPOSE) exec app $(CMD)

arch:
	@echo "Detected: $(ARCH) -> $(ARCH_FILE)"

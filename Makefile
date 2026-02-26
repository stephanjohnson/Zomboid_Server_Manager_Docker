ARCH := $(shell uname -m)
ifeq ($(ARCH),aarch64)
    ARCH_FILE := docker-compose.arm64.yml
else
    ARCH_FILE := docker-compose.amd64.yml
endif

COMPOSE := docker compose -f docker-compose.yml -f $(ARCH_FILE)

.PHONY: up down build restart logs ps stop pull migrate test exec arch init

# ── First-run setup ──────────────────────────────────────────────────
# Generates .env with random secrets from .env.example template
init:
	@if [ -f .env ]; then \
		echo ".env already exists — skipping init (delete .env to regenerate)"; \
	else \
		echo "Generating .env with random secrets..."; \
		DB_PASS=$$(openssl rand -base64 18 | tr -dc 'A-Za-z0-9' | head -c 24); \
		RCON_PASS=$$(openssl rand -base64 18 | tr -dc 'A-Za-z0-9' | head -c 16); \
		ADMIN_PASS=$$(openssl rand -base64 18 | tr -dc 'A-Za-z0-9' | head -c 16); \
		API_SECRET=$$(openssl rand -base64 32 | tr -dc 'A-Za-z0-9' | head -c 48); \
		APP_SECRET=$$(openssl rand -base64 32); \
		sed \
			-e "s|^DB_PASSWORD=.*|DB_PASSWORD=$$DB_PASS|" \
			-e "s|^PZ_RCON_PASSWORD=.*|PZ_RCON_PASSWORD=$$RCON_PASS|" \
			-e "s|^PZ_ADMIN_PASSWORD=.*|PZ_ADMIN_PASSWORD=$$ADMIN_PASS|" \
			-e "s|^API_KEY=.*|API_KEY=$$API_SECRET|" \
			-e "s|^APP_KEY=.*|APP_KEY=base64:$$APP_SECRET|" \
			.env.example > .env; \
		echo ""; \
		echo "  .env created with generated secrets"; \
		echo "  Edit .env to customize server settings before starting"; \
		echo ""; \
	fi

# ── Core commands ────────────────────────────────────────────────────
up: init
	$(COMPOSE) up -d --build

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

# ── App commands ─────────────────────────────────────────────────────
migrate:
	$(COMPOSE) exec app php artisan migrate --force

test:
	$(COMPOSE) exec app php artisan test

exec:
	$(COMPOSE) exec app $(CMD)

arch:
	@echo "Detected: $(ARCH) -> $(ARCH_FILE)"

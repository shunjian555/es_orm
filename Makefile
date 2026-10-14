SHELL := /bin/bash
COMPOSER ?= composer
PHP ?= php

.PHONY: help install test test-unit test-integration analyse cs fix elasticsearch kibana clean

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'

install: ## Install composer dependencies
	$(COMPOSER) install

test: ## Run the whole test suite
	$(PHP) vendor/bin/phpunit

test-unit: ## Run unit tests only (no Elasticsearch required)
	$(PHP) vendor/bin/phpunit --testsuite Unit

test-integration: ## Run integration tests (requires a running Elasticsearch)
	ES_INTEGRATION=true $(PHP) vendor/bin/phpunit --testsuite Integration

analyse: ## Run PHPStan
	$(PHP) vendor/bin/phpstan analyse --memory-limit=1G

cs: ## Check the coding standard (dry run)
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff

fix: ## Fix the coding standard
	$(PHP) vendor/bin/php-cs-fixer fix

elasticsearch: ## Start Elasticsearch (+ Kibana) with docker compose
	docker compose up -d elasticsearch kibana

kibana: ## Start Kibana only
	docker compose up -d kibana

clean: ## Remove caches and build artefacts
	rm -rf .phpunit.cache .phpunit.result.cache build coverage .php-cs-fixer.cache

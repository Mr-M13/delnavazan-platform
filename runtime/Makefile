# Delnavazan Platform — local disposable test runtime.
# All commands run from this directory so relative bind-mount paths resolve here.

SHELL := /usr/bin/env bash
BIN := bin

.PHONY: help env up down reset destroy logs status \
	install-wordpress install-plugin fresh-migration upgrade-rehearsal \
	pure-tests runtime-tests concurrency verify-schema25 all

help:
	@echo "Delnavazan Platform disposable runtime"
	@echo ""
	@echo "  make env              copy .env.example -> .env (edit DZN_REPO_ROOT)"
	@echo "  make up               start db + wordpress + cli + mailpit"
	@echo "  make down             stop (keep data)"
	@echo "  make reset            wipe db + files and restart empty"
	@echo "  make destroy          remove containers, volumes and WordPress files"
	@echo "  make install-wordpress  wp core install"
	@echo "  make install-plugin   symlink + activate the Platform plugin"
	@echo "  make fresh-migration  zero -> Schema 25 migration test"
	@echo "  make upgrade-rehearsal  Schema 24 -> 25 upgrade rehearsal"
	@echo "  make pure-tests       run pure-PHP contract/static tests"
	@echo "  make runtime-tests    run WP-CLI runtime/migration/failure/corruption"
	@echo "  make concurrency      run the R1 concurrency suite"
	@echo "  make verify-schema25  assert the Schema 25 ledger"
	@echo "  make all              full acceptance (destroy/rebuild included)"

env:
	@test -f .env || cp .env.example .env
	@echo "Wrote .env (edit DZN_REPO_ROOT to match this checkout)."

up: env
	$(BIN)/up.sh

down:
	$(BIN)/down.sh

reset:
	$(BIN)/reset.sh

destroy:
	$(BIN)/destroy.sh

logs:
	$(BIN)/logs.sh

status:
	$(BIN)/status.sh

install-wordpress:
	$(BIN)/install-wordpress.sh

install-plugin:
	$(BIN)/install-plugin.sh

fresh-migration:
	$(BIN)/run-fresh-migration.sh

upgrade-rehearsal:
	$(BIN)/run-upgrade-rehearsal.sh

pure-tests:
	$(BIN)/run-pure-tests.sh

runtime-tests:
	$(BIN)/run-runtime-tests.sh

concurrency:
	$(BIN)/run-concurrency.sh

verify-schema25:
	$(BIN)/verify-schema25.sh

all:
	$(BIN)/run-all.sh

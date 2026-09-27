SHELL := /usr/bin/env bash
BIN := bin
.PHONY: help check-cache up fresh-install retained migration-30-to-31 pure path-guards regressions concurrency all destroy
help:
	@echo "Schema-31 offline disposable runtime"
	@echo "State is outside this checkout; images must already be cached."
check-cache: ; @$(BIN)/check-cache.sh
up: ; @$(BIN)/up.sh
fresh-install: ; @$(BIN)/run-fresh-install.sh
retained: ; @$(BIN)/run-retained-migration.sh
migration-30-to-31: ; @$(BIN)/run-schema30-to-31-rehearsal.sh
pure: ; @$(BIN)/run-pure-tests.sh
path-guards: ; @../tests/runtime-path-guards.sh
regressions: ; @$(BIN)/run-regressions.sh
concurrency: ; @$(BIN)/run-concurrency.sh
all: ; @$(BIN)/run-all.sh
destroy: ; @$(BIN)/destroy.sh

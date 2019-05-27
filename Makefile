SHELL = /bin/bash

COMPOSER_FILENAME = composer.phar
COMPOSER_VERSION = 1.8.5

.PHONY: all clean dependencies reset run
.DEFAULT_GOAL = all
all: run

$(COMPOSER_FILENAME): composer-setup.php
	php '$<' -- --filename='$@' --install-dir=./ --version='$(COMPOSER_VERSION)'

dependencies: $(COMPOSER_FILENAME) composer.json
	php '$<' install

run: dependencies
	bin/websocket-server &

reset:
	bin/reset

clean: reset
	rm -rf vendor/ '$(COMPOSER_FILENAME)'

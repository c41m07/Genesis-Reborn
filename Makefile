DOCKER_COMPOSE = docker-compose
EXEC_APP = $(DOCKER_COMPOSE) exec -it app
CONNECT_APP = $(EXEC_APP) /bin/bash

###############
# Dev Command #
###############
dev-dependencies:
	$(EXEC_APP) composer install
	$(EXEC_APP) composer dump-autoload --optimize
	$(EXEC_APP) npm install
	$(EXEC_APP) composer db:create

gitignore:
	git rm -r --cached .
	git add .
	git commit -m ".gitignore est maintenant fonctionnel"

##################
# Docker Command #
##################

upgrade:
	$(DOCKER_COMPOSE) stop
	$(DOCKER_COMPOSE) build
	$(DOCKER_COMPOSE) up -d

boot:
	$(DOCKER_COMPOSE) up -d

connect:
	$(CONNECT_APP)

start: boot dev-dependencies

stop: ## Stop containers
	$(DOCKER_COMPOSE) stop

restart: stop start cache-clear watch

-include Makefile.override
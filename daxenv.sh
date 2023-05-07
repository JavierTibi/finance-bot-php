#!/bin/bash

case $1 in
	up)
	docker-compose up -d
  ;;
	down)
	docker-compose down
  ;;
	ssh)
	docker exec -it ticket_api bash
	;;
	*)
	echo "Run up to start the environment, down to stop it. run ssh to shh into the laravel instance"
	;;
esac

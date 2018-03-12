#!/bin/bash

php artisan cache:clear

while true; do

	#Trigger
	if ! screen -list | grep -q msg_trigger; then
	  screen -d -m -S msg_trigger bash -c "php artisan vk:trigger 1 1 1"
	fi

	#Response
	if ! screen -list | grep -q msg_response; then
	  screen -d -m -S msg_response bash -c "php artisan vk:message"
	fi

	echo $(date +%d-%m-%Y" "%H:%M:%S) >> ./storage/screen.log
	sleep 60 
done
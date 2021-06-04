#!/bin/bash
php bin/console c:c --env=dev && 
chmod 777 -R cache && 
chmod 777 -R logs && 
chmod 777 -R var && 
chown apache:apache -R cache && 
chown apache:apache -R logs &&
chown apache:apache -R var

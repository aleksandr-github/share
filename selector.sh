#!/bin/bash
if [ -f .env.prod ]
then
  export $(cat .env | sed 's/#.*//g' | xargs)
fi

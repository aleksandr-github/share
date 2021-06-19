#!/bin/bash
echo 'starting'
if [ -f .env.dev ]
then  
    source .env.dev
    echo $selector
fi



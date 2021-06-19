#!/bin/bash
echo 'starting'
selector=1
if [ -f .env.dev1 ]
then  
    source .env.dev
    echo $selector
fi
echo $selector



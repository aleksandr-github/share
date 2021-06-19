#!/bin/bash
echo 'starting'
selector=1
if [ -f .env.dev ]
then  
    source .env.dev
    echo $selector
fi
echo $selector
# iterater
for ((a=1; a <= $selector ; a++))
do
   printf 'Running test %s\n' "$selector"
   # php bin/console run:algo RANK
   # echo profit/loss $variableused somefile.txt
done


#!/bin/bash
echo '############### Starting ##################'
selector=1
if [ -f .env.dev ]
then  
    source .env.dev
    #echo $selector
fi
#echo $selector
# iterater
for ((s=1; s <= $selector ; s++))
do
   printf 'Running test %s\n' "$s"
   # php bin/console run:algo RANK
   # echo profit/loss $variableused somefile.txt
done


#!/bin/bash

if [[ -z $WORKSPACE ]]
then
    export WORKSPACE=.
fi

cd $WORKSPACE

JUNK=/var/tmp/junk.txt

for dir in \
    src
do
    if [ -d "$dir" ]; then
        find $dir -name '*.php' >> $JUNK
    fi
done

cat $JUNK | \
    awk '!x[$0]++'

rm $JUNK

#!/bin/bash

# Wrapper script to run.php, takes the same parameters.
#
# This script is *not* necessary, as run.php currently runs
# to completion on all cases without errors (as of r87).
# However, it is useful for logging/debugging.
#
# Adds logging of output and errors to files and attempts to
# recover from early exits (crashes) by run.php, by restarting
# run.php and setting its third parameter to skip past the
# problematic file. (This last feature works for segmentation
# faults, but allows catchable PHP exceptions/fatal errors to
# force termination.)
#
# Loops until string 'Done' is output
# by run.php (so be sure run.php eventually does this, or
# you'll have an infinite-reprocessing mess!)

SPATH=`dirname $0`

if [ -d "$1" ] && [ -d "$2" ]; then

DONE="no"
LOGFILE=`basename $1`.log
ERRLOG=`basename $1`.err.log
SKIPPASTFILE="$3"

while [ "$DONE" != "Done" ]; do
  if [ "$SKIPPASTFILE" == "" ] || [ -f "$1/$SKIPPASTFILE" ]; then
    echo "Calling: php $SPATH/run.php $1 $2 $SKIPPASTFILE &> $LOGFILE"
    php $SPATH/run.php $1 $2 $SKIPPASTFILE &> $LOGFILE
    SKIPPASTFILE=`tail -1 $LOGFILE | grep 'Loading' | grep -v 'Refs' | egrep -o '[^/]+\.html'`
    if [ "$SKIPPASTFILE" == "" ]; then
      SKIPPASTFILE="nonefound"
    else
      tail $LOGFILE >> $ERRLOG
    fi
    DONE=`tail $LOGFILE | grep -o Done`
  else
    echo "Problem with run-safe.sh, exiting!"
    echo
    echo "run.php exited, but could not determine whether it completed"
    echo "successfully or not. You should examine $LOGFILE to find out."
    DONE="Done"
  fi
done

echo "Done, check $LOGFILE to be sure. tail $LOGFILE:"
tail $LOGFILE

else
  php $SPATH/run.php
fi

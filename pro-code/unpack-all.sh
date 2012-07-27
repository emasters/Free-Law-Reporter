#!/bin/bash

# Unpacks all /raw/ case source HTML tarballs, removes 
# outdated versions of cases and renames certain files to
# avoid overwriting others

WORKING=`pwd`
SCRIPTPATH=`dirname $WORKING/$0`

if [ -d "$1" ]; then

  SRCDIR="$1"
  US="raw/US"
  F2="raw/F2"
  F3="raw/F3"
  mkdir -p $US/build
  mkdir -p $F2/build
  mkdir -p $F3/build

  cd $F2/build
  tar -xjf $SRCDIR/F2d_1.tar.bz2
  tar -xjf $SRCDIR/F2d_2.tar.bz2
  tar -xjf $SRCDIR/F2d_3.tar.bz2
  tar -xjf $SRCDIR/F2d_4.tar.bz2
  tar -xjf $SRCDIR/F2d_5.tar.bz2
  tar -xjf $SRCDIR/F2d_6.tar.bz2
  tar -xjf $SRCDIR/F2d_7.tar.bz2
  tar -xjf $SRCDIR/F2d_8.tar.bz2
  # FYI: We have examined these archives and there are no filename collisions
  #      (see below for how we handle collisions in the other source tarballs)
  find . -name '*html' -exec mv {} ../ \; && find . -type d -name '*F*' -exec rmdir {} \;
  cd .. && rmdir build

  cd $WORKING

  cd $F3/build
  tar -xjf $SRCDIR/F3d_1.tar.bz2
  tar -xjf $SRCDIR/F3d_2.tar.bz2
  tar -xjf $SRCDIR/F3d_3.tar.bz2
  tar -xjf $SRCDIR/F3d_4.tar.bz2
  # Handle filename collisions
  mv F3d_4/1121100556.html F3d_4/1121100556-200802-4.html
  mv F3d_3/130426720.html F3d_3/130426720-200802-3.html
  mv F3d_4/167678013.html F3d_4/167678013-200802-4.html
  mv F3d_4/1740241416.html F3d_4/1740241416-200802-4.html
  mv F3d_3/1987112105.html F3d_3/1987112105-200802-3.html
  mv F3d_2/434197591.html F3d_2/434197591-200802-2.html
  mv F3d_3/540310038.html F3d_3/540310038-200802-3.html
  mv F3d_2/714067156.html F3d_2/714067156-200802-2.html
  tar -xjf $SRCDIR/F3d_Updated.tar.bz2
  tar -xjf $SRCDIR/F3d20080307.tar.bz2
  find . -name '*html' -exec mv {} ../ \; && find . -type d -name '*F*' -exec rm -fr {} \;
  cd .. && rmdir build
  # Delete outdated files
  cat $SCRIPTPATH/F3_exclude.txt | xargs rm

  cd $WORKING

  cd $US/build
  tar -xjf $SRCDIR/US20080307.tar.bz2
  find . -name '*html' -exec mv {} ../ \;
  # Handle filename collision
  mv ../656836554.html ../656836554-080307.html
  tar -xjf $SRCDIR/US.tar.bz2
  find . -name '*html' -exec mv {} ../ \; && find . -type d -name '*U*' -exec rmdir {} \;
  cd .. && rmdir build
  # Delete outdated files
  cat $SCRIPTPATH/US_exclude.txt | xargs rm

else
  echo "USAGE: unpack-all.sh [srcdir]"
  echo
  echo "[srcdir] is directory containing all Fastcase source tarballs"
  echo "Files are unpacked into working directory."
fi

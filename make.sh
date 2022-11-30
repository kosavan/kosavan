#!/bin/bash

# Check the HTtrack installation
HTTRACK=`which httrack`
if [ -z "$HTTRACK" ]; then
	echo "HTtrack is not installed, please run somenting like:"
	echo "apt-get install httrack"
	echo "yum install httrack"
	echo "brew install httrack"
fi

# Check the site URI
if [ -z "$1" ]; then
	echo "Site URL not specified, nothing to download"
	echo "Site maker usage:"
	echo "bash make.sh http://site.com"
	exit 1
fi

# Download the site via HTtrack
echo "Downloading site, please wait, it may take a while ..."
$HTTRACK "$1" -F "Mozilla/5.0 (Windows NT 10.0; WOW64; rv:59.0) Gecko/20100101 Firefox/59.0" -C0 -N1004 -n -I0
mv index-2.html index.html
rm -f ./hts-log.txt
rm -f ./cookies.txt
echo "Site is ready!"
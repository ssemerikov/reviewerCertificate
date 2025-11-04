#!/bin/bash
# Find bootstrap.inc.php in OJS installation
echo "Searching for bootstrap.inc.php..."
find /home/easyscie/acnsci.org -name "bootstrap.inc.php" -type f 2>/dev/null

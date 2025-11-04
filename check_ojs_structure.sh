#!/bin/bash
# Check OJS installation structure
echo "Checking OJS installation at /home/easyscie/acnsci.org/journal/"
echo ""
echo "=== Root directory files ==="
ls -la /home/easyscie/acnsci.org/journal/*.php 2>/dev/null | head -20
echo ""
echo "=== Looking for lib/pkp ==="
ls -la /home/easyscie/acnsci.org/journal/lib/pkp/ 2>/dev/null | head -10
echo ""
echo "=== Looking for config.inc.php ==="
ls -la /home/easyscie/acnsci.org/journal/config.inc.php 2>/dev/null
echo ""
echo "=== Looking for classes directory ==="
ls -la /home/easyscie/acnsci.org/journal/classes/ 2>/dev/null | head -10
echo ""
echo "=== Searching for any bootstrap or init files ==="
find /home/easyscie/acnsci.org/journal -maxdepth 3 -name "*bootstrap*" -o -name "*init*" 2>/dev/null | head -20

#!/usr/bin/env bash
set -euo pipefail
echo "Searching for package.json files..."
find . -type f -name 'package.json' | sort | while read -r f; do
  echo
echo "---- $f ----"
  node -e "const fs=require('fs'); const p=JSON.parse(fs.readFileSync('$f','utf8')); const s=Object.keys(p.scripts||{}); console.log(s.length ? s.join(' ') : '<no scripts>')"
done

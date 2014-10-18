#!/bin/bash

mkdir -p doc
php -d date.timezone=UTC vendor/bin/phpdoc -t doc/ -d src/ --title "FutoIn AsyncSteps RI" --template xml --force

mkdir -p docmd
vendor/bin/phpdocmd doc/structure.xml docmd

cat misc/README.base.md > README.md

pushd docmd

for f in *.md; do
    echo >> ../README.md
    echo "<a name=\"${f}\"></a>" >> ../README.md
    cat $f | sed -e 's/(FutoIn-RI/(#FutoIn-RI/g' >> ../README.md
done
